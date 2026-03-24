<?php

declare(strict_types=1);

namespace App\Task\DataFixtures;

use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Story;

final class TaskStory extends Story
{
    private const int BATCH_SIZE = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function build(): void
    {
        $lastId = 0;

        while (true) {
            $users = $this->em->createQuery(
                'SELECT u FROM App\Auth\Entity\User u WHERE u.id > :lastId ORDER BY u.id ASC'
            )
                ->setParameter('lastId', $lastId)
                ->setMaxResults(self::BATCH_SIZE)
                ->getResult();

            if (0 === count($users)) {
                break;
            }

            foreach ($users as $user) {
                TaskFactory::createMany(random_int(1, 3), ['user' => $user]);
            }

            $lastId = end($users)->getId();
            $this->em->clear();
        }
    }
}
