<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\Exception;

/**
 * A domain exception whose message is a translation key, never free text (spec 004).
 * The Presentation edge turns key + parameters into text in the request locale; the domain
 * never sees the Translator or a language.
 */
interface TranslatableException extends \Throwable
{
    /** Translation domain = the bounded context that owns the text, e.g. "identity". */
    public function translationDomain(): string;

    /** Key inside the translation domain, e.g. "email.already_registered". */
    public function messageKey(): string;

    /** @return array<string, scalar> placeholders, e.g. ['%email%' => 'a@b.io'] */
    public function messageParameters(): array;
}
