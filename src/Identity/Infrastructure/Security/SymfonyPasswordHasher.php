<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\ValueObject\HashedPassword;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Adapter: the Symfony PasswordHasher component behind the application's {@see PasswordHasher} port.
 * Uses the hasher configured for SecurityUser, so login verifies with the same algorithm.
 */
final readonly class SymfonyPasswordHasher implements PasswordHasher
{
    public function __construct(private PasswordHasherFactoryInterface $hasherFactory)
    {
    }

    public function hash(#[\SensitiveParameter] string $plainPassword): HashedPassword
    {
        return HashedPassword::fromHash($this->hasherFactory->getPasswordHasher(SecurityUser::class)->hash($plainPassword));
    }
}
