<?php

declare(strict_types=1);

namespace App\Identity;

use App\SharedKernel\Infrastructure\Doctrine\Type\UserIdType;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Wires the Identity context into the framework, so its Doctrine setup lives with the context
 * instead of in the shared config/packages/doctrine.yaml (Laravel analogy: a service provider).
 */
final class IdentityBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->extension('doctrine', [
            'dbal' => [
                // DBAL types are global by design; the context that owns the aggregate registers its id type.
                'types' => ['user_id' => UserIdType::class],
            ],
            'orm' => [
                'mappings' => [
                    'Identity' => [
                        'type' => 'xml',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Infrastructure/Persistence/Doctrine/Mapping',
                        'prefix' => 'App\Identity',
                    ],
                ],
            ],
        ]);
    }
}
