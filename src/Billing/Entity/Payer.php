<?php

declare(strict_types=1);

namespace App\Billing\Entity;

use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;
use App\Billing\Exception\SecondSubscriptionException;
use App\Billing\Repository\PayerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PayerRepository::class)]
#[ORM\Table(name: 'payers')]
class Payer
{
    /** @var Collection<string, Subscription> */
    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'payer', cascade: ['persist'], indexBy: 'stripeSubscriptionId')]
    private Collection $subscriptions;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $workspaceId,

        #[ORM\Column(length: 255, unique: true)]
        private string $stripeCustomerId,
    ) {
        $this->subscriptions = new ArrayCollection();
    }

    public function getWorkspaceId(): Uuid
    {
        return $this->workspaceId;
    }

    public function getStripeCustomerId(): string
    {
        return $this->stripeCustomerId;
    }

    /** @return list<Subscription> */
    public function getSubscriptions(): array
    {
        return array_values($this->subscriptions->toArray());
    }

    /**
     * @throws SecondSubscriptionException
     */
    public function syncSubscription(string $stripeSubscriptionId, Plan $plan, SubscriptionStatus $status, \DateTimeImmutable $at): void
    {
        $known = $this->subscriptions->get($stripeSubscriptionId);
        if (null !== $known) {
            $known->sync($plan, $status, $at);

            return;
        }

        $current = $this->findNotCanceledSubscription();
        if (null !== $current) {
            throw new SecondSubscriptionException(workspaceId: $this->workspaceId, subscriptionId: $stripeSubscriptionId, currentSubscriptionId: $current->getStripeSubscriptionId());
        }

        $this->subscriptions->set($stripeSubscriptionId, new Subscription(
            stripeSubscriptionId: $stripeSubscriptionId,
            payer: $this,
            plan: $plan,
            status: $status,
            updatedAt: $at,
        ));
    }

    private function findNotCanceledSubscription(): ?Subscription
    {
        foreach ($this->subscriptions as $subscription) {
            if (SubscriptionStatus::CANCELED !== $subscription->getStatus()) {
                return $subscription;
            }
        }

        return null;
    }
}
