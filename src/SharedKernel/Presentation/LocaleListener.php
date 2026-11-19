<?php

declare(strict_types=1);

namespace App\SharedKernel\Presentation;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Picks the request locale from Accept-Language among the enabled locales (fallback: the default).
 * Runs before the translator's own locale listener (priority 15), so everything translated later
 * in the request, including validator messages, follows it. Under /api errors are always
 * rendered as JSON whatever the Accept header says (the dev profiler under /_profiler stays HTML).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 100)]
final readonly class LocaleListener
{
    /**
     * @param list<string> $enabledLocales
     */
    public function __construct(
        #[Autowire('%kernel.enabled_locales%')]
        private array $enabledLocales,
        #[Autowire('%kernel.default_locale%')]
        private string $defaultLocale,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $request->setLocale($request->getPreferredLanguage($this->enabledLocales) ?? $this->defaultLocale);
        if (str_starts_with($request->getPathInfo(), '/api')) {
            $request->setRequestFormat('json');
        }
    }
}
