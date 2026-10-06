<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\DirectoryConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

return static function (DeptracConfig $config): void {
    $module = static fn (string $name): Layer => Layer::withName($name)->collectors(DirectoryConfig::create('src/'.$name.'/.*'));
    $contract = static fn (string $name): Layer => Layer::withName($name.'Contract')->collectors(DirectoryConfig::create('src/'.$name.'/Contract/.*'));
    $internals = static fn (string $name): Layer => Layer::withName($name)->collectors(DirectoryConfig::create('src/'.$name.'/(?!Contract/).*'));

    $config
        ->paths('./src')
        ->layers(
            $authContract = $contract('Auth'),
            $auth = $internals('Auth'),
            $workspaceContract = $contract('Workspace'),
            $workspace = $internals('Workspace'),
            $task = $module('Task'),
            $auditLog = $module('AuditLog'),
            $shared = $module('Shared'),
        )
        ->rulesets(
            Ruleset::forLayer($task)->accesses($authContract, $workspaceContract, $auditLog, $shared),
            Ruleset::forLayer($workspace)->accesses($workspaceContract, $authContract, $auditLog, $shared),
            Ruleset::forLayer($workspaceContract),
            Ruleset::forLayer($auth)->accesses($authContract, $shared),
            Ruleset::forLayer($authContract),
            Ruleset::forLayer($auditLog)->accesses($shared),
            Ruleset::forLayer($shared),
        )
    ;
};
