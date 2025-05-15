<?php

namespace SmartTechTank\Larastarter\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use function Laravel\Prompts\select;

class InstallCommand extends Command
{
    use InstallApiStackTrait;

    protected $signature = 'larastarter:install
                            {--force : Overwrite existing files}
                            {--replace : Internal option to handle file replacement choices}';

    protected $description = 'Install the LaraStarter package components, including API stack, roles, repositories, API controllers, and more';

    public function handle()
    {
        $this->info('Installing LaraStarter...');

        // First install the API stack
        $this->installApiStack();

        // Install migrations
        $this->publishMigrations();

        // Publish Sanctum migrations
        $this->publishSanctumMigrations();

        // Install factories
        $this->publishFactories();

        // Install models
        $this->installModels();

        // Install repositories
        $this->installRepositories();

        // Install policies
        $this->installPolicies();

        // Install middleware
        $this->installMiddleware();

        // Install seeders
        $this->installSeeders();

        // Install request classes
        $this->installRequests();

        // Always install API controllers
        $this->installApiControllers();
        $this->updateRoutes();

        // Update base User model
        $this->updateUserModel();

        // Update base Controller
        $this->updateController();

        // Update auth files
        $this->updateAuthFiles();

        // Install email views
        $this->installEmailViews();

        // Update gitignore
        $this->updateGitignore();

        // Update composer.json and install ide-helper if not exists
        $this->updateComposerJson();

        $this->info('LaraStarter installation complete!');
        $this->info('Remember to run "php artisan migrate" to create the necessary database tables.');

        return Command::SUCCESS;
    }

    protected function publishMigrations()
    {
        $this->info('Publishing migrations...');
        $this->call('vendor:publish', [
            '--tag' => 'larastarter-migrations',
            '--force' => $this->option('force'),
        ]);
    }

    protected function publishSanctumMigrations()
    {
        $this->info('Publishing Sanctum migrations...');
        $this->call('vendor:publish', [
            '--provider' => 'Laravel\\Sanctum\\SanctumServiceProvider',
            '--tag' => 'sanctum-migrations',
            '--force' => $this->option('force'),
        ]);
    }

    protected function publishFactories()
    {
        $this->info('Publishing factories...');
        $this->call('vendor:publish', [
            '--tag' => 'larastarter-factories',
            '--force' => $this->option('force'),
        ]);
    }

    protected function installModels()
    {
        $this->info('Installing models...');

        // Create the Role model
        $this->copyFile(
            __DIR__ . '/../../stubs/app/Models/Role.php',
            app_path('Models/Role.php')
        );
    }

    protected function installRepositories()
    {
        $this->info('Installing repositories...');

        // Create repository directory if it doesn't exist
        (new Filesystem)->ensureDirectoryExists(app_path('Repositories'));

        // Copy base repository
        $this->copyFile(
            __DIR__ . '/../../stubs/app/Repositories/BaseRepository.php',
            app_path('Repositories/BaseRepository.php')
        );

        // Copy user repository
        $this->copyFile(
            __DIR__ . '/../../stubs/app/Repositories/UserRepository.php',
            app_path('Repositories/UserRepository.php')
        );

        // Copy role repository
        $this->copyFile(
            __DIR__ . '/../../stubs/app/Repositories/RoleRepository.php',
            app_path('Repositories/RoleRepository.php')
        );
    }

    protected function installPolicies()
    {
        $this->info('Installing policies...');

        // Create policies directory if it doesn't exist
        (new Filesystem)->ensureDirectoryExists(app_path('Policies'));

        // Copy role policy
        $this->copyFile(
            __DIR__ . '/../../stubs/app/Policies/RolePolicy.php',
            app_path('Policies/RolePolicy.php')
        );

        // Copy user policy
        $this->copyFile(
            __DIR__ . '/../../stubs/app/Policies/UserPolicy.php',
            app_path('Policies/UserPolicy.php')
        );
    }

    protected function installMiddleware()
    {
        $this->info('Installing middleware...');

        // Copy SkipCsrfToken middleware
        $this->copyFile(
            __DIR__ . '/../../stubs/app/Http/Middleware/SkipCsrfToken.php',
            app_path('Http/Middleware/SkipCsrfToken.php')
        );

        // Update EnsureEmailIsVerified middleware
        $this->copyFile(
            __DIR__ . '/../../stubs/app/Http/Middleware/EnsureEmailIsVerified.php',
            app_path('Http/Middleware/EnsureEmailIsVerified.php'),
            $this->option('force')
        );
    }

