<?php

declare(strict_types=1);

namespace App\Auth\Exception;

final class EmailAlreadyTakenException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Email is already taken.');
    }
}
