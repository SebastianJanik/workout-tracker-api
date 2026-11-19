<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\Exception;

/**
 * Marker: the input is understood but not acceptable (HTTP 422).
 */
interface InvalidInput extends \Throwable
{
}
