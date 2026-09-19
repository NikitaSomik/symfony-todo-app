<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    // @phpstan-ignore method.unused (overrides the private KernelTrait method called on boot)
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
