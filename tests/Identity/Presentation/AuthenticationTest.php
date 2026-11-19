<?php

declare(strict_types=1);

namespace App\Tests\Identity\Presentation;

use App\Tests\Identity\Support\ApiTestCase;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;

final class AuthenticationTest extends ApiTestCase
{
    public function testLoginReturnsATokenAndARefreshToken(): void
    {
        $this->register();

        $tokens = $this->login();

        self::assertNotSame('', $tokens['token']);
        self::assertNotSame('', $tokens['refresh_token']);
    }

    public function testLoginFailuresLookIdenticalForWrongPasswordAndUnknownEmail(): void
    {
        $this->register();

        $this->client->jsonRequest('POST', '/api/auth/login', ['email' => 'anna@example.com', 'password' => 'wrong-password-123']);
        self::assertResponseStatusCodeSame(401);
        $wrongPassword = (string) $this->client->getResponse()->getContent();

        $this->client->jsonRequest('POST', '/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password-123']);
        self::assertResponseStatusCodeSame(401);

        self::assertSame($wrongPassword, (string) $this->client->getResponse()->getContent());
    }

    public function testRefreshIssuesANewTokenAndTheOldRefreshTokenIsSingleUse(): void
    {
        $this->register();
        $tokens = $this->login();

        $data = $this->api('POST', '/api/auth/refresh', ['refresh_token' => $tokens['refresh_token']]);
        self::assertResponseStatusCodeSame(200);
        self::assertIsString($data['token'] ?? null);
        self::assertNotSame($tokens['refresh_token'], $data['refresh_token'] ?? null);

        $this->api('POST', '/api/auth/refresh', ['refresh_token' => $tokens['refresh_token']]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testRefreshRejectsAnUnknownToken(): void
    {
        $this->api('POST', '/api/auth/refresh', ['refresh_token' => 'does-not-exist']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testMeReturnsTheCurrentUser(): void
    {
        $this->register();
        $tokens = $this->login();

        $data = $this->api('GET', '/api/me', [], self::bearer($tokens['token']));

        self::assertResponseIsSuccessful();
        self::assertSame('anna@example.com', $data['email']);
        self::assertIsString($data['id']);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $data['id']);
    }

    public function testMeRequiresAToken(): void
    {
        $this->api('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }

    public function testMeRejectsAnExpiredToken(): void
    {
        $this->register();
        /** @var JWTEncoderInterface $encoder */
        $encoder = self::getContainer()->get('lexik_jwt_authentication.encoder');
        $expired = $encoder->encode(['username' => 'anna@example.com', 'iat' => time() - 7200, 'exp' => time() - 3600]);

        $this->api('GET', '/api/me', [], self::bearer($expired));

        self::assertResponseStatusCodeSame(401);
    }

    public function testOnlyHealthAndAuthRoutesArePublic(): void
    {
        $this->api('GET', '/api/health');
        self::assertResponseIsSuccessful();

        // Any other existing /api route needs a JWT (unknown paths are a 404 from the router, before the firewall).
        $this->api('GET', '/api/doc.json');
        self::assertResponseStatusCodeSame(401);
    }
}
