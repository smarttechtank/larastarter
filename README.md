# LaraStarter Package

A Laravel package that sets up a starter project with role-based authentication, API controllers, repositories, and more.

## Features

-   Role-based user authentication
-   Two-factor authentication via email
-   API controllers for users and roles
-   Repository pattern implementation
-   Policy-based authorization
-   Database seeders for roles and users
-   Custom request validation classes
-   Email verification via API
-   IDE Helper setup with auto-generation
-   Interactive installation with modern UI prompts

## Installation

### From Packagist (Public)

```bash
composer require smarttechtank/larastarter
```

### From Private GitHub Repository

1. Configure GitHub authentication:

    ```bash
    # Using GitHub CLI (recommended)
    gh auth login
    composer config github-oauth.github.com $(gh auth token)

    # Or manually with a Personal Access Token
    composer config github-oauth.github.com YOUR_GITHUB_TOKEN
    ```

2. Add the repository to your `composer.json`:

    ```json
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/smarttechtank/larastarter"
        }
    ]
    ```

3. Require the package:

    ```bash
    composer require smarttechtank/larastarter:dev-main
    ```

## Usage

After installing the package, run the installation command:

```bash
php artisan larastarter:install
```

This will:

1. Create the necessary migrations for roles and two-factor authentication
2. Install the Role model
3. Update the User model to support roles and two-factor authentication
4. Install repositories for users and roles
5. Install policies for authorization
6. Install middleware for CSRF protection and email verification
7. Install database seeders
8. Install request validation classes
9. Install API controllers and routes
10. Configure IDE Helper

The installation process uses Laravel Prompts to provide an interactive user experience. When files already exist, you'll be presented with a selection prompt asking if you want to replace the file, with "Yes" as the default option.

To force overwrite existing files without prompts, use the `--force` flag:

```bash
php artisan larastarter:install --force
```

After installation, don't forget to run the migrations:

```bash
php artisan migrate
```

And seed the database:

```bash
php artisan db:seed
```

## Two-Factor Authentication

LaraStarter includes a complete two-factor authentication system that works with both API and web routes:

-   Users can enable/disable 2FA through their profile settings
-   When 2FA is enabled, a verification code is sent via email during login
-   The code expires after 10 minutes for security
-   Supports both token-based (API) and session-based authentication
-   Graceful fallback if email sending fails

### API Routes

-   `POST /api/two-factor/toggle` - Enable/disable 2FA (requires authentication)
-   `POST /api/two-factor/verify` - Verify the 2FA code during login

### Web Routes

-   `POST /two-factor/toggle` - Enable/disable 2FA (requires authentication)
-   `POST /two-factor/verify` - Verify the 2FA code during login

## IDE Helper Integration

LaraStarter automatically configures Laravel IDE Helper to improve your development experience. The package:

-   Adds IDE Helper to your project's dependencies
-   Configures post-update commands to generate helper files
-   Adds IDE helper files to .gitignore

This provides better code completion and static analysis for your IDE.

## Configuration

You can publish the configuration file to customize the roles:

```bash
php artisan vendor:publish --tag=larastarter-config
```

This will publish a config file at `config/larastarter.php` where you can customize:

-   Default role for new users
-   Available roles and their descriptions

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
