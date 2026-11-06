<?php

declare(strict_types=1);

namespace App\Core;

/** Servisler geçersiz veride bunu atar; controller hataları forma geri taşır. */
final class ValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Validation failed');
    }
}
