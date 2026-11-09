<?php

declare(strict_types=1);

namespace App\Billing\Repository;

use App\Billing\Entity\Payer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Payer>
 */
class PayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payer::class);
    }

    public function findCustomerId(Uuid $workspaceId): ?string
    {
        $payer = $this->find($workspaceId);

        return null === $payer ? null : $payer->getStripeCustomerId();
    }

    public function saveOrGetExisting(Uuid $workspaceId, string $customerId): string
    {
        $connection = $this->getEntityManager()->getConnection();

        // The no-op update is what makes RETURNING give back the row that was already there.
        $saved = $connection->fetchOne(
            'INSERT INTO payers (workspace_id, stripe_customer_id) VALUES (:workspace, :customer)
             ON CONFLICT (workspace_id) DO UPDATE SET stripe_customer_id = payers.stripe_customer_id
             RETURNING stripe_customer_id',
            ['workspace' => $workspaceId->toRfc4122(), 'customer' => $customerId],
        );

        if (!is_string($saved)) {
            throw new \LogicException(sprintf('The upsert returned no payer for the workspace %s.', $workspaceId->toRfc4122()));
        }

        return $saved;
    }
}
