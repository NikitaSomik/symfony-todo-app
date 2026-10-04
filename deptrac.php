<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\DirectoryConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

/*
 * Which module may depend on which (ADR 0001). A module that is not listed after
 * accesses() must not be imported: Shared depends on nothing, and nothing depends on Task.
 */
return static function (DeptracConfig $config): void {
    $module = static fn (string $name): Layer => Layer::withName($name)->collectors(DirectoryConfig::create('src/'.$name.'/.*'));

    $config
        ->paths('./src')
        ->layers(
            $auth = $module('Auth'),
            $task = $module('Task'),
            $auditLog = $module('AuditLog'),
            $shared = $module('Shared'),
        )
        ->rulesets(
            Ruleset::forLayer($task)->accesses($auth, $auditLog, $shared),
            Ruleset::forLayer($auth)->accesses($shared),
            Ruleset::forLayer($auditLog)->accesses($shared),
            Ruleset::forLayer($shared),
        )
    ;
};
