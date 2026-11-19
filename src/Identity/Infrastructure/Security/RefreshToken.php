<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

/**
 * Refresh token storage for the gesdinet bundle. Owned by Identity (table `refresh_tokens`),
 * mapped in Persistence/Doctrine/Mapping, not part of the domain.
 */
final class RefreshToken extends BaseRefreshToken
{
}
