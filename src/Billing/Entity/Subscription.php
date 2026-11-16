<?php

declare(strict_types=1);

namespace App\Billing\Entity;

use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;
use App\Billing\Repository\SubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscriptions')]
#[ORM\Index(name: 'idx_subscriptions_workspace_id', columns: ['workspace_id'])]
#[ORM\UniqueConstraint(name: 'uniq_subscriptions_workspace_id_not_canceled', columns: ['workspace_id'], options: ['where' => "((status)::text <> 'canceled'::text)"])]
class Subscription
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 255)]
        private string $stripeSubscriptionId,

        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $workspaceId,

        #[ORM\Column(length: 20, enumType: Plan::class)]
        private Plan $plan,

        #[ORM\Column(length: 20, enumType: SubscriptionStatus::class)]
        private SubscriptionStatus $status,
    ) {
    }

    public function getStripeSubscriptionId(): string
    {
        return $this->stripeSubscriptionId;
    }

    public function getPlan(): Plan
    {
        return $this->plan;
    }

    public function getStatus(): SubscriptionStatus
    {
        return $this->status;
    }

    public function grantedPlan(): Plan
    {
        if ($this->status->grantsPlan()) {
            return $this->plan;
        }

        return Plan::FREE;
    }

    public function refresh(Plan $plan, SubscriptionStatus $status): void
    {
        $this->plan = $plan;
        $this->status = $status;
    }
}
