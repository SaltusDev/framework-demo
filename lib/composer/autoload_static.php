<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit81dd07a72d54cfba2a2beaa1fe7e252a
{
    public static $files = array (
        '320cde22f66dd4f5d3fd621d3e88b98f' => __DIR__ . '/..' . '/symfony/polyfill-ctype/bootstrap.php',
        'a2c48002d05f7782d8b603bd2bcb5252' => __DIR__ . '/..' . '/johnbillion/extended-cpts/extended-cpts.php',
        '90abbbd1b4ec36aebe50d92b3788f45e' => __DIR__ . '/..' . '/soberwp/models/models.php',
    );

    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Symfony\\Polyfill\\Ctype\\' => 23,
            'Symfony\\Component\\Yaml\\' => 23,
            'Sober\\Models\\Model\\' => 19,
            'Sober\\Models\\' => 13,
            'Saltus\\WP\\Plugin\\Saltus\\PluginFramework\\Tests\\' => 46,
            'Saltus\\WP\\Plugin\\Saltus\\PluginFramework\\' => 40,
            'Saltus\\WP\\Plugin\\Saltus\\Framework\\' => 34,
        ),
        'N' => 
        array (
            'Noodlehaus\\' => 11,
        ),
        'C' => 
        array (
            'Composer\\Installers\\' => 20,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Symfony\\Polyfill\\Ctype\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-ctype',
        ),
        'Symfony\\Component\\Yaml\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/yaml',
        ),
        'Sober\\Models\\Model\\' => 
        array (
            0 => __DIR__ . '/..' . '/soberwp/models/src/Model',
        ),
        'Sober\\Models\\' => 
        array (
            0 => __DIR__ . '/..' . '/soberwp/models/src',
        ),
        'Saltus\\WP\\Plugin\\Saltus\\PluginFramework\\Tests\\' => 
        array (
            0 => __DIR__ . '/../..'.'/build' . '/../tests',
        ),
        'Saltus\\WP\\Plugin\\Saltus\\PluginFramework\\' => 
        array (
            0 => __DIR__ . '/../..'.'/build' . '/../src',
        ),
        'Saltus\\WP\\Plugin\\Saltus\\Framework\\' => 
        array (
            0 => __DIR__ . '/..' . '/saltus/framework/src',
        ),
        'Noodlehaus\\' => 
        array (
            0 => __DIR__ . '/..' . '/hassankhan/config/src',
        ),
        'Composer\\Installers\\' => 
        array (
            0 => __DIR__ . '/..' . '/composer/installers/src/Composer/Installers',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit81dd07a72d54cfba2a2beaa1fe7e252a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit81dd07a72d54cfba2a2beaa1fe7e252a::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
