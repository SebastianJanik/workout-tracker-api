<?php

declare(strict_types=1);

namespace App\Tests\Identity\Presentation;

use App\Identity\Domain\Repository\UserRepository;
use App\Identity\Domain\ValueObject\Email;
use App\Tests\Identity\Support\ApiTestCase;

final class RegisterControllerTest extends ApiTestCase
{
    public function testRegistersAUserAndNeverReturnsThePassword(): void
    {
        $data = $this->api('POST', '/api/auth/register', ['email' => '  Anna@Example.com ', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['email', 'id'], $this->sortedKeys($data));
        self::assertSame('anna@example.com', $data['email']);
        self::assertIsString($data['id']);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $data['id']);
        self::assertStringNotContainsString(self::PASSWORD, (string) $this->client->getResponse()->getContent());
    }

    public function testStoresAHashNotThePlainPassword(): void
    {
        $this->register();

        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        $user = $users->getByEmail(Email::fromString('anna@example.com'));
        self::assertNotSame(self::PASSWORD, $user->password()->value());
        self::assertStringStartsWith('$', $user->password()->value());
    }

    public function testRejectsInvalidInputWithViolationsPerField(): void
    {
        $data = $this->api('POST', '/api/auth/register', ['email' => 'not-an-email', 'password' => 'short']);

        self::assertResponseStatusCodeSame(422);
        self::assertIsArray($data['violations'] ?? null);
        $fields = array_column($data['violations'], 'propertyPath');
        sort($fields);
        self::assertSame(['email', 'password'], $fields);
    }

    public function testRejectsAMissingPassword(): void
    {
        $this->api('POST', '/api/auth/register', ['email' => 'anna@example.com']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testRejectsAnExistingEmailCaseInsensitively(): void
    {
        $this->register('anna@example.com');

        $this->api('POST', '/api/auth/register', ['email' => 'ANNA@Example.com', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(409);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private function sortedKeys(array $data): array
    {
        $keys = array_keys($data);
        sort($keys);

        return $keys;
    }
}
