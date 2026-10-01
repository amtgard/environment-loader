<?php

declare(strict_types=1);

namespace Amtgard\EnvironmentLoader;

final class OverConstrainedEnvironment extends \RuntimeException
{
    public static function forModule(string $module): self
    {
        return new self(sprintf('Module %s declares more than one DEFAULT environment binding.', $module));
    }
}
