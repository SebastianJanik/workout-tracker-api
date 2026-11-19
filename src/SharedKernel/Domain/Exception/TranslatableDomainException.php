<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\Exception;

/**
 * Base for domain exceptions that carry a key instead of text. The key is "<domain>.<message key>",
 * so getMessage() returns something readable in logs while the parts stay separate for translation.
 */
abstract class TranslatableDomainException extends \DomainException implements TranslatableException
{
    private readonly string $translationDomain;
    private readonly string $messageKey;

    /**
     * @param array<string, scalar> $messageParameters
     */
    final public function __construct(string $key, private readonly array $messageParameters = [])
    {
        if (!str_contains($key, '.')) {
            throw new \InvalidArgumentException(sprintf('Translation key "%s" must be prefixed with its domain, e.g. "identity.email.invalid".', $key));
        }

        [$this->translationDomain, $this->messageKey] = explode('.', $key, 2);

        parent::__construct($key);
    }

    public function translationDomain(): string
    {
        return $this->translationDomain;
    }

    public function messageKey(): string
    {
        return $this->messageKey;
    }

    public function messageParameters(): array
    {
        return $this->messageParameters;
    }
}
