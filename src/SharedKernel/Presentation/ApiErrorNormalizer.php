<?php

declare(strict_types=1);

namespace App\SharedKernel\Presentation;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Plugs into Symfony's error pipeline (ErrorListener -> SerializerErrorRenderer) and replaces the
 * stock ProblemNormalizer (priority -880) so every exception is rendered by ApiErrorFactory.
 */
#[AutoconfigureTag('serializer.normalizer', ['priority' => -800])]
final readonly class ApiErrorNormalizer implements NormalizerInterface
{
    public function __construct(private ApiErrorFactory $factory)
    {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $exception = $context['exception'] ?? null;

        if ($exception instanceof \Throwable) {
            return $this->factory->fromThrowable($exception);
        }

        \assert($data instanceof FlattenException);

        return $this->factory->create($data->getStatusCode());
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof FlattenException;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [FlattenException::class => false];
    }
}
