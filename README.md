# environment-loader

Environment-agnostic helper for composition roots: register include files per module and environment name, then resolve absolute paths for `require`.

Depends on `amtgard/builder-traits` (Builder/Getter) and `jedibc/optional` for binding selection and optional logging.

## Install

```bash
composer require amtgard/environment-loader
```

## Usage

```php
use Amtgard\EnvironmentLoader\EnvironmentLoader;

$environment = getenv('APP_ENV') ?: 'environment-one';

$envLoad = EnvironmentLoader::builder()
    ->build()
    ->defaultPath('environment-one', '/path/to/live/container')
    ->defaultPath('environment-two', '/path/to/integ/container');

$envLoad->register('oauth')
    ->withEnvironment('environment-one', 'oauth-providers.php', EnvironmentLoader::DEFAULT)
    ->withEnvironment('environment-two', 'oauth-providers-integ.php');

(require $envLoad->emit('oauth', $environment))($containerBuilder);
```

Optional PSR-3 logger: `EnvironmentLoader::builder()->logger($logger)->build()->defaultPath(...)`.

## Development

```bash
composer install
composer test
```
