# Multiple Composer Manager (MCM)

[![Build Status](https://img.shields.io/travis/uuf6429/multi-composer-manager/master.svg?style=flat-square)](https://travis-ci.org/uuf6429/multi-composer-manager)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg?style=flat-square)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](https://raw.githubusercontent.com/uuf6429/multi-composer-manager/master/LICENSE)
[![Coverage](https://img.shields.io/codecov/c/github/uuf6429/multi-composer-manager.svg?style=flat-square)](https://codecov.io/github/uuf6429/multi-composer-manager?branch=master)

Multiple Composer Manager - manages dependencies from multiple composer packages and provides one general loader.

## Table of Contents

- [Multiple Composer Manager (MCM)](#multiple-composer-manager-mcm)
  - [Table of Contents](#table-of-contents)
  - [Frequently Asked Questions](#frequently-asked-questions)
    - [Why this class?](#why-this-class)
    - [How does it work?](#how-does-it-work)
    - [How does it look like?](#How-does-it-look-like)
    - [Who is it intended for?](#who-is-it-intended-for)
  - [Installation](#installation)
  - [Usage / API](#usage--api)
    - [`new MCM()`](#new-mcm)
    - [`->register()`](#-register)
    - [`->unregisterByFile()`](#-unregisterbyfile)
    - [`->unregisterByName()`](#-unregisterbyname)
    - [`->install()`](#-install)
    - [`->update()`](#-update)
    - [`->autoload()`](#-autoload)

## Frequently Asked Questions

### Why this class?

Before composer, many PHP applications had their own way of loading classes and third-party code.
Often times, they did a very poor job of managing classes. Consider for example two WordPress plugins that both include the same utility class but of a different version.
This can lead to very confusing incompatibility issues.

Even after composer became popular, many of these applications still do not make use of it.
Some efforts in using composer may even fall short: consider the same example with two plugins and a utility class, but instead with a utility package - same issue.

This class aims to solve the problem by creating a third (or dummy global version) of `composer.json`, so that composer will correctly resolve package versions.

### How does it work?

The composer file schema provides a [repositories](https://getcomposer.org/doc/05-repositories.md) section.
One of the available options is to specify the path to a `composer.json`, thus make use of a local package in place of a remote one.
On the next install/update composer will correctly determine the correct combination of subpackages that can be used.

This also means that one must always load the global autoloader (see `autoload()` method) and never the one provided in the individual package.

### How does it look like?

```
                           .-------------.
                           | Application |
                           '-------------'
                                  |
        .--------------------------------------------.
        |                         |                  |
     .----------.           .----------.   .-------------------.
     | Plugin 1 |           | Plugin 2 |   | composer.json (1) |
     '----------'           '----------'   '-------------------'
          |                       |
.-------------------.   .-------------------.
| composer.json (2) |   | composer.json (3) |
'-------------------'   '-------------------'
```
_One global managed composer.json (1) that points to all other dependencies' (2) and (3)_

### Who is it intended for?

- *Application Writers*
  By bundling this class and exposing it in your API, you will make your application extendable by third-party code that builds on composer - a powerful, safe and controlled mechanism that builds on standard and proven software.
- *Plugins Writers*
  This is a bit tricky since it depends on all plugin writers (that need composer) to make use of this class.
  Making use of this class means that you can use composer packages in your project as well as ensure a higher degree of compatibility with other plugins.

## Installation

This class cannot be loaded from composer since it's a "chicken & egg" situation: it would imply that a `composer.json` already exists and is not manageable.

Therefore the easiest way would be to simply copy the class (in `src/`) into your project somewhere (don't forget to `require`/`include` it).

## Usage / API

Simply load the class and create an MCM instance:
```php
require_once('src/MCM.php');
$mcm = new \uuf6429\MultiComposerManager\MCM(/* ... */);
```

### `new MCM()`

```php
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
);
```

### `->register()`

```php
/**
 * Add a new `composer.json` file to load dependencies from.
 * @param string $fileName Path to `composer.json` to register for dependencies (can be absolute or relative).
 * @param boolean $applyChanges If true, the newly added dependency is also installed.
 * @return $this
 */
public function register($fileName, $applyChanges = false);
```

### `->unregisterByFile()`

```php
/**
 * Remove `composer.json` file from dependencies, by file path.
 * @param string $fileName
 * @param boolean $applyChanges If true, the dependency is also physically removed.
 * @return $this
 */
public function unregisterByFile($fileName, $applyChanges = false);
```

### `->unregisterByName()`

```php
/**
 * Remove `composer.json` file from dependencies, by package name (the value of "name" in composer config).
 * @param string $packageName
 * @param boolean $applyChanges If true, the dependency is also physically removed.
 * @return $this
 */
public function unregisterByName($packageName, $applyChanges = false);
```

### `->install()`

```php
/**
 * Performs a `composer install`.
 * @return $this
 */
public function install();
```

### `->update()`

```php
/**
 * Performs a `composer update` (optionally, for particular packages only).
 * @param null|string[] $packageNames If null, all packages are updated. Otherwise, only the specified packages will be updated.
 * @return $this
 */
public function update($packageNames = null);
```

### `->autoload()`

```php
/**
 * "Requires" the base composer class loader, making all composer dependencies available for use.
 * @return \Composer\Autoload\ClassLoader
 */
public function autoload();
```
