<?php

declare(strict_types=1);

namespace App\Tests\SharedKernel\Presentation;

use App\Tests\Identity\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class LocalizedErrorsTest extends ApiTestCase
{
    #[DataProvider('languages')]
    public function testDuplicateEmailIsTranslatedByAcceptLanguage(?string $header, string $title, string $detail): void
    {
        $this->register();

        $server = null === $header ? [] : ['HTTP_ACCEPT_LANGUAGE' => $header];
        $body = $this->api('POST', '/api/auth/register', ['email' => 'anna@example.com', 'password' => self::PASSWORD], $server);

        self::assertResponseStatusCodeSame(409);
        self::assertSame($title, $body['title']);
        self::assertSame($detail, $body['detail']);
    }

    /** @return iterable<string, array{?string, string, string}> */
    public static function languages(): iterable
    {
        $en = ['Conflict', 'A user with email "anna@example.com" is already registered.'];
        yield 'no header' => [null, ...$en];
        yield 'english' => ['en', ...$en];
        yield 'unsupported falls back to english' => ['fr', ...$en];
        yield 'polish' => ['pl', 'Konflikt', 'Użytkownik z adresem e-mail "anna@example.com" jest już zarejestrowany.'];
        yield 'polish with quality list' => ['fr;q=0.9, pl;q=0.8', 'Konflikt', 'Użytkownik z adresem e-mail "anna@example.com" jest już zarejestrowany.'];
    }

    public function testValidationViolationsAreTranslated(): void
    {
        $body = $this->api('POST', '/api/auth/register', ['email' => 'nope', 'password' => 'short'], ['HTTP_ACCEPT_LANGUAGE' => 'pl']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Nieprawidłowe dane', $body['title']);
        $violations = $body['violations'];
        self::assertIsArray($violations);
        self::assertSame(['email', 'password'], array_column($violations, 'field'));
        self::assertSame('Ta wartość nie jest prawidłowym adresem email.', array_column($violations, 'message')[0]);
    }

    public function testLoginFailureIsTranslated(): void
    {
        $body = $this->api('POST', '/api/auth/login', ['email' => 'x@example.com', 'password' => 'wrong-password-123'], ['HTTP_ACCEPT_LANGUAGE' => 'pl']);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('Brak autoryzacji', $body['title']);
        self::assertSame('Nieprawidłowy e-mail lub hasło.', $body['detail']);
    }

    public function testMissingTokenIsTranslated(): void
    {
        $body = $this->api('GET', '/api/me', [], ['HTTP_ACCEPT_LANGUAGE' => 'pl']);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('Brak autoryzacji', $body['title']);
        self::assertSame('Wymagane jest uwierzytelnienie.', $body['detail']);
    }
}
