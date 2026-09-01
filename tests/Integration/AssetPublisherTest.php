<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Integration;

use Holduix\FlexPay\Checkout\Support\AssetPublisher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AssetPublisherTest extends TestCase
{
    private string $targetDirectory;

    protected function setUp(): void
    {
        $this->targetDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'flexpay-assets-'
            . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->targetDirectory)) {
            return;
        }
        foreach (scandir($this->targetDirectory) ?: [] as $file) {
            if ($file !== '.' && $file !== '..') {
                unlink($this->targetDirectory . DIRECTORY_SEPARATOR . $file);
            }
        }
        rmdir($this->targetDirectory);
    }

    public function testAssetsArePublishedWithoutNode(): void
    {
        $published = (new AssetPublisher())->publish($this->targetDirectory);

        self::assertCount(3, $published);
        self::assertFileExists($this->targetDirectory . '/flexpay-checkout.esm.js');
        self::assertFileExists($this->targetDirectory . '/flexpay-checkout.iife.js');
        self::assertFileExists($this->targetDirectory . '/flexpay-checkout.css');
    }

    public function testExistingAssetRequiresExplicitOverwrite(): void
    {
        $publisher = new AssetPublisher();
        $publisher->publish($this->targetDirectory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('--force');
        $publisher->publish($this->targetDirectory);
    }

    public function testForceOverwritesExistingAssets(): void
    {
        $publisher = new AssetPublisher();
        $publisher->publish($this->targetDirectory);
        file_put_contents($this->targetDirectory . '/flexpay-checkout.css', 'old');

        $publisher->publish($this->targetDirectory, true);

        self::assertNotSame('old', file_get_contents($this->targetDirectory . '/flexpay-checkout.css'));
    }

    public function testForceRejectsDestinationSymlinkWithoutChangingItsTarget(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('Les liens symboliques ne sont pas disponibles.');
        }

        mkdir($this->targetDirectory, 0775, true);
        $sentinel = sys_get_temp_dir() . '/flexpay-assets-sentinel-' . bin2hex(random_bytes(6));
        file_put_contents($sentinel, 'unchanged');
        symlink($sentinel, $this->targetDirectory . '/flexpay-checkout.css');

        try {
            (new AssetPublisher())->publish($this->targetDirectory, true);
            self::fail('La publication devait refuser le lien symbolique.');
        } catch (RuntimeException) {
            self::assertSame('unchanged', file_get_contents($sentinel));
        } finally {
            @unlink($this->targetDirectory . '/flexpay-checkout.css');
            @unlink($sentinel);
        }
    }
}
