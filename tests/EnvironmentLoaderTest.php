<?php

declare(strict_types=1);

namespace Amtgard\EnvironmentLoader\Tests;

use Amtgard\EnvironmentLoader\EnvironmentLoader;
use Amtgard\EnvironmentLoader\OverConstrainedEnvironment;
use Amtgard\EnvironmentLoader\UnderConstrainedEnvironment;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class EnvironmentLoaderTest extends TestCase
{
    private string $pathOne;

    private string $pathTwo;

    protected function setUp(): void
    {
        $this->pathOne = sys_get_temp_dir() . '/env-loader-one-' . uniqid('', true);
        $this->pathTwo = sys_get_temp_dir() . '/env-loader-two-' . uniqid('', true);
        mkdir($this->pathOne);
        mkdir($this->pathTwo);
        touch($this->pathOne . '/live.php');
        touch($this->pathTwo . '/integ.php');
    }

    protected function tearDown(): void
    {
        @unlink($this->pathOne . '/live.php');
        @unlink($this->pathTwo . '/integ.php');
        @rmdir($this->pathOne);
        @rmdir($this->pathTwo);
    }

    public function testEmitSelectsBindingForMatchingEnvironment(): void
    {
        $loader = $this->loader();
        $loader->register('demo')
            ->withEnvironment('environment-one', 'live.php', EnvironmentLoader::DEFAULT)
            ->withEnvironment('environment-two', 'integ.php');

        $this->assertSame($this->pathTwo . '/integ.php', $loader->emit('demo', 'environment-two'));
    }

    public function testEmitFallsBackToDefaultWhenEnvironmentHasNoBinding(): void
    {
        $loader = $this->loader();
        $loader->register('demo')
            ->withEnvironment('environment-one', 'live.php', EnvironmentLoader::DEFAULT)
            ->withEnvironment('environment-two', 'integ.php');

        $this->assertSame($this->pathOne . '/live.php', $loader->emit('demo', 'composer-can-get-fucked'));
    }

    public function testEmitUsesAbsolutePathWhenProvided(): void
    {
        $absolute = $this->pathTwo . '/integ.php';
        $loader = EnvironmentLoader::builder()
            ->build()
            ->defaultPath('environment-one', $this->pathOne);
        $loader->register('demo')
            ->withEnvironment('environment-two', $absolute);

        $this->assertSame($absolute, $loader->emit('demo', 'environment-two'));
    }

    public function testTwoDefaultBindingsThrowOverConstrainedEnvironment(): void
    {
        $loader = $this->loader();

        $this->expectException(OverConstrainedEnvironment::class);
        $loader->register('demo')
            ->withEnvironment('environment-one', 'live.php', EnvironmentLoader::DEFAULT)
            ->withEnvironment('environment-two', 'integ.php', EnvironmentLoader::DEFAULT);
    }

    public function testEmitThrowsUnderConstrainedEnvironmentWhenNoMatchAndNoDefault(): void
    {
        $loader = $this->loader();
        $loader->register('demo')
            ->withEnvironment('environment-two', 'integ.php');

        $this->expectException(UnderConstrainedEnvironment::class);
        $loader->emit('demo', 'environment-one');
    }

    public function testEmitThrowsForUnknownModule(): void
    {
        $loader = $this->loader();

        $this->expectException(\InvalidArgumentException::class);
        $loader->emit('missing', 'environment-one');
    }

    public function testEmitLogsSelectionWhenLoggerConfigured(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('test');
        $logger->pushHandler($handler);

        $loader = EnvironmentLoader::builder()
            ->logger($logger)
            ->build()
            ->defaultPath('environment-one', $this->pathOne)
            ->defaultPath('environment-two', $this->pathTwo);
        $loader->register('demo')
            ->withEnvironment('environment-one', 'live.php', EnvironmentLoader::DEFAULT)
            ->withEnvironment('environment-two', 'integ.php');
        $loader->emit('demo', 'environment-two');

        $this->assertTrue($handler->hasDebugRecords());
        $record = $handler->getRecords()[0];
        $this->assertSame('environment-two', $record['context']['binding_environment']);
        $this->assertFalse($record['context']['fallback']);
    }

    private function loader(): EnvironmentLoader
    {
        return EnvironmentLoader::builder()
            ->build()
            ->defaultPath('environment-one', $this->pathOne)
            ->defaultPath('environment-two', $this->pathTwo);
    }
}
