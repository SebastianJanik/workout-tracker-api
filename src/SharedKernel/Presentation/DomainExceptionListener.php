<?php

declare(strict_types=1);

namespace App\SharedKernel\Presentation;

use App\SharedKernel\Domain\Exception\Conflict;
use App\SharedKernel\Domain\Exception\InvalidInput;
use App\SharedKernel\Domain\Exception\NotFound;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Turns domain exceptions into HTTP errors, once for the whole app (Laravel analogy: the exception
 * handler's render(), but driven by marker interfaces so no exception is listed anywhere).
 *
 * 1. Messenger wraps whatever a handler throws in a HandlerFailedException: unwrap it (single cause only).
 * 2. A cause implementing a marker from SharedKernel\Domain\Exception becomes an HttpException with
 *    the matching status; anything else is left alone, so real bugs stay 500.
 *
 * Runs before Symfony's ErrorListener (priority -128). A new status means a new marker and a line here.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final class DomainExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if ($throwable instanceof HandlerFailedException) {
            $wrapped = $throwable->getWrappedExceptions();
            if (1 !== count($wrapped)) {
                return;
            }
            $throwable = reset($wrapped);
            $event->setThrowable($throwable);
        }

        $status = match (true) {
            $throwable instanceof Conflict => Response::HTTP_CONFLICT,
            $throwable instanceof NotFound => Response::HTTP_NOT_FOUND,
            $throwable instanceof InvalidInput => Response::HTTP_UNPROCESSABLE_ENTITY,
            default => null,
        };

        if (null !== $status) {
            $event->setThrowable(new HttpException($status, $throwable->getMessage(), $throwable));
        }
    }
}
