<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\Contract\UserDirectory;
use App\Auth\Entity\User;
use App\Auth\ValueObject\Email;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface, UserDirectory
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        return $this->findOneBy(['email' => (new Email($identifier))->value]);
    }

    public function findIdByEmail(string $email): ?int
    {
        $id = $this->createQueryBuilder('u')
            ->select('u.id')
            ->where('u.email = :email')
            ->setParameter('email', (new Email($email))->value)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR);

        return null === $id ? null : (int) $id;
    }

    public function findEmailById(int $id): ?string
    {
        $email = $this->createQueryBuilder('u')
            ->select('u.email')
            ->where('u.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR);

        return null === $email ? null : (string) $email;
    }
}
