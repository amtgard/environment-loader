<?php

declare(strict_types=1);

namespace Amtgard\EnvironmentLoader;

use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Getter;
use Optional\Optional;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Maps (module key, environment name) → absolute path of a PHP include file.
 */
final class EnvironmentLoader
{
    use Builder;
    use Getter;

    public const REQUIRES_SELECTION = 0;

    public const DEFAULT = 1;

    /** @var array<string, string> environment name → base directory */
    protected array $defaultPaths = [];

    protected ?LoggerInterface $logger = null;

    /**
     * @var array<string, list<array{environment: string, file: string, mode: int}>>
     */
    protected array $moduleBindings = [];

    public function defaultPath(string $environment, string $path): self
    {
        $this->defaultPaths[$environment] = $path;

        return $this;
    }

    public function register(string $module): ModuleRegistration
    {
        return new ModuleRegistration($this, $module);
    }

    public function emit(string $module, string $environment): string
    {
        if (!isset($this->moduleBindings[$module])) {
            throw new \InvalidArgumentException(sprintf('Unknown container module: %s', $module));
        }

        $binding = $this->selectBinding($this->moduleBindings[$module], $environment)
            ->orElseThrow(UnderConstrainedEnvironment::forModule($module, $environment));

        $usedFallback = !$this->hasExactBinding($this->moduleBindings[$module], $environment);
        $path = $this->resolvePath($binding);

        Optional::ofNullable($this->logger)->ifPresent(
            function (LoggerInterface $logger) use ($module, $environment, $binding, $usedFallback): void {
                $logger->log(
                    LogLevel::DEBUG,
                    'EnvironmentLoader selected container include',
                    [
                        'module' => $module,
                        'requested_environment' => $environment,
                        'binding_environment' => $binding['environment'],
                        'include' => $binding['file'],
                        'fallback' => $usedFallback,
                    ]
                );
            }
        );

        return $path;
    }

    /**
     * @internal Called from {@see ModuleRegistration}.
     */
    public function addModuleBinding(string $module, string $environment, string $fileOrPath, int $mode): void
    {
        if ($mode !== self::REQUIRES_SELECTION && $mode !== self::DEFAULT) {
            throw new \InvalidArgumentException(sprintf('Invalid environment binding mode: %d', $mode));
        }

        if (!isset($this->moduleBindings[$module])) {
            $this->moduleBindings[$module] = [];
        }

        if ($mode === self::DEFAULT) {
            foreach ($this->moduleBindings[$module] as $existing) {
                if ($existing['mode'] === self::DEFAULT) {
                    throw OverConstrainedEnvironment::forModule($module);
                }
            }
        }

        $this->moduleBindings[$module][] = [
            'environment' => $environment,
            'file' => $fileOrPath,
            'mode' => $mode,
        ];
    }

    /**
     * @param list<array{environment: string, file: string, mode: int}> $bindings
     */
    private function selectBinding(array $bindings, string $environment): Optional
    {
        $fallback = null;

        foreach ($bindings as $binding) {
            if ($binding['environment'] === $environment) {
                return Optional::of($binding);
            }
            if ($binding['mode'] === self::DEFAULT) {
                $fallback = $binding;
            }
        }

        return Optional::ofNullable($fallback);
    }

    /**
     * @param list<array{environment: string, file: string, mode: int}> $bindings
     */
    private function hasExactBinding(array $bindings, string $environment): bool
    {
        foreach ($bindings as $binding) {
            if ($binding['environment'] === $environment) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{environment: string, file: string, mode: int} $binding
     */
    private function resolvePath(array $binding): string
    {
        $fileOrPath = $binding['file'];
        if ($this->isAbsolutePath($fileOrPath)) {
            return $fileOrPath;
        }

        return Optional::ofNullable($this->defaultPaths[$binding['environment']] ?? null)
            ->filter(static fn (string $base): bool => $base !== '')
            ->map(static fn (string $base): string => rtrim($base, '/') . '/' . $fileOrPath)
            ->orElseThrow(new \InvalidArgumentException(sprintf(
                'No defaultPath registered for environment %s',
                $binding['environment']
            )));
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('#^[A-Za-z]:[/\\\\]#', $path) === 1;
    }
}
