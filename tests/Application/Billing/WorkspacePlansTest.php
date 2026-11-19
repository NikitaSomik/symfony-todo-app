<?php

declare(strict_types=1);

namespace App\Tests\Application\Billing;

use App\Billing\Entity\Payer;
use App\Billing\Entity\Subscription;
use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;
use App\Billing\Query\WorkspacePlans;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class WorkspacePlansTest extends KernelTestCase
{
    private function plans(): WorkspacePlans
    {
        return static::getContainer()->get(WorkspacePlans::class);
    }

    /** Straight to the table, past the payer's rule of one: these tests are about the reads and the database's own guards. */
    private function save(Uuid $workspaceId, SubscriptionStatus $status, Plan $plan = Plan::TEAM): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $payer = $em->find(Payer::class, $workspaceId);
        if (null === $payer) {
            $payer = new Payer(workspaceId: $workspaceId, stripeCustomerId: 'cus_'.$workspaceId->toBase58());
            $em->persist($payer);
        }
        $em->persist(new Subscription(stripeSubscriptionId: 'sub_'.Uuid::v7()->toBase58(), payer: $payer, plan: $plan, status: $status, updatedAt: new \DateTimeImmutable()));
        $em->flush();
    }

    #[Test]
    public function aWorkspaceWithoutASubscriptionShouldBeOnFree(): void
    {
        self::assertSame(Plan::FREE, $this->plans()->planOf(Uuid::v7()));
    }

    #[Test]
    #[TestWith([SubscriptionStatus::ACTIVE, Plan::BUSINESS])]
    #[TestWith([SubscriptionStatus::GRACE, Plan::BUSINESS])]
    #[TestWith([SubscriptionStatus::PENDING, Plan::FREE])]
    #[TestWith([SubscriptionStatus::SUSPENDED, Plan::FREE])]
    #[TestWith([SubscriptionStatus::CANCELED, Plan::FREE])]
    public function aWorkspacesPlanShouldFollowTheStatusOfItsSubscription(SubscriptionStatus $status, Plan $expected): void
    {
        $workspaceId = Uuid::v7();
        $this->save($workspaceId, $status, Plan::BUSINESS);

        self::assertSame($expected, $this->plans()->planOf($workspaceId));
    }

    #[Test]
    public function aNewSubscriptionAfterACancelledOneShouldGiveThePlan(): void
    {
        $workspaceId = Uuid::v7();
        $this->save($workspaceId, SubscriptionStatus::CANCELED, Plan::BUSINESS);
        $this->save($workspaceId, SubscriptionStatus::ACTIVE, Plan::TEAM);

        self::assertSame(Plan::TEAM, $this->plans()->planOf($workspaceId));
    }

    #[Test]
    #[TestWith([SubscriptionStatus::ACTIVE])]
    #[TestWith([SubscriptionStatus::PENDING])]
    #[TestWith([SubscriptionStatus::GRACE])]
    #[TestWith([SubscriptionStatus::SUSPENDED])]
    public function aSecondSubscriptionBesideOneNotCancelledShouldBeRefusedByTheDatabase(SubscriptionStatus $first): void
    {
        $workspaceId = Uuid::v7();
        $this->save($workspaceId, $first);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->save($workspaceId, SubscriptionStatus::PENDING);
    }

    #[Test]
    public function cancelledSubscriptionsShouldStayBesideEachOther(): void
    {
        $workspaceId = Uuid::v7();
        $this->save($workspaceId, SubscriptionStatus::CANCELED);
        $this->save($workspaceId, SubscriptionStatus::CANCELED);

        self::assertSame(Plan::FREE, $this->plans()->planOf($workspaceId));
    }

    #[Test]
    public function aWorkspaceWithAPlanGrantedShouldNotCountOnFree(): void
    {
        $paid = Uuid::v7();
        $inGrace = Uuid::v7();
        $this->save($paid, SubscriptionStatus::ACTIVE);
        $this->save($inGrace, SubscriptionStatus::GRACE);

        self::assertSame(1, $this->plans()->countOnFree([$paid, $inGrace, Uuid::v7()]));
    }

    #[Test]
    #[TestWith([SubscriptionStatus::PENDING])]
    #[TestWith([SubscriptionStatus::SUSPENDED])]
    #[TestWith([SubscriptionStatus::CANCELED])]
    public function aWorkspaceWhoseSubscriptionGrantsNoPlanShouldCountOnFree(SubscriptionStatus $status): void
    {
        $workspaceId = Uuid::v7();
        $this->save($workspaceId, $status);

        self::assertSame(2, $this->plans()->countOnFree([$workspaceId, Uuid::v7()]));
    }

    #[Test]
    public function aWorkspacePaidAgainAfterACancelledSubscriptionShouldCountOnce(): void
    {
        $workspaceId = Uuid::v7();
        $this->save($workspaceId, SubscriptionStatus::CANCELED);
        $this->save($workspaceId, SubscriptionStatus::ACTIVE);

        self::assertSame(1, $this->plans()->countOnFree([$workspaceId, Uuid::v7()]));
    }

    #[Test]
    public function noWorkspacesShouldCountNothingOnFree(): void
    {
        self::assertSame(0, $this->plans()->countOnFree([]));
    }

    #[Test]
    public function aSubscriptionWithoutAPayerShouldBeRefusedByTheDatabase(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $this->expectException(ForeignKeyConstraintViolationException::class);

        $em->getConnection()->insert('subscriptions', [
            'stripe_subscription_id' => 'sub_orphan',
            'workspace_id' => Uuid::v7()->toRfc4122(),
            'plan' => Plan::TEAM->value,
            'status' => SubscriptionStatus::ACTIVE->value,
            'updated_at' => '2026-11-18 10:00:00+00',
        ]);
    }
}
