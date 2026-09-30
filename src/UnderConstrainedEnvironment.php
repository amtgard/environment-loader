<?php

declare(strict_types=1);

namespace Amtgard\EnvironmentLoader;

final class UnderConstrainedEnvironment extends \RuntimeException
{
    public static function forModule(string $module, string $environment): self
    {
        return new self(sprintf(
            'Module %s has no binding for environment %s and no DEFAULT fallback.',
            $module,
            $environment
        ));
    }
}
