<?php

declare(strict_types=1);

namespace App\Tests\SharedKernel\Presentation;

use App\SharedKernel\Domain\Exception\Conflict;
use App\SharedKernel\Domain\Exception\InvalidInput;
use App\SharedKernel\Domain\Exception\NotFound;
use App\SharedKernel\Domain\Exception\TranslatableDomainException;
use App\SharedKernel\Presentation\DomainExceptionListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

final class DomainExceptionListenerTest extends TestCase
{
    /**
     * @param class-string<\Throwable> $exceptionClass
     */
    #[DataProvider('markers')]
    public function testMapsAMarkedDomainExceptionToItsHttpStatus(string $exceptionClass, int $status): void
    {
        $event = $this->eventFor(new $exceptionClass('shared.boom'));

        (new DomainExceptionListener())($event);

        $mapped = $event->getThrowable();
        self::assertInstanceOf(HttpExceptionInterface::class, $mapped);
        self::assertSame($status, $mapped->getStatusCode());
        self::assertSame('shared.boom', $mapped->getMessage());
        self::assertInstanceOf($exceptionClass, $mapped->getPrevious());
    }

    /** @return iterable<string, array{class-string<\Throwable>, int}> */
    public static function markers(): iterable
    {
        yield 'conflict' => [ConflictStub::class, 409];
        yield 'not found' => [NotFoundStub::class, 404];
        yield 'invalid input' => [InvalidInputStub::class, 422];
    }

    public function testUnwrapsAMessengerFailureBeforeMapping(): void
    {
        $event = $this->eventFor(new HandlerFailedException(new Envelope(new \stdClass()), [new ConflictStub('shared.taken')]));

        (new DomainExceptionListener())($event);

        $mapped = $event->getThrowable();
        self::assertInstanceOf(HttpExceptionInterface::class, $mapped);
        self::assertSame(409, $mapped->getStatusCode());
    }

    public function testLeavesAnUnmarkedExceptionUntouched(): void
    {
        $original = new \RuntimeException('real bug');
        $event = $this->eventFor($original);

        (new DomainExceptionListener())($event);

        self::assertSame($original, $event->getThrowable());
    }

    public function testUnwrapsButDoesNotMapAnUnmarkedCause(): void
    {
        $cause = new \RuntimeException('real bug');
        $event = $this->eventFor(new HandlerFailedException(new Envelope(new \stdClass()), [$cause]));

        (new DomainExceptionListener())($event);

        self::assertSame($cause, $event->getThrowable());
    }

    private function eventFor(\Throwable $throwable): ExceptionEvent
    {
        return new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $throwable);
    }
}

final class ConflictStub extends TranslatableDomainException implements Conflict
{
}

final class NotFoundStub extends TranslatableDomainException implements NotFound
{
}

final class InvalidInputStub extends TranslatableDomainException implements InvalidInput
{
}
