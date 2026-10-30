<?php

declare(strict_types=1);

namespace App\Billing\Entity;

use App\Billing\Enum\Plan;
use App\Billing\Repository\SubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** A workspace without one is on Free. */
#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscriptions')]
class Subscription
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $workspaceId,

        #[ORM\Column(length: 20, enumType: Plan::class)]
        private Plan $plan,
    ) {
    }

    public function getPlan(): Plan
    {
        return $this->plan;
    }
}
