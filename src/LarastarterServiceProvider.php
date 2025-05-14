<?php

namespace SmartTechTank\Larastarter;

use Illuminate\Support\ServiceProvider;

class LarastarterServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register commands
        $this->commands([
            Console\InstallCommand::class,
        ]);

        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/larastarter.php',
            'larastarter'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/larastarter.php' => config_path('larastarter.php'),
        ], 'larastarter-config');

        // Publish migrations with fixed timestamps
        $this->publishes([
            __DIR__ . '/../database/migrations/create_roles_table.php.stub' => database_path('migrations/0001_01_01_000003_create_roles_table.php'),
            __DIR__ . '/../database/migrations/add_role_id_to_users_table.php.stub' => database_path('migrations/0001_01_01_000004_add_role_id_to_users_table.php'),
            __DIR__ . '/../database/migrations/add_two_factor_auth_to_users_table.php.stub' => database_path('migrations/0001_01_01_000005_add_two_factor_auth_to_users_table.php'),
        ], 'larastarter-migrations');

        // Publish factories
        $this->publishes([
            __DIR__ . '/../stubs/database/factories/' => database_path('factories/'),
        ], 'larastarter-factories');

        // Publish seeders
        $this->publishes([
            __DIR__ . '/../stubs/database/seeders/' => database_path('seeders/'),
        ], 'larastarter-seeders');

        // Publish views
        $this->publishes([
            __DIR__ . '/../stubs/resources/views/emails/' => resource_path('views/emails/'),
        ], 'larastarter-views');

        // Publish stubs
        $this->publishes([
            __DIR__ . '/../stubs/' => base_path('stubs/larastarter'),
        ], 'larastarter-stubs');
    }
}
