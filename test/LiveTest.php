<?php

namespace uuf6429\MultiComposerManagerTest;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use uuf6429\MultiComposerManager\MCM;

class LiveTest extends TestCase
{
    /**
     * @var string
     */
    private static $tempLock;

    /**
     * @var string
     */
    private static $tempPath;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$tempLock = tempnam(sys_get_temp_dir(), 'lck');
        self::$tempPath = self::$tempLock . '_dir';
        mkdir(self::$tempPath);
    }

    public static function tearDownAfterClass()
    {
        rmdir(self::$tempPath);
        unlink(self::$tempLock);

        parent::tearDownAfterClass();
    }

    public function tearDown()
    {
        /** @var \SplFileInfo[] $iterator */
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::$tempPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isFile() ? unlink($file->getPathname()) : rmdir($file->getPathname());
        }

        parent::tearDown();
    }

    public function testEmptyInstall()
    {
        $this->createConfig(self::$tempPath, ['name' => 'myapp/app', 'require' => ['psr/log' => '*']]);

        $mcm = new MCM(self::$tempPath);
        $mcm->install();

        $this->assertFileExists(self::$tempPath . '/composer.lock');
        $this->assertDirectoryExists(self::$tempPath . '/vendor');
    }

    public function testCombineTwoPluginsWithApp()
    {
        $baseDir = self::$tempPath . '/common';
        $appDir = self::$tempPath . '/app';
        $plgDir = self::$tempPath . '/ext';
        $plg1Dir = $plgDir . '/plugin1';
        $plg2Dir = $plgDir . '/plugin2';

        mkdir($baseDir);

        $this->createConfig($appDir, ['name' => 'myapp/app', 'require' => ['psr/log' => '*']]);
        $this->createConfig($plg1Dir, ['name' => 'myapp/plugin1', 'require' => ['symfony/console' => '*']]);
        $this->createConfig($plg2Dir, ['name' => 'myapp/plugin2', 'require' => ['symfony/process' => '*']]);

        $mcm = new MCM($baseDir);
        $mcm->register('../app/composer.json')
            ->register('../ext/plugin1/composer.json')
            ->register('../ext/plugin2/composer.json')
            ->install();

        $this->assertFileNotExists(self::$tempPath . '/composer.lock');
        $this->assertDirectoryNotExists(self::$tempPath . '/vendor');

        $this->assertFileExists($baseDir . '/composer.lock');
        $this->assertDirectoryExists($baseDir . '/vendor');
        $this->assertDirectoryExists($baseDir . '/vendor/psr/log');
        $this->assertDirectoryExists($baseDir . '/vendor/symfony/console');
        $this->assertDirectoryExists($baseDir . '/vendor/symfony/process');
    }

    /**
     * @runInSeparateProcess
     */
    public function testAutoloadWorks()
    {
        $oldDir = self::$tempPath . '/old';
        $newDir = self::$tempPath . '/new';

        mkdir($oldDir);
        mkdir($newDir);

        $this->createConfig($oldDir, ['name' => 'myapp/app', 'require' => ['symfony/yaml' => '*']]);

        $mcm = new MCM($newDir);
        $mcm->register('../old/composer.json')
            ->install();

        $loader = $mcm->autoload();

        $this->assertInstanceOf(ClassLoader::class, $loader);
        $this->assertTrue(
            class_exists(Yaml::class),
            sprintf('Class "%s" does not exists and cannot be loaded.', Yaml::class)
        );
    }

    /**
     * @param string $baseDir
     * @param array $config
     */
    private function createConfig($baseDir, $config)
    {
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

        file_put_contents(
            $baseDir . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }
}
