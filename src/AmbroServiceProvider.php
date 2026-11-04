<?php

declare(strict_types=1);

namespace Beziworld\Ambro;

use Beziworld\Ambro\Console\ServicesCommand;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Ambro Express manager, the client of the default connection and the artisan command.
 */
final class AmbroServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ambro.php', 'ambro');

        $this->app->singleton(AmbroManager::class, static fn(Application $app): AmbroManager => new AmbroManager(
            $app->make(HttpFactory::class),
            $app->make(Config::class),
        ));

        $this->app->bind(AmbroClient::class, static fn(Application $app): AmbroClient => $app->make(AmbroManager::class)->connection());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/ambro.php' => $this->app->configPath('ambro.php')], 'ambro-config');
            $this->commands([ServicesCommand::class]);
        }
    }
}
