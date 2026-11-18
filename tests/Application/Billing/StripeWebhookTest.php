<?php

declare(strict_types=1);

namespace App\Tests\Application\Billing;

use App\Billing\Command\SyncSubscription;
use App\Billing\Controller\StripeWebhookController;
use App\Tests\ApiTestCase;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Stripe signs `{timestamp}.{body}` with HMAC-SHA256 and sends `t=…,v1=…` in Stripe-Signature.
 *
 * One request per test: the next request boots a new container, with an empty queue and an empty log.
 */
final class StripeWebhookTest extends ApiTestCase
{
    private const string SECRET = 'whsec_test_not_a_real_secret';
    private const string CUSTOMER_EMAIL = 'MÜLLER@example.com';
    private const string STRIPE_IP = '54.187.174.169';
    private const string STRIPE_USER_AGENT = 'Stripe/1.0 (+https://stripe.com/docs/webhooks)';

    private function event(string $type, array $object): string
    {
        return json_encode([
            'id' => 'evt_1QwXyZ',
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $object],
        ], \JSON_THROW_ON_ERROR);
    }

    private function subscriptionEvent(string $type, string $subscriptionId): string
    {
        return $this->event($type, ['id' => $subscriptionId, 'object' => 'subscription', 'customer' => 'cus_workspace', 'status' => 'active']);
    }

    private function signature(string $body, string $secret = self::SECRET, ?int $signedAt = null): string
    {
        $signedAt ??= time();

        return sprintf('t=%d,v1=%s', $signedAt, hash_hmac('sha256', $signedAt.'.'.$body, $secret));
    }

    private function deliver(string $body, ?string $signature): Response
    {
        $this->fromIp(self::STRIPE_IP)->withHeader('User-Agent', self::STRIPE_USER_AGENT);
        if (null !== $signature) {
            $this->withHeader('Stripe-Signature', $signature);
        }

        return $this->sendRaw('POST', '/webhooks/stripe', 'application/json; charset=utf-8', $body);
    }

    /** @return list<string> the subscription ids queued for a sync */
    private function queuedSyncs(): array
    {
        /** @var InMemoryTransport $queue */
        $queue = static::getContainer()->get('messenger.transport.stripe_events');

        return array_map(static function (Envelope $envelope): string {
            $message = $envelope->getMessage();
            self::assertInstanceOf(SyncSubscription::class, $message);

            return $message->stripeSubscriptionId;
        }, $queue->getSent());
    }

    /** @return list<LogRecord> */
    private function logRecords(): array
    {
        /** @var TestHandler $logs */
        $logs = static::getContainer()->get('monolog.handler.test_records');

        return $logs->getRecords();
    }

    /** @return list<LogRecord> */
    private function billingWarnings(): array
    {
        return array_values(array_filter($this->logRecords(), static fn (LogRecord $record): bool => 'billing' === $record->channel && Level::Warning === $record->level));
    }

    private function assertNotLogged(string $secret): void
    {
        $formatter = new JsonFormatter(includeStacktraces: true);

        foreach ($this->logRecords() as $record) {
            self::assertStringNotContainsString($secret, $formatter->format($record), sprintf('Log record "%s" contains "%s".', $record->message, $secret));
        }
    }

    #[Test]
    #[TestWith(['customer.subscription.created'])]
    #[TestWith(['customer.subscription.updated'])]
    #[TestWith(['customer.subscription.deleted'])]
    public function aSignedSubscriptionEventShouldQueueASyncOfItsSubscription(string $type): void
    {
        $body = $this->subscriptionEvent($type, 'sub_paid');

        $response = $this->deliver($body, $this->signature($body));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['sub_paid'], $this->queuedSyncs());
    }

    #[Test]
    public function aSignedCompletedCheckoutShouldQueueASyncOfTheSubscriptionItOpened(): void
    {
        $body = $this->event('checkout.session.completed', ['id' => 'cs_test_paid', 'object' => 'checkout.session', 'mode' => 'subscription', 'subscription' => 'sub_paid']);

        $response = $this->deliver($body, $this->signature($body));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['sub_paid'], $this->queuedSyncs());
    }

    #[Test]
    public function aSignedCompletedCheckoutWithoutASubscriptionShouldBeAcceptedAndDropped(): void
    {
        $body = $this->event('checkout.session.completed', ['id' => 'cs_test_one_off', 'object' => 'checkout.session', 'mode' => 'payment', 'subscription' => null]);

        $response = $this->deliver($body, $this->signature($body));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $this->queuedSyncs());
    }

    #[Test]
    #[TestWith(['invoice.paid'])]
    #[TestWith(['customer.created'])]
    public function aSignedEventOfAnotherTypeShouldBeAcceptedAndDropped(string $type): void
    {
        $body = $this->event($type, ['id' => 'in_paid', 'object' => 'invoice', 'subscription' => 'sub_paid']);

        $response = $this->deliver($body, $this->signature($body));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $this->queuedSyncs());
    }

    #[Test]
    public function anEventSignedWithinTheToleranceShouldBeAccepted(): void
    {
        $body = $this->subscriptionEvent('customer.subscription.updated', 'sub_paid');

        $response = $this->deliver($body, $this->signature($body, signedAt: time() - 240));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['sub_paid'], $this->queuedSyncs());
    }

    #[Test]
    public function anEventWithoutASignatureShouldBeRefusedWithoutDetailsAndLogged(): void
    {
        $body = $this->subscriptionEvent('customer.subscription.updated', 'sub_paid');

        $response = $this->deliver($body, signature: null);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertSame([], $this->queuedSyncs());
        $warnings = $this->billingWarnings();
        self::assertCount(1, $warnings);
        self::assertSame('Stripe webhook refused: its signature does not verify', $warnings[0]->message);
        self::assertSame([
            'reason' => 'no_header',
            'ip' => self::STRIPE_IP,
            'user_agent' => self::STRIPE_USER_AGENT,
            'body_bytes' => strlen($body),
        ], $warnings[0]->context);
    }

    #[Test]
    public function anEventSignedWithAnotherSecretShouldBeRefusedWithoutDetailsAndLogged(): void
    {
        $body = $this->subscriptionEvent('customer.subscription.updated', 'sub_paid');

        $response = $this->deliver($body, $this->signature($body, secret: 'whsec_of_someone_else'));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertSame([], $this->queuedSyncs());
        $warnings = $this->billingWarnings();
        self::assertCount(1, $warnings);
        self::assertSame('no_match', $warnings[0]->context['reason']);
        self::assertSame(self::STRIPE_IP, $warnings[0]->context['ip']);
        self::assertSame(self::STRIPE_USER_AGENT, $warnings[0]->context['user_agent']);
        self::assertSame(strlen($body), $warnings[0]->context['body_bytes']);
    }

    #[Test]
    public function aBodyChangedAfterItWasSignedShouldBeRefused(): void
    {
        $signed = $this->subscriptionEvent('customer.subscription.updated', 'sub_paid');
        $changed = $this->subscriptionEvent('customer.subscription.updated', 'sub_of_someone_else');

        $response = $this->deliver($changed, $this->signature($signed));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame([], $this->queuedSyncs());
        self::assertSame('no_match', $this->billingWarnings()[0]->context['reason']);
    }

    #[Test]
    public function anEventSignedLongerAgoThanTheToleranceShouldBeRefusedAndLogged(): void
    {
        $body = $this->subscriptionEvent('customer.subscription.updated', 'sub_paid');

        $response = $this->deliver($body, $this->signature($body, signedAt: time() - 301));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertSame([], $this->queuedSyncs());
        $warnings = $this->billingWarnings();
        self::assertCount(1, $warnings);
        self::assertSame('outside_tolerance', $warnings[0]->context['reason']);
    }

    #[Test]
    public function aRefusedEventShouldBeLoggedByNeitherItsBodyNorItsHeaders(): void
    {
        $body = $this->event('customer.subscription.updated', ['id' => 'sub_paid', 'object' => 'subscription', 'customer_email' => self::CUSTOMER_EMAIL]);
        $signature = $this->signature($body, secret: 'whsec_of_someone_else');

        $response = $this->deliver($body, $signature);

        self::assertSame(400, $response->getStatusCode());
        $this->assertNotLogged(self::CUSTOMER_EMAIL);
        $this->assertNotLogged($signature);
    }

    #[Test]
    public function anAcceptedEventShouldBeLoggedByNeitherItsBodyNorItsHeaders(): void
    {
        $body = $this->event('customer.subscription.updated', ['id' => 'sub_paid', 'object' => 'subscription', 'customer_email' => self::CUSTOMER_EMAIL]);
        $signature = $this->signature($body);

        $response = $this->deliver($body, $signature);

        self::assertSame(200, $response->getStatusCode());
        $this->assertNotLogged(self::CUSTOMER_EMAIL);
        $this->assertNotLogged($signature);
    }

    #[Test]
    public function anEventShouldNeedNoAccessTokenToReachTheSignatureCheck(): void
    {
        $body = $this->subscriptionEvent('customer.subscription.updated', 'sub_paid');

        $this->clearCookies();
        $response = $this->deliver($body, $this->signature($body, secret: 'whsec_of_someone_else'));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('no_match', $this->billingWarnings()[0]->context['reason']);
    }

    #[Test]
    public function aStaleAccessTokenShouldNotStandBetweenStripeAndTheSignatureCheck(): void
    {
        $body = $this->subscriptionEvent('customer.subscription.updated', 'sub_paid');

        $this->setCookie('access_token', 'not.a.jwt');
        $response = $this->deliver($body, $this->signature($body));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['sub_paid'], $this->queuedSyncs());
    }

    #[Test]
    public function anEventShouldAnswer500AndBeLoggedAsCriticalWhileOurSecretIsNotSet(): void
    {
        /** @var MessageBusInterface $bus */
        $bus = static::getContainer()->get('billing.bus');
        static::getContainer()->set(StripeWebhookController::class, new StripeWebhookController(
            billingBus: $bus,
            logger: static::getContainer()->get('monolog.logger.billing'),
            webhookSecret: '',
        ));
        $body = $this->subscriptionEvent('customer.subscription.updated', 'sub_paid');

        $response = $this->deliver($body, $this->signature($body, secret: ''));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertSame([], $this->queuedSyncs());
        $critical = array_values(array_filter($this->logRecords(), static fn (LogRecord $record): bool => 'billing' === $record->channel && Level::Critical === $record->level));
        self::assertCount(1, $critical);
        self::assertSame('Stripe webhook not checked: STRIPE_WEBHOOK_SECRET is not set', $critical[0]->message);
        self::assertSame([], $this->billingWarnings());
    }

    #[Test]
    public function anEventTheQueueRefusesShouldAnswer500SoThatStripeDeliversItAgain(): void
    {
        static::getContainer()->set('messenger.transport.stripe_events', new class implements TransportInterface {
            public function get(): iterable
            {
                return [];
            }

            public function ack(Envelope $envelope): void
            {
            }

            public function reject(Envelope $envelope): void
            {
            }

            public function send(Envelope $envelope): Envelope
            {
                throw new TransportException('The broker is down.');
            }
        });
        $body = $this->subscriptionEvent('customer.subscription.updated', 'sub_paid');

        $response = $this->deliver($body, $this->signature($body));

        self::assertSame(500, $response->getStatusCode());
    }
}
