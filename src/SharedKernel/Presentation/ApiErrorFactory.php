<?php

declare(strict_types=1);

namespace App\SharedKernel\Presentation;

use App\SharedKernel\Domain\Exception\TranslatableException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The single place that builds an API error body (RFC 9457 "problem details"), translated into the
 * request locale. Every producer (exception pipeline, JWT and refresh failures) goes through it, so
 * no error has its own shape (Factory pattern).
 */
final readonly class ApiErrorFactory
{
    private const array KNOWN_STATUSES = [400, 401, 403, 404, 405, 409, 422, 429, 500];

    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * @param array<string, scalar>                       $parameters
     * @param list<array{field: string, message: string}> $violations
     *
     * @return array<string, mixed>
     */
    public function create(int $status, ?string $detailKey = null, array $parameters = [], string $domain = 'shared', array $violations = []): array
    {
        $statusKey = in_array($status, self::KNOWN_STATUSES, true) ? (string) $status : 'other';

        $body = [
            'type' => 'about:blank',
            'title' => $this->translator->trans('error.title.'.$statusKey, [], 'shared'),
            'status' => $status,
            'detail' => null === $detailKey
                ? $this->translator->trans('error.detail.'.$statusKey, [], 'shared')
                : $this->translator->trans($detailKey, $parameters, $domain),
        ];

        if ([] !== $violations) {
            $body['violations'] = $violations;
        }

        return $body;
    }

    /**
     * Never exposes the exception text of an unexpected error (only translatable domain exceptions
     * and validation violations carry text meant for a person).
     *
     * @return array<string, mixed>
     */
    public function fromThrowable(\Throwable $throwable): array
    {
        $status = $throwable instanceof HttpExceptionInterface ? $throwable->getStatusCode() : 500;
        $cause = $throwable->getPrevious();

        if ($cause instanceof TranslatableException) {
            return $this->create($status, $cause->messageKey(), $cause->messageParameters(), $cause->translationDomain());
        }

        if ($cause instanceof ValidationFailedException) {
            $violations = [];
            foreach ($cause->getViolations() as $violation) {
                $violations[] = ['field' => $violation->getPropertyPath(), 'message' => (string) $violation->getMessage()];
            }

            return $this->create($status, violations: $violations);
        }

        if ($cause instanceof PartialDenormalizationException) {
            $violations = [];
            foreach ($cause->getNotNormalizableValueErrors() as $error) {
                $violations[] = [
                    'field' => (string) $error->getPath(),
                    'message' => $this->translator->trans('This value should be of type {{ type }}.', ['{{ type }}' => implode('|', $error->getExpectedTypes() ?? ['?'])], 'validators'),
                ];
            }

            return $this->create($status, violations: $violations);
        }

        return $this->create($status);
    }
}
