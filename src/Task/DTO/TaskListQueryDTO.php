<?php

declare(strict_types=1);

namespace App\Task\DTO;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    properties: [
        new OA\Property(property: 'page', ref: new Model(type: TaskListPageDTO::class)),
    ]
)]
readonly class TaskListQueryDTO
{
    public function __construct(
        public TaskListPageDTO $page = new TaskListPageDTO(),
    ) {
    }
}
