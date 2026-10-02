<?php

declare(strict_types=1);

namespace App\Task;

use App\Task\Entity\Task;
use App\Task\Enum\TaskStatus;
use App\Task\Identity\TaskIdGenerator;
use App\Task\Identity\UuidV7TaskIdGenerator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $di): void {
    $services = $di->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->load('App\Task\\', __DIR__)
        ->exclude([
            __DIR__.'/DTO/',
            __DIR__.'/Entity/',
            __DIR__.'/Enum/',
            __DIR__.'/{di,routing}.php',
        ]);

    $services->alias(TaskIdGenerator::class, UuidV7TaskIdGenerator::class);

    $di->extension('framework', [
        'workflows' => [
            'task_lifecycle' => [
                'type' => 'state_machine',
                'marking_store' => ['type' => 'method', 'property' => 'status'],
                'supports' => [Task::class],
                'initial_marking' => TaskStatus::TODO,
                'places' => TaskStatus::class.'::*',
                'transitions' => [
                    'start' => ['from' => TaskStatus::TODO, 'to' => TaskStatus::IN_PROGRESS],
                    'submit_for_review' => ['from' => TaskStatus::IN_PROGRESS, 'to' => TaskStatus::IN_REVIEW],
                    'complete' => ['from' => TaskStatus::IN_REVIEW, 'to' => TaskStatus::COMPLETED],
                    'cancel' => [
                        'from' => [TaskStatus::TODO, TaskStatus::IN_PROGRESS, TaskStatus::IN_REVIEW],
                        'to' => TaskStatus::CANCELLED,
                    ],
                ],
            ],
        ],
    ]);
};
