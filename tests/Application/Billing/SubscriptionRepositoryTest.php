<?php

declare(strict_types=1);

namespace App\Tests\Application\Billing;

use App\Billing\Entity\Subscription;
use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;
use App\Billing\Repository\SubscriptionRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class SubscriptionRepositoryTest extends KernelTestCase
{
    private function subscriptions(): SubscriptionRepository
    {
        return static::getContainer()->get(SubscriptionRepository::class);
    }

    private function save(Uuid $workspaceId, SubscriptionStatus $status, Plan $plan = Plan::TEAM): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist(new Subscription(stripeSubscriptionId: 'sub_'.Uuid::v7()->toBase58(), workspaceId: $workspaceId, plan: $plan, status: $status));
        $em->flush();
    }

    #[Test]
    public function aWorkspaceWithoutASubscriptionShouldBeOnFree(): void
    {
        self::assertSame(Plan::FREE, $this->subscriptions()->planOf(Uuid::v7()));
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

        self::assertSame($expected, $this->subscriptions()->planOf($workspaceId));
    }

    #[Test]
    public function aNewSubscriptionAfterACancelledOneShouldGiveThePlan(): void
    {
        $workspaceId = Uuid::v7();
        $this->save($workspaceId, SubscriptionStatus::CANCELED, Plan::BUSINESS);
        $this->save($workspaceId, SubscriptionStatus::ACTIVE, Plan::TEAM);

        self::assertSame(Plan::TEAM, $this->subscriptions()->planOf($workspaceId));
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

        self::assertSame(Plan::FREE, $this->subscriptions()->planOf($workspaceId));
    }

    #[Test]
    public function aWorkspaceWithAPlanGrantedShouldNotCountOnFree(): void
    {
        $paid = Uuid::v7();
        $inGrace = Uuid::v7();
        $this->save($paid, SubscriptionStatus::ACTIVE);
        $this->save($inGrace, SubscriptionStatus::GRACE);

        self::assertSame(1, $this->subscriptions()->countWorkspacesOnFree([$paid, $inGrace, Uuid::v7()]));
    }

    #[Test]
    #[TestWith([SubscriptionStatus::PENDING])]
    #[TestWith([SubscriptionStatus::SUSPENDED])]
    #[TestWith([SubscriptionStatus::CANCELED])]
    public function aWorkspaceWhoseSubscriptionGrantsNoPlanShouldCountOnFree(SubscriptionStatus $status): void
    {
        $workspaceId = Uuid::v7();
        $this->save($workspaceId, $status);

        self::assertSame(2, $this->subscriptions()->countWorkspacesOnFree([$workspaceId, Uuid::v7()]));
    }

    #[Test]
    public function aWorkspacePaidAgainAfterACancelledSubscriptionShouldCountOnce(): void
    {
        $workspaceId = Uuid::v7();
        $this->save($workspaceId, SubscriptionStatus::CANCELED);
        $this->save($workspaceId, SubscriptionStatus::ACTIVE);

        self::assertSame(1, $this->subscriptions()->countWorkspacesOnFree([$workspaceId, Uuid::v7()]));
    }

    #[Test]
    public function noWorkspacesShouldCountNothingOnFree(): void
    {
        self::assertSame(0, $this->subscriptions()->countWorkspacesOnFree([]));
    }
}
