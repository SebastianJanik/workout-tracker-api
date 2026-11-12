<?php

declare(strict_types=1);

namespace App\Progress\Domain\Strength\ValueObject;

final readonly class SessionId
{
    public function __construct(public string $value)
    {
        if ('' === $value) {
            throw new \InvalidArgumentException('A session id cannot be empty.');
        }
    }
}
