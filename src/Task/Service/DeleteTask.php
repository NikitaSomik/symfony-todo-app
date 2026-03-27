<?php

declare(strict_types=1);

namespace App\Task\Service;

use App\Task\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;

final class DeleteTask
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function handle(Task $task): void
    {
        $this->em->remove($task);
        $this->em->flush();
    }
}
