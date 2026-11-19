<?php

declare(strict_types=1);

namespace App\Tests\Identity\Support;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base for HTTP tests of the Identity API. Each test is rolled back by DAMA, so no cleanup is needed.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected const string PASSWORD = 'correct-horse-battery';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $server
     *
     * @return array<string, mixed>
     */
    protected function api(string $method, string $uri, array $body = [], array $server = []): array
    {
        $this->client->jsonRequest($method, $uri, $body, $server);

        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true);

        /** @var array<string, mixed> $result */
        $result = is_array($decoded) ? $decoded : [];

        return $result;
    }

    protected function register(string $email = 'anna@example.com'): void
    {
        $this->api('POST', '/api/auth/register', ['email' => $email, 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(201);
    }

    /**
     * @return array{token: string, refresh_token: string}
     */
    protected function login(string $email = 'anna@example.com', string $password = self::PASSWORD): array
    {
        $data = $this->api('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
        self::assertResponseStatusCodeSame(200);
        self::assertIsString($data['token'] ?? null);
        self::assertIsString($data['refresh_token'] ?? null);

        return ['token' => $data['token'], 'refresh_token' => $data['refresh_token']];
    }

    /**
     * @return array<string, string>
     */
    protected static function bearer(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$token];
    }
}
