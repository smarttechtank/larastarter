<?php

namespace SmartTechTank\Larastarter\Console;

use Illuminate\Filesystem\Filesystem;

trait InstallApiStackTrait
{
    /**
     * Install the API stack.
     *
     * @return void
     */
    protected function installApiStack()
    {
        $this->info('Installing API stack...');
        $files = new Filesystem;

        // Controllers...
        $files->ensureDirectoryExists(app_path('Http/Controllers/Auth'));

        // Copy all auth controllers
        $authControllers = [
            'AuthenticatedSessionController.php',
            'EmailVerificationNotificationController.php',
            'RegisteredUserController.php',
            'VerifyEmailController.php',
            'NewPasswordController.php',
            'PasswordResetLinkController.php',
        ];

        foreach ($authControllers as $controller) {
            $this->copyFile(
                __DIR__ . '/../../stubs/api/app/Http/Controllers/Auth/' . $controller,
                app_path('Http/Controllers/Auth/' . $controller),
                $this->option('force')
            );
        }

        // Middleware...
        $this->copyFile(
            __DIR__ . '/../../stubs/api/app/Http/Middleware/EnsureEmailIsVerified.php',
            app_path('Http/Middleware/EnsureEmailIsVerified.php'),
            $this->option('force')
        );

        // Install middleware and aliases
        $this->addMiddlewareToApp([
            '\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class',
        ], 'api');

        $this->addMiddlewareAliasesToApp([
            'verified' => '\App\Http\Middleware\EnsureEmailIsVerified::class',
        ]);

        // Requests...
        $files->ensureDirectoryExists(app_path('Http/Requests/Auth'));

        $authRequests = [
            'LoginRequest.php',
        ];

        foreach ($authRequests as $request) {
            $this->copyFile(
                __DIR__ . '/../../stubs/api/app/Http/Requests/Auth/' . $request,
                app_path('Http/Requests/Auth/' . $request),
                $this->option('force')
            );
        }

        // Providers...
        $this->installProviders();

        // Routes...
        $this->copyFile(
            __DIR__ . '/../../stubs/api/routes/api.php',
            base_path('routes/api.php'),
            $this->option('force')
        );

        $this->copyFile(
            __DIR__ . '/../../stubs/api/routes/auth.php',
            base_path('routes/auth.php'),
            $this->option('force')
        );

        $this->copyFile(
            __DIR__ . '/../../stubs/api/routes/web.php',
            base_path('routes/web.php'),
            $this->option('force')
        );

        // Configuration...
        $this->copyFile(
            __DIR__ . '/../../stubs/api/config/cors.php',
            config_path('cors.php'),
            $this->option('force')
        );

        $this->copyFile(
            __DIR__ . '/../../stubs/api/config/sanctum.php',
            config_path('sanctum.php'),
            $this->option('force')
        );

        // Environment...
        if (file_exists(base_path('.env'))) {
            $env = file_get_contents(base_path('.env'));

            if (!str_contains($env, 'FRONTEND_URL=')) {
                $env = preg_replace(
                    '/APP_URL=(.*)/',
                    'APP_URL=$1' . PHP_EOL . 'FRONTEND_URL=http://localhost:3000',
                    $env
                );

                file_put_contents(base_path('.env'), $env);
                $this->info('Added FRONTEND_URL to .env file');
            }
        }

        // Install tests
        $this->installApiTests();

        // Remove frontend files
        $this->cleanupFrontendFiles();
    }

    /**
     * Install API-specific providers.
     *
     * @return void
     */
    protected function installProviders()
    {
        $this->info('Installing providers...');

        // Create directory if it doesn't exist
        (new Filesystem)->ensureDirectoryExists(app_path('Providers'));

        // Publish AppServiceProvider with password reset URL customization
        $this->copyFile(
            __DIR__ . '/../../stubs/api/app/Providers/AppServiceProvider.php',
            app_path('Providers/AppServiceProvider.php'),
            $this->option('force')
        );
    }

    /**
     * Install API test files.
     *
     * @return void
     */
    protected function installApiTests()
    {
        $this->info('Installing API test files...');
        $files = new Filesystem;

        // Create test directories if they don't exist
        $files->ensureDirectoryExists(base_path('tests/Feature/Auth'));

        // Copy test files
        $testFiles = [
            'AuthenticationTest.php',
            'EmailVerificationTest.php',
            'PasswordResetTest.php',
            'RegistrationTest.php',
        ];

        foreach ($testFiles as $testFile) {
            $this->copyFile(
                __DIR__ . '/../../stubs/api/tests/Feature/Auth/' . $testFile,
                base_path('tests/Feature/Auth/' . $testFile),
                $this->option('force')
            );
        }

        // Delete Password Confirmation test if it exists
        if ($files->exists(base_path('tests/Feature/Auth/PasswordConfirmationTest.php'))) {
            $files->delete(base_path('tests/Feature/Auth/PasswordConfirmationTest.php'));
        }
    }

