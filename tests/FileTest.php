<?php

namespace uuf6429\MultiComposerManagerTest;

use PHPUnit\Framework\TestCase;
use uuf6429\MultiComposerManager\MCM;

class FileTest extends TestCase
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

    /**
     * @var MCM
     */
    private $mcm;

    public function setUp()
    {
        parent::setUp();

        $this->mcm = new MCM(self::$tempPath);
        $this->createConfig(self::$tempPath . '/ext1', ['name' => 'vnd/ext1', 'require' => ['psr/log' => '*']]);
        $this->createConfig(self::$tempPath . '/ext2', ['name' => 'vnd/ext2', 'require' => ['psr/log' => '*']]);
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

    public function testRegister()
    {
        $this->mcm->register('ext1/composer.json');
        $this->mcm->register('ext2/composer.json');
        $this->assertBaseConfig(
            [
                'name' => 'mcm/base',
                'description' => 'MCM root composer package.',
                'minimum-stability' => 'dev',
                'prefer-stable' => true,
                'repositories' => [
                    [
                        'type' => 'path',
                        'url' => 'ext1',
                    ],
                    [
                        'type' => 'path',
                        'url' => 'ext2',
                    ],
                ],
                'require' => [
                    'vnd/ext1' => '*',
                    'vnd/ext2' => '*',
                ],
            ]
        );
    }

    public function testUnregisterByName()
    {
        $this->mcm->register('ext1/composer.json');
        $this->mcm->register('ext2/composer.json');

        $this->mcm->unregisterByName('vnd/ext1');
        $this->assertBaseConfig(
            [
                'name' => 'mcm/base',
                'description' => 'MCM root composer package.',
                'minimum-stability' => 'dev',
                'prefer-stable' => true,
                'repositories' => [
                    [
                        'type' => 'path',
                        'url' => 'ext2',
                    ],
                ],
                'require' => [
                    'vnd/ext2' => '*',
                ],
            ]
        );
    }

    public function testUnregisterByFile()
    {
        $this->mcm->register('ext1/composer.json');
        $this->mcm->register('ext2/composer.json');
        $this->mcm->unregisterByFile('ext2/composer.json');
        $this->assertBaseConfig(
            [
                'name' => 'mcm/base',
                'description' => 'MCM root composer package.',
                'minimum-stability' => 'dev',
                'prefer-stable' => true,
                'repositories' => [
                    [
                        'type' => 'path',
                        'url' => 'ext1',
                    ],
                ],
                'require' => [
                    'vnd/ext1' => '*',
                ],
            ]
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

    /**
     * @param array $expected
     * @param $message
     */
    private function assertBaseConfig(array $expected, $message = '')
    {
        $this->assertFileExists(self::$tempPath . '/composer.json');
        $config = json_decode(file_get_contents(self::$tempPath . '/composer.json'), true);
        $this->assertEquals($expected, $config, $message);
    }
}
