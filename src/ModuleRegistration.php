<?php

declare(strict_types=1);

namespace Amtgard\EnvironmentLoader;

final class ModuleRegistration
{
    public function __construct(
        private readonly EnvironmentLoader $loader,
        private readonly string $module,
    ) {
    }

    public function withEnvironment(string $environment, string $fileOrPath, int $mode = EnvironmentLoader::REQUIRES_SELECTION): self
    {
        $this->loader->addModuleBinding($this->module, $environment, $fileOrPath, $mode);

        return $this;
    }
}