    /**
     * Remove frontend related files as they're not needed in API-only projects.
     *
     * @return void
     */
    protected function cleanupFrontendFiles()
    {
        $this->info('Removing frontend files...');
        $files = new Filesystem;

        // Remove frontend related files
        $frontendFiles = [
            'package.json',
            'vite.config.js',
            'tailwind.config.js',
            'postcss.config.js'
        ];

        foreach ($frontendFiles as $file) {
            if (file_exists(base_path($file))) {
                $files->delete(base_path($file));
                $this->line("<info>Deleted:</info> {$file}");
            }
        }

        // Remove Laravel "welcome" view...
        if (file_exists(resource_path('views/welcome.blade.php'))) {
            $files->delete(resource_path('views/welcome.blade.php'));
            $this->line("<info>Deleted:</info> views/welcome.blade.php");
            $files->put(resource_path('views/.gitkeep'), PHP_EOL);
        }

        // Remove CSS and JavaScript directories...
        if ($files->isDirectory(resource_path('css'))) {
            $files->deleteDirectory(resource_path('css'));
            $this->line("<info>Deleted directory:</info> resources/css");
        }

        if ($files->isDirectory(resource_path('js'))) {
            $files->deleteDirectory(resource_path('js'));
            $this->line("<info>Deleted directory:</info> resources/js");
        }

        // Remove node_modules and related files if they exist
        $nodeFiles = [
            'node_modules',
            'package-lock.json',
            'yarn.lock',
            'pnpm-lock.yaml'
        ];

        foreach ($nodeFiles as $file) {
            if (file_exists(base_path($file))) {
                if (is_dir(base_path($file))) {
                    $files->deleteDirectory(base_path($file));
                    $this->line("<info>Deleted directory:</info> {$file}");
                } else {
                    $files->delete(base_path($file));
                    $this->line("<info>Deleted:</info> {$file}");
                }
            }
        }
    }

    /**
     * Add middleware to app.php.
     *
     * @param array $middleware
     * @param string $group
     * @return void
     */
    protected function addMiddlewareToApp(array $middleware, string $group = 'web')
    {
        $appPath = base_path('bootstrap/app.php');

        if (file_exists($appPath)) {
            $appContent = file_get_contents($appPath);

            $middleware = collect($middleware)
                ->filter(fn($mw) => !str_contains($appContent, $mw))
                ->whenNotEmpty(function ($middlewareToAdd) use (&$appContent, $group, $appPath) {
                    $middlewareString = $middlewareToAdd->implode(',' . PHP_EOL . '            ');

                    // Check if middleware group already exists and append to it
                    if (preg_match('/->withMiddleware\(function \(Middleware \$middleware\) \{(.*?)\$middleware->' . $group . '\((.*?)\]\);/s', $appContent)) {
                        $appContent = preg_replace(
                            '/(\$middleware->' . $group . '\(.*?\[)(.*?)(\s*\]\);)/s',
                            '$1$2,' . PHP_EOL . '            ' . $middlewareString . '$3',
                            $appContent
                        );
                    } else {
                        // Add new middleware group
                        $appContent = str_replace(
                            '->withMiddleware(function (Middleware $middleware) {',
                            '->withMiddleware(function (Middleware $middleware) {' . PHP_EOL .
                                '        $middleware->' . $group . '([' . PHP_EOL .
                                '            ' . $middlewareString . ',' . PHP_EOL .
                                '        ]);' . PHP_EOL,
                            $appContent
                        );
                    }

                    file_put_contents($appPath, $appContent);
                    $this->info("Added middleware to {$group} group in bootstrap/app.php");
                });
        }
    }

    /**
     * Add middleware aliases to app.php.
     *
     * @param array $aliases
     * @return void
     */
    protected function addMiddlewareAliasesToApp(array $aliases)
    {
        $appPath = base_path('bootstrap/app.php');

        if (file_exists($appPath)) {
            $appContent = file_get_contents($appPath);

            $aliases = collect($aliases)
                ->filter(fn($alias, $name) => !str_contains($appContent, "'$name' => $alias"))
                ->whenNotEmpty(function ($aliasesToAdd) use (&$appContent, $appPath) {
                    $aliasString = $aliasesToAdd->map(fn($class, $name) => "'$name' => $class")
                        ->implode(',' . PHP_EOL . '            ');

                    // Check if aliases section already exists and append to it
                    if (preg_match('/\$middleware->alias\(\[(.*?)\]\);/s', $appContent)) {
                        $appContent = preg_replace(
                            '/(\$middleware->alias\(\[)(.*?)(\s*\]\);)/s',
                            '$1$2,' . PHP_EOL . '            ' . $aliasString . '$3',
                            $appContent
                        );
                    } else {
                        // Add new aliases section
                        $appContent = str_replace(
                            '->withMiddleware(function (Middleware $middleware) {',
                            '->withMiddleware(function (Middleware $middleware) {' . PHP_EOL .
                                '        $middleware->alias([' . PHP_EOL .
                                '            ' . $aliasString . ',' . PHP_EOL .
                                '        ]);' . PHP_EOL,
                            $appContent
                        );
                    }

                    file_put_contents($appPath, $appContent);
                    $this->info('Added middleware aliases to bootstrap/app.php');
                });
        }
    }
}
