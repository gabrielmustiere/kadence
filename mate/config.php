<?php

// User's service configuration file
// This file is loaded into the Symfony DI container

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        // The command your coding agent must use, wrapper included.
        ->set('mate.invocation', 'symfony php vendor/bin/mate')
        // The major.minor your application runs on: Mate refuses to start under another one.
        ->set('mate.php_version', '8.5')
        ->set('ai_mate_symfony.cache_dir', '%mate.root_dir%/var/cache')
        ->set('ai_mate_symfony.profiler_dir', '%mate.root_dir%/var/cache/dev/profiler')
        ->set('ai_mate_monolog.log_dir', '%mate.root_dir%/var/log')
    ;

    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()

        // Register your custom services here
    ;
};
