<?php

declare(strict_types=1);

namespace App\SharedKernel;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Registers the Shared Kernel's translation files (error titles and generic security messages,
 * translation domain "shared"), the same way each context registers its own.
 */
final class SharedKernelBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->extension('framework', [
            'translator' => ['paths' => [__DIR__.'/translations']],
        ]);
    }
}
