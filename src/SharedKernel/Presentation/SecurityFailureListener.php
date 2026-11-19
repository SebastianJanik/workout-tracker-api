<?php

declare(strict_types=1);

namespace App\SharedKernel\Presentation;

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshAuthenticationFailureEvent;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\TooManyRefreshRequestsException;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replaces the JWT and refresh bundles' own failure bodies with the app's error body.
 * Both bundles dispatch an event that lets a listener swap the response (Observer pattern).
 */
final readonly class SecurityFailureListener
{
    public function __construct(private ApiErrorFactory $factory)
    {
    }

    #[AsEventListener(event: Events::JWT_NOT_FOUND)]
    public function onTokenMissing(JWTNotFoundEvent $event): void
    {
        $event->setResponse($this->unauthorized('security.token_missing'));
    }

    #[AsEventListener(event: Events::JWT_INVALID)]
    public function onTokenInvalid(JWTInvalidEvent $event): void
    {
        $event->setResponse($this->unauthorized('security.token_invalid'));
    }

    #[AsEventListener(event: Events::JWT_EXPIRED)]
    public function onTokenExpired(JWTExpiredEvent $event): void
    {
        $event->setResponse($this->unauthorized('security.token_expired'));
    }

    #[AsEventListener(event: Events::AUTHENTICATION_FAILURE)]
    public function onLoginFailure(AuthenticationFailureEvent $event): void
    {
        $event->setResponse($this->unauthorized('security.invalid_credentials'));
    }

    #[AsEventListener(event: 'gesdinet.refresh_token_failure')]
    public function onRefreshFailure(RefreshAuthenticationFailureEvent $event): void
    {
        if ($event->getException() instanceof TooManyRefreshRequestsException) {
            $response = new JsonResponse($this->factory->create(Response::HTTP_TOO_MANY_REQUESTS), Response::HTTP_TOO_MANY_REQUESTS);
            $retryAfter = $event->getResponse()->headers->get('Retry-After');
            if (null !== $retryAfter) {
                $response->headers->set('Retry-After', $retryAfter);
            }
            $event->setResponse($response);

            return;
        }

        $event->setResponse($this->unauthorized('security.refresh_invalid'));
    }

    private function unauthorized(string $detailKey): JsonResponse
    {
        return new JsonResponse($this->factory->create(Response::HTTP_UNAUTHORIZED, $detailKey), Response::HTTP_UNAUTHORIZED);
    }
}
