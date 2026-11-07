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

        $connection->executeStatement(
            'INSERT INTO payers (workspace_id, stripe_customer_id) VALUES (:workspace, :customer) ON CONFLICT (workspace_id) DO NOTHING',
            ['workspace' => $workspaceId->toRfc4122(), 'customer' => $customerId],
        );

        $saved = $connection->fetchOne('SELECT stripe_customer_id FROM payers WHERE workspace_id = :workspace', ['workspace' => $workspaceId->toRfc4122()]);

        if (!is_string($saved)) {
            throw new \LogicException(sprintf('The workspace %s has no payer right after saving one.', $workspaceId->toRfc4122()));
        }

        return $saved;
    }
}
