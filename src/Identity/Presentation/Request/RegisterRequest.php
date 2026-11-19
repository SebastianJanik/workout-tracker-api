<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of POST /api/auth/register. Bound and validated by #[MapRequestPayload] (422 on violations).
 */
final readonly class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Email(normalizer: 'trim')]
        #[Assert\Length(max: 180)]
        public string $email = '',
        #[Assert\NotBlank]
        #[Assert\Length(min: 12, max: 4096)]
        #[\SensitiveParameter]
        public string $password = '',
    ) {
    }
}
