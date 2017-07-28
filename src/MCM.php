<?php

namespace uuf6429\MultiComposerManager;

/**
 * @author Christian Sciberras <christian@sciberras.me>
 * @copyright 2017 Christian Sciberras & contributors
 * @license MIT
 * @link https://github.com/uuf6429/multi-composer-manager
 * @version 0.1.0
 */
class MCM
{
    /**
     * @var string
     */
    protected $baseDir;

    /**
     * @var string
     */
    protected $baseFile;

    /**
     * @var array
     */
    protected $baseConfig;

    /**
     * @param string $baseDir The directory that will hold composer config, lock file and vendor sources.
     * @param array $baseConfig Default `composer.json` configuration for the base config file.
     */
    public function __construct(
        $baseDir,
        $baseConfig = [
            'name' => 'mcm/base',
            'description' => 'MCM root composer package.',
            'minimum-stability' => 'dev',
            'prefer-stable' => true,
        ]
    )
    {
        $this->baseDir  = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->baseFile = $this->buildConfigPath($this->baseDir);
        $this->baseConfig = $baseConfig;
    }

    /**
     * Add a new `composer.json` file to load dependencies from.
     * @param string $fileName Path to `composer.json` to register for dependencies (can be absolute or relative).
     * @param boolean $applyChanges If true, the newly added dependency is also installed.
     * @return $this
     */
    public function register($fileName, $applyChanges = false)
    {
        $cwd = getcwd();

        try {
            chdir($this->baseDir);

            $baseConfig = $this->loadBaseConfig();
            $newName = $this->getConfigEntry($this->loadConfig($fileName), ['name']);
            $newIndex = count($this->getConfigEntry($baseConfig, ['repositories'], []));

            if (!$newName) {
                throw new \RuntimeException('Package name cannot be empty in ' . $fileName);
            }

            $this->setConfigEntry($baseConfig, ['repositories', $newIndex], ['type' => 'path', 'url' => dirname($fileName)]);
            $this->setConfigEntry($baseConfig, ['require', $newName], '*');

            $this->saveBaseConfig($baseConfig);

            if ($applyChanges) {
                $this->update([$newName]);
            }
        } finally {
            chdir($cwd);
        }

        return $this;
    }

    /**
     * Remove `composer.json` file from dependencies, by file path.
     * @param string $fileName
     * @param boolean $applyChanges If true, the dependency is also physically removed.
     * @return $this
     */
    public function unregisterByFile($fileName, $applyChanges = false)
    {
        $cwd = getcwd();

        try {
            chdir($this->baseDir);
            $packageName = $this->getConfigEntry($this->loadConfig($fileName), ['name']);
        } finally {
            chdir($cwd);
        }

        return $this->unregisterByName($packageName, $applyChanges);
    }

    /**
     * Remove `composer.json` file from dependencies, by package name (the value of "name" in composer config).
     * @param string $packageName
     * @param boolean $applyChanges If true, the dependency is also physically removed.
     * @return $this
     */
    public function unregisterByName($packageName, $applyChanges = false)
    {
        $cwd = getcwd();

        try {
            chdir($this->baseDir);
            $baseConfig = $this->loadBaseConfig();

            $repositories = array_filter(
                $this->getConfigEntry($baseConfig, ['repositories']),
                function($repository)use($packageName) {
                    // filter out only the specified package
                    return ! (
                        isset($repository['type'])
                        && $repository['type'] === 'path'
                        && isset($repository['url'])
                        && file_exists($file = $this->buildConfigPath($repository['url']))
                        && $this->getConfigEntry($this->loadConfig($file), ['name']) === $packageName
                    );
                }
            );

            count($repositories)
                ? $this->setConfigEntry($baseConfig, ['repositories'], array_values($repositories))
                : $this->unsetConfigEntry($baseConfig, ['repositories']);
            $this->unsetConfigEntry($baseConfig, ['require', $packageName]);

            $this->saveBaseConfig($baseConfig);

            if ($applyChanges) {
                $this->update([$packageName]);
            }
        } finally {
            chdir($cwd);
        }

        return $this;
    }

    /**
     * Performs a `composer install`.
     * @return $this
     */
    public function install()
    {
        $this->composerExec('install');

        return $this;
    }

    /**
     * Performs a `composer update` (optionally, for particular packages only).
     * @param null|string[] $packageNames If null, all packages are updated. Otherwise, only the specified packages will be updated.
     * @return $this
     */
    public function update($packageNames = null)
    {
        $this->composerExec('update', $packageNames ?: []);

        return $this;
    }

    /**
     * "Requires" the base composer class loader, making all composer dependencies available for use.
     * @return \Composer\Autoload\ClassLoader
     */
    public function autoload()
    {
        /** @noinspection PhpIncludeInspection */
        return require_once($this->baseDir . 'vendor/autoload.php');
    }

    /**
     * @param $fileName
     * @return array
     */
    protected function loadConfig($fileName)
    {
        $config = [];

        if (file_exists($fileName)) {
            $config = json_decode(file_get_contents($fileName), true);
        }

        return $config;
    }

    /**
     * @return array
     */
    protected function loadBaseConfig()
    {
        return $this->baseConfig + $this->loadConfig($this->baseFile);
    }

    /**
     * @param string $basePath
     * @return string
     */
    protected function buildConfigPath($basePath)
    {
        return rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'composer.json';
    }

    /**
     * @param array $config
     */
    protected function saveBaseConfig($config)
    {
        file_put_contents($this->baseFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param string $cmd
     * @param string[] $args
     */
    protected function composerExec($cmd, $args = [])
    {
        $oldDir = getcwd();
        $command = 'composer ' . escapeshellarg($cmd);

        if (count($args)) {
            $command .= ' '.implode(' ', array_map('escapeshellarg', $args));
        }

        $command .= ' 2>&1'; // redirect stderr to stdout

        if (!is_string($oldDir)) {
            throw new \RuntimeException('Cannot get the current working directory.');
        }

        try {
            chdir($this->baseDir);

            ob_start();
            passthru($command, $exitCode);
            $output = ob_get_clean();

            if ($exitCode) {
                throw new \RuntimeException(
                    sprintf(
                        'Composer stopped with a non-zero exit code (%d), output:%s',
                        $exitCode,
                        $output ? (PHP_EOL . $output) : ' (none)'
                    )
                );
            }
        } finally {
            chdir($oldDir);
        }
    }

    /**
     * @param array $config
     * @param array $path
     * @param mixed $value
     */
    protected function setConfigEntry(&$config, $path, $value)
    {
        foreach ($path as $part) {
            if (!isset($config[$part])) {
                $config[$part] = [];
            }
            $config = &$config[$part];
        }

        $config = $value;
    }

    /**
     * @param array $config
     * @param array $path
     * @param mixed $default
     * @return mixed
     */
    protected function getConfigEntry($config, $path, $default = null)
    {
        foreach ($path as $part) {
            if (!isset($config[$part])) {
                return $default;
            }
            $config = &$config[$part];
        }

        return $config;
    }

    /**
     * @param array $config
     * @param array $path
     */
    protected function unsetConfigEntry(&$config, $path)
    {
        while (is_array($config) && count($path)) {
            $part = array_shift($path);

            if (count($path)) {
                $config = &$config[$part];
            } else {
                unset($config[$part]);
            }
        }
    }
}