    protected function installSeeders()
    {
        $this->info('Installing seeders...');

        // Create seeders directory if it doesn't exist
        (new Filesystem)->ensureDirectoryExists(database_path('seeders'));

        // Directly copy seeder files to ensure they replace existing ones
        $this->copyFile(
            __DIR__ . '/../../stubs/database/seeders/DatabaseSeeder.php',
            database_path('seeders/DatabaseSeeder.php'),
            $this->option('force')
        );

        $this->copyFile(
            __DIR__ . '/../../stubs/database/seeders/RoleSeeder.php',
            database_path('seeders/RoleSeeder.php'),
            $this->option('force')
        );
    }

    protected function installRequests()
    {
        $this->info('Installing request classes...');

        // Create directories if they don't exist
        (new Filesystem)->ensureDirectoryExists(app_path('Http/Requests'));
        (new Filesystem)->ensureDirectoryExists(app_path('Http/Requests/Auth'));

        // Copy request files
        $requests = [
            'BulkDestroyRolesRequest.php',
            'BulkDestroyUsersRequest.php',
            'StoreRoleRequest.php',
            'StoreUserRequest.php',
            'UpdateRoleRequest.php',
            'UpdateUserRequest.php',
            'UpdateUserPasswordRequest.php',
        ];

        foreach ($requests as $request) {
            $this->copyFile(
                __DIR__ . '/../../stubs/app/Http/Requests/' . $request,
                app_path('Http/Requests/' . $request)
            );
        }

        // Copy Auth request files
        $authRequests = [
            'LoginRequest.php',
            'TwoFactorVerifyRequest.php',
            'TwoFactorToggleRequest.php',
        ];

        foreach ($authRequests as $request) {
            $this->copyFile(
                __DIR__ . '/../../stubs/app/Http/Requests/Auth/' . $request,
                app_path('Http/Requests/Auth/' . $request),
                $this->option('force')
            );
        }
    }

    protected function installApiControllers()
    {
        $this->info('Installing API controllers...');

        // Create API controllers directory if it doesn't exist
        (new Filesystem)->ensureDirectoryExists(app_path('Http/Controllers/API'));

        // Copy API controllers
        $this->copyFile(
            __DIR__ . '/../../stubs/app/Http/Controllers/API/RoleAPIController.php',
            app_path('Http/Controllers/API/RoleAPIController.php')
        );

        $this->copyFile(
            __DIR__ . '/../../stubs/app/Http/Controllers/API/UserAPIController.php',
            app_path('Http/Controllers/API/UserAPIController.php')
        );

        // Copy modified auth controllers
        $authControllers = [
            'AuthenticatedSessionController.php',
            'EmailVerificationNotificationController.php',
            'RegisteredUserController.php',
            'VerifyEmailController.php',
            'TwoFactorAuthController.php',
            'NewPasswordController.php',
            'PasswordResetLinkController.php',
        ];

        foreach ($authControllers as $controller) {
            $this->copyFile(
                __DIR__ . '/../../stubs/app/Http/Controllers/Auth/' . $controller,
                app_path('Http/Controllers/Auth/' . $controller),
                $this->option('force')
            );
        }

        // Copy notification
        (new Filesystem)->ensureDirectoryExists(app_path('Notifications'));
        $this->copyFile(
            __DIR__ . '/../../stubs/app/Notifications/VerifyEmail.php',
            app_path('Notifications/VerifyEmail.php')
        );

        // Copy TwoFactorCode notification
        $this->copyFile(
            __DIR__ . '/../../stubs/app/Notifications/TwoFactorCode.php',
            app_path('Notifications/TwoFactorCode.php')
        );
    }

    protected function updateRoutes()
    {
        $this->info('Updating routes...');

        // Update api.php routes file
        $this->copyFile(
            __DIR__ . '/../../stubs/routes/api.php',
            base_path('routes/api.php'),
            $this->option('force')
        );

        // Update auth.php routes file
        $this->copyFile(
            __DIR__ . '/../../stubs/routes/auth.php',
            base_path('routes/auth.php'),
            $this->option('force')
        );

        // Update web.php routes file
        $this->copyFile(
            __DIR__ . '/../../stubs/routes/web.php',
            base_path('routes/web.php'),
            $this->option('force')
        );
    }

    protected function updateUserModel()
    {
        $this->info('Updating User model...');

        // Update User.php
        $this->copyFile(
            __DIR__ . '/../../stubs/app/Models/User.php',
            app_path('Models/User.php'),
            $this->option('force')
        );
    }

