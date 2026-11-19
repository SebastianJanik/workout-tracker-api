<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Doctrine\Type;

use App\SharedKernel\Domain\ValueObject\UserId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\GuidType;

/**
 * Maps the {@see UserId} value object to a native UUID column. Registered as `user_id` in doctrine.yaml.
 */
final class UserIdType extends GuidType
{
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UserId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return $value instanceof UserId ? $value : UserId::fromString(is_string($value) ? $value : throw new \InvalidArgumentException(sprintf('Expected a string, got %s.', get_debug_type($value))));
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!$value instanceof UserId) {
            throw new \InvalidArgumentException(sprintf('Expected %s, got %s.', UserId::class, get_debug_type($value)));
        }

        return $value->value();
    }
}
