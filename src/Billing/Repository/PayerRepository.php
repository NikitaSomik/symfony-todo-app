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

    /**
     * Two first purchases at once each create a customer in Stripe; the one saved first wins
     * and both go on with it. The other stays in Stripe, empty.
     *
     * @return string the customer the workspace has after this call
     */
    public function saveIfAbsent(Uuid $workspaceId, string $customerId): string
    {
        $connection = $this->getEntityManager()->getConnection();

        $connection->executeStatement(
            'INSERT INTO payers (workspace_id, stripe_customer_id) VALUES (:workspace, :customer) ON CONFLICT (workspace_id) DO NOTHING',
            ['workspace' => $workspaceId->toRfc4122(), 'customer' => $customerId],
        );

        /** @var string $saved */
        $saved = $connection->fetchOne('SELECT stripe_customer_id FROM payers WHERE workspace_id = :workspace', ['workspace' => $workspaceId->toRfc4122()]);

        return $saved;
    }
}
