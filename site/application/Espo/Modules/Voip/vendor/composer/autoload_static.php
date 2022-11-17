<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitb5dbd065d206f8e09e30ca04699e5a24
{
    public static $prefixLengthsPsr4 = array (
        'G' => 
        array (
            'Giggsey\\Locale\\' => 15,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Giggsey\\Locale\\' => 
        array (
            0 => __DIR__ . '/..' . '/giggsey/locale/src',
        ),
    );

    public static $prefixesPsr0 = array (
        'l' => 
        array (
            'libphonenumber' => 
            array (
                0 => __DIR__ . '/..' . '/giggsey/libphonenumber-for-php/src',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitb5dbd065d206f8e09e30ca04699e5a24::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitb5dbd065d206f8e09e30ca04699e5a24::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInitb5dbd065d206f8e09e30ca04699e5a24::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}