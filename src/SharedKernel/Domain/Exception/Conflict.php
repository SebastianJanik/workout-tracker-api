<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\Exception;

/**
 * Marker: the request conflicts with the current state (HTTP 409), e.g. a duplicate.
 * Domain exceptions pick the HTTP meaning by implementing one of these markers; no framework class is needed.
 */
interface Conflict extends \Throwable
{
}
