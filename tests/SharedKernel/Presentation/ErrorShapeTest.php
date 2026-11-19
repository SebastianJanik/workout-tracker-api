<?php

declare(strict_types=1);

namespace App\Tests\SharedKernel\Presentation;

use App\Tests\Identity\Support\ApiTestCase;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every error source returns the same RFC 9457 body (spec 004).
 */
final class ErrorShapeTest extends ApiTestCase
{
    #[DataProvider('errorSources')]
    public function testEveryErrorHasTheSameShape(string $source, int $status): void
    {
        $this->client->catchExceptions(true);
        $this->trigger($source);

        self::assertResponseStatusCodeSame($status);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($body);

        $expected = ['type', 'title', 'status', 'detail'];
        if (422 === $status) {
            $expected[] = 'violations';
        }
        $keys = array_keys($body);
        sort($keys);
        sort($expected);
        self::assertSame($expected, $keys);
        self::assertSame('about:blank', $body['type']);
        self::assertSame($status, $body['status']);
        self::assertNotSame('', $body['title']);
        self::assertNotSame('', $body['detail']);
        self::assertStringNotContainsString('secret internals', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('application/json', (string) $this->client->getResponse()->headers->get('Content-Type'));
    }

    /** @return iterable<string, array{string, int}> */
    public static function errorSources(): iterable
    {
        yield 'unknown route' => ['unknown_route', 404];
        yield 'wrong method' => ['wrong_method', 405];
        yield 'no token' => ['no_token', 401];
        yield 'bad login' => ['bad_login', 401];
        yield 'expired jwt' => ['expired_jwt', 401];
        yield 'invalid jwt' => ['invalid_jwt', 401];
        yield 'invalid refresh token' => ['bad_refresh', 401];
        yield 'forbidden' => ['forbidden', 403];
        yield 'duplicate email' => ['duplicate_email', 409];
        yield 'invalid input' => ['invalid_input', 422];
        yield 'uncaught error' => ['boom', 500];
    }

    private function trigger(string $source): void
    {
        switch ($source) {
            case 'unknown_route':
                $this->api('GET', '/api/nope');
                break;
            case 'wrong_method':
                $this->api('DELETE', '/api/health');
                break;
            case 'no_token':
                $this->api('GET', '/api/me');
                break;
            case 'bad_login':
                $this->api('POST', '/api/auth/login', ['email' => 'x@example.com', 'password' => 'wrong-password-123']);
                break;
            case 'expired_jwt':
                $this->register();
                /** @var JWTEncoderInterface $encoder */
                $encoder = self::getContainer()->get('lexik_jwt_authentication.encoder');
                $expired = $encoder->encode(['username' => 'anna@example.com', 'iat' => time() - 7200, 'exp' => time() - 3600]);
                $this->api('GET', '/api/me', [], self::bearer($expired));
                break;
            case 'invalid_jwt':
                $this->api('GET', '/api/me', [], self::bearer('not.a.jwt'));
                break;
            case 'bad_refresh':
                $this->api('POST', '/api/auth/refresh', ['refresh_token' => 'does-not-exist']);
                break;
            case 'forbidden':
                $this->register();
                $tokens = $this->login();
                $this->api('GET', '/api/_probe/forbidden', [], self::bearer($tokens['token']));
                break;
            case 'duplicate_email':
                $this->register();
                $this->api('POST', '/api/auth/register', ['email' => 'anna@example.com', 'password' => self::PASSWORD]);
                break;
            case 'invalid_input':
                $this->api('POST', '/api/auth/register', ['email' => 'not-an-email', 'password' => 'short']);
                break;
            case 'boom':
                $this->register();
                $tokens = $this->login();
                $this->api('GET', '/api/_probe/boom', [], self::bearer($tokens['token']));
                break;
        }
    }
}
