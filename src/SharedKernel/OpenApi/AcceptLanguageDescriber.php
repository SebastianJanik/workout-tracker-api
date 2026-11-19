<?php

declare(strict_types=1);

namespace App\SharedKernel\OpenApi;

use Nelmio\ApiDocBundle\Describer\DescriberInterface;
use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use OpenApi\Annotations as OA;
use OpenApi\Undefined;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Documents the optional Accept-Language header once, on every operation of the API, so no
 * controller repeats it. Runs after the route describer (priority -400), when all paths exist.
 * OpenAPI has no global header parameter, so each operation gets the same entry.
 */
#[AutoconfigureTag('nelmio_api_doc.describer.default', ['priority' => -500])]
final class AcceptLanguageDescriber implements DescriberInterface
{
    private const array OPERATIONS = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'];

    public function describe(OA\OpenApi $api): void
    {
        if (Undefined::isDefault($api->paths)) {
            return;
        }

        foreach ($api->paths as $path) {
            foreach (self::OPERATIONS as $method) {
                $operation = $path->{$method};
                if (Undefined::isDefault($operation)) {
                    continue;
                }

                // Finds the existing parameter or creates it properly nested (idempotent).
                $parameter = Util::getOperationParameter($operation, 'Accept-Language', 'header');
                $parameter->required = false;
                $parameter->description = 'Language of error messages, `en` (default) or `pl`. Any other value falls back to `en`.';
                $schema = Util::getChild($parameter, OA\Schema::class);
                $schema->type = 'string';
                $schema->default = 'en';
                $schema->enum = ['en', 'pl'];
            }
        }
    }
}
