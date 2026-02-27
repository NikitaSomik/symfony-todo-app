<?php

declare(strict_types=1);

namespace App\Task;

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routing): void {
    $routing->import(__DIR__.'/Controller/', 'attribute');
};
