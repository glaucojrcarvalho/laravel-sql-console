<?php

namespace Glaucojrcarvalho\SqlConsole;

use Glaucojrcarvalho\SqlConsole\Console\Commands\SqlConsoleAllowUserCommand;
use Glaucojrcarvalho\SqlConsole\Console\Commands\SqlConsoleRevokeUserCommand;
use Illuminate\Support\ServiceProvider;

class SqlConsoleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sql-console');

        $this->publishes([
            __DIR__.'/../database/migrations/create_sql_console_allowed_users_table.php' =>
                database_path('migrations/'.date('Y_m_d_His').'_create_sql_console_allowed_users_table.php'),
        ], 'sql-console-migrations');

        $this->publishes([
            __DIR__.'/../config/sql-console.php' => config_path('sql-console.php'),
        ], 'sql-console-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/sql-console'),
        ], 'sql-console-views');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sql-console.php', 'sql-console');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SqlConsoleAllowUserCommand::class,
                SqlConsoleRevokeUserCommand::class,
            ]);
        }
    }
}
