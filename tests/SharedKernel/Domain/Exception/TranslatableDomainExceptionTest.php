<?php

declare(strict_types=1);

namespace App\Tests\SharedKernel\Domain\Exception;

use App\SharedKernel\Domain\Exception\TranslatableDomainException;
use App\SharedKernel\Domain\Exception\TranslatableException;
use PHPUnit\Framework\TestCase;

final class TranslatableDomainExceptionTest extends TestCase
{
    public function testSplitsTheKeyIntoTranslationDomainAndMessageKey(): void
    {
        $exception = new SampleTranslatable('identity.email.taken', ['%email%' => 'a@b.io']);

        self::assertInstanceOf(TranslatableException::class, $exception);
        self::assertSame('identity', $exception->translationDomain());
        self::assertSame('email.taken', $exception->messageKey());
        self::assertSame(['%email%' => 'a@b.io'], $exception->messageParameters());
    }

    public function testGetMessageReturnsTheFullKeySoLogsStayReadable(): void
    {
        self::assertSame('identity.email.taken', (new SampleTranslatable('identity.email.taken'))->getMessage());
    }

    public function testRejectsAKeyWithoutADomainPrefix(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SampleTranslatable('nodomain');
    }
}

final class SampleTranslatable extends TranslatableDomainException
{
}
