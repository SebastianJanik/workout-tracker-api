<?php

declare(strict_types=1);

namespace App\Tests\SharedKernel\Translation;

use App\SharedKernel\Domain\Exception\Conflict;
use App\SharedKernel\Domain\Exception\InvalidInput;
use App\SharedKernel\Domain\Exception\NotFound;
use App\SharedKernel\Domain\Exception\TranslatableException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Guards the rules of spec 004: same keys in every locale, and no hard-coded exception messages.
 */
final class TranslationConventionsTest extends TestCase
{
    private const array LOCALES = ['en', 'pl'];

    public function testEveryLocaleDefinesTheSameKeysInEveryContext(): void
    {
        $files = glob(self::root().'/src/*/translations/*.en.yaml') ?: [];
        self::assertNotEmpty($files);

        foreach ($files as $enFile) {
            $reference = self::keys($enFile);
            foreach (self::LOCALES as $locale) {
                $other = str_replace('.en.yaml', '.'.$locale.'.yaml', $enFile);
                self::assertFileExists($other);
                self::assertEqualsCanonicalizing($reference, self::keys($other), sprintf('%s differs from %s', basename($other), basename($enFile)));
            }
        }
    }

    public function testEveryKeyUsedByATranslatableExceptionExistsInEveryLocale(): void
    {
        $used = $this->keysUsedByExceptions();
        self::assertNotEmpty($used);

        foreach ($used as $domainAndKey) {
            [$domain, $key] = explode('.', $domainAndKey, 2);
            foreach (self::LOCALES as $locale) {
                $keys = [];
                foreach (glob(self::root().sprintf('/src/*/translations/%s.%s.yaml', $domain, $locale)) ?: [] as $file) {
                    $keys = [...$keys, ...self::keys($file)];
                }
                self::assertContains($key, $keys, sprintf('"%s" is missing in the "%s" %s file', $key, $domain, $locale));
            }
        }
    }

    public function testEveryHttpMappedExceptionIsTranslatable(): void
    {
        $classes = $this->domainExceptionClasses();
        self::assertNotEmpty($classes);

        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            $mapped = $reflection->implementsInterface(Conflict::class)
                || $reflection->implementsInterface(NotFound::class)
                || $reflection->implementsInterface(InvalidInput::class);
            if ($mapped) {
                self::assertTrue($reflection->implementsInterface(TranslatableException::class), sprintf('%s maps to HTTP but is not a TranslatableException (no hard-coded messages).', $class));
            }
        }
    }

    /** @return list<string> */
    private function keysUsedByExceptions(): array
    {
        $used = [];
        foreach ($this->domainExceptionClasses() as $class) {
            $source = (string) file_get_contents((string) (new \ReflectionClass($class))->getFileName());
            preg_match_all("/new self\\('([a-z_]+\\.[a-z_.]+)'/", $source, $matches);
            $used = [...$used, ...$matches[1]];
        }

        return $used;
    }

    /** @return list<class-string> */
    private function domainExceptionClasses(): array
    {
        $classes = [];
        foreach (glob(self::root().'/src/*/Domain/Exception/*.php') ?: [] as $file) {
            $context = basename(dirname($file, 3));
            $class = sprintf('App\\%s\\Domain\\Exception\\%s', $context, basename($file, '.php'));
            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /** @return list<string> */
    private static function keys(string $file): array
    {
        /** @var array<string, mixed> $data */
        $data = Yaml::parseFile($file);
        $keys = [];
        $walk = static function (array $node, string $prefix) use (&$walk, &$keys): void {
            foreach ($node as $key => $value) {
                $path = '' === $prefix ? (string) $key : $prefix.'.'.$key;
                is_array($value) ? $walk($value, $path) : $keys[] = $path;
            }
        };
        $walk($data, '');

        return $keys;
    }

    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }
}
