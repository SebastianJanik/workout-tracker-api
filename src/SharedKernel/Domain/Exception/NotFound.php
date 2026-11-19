<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\Exception;

/**
 * Marker: something that was asked for does not exist (HTTP 404).
 */
interface NotFound extends \Throwable
{
}
