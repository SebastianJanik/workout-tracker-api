<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use Doctrine\Migrations\Version\Comparator;
use Doctrine\Migrations\Version\Version;

/**
 * Orders migrations by their class name without the namespace (Version<timestamp>...),
 * so migrations from different bounded contexts run in one chronological timeline.
 * Doctrine's default compares the full class name, which would sort by context name first.
 */
final class TimestampVersionComparator implements Comparator
{
    public function compare(Version $a, Version $b): int
    {
        return [self::shortName($a), (string) $a] <=> [self::shortName($b), (string) $b];
    }

    private static function shortName(Version $version): string
    {
        $name = (string) $version;
        $position = strrpos($name, '\\');

        return false === $position ? $name : substr($name, $position + 1);
    }
}
