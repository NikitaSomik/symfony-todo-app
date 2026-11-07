<?php

declare(strict_types=1);

namespace App\Billing\Entity;

use App\Billing\Repository\PayerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Who pays for a workspace: a Stripe customer, whose invoice details live in Stripe. */
#[ORM\Entity(repositoryClass: PayerRepository::class)]
#[ORM\Table(name: 'payers')]
class Payer
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $workspaceId,

        #[ORM\Column(length: 255, unique: true)]
        private string $stripeCustomerId,
    ) {
    }

    public function getStripeCustomerId(): string
    {
        return $this->stripeCustomerId;
    }
}