    protected function updateController()
    {
        $this->info('Updating base Controller...');

        // Update Controller.php
        $this->copyFile(
            __DIR__ . '/../../stubs/app/Http/Controllers/Controller.php',
            app_path('Http/Controllers/Controller.php'),
            $this->option('force')
        );
    }

    protected function updateAuthFiles()
    {
        $this->info('Updating auth-related files...');

        // Update bootstrap/app.php
        $this->copyFile(
            __DIR__ . '/../../stubs/bootstrap/app.php',
            base_path('bootstrap/app.php'),
            $this->option('force')
        );
    }

    protected function updateGitignore()
    {
        $this->info('Updating .gitignore...');

        $gitignorePath = base_path('.gitignore');

        if (file_exists($gitignorePath)) {
            $content = file_get_contents($gitignorePath);

            if (!str_contains($content, '_ide_helper')) {
                $this->info('Adding IDE helper files to .gitignore');
                file_put_contents(
                    $gitignorePath,
                    $content . PHP_EOL . '# IDE Helper' . PHP_EOL .
                        '_ide_helper.php' . PHP_EOL .
                        '.phpstorm.meta.php' . PHP_EOL
                );
            }
        }
    }

    protected function updateComposerJson()
    {
        $this->info('Updating composer.json...');

        $composerJsonPath = base_path('composer.json');

        if (file_exists($composerJsonPath)) {
            $composerJson = json_decode(file_get_contents($composerJsonPath), true);

            // Add Laravel IDE Helper to require-dev if it doesn't exist
            if (!isset($composerJson['require-dev']['barryvdh/laravel-ide-helper'])) {
                $this->info('Adding Laravel IDE Helper to composer.json');
                $composerJson['require-dev']['barryvdh/laravel-ide-helper'] = '^3.5';
            }

            // Add IDE helper commands to post-update-cmd if not already added
            if (
                !isset($composerJson['scripts']['post-update-cmd']) ||
                !in_array('@php artisan ide-helper:generate', $composerJson['scripts']['post-update-cmd'])
            ) {

                $this->info('Adding IDE Helper commands to post-update-cmd in composer.json');

                // Initialize post-update-cmd if it doesn't exist
                if (!isset($composerJson['scripts']['post-update-cmd'])) {
                    $composerJson['scripts']['post-update-cmd'] = [
                        '@php artisan vendor:publish --tag=laravel-assets --ansi --force'
                    ];
                }

                // Add IDE Helper commands
                if (!in_array('@php artisan ide-helper:generate', $composerJson['scripts']['post-update-cmd'])) {
                    $composerJson['scripts']['post-update-cmd'][] = '@php artisan ide-helper:generate';
                }

                if (!in_array('@php artisan ide-helper:meta', $composerJson['scripts']['post-update-cmd'])) {
                    $composerJson['scripts']['post-update-cmd'][] = '@php artisan ide-helper:meta';
                }
            }

            // Save the updated composer.json
            file_put_contents(
                $composerJsonPath,
                json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $this->info('Installing Laravel IDE Helper...');
            $this->runComposerUpdate();
        }
    }

    protected function runComposerUpdate()
    {
        $process = Process::fromShellCommandline('composer update', null, null, null, null);
        $process->run(function ($type, $line) {
            $this->output->write($line);
        });
    }

    protected function copyFile(string $from, string $to, bool $force = false)
    {
        // Check if file exists and force option is not set
        if (file_exists($to) && !$force && !$this->option('force')) {
            // Using Laravel Prompts directly
            $this->input->setOption('replace', select(
                label: "The file {$to} already exists. Do you want to replace it?",
                options: ['No', 'Yes'],
                default: 'Yes'
            ) === 'Yes');

            if (!$this->option('replace')) {
                return;
            }
        }

        if (!file_exists($from)) {
            $this->error("File {$from} does not exist!");
            return;
        }

        // Ensure the destination directory exists
        (new Filesystem)->ensureDirectoryExists(dirname($to));

        // Copy the file
        copy($from, $to);

        $this->line("<info>Copied:</info> {$from} <info>to</info> {$to}");
    }

    protected function installEmailViews()
    {
        $this->info('Installing email views...');

        // Publish views
        $this->call('vendor:publish', [
            '--tag' => 'larastarter-views',
            '--force' => $this->option('force'),
        ]);
    }
}
