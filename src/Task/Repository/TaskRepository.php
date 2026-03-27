<?php

declare(strict_types=1);

namespace App\Task\Repository;

use App\Auth\Entity\User;
use App\Shared\Persistence\Doctrine\SpecificationApplier;
use App\Task\DTO\TaskListQueryDTO;
use App\Task\Entity\Task;
use App\Task\Query\Specification\TaskSortSpecification;
use App\Task\Query\Specification\TaskStatusSpecification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SpecificationApplier $specificationApplier,
    ) {
        parent::__construct($registry, Task::class);
    }

    /**
     * @return Task[]
     */
    public function findForUserList(User $user, TaskListQueryDTO $query): array
    {
        if (null !== $query->searchQuery()) {
            return $this->searchForUserList($user, $query);
        }

        $queryBuilder = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->setFirstResult(($query->page->number - 1) * $query->page->size)
            ->setMaxResults($query->page->size);

        $this->specificationApplier->apply($queryBuilder, [
            new TaskStatusSpecification($query->filter->status),
            new TaskSortSpecification($query->sort()),
        ]);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function countForUserList(User $user, TaskListQueryDTO $query): int
    {
        if (null !== $query->searchQuery()) {
            return $this->countSearchResultsForUserList($user, $query);
        }

        $queryBuilder = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.user = :user')
            ->setParameter('user', $user);

        $this->specificationApplier->apply($queryBuilder, [
            new TaskStatusSpecification($query->filter->status),
        ]);

        return (int) $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Task[]
     */
    private function searchForUserList(User $user, TaskListQueryDTO $query): array
    {
        $searchQuery = $query->searchQuery();

        if (null === $searchQuery) {
            return [];
        }

        [$where, $params, $types] = $this->buildSearchConditions($user, $query);
        $params['limit'] = $query->page->size;
        $params['offset'] = ($query->page->number - 1) * $query->page->size;
        $types['limit'] = ParameterType::INTEGER;
        $types['offset'] = ParameterType::INTEGER;

        $sql = <<<'SQL'
            SELECT t.id
            FROM tasks t
            WHERE %s
            ORDER BY
              ts_rank_cd(t.search_vector, websearch_to_tsquery('simple', :search)) DESC,
              t.created_at DESC,
              t.id DESC
            LIMIT :limit OFFSET :offset
        SQL;

        $ids = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                sprintf($sql, implode(' AND ', $where)),
                $params,
                $types,
            )
            ->fetchFirstColumn();

        if ([] === $ids) {
            return [];
        }

        return $this->hydrateTasksInSearchOrder($ids);
    }

    private function countSearchResultsForUserList(User $user, TaskListQueryDTO $query): int
    {
        $searchQuery = $query->searchQuery();

        if (null === $searchQuery) {
            return 0;
        }

        [$where, $params, $types] = $this->buildSearchConditions($user, $query);

        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM tasks t
            WHERE %s
        SQL;

        return (int) $this->getEntityManager()
            ->getConnection()
            ->fetchOne(
                sprintf($sql, implode(' AND ', $where)),
                $params,
                $types,
            );
    }

    /**
     * @return array{0: string[], 1: array<string, int|string>, 2: array<string, ParameterType>}
     */
    private function buildSearchConditions(User $user, TaskListQueryDTO $query): array
    {
        $searchQuery = $query->searchQuery();
        $userId = $user->getId();

        if (null === $searchQuery || null === $userId) {
            return [[], [], []];
        }

        $where = ['t.user_id = :user_id', "t.search_vector @@ websearch_to_tsquery('simple', :search)"];
        $params = [
            'user_id' => $userId,
            'search' => $searchQuery->value,
        ];
        $types = [
            'user_id' => ParameterType::INTEGER,
            'search' => ParameterType::STRING,
        ];

        if (null !== $query->filter->status) {
            $where[] = 't.status = :status';
            $params['status'] = $query->filter->status;
            $types['status'] = ParameterType::STRING;
        }

        return [$where, $params, $types];
    }

    /**
     * @param list<int|string> $ids
     *
     * @return Task[]
     */
    private function hydrateTasksInSearchOrder(array $ids): array
    {
        $tasks = $this->createQueryBuilder('t')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', array_map('intval', $ids))
            ->getQuery()
            ->getResult();

        $tasksById = [];

        foreach ($tasks as $task) {
            $tasksById[$task->getId()] = $task;
        }

        $orderedTasks = [];

        foreach ($ids as $id) {
            $task = $tasksById[(int) $id] ?? null;

            if (null !== $task) {
                $orderedTasks[] = $task;
            }
        }

        return $orderedTasks;
    }
}
