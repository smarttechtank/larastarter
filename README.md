# LaraStarter Package

A Laravel package that sets up a starter project with API stack, role-based authentication, API controllers, repositories, and more.

## Features

- Integrated API starter kit with Sanctum authentication (no need for Laravel Breeze)
- Cross-Origin Resource Sharing (CORS) configuration
- Frontend/Backend separation with proper API endpoints
- Role-based user authentication
- Two-factor authentication via email
- User avatar upload and management system
- User phone number management with international format support
- Comprehensive user search, filtering, and sorting capabilities
- Bulk user management operations with proper authorization
- API controllers for users and roles
- Repository pattern implementation
- Policy-based authorization with granular permissions
- Database seeders for roles and users
- Custom request validation classes
- Email verification via API
- IDE Helper setup with auto-generation
- Interactive installation with modern UI prompts

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

1. Install the API starter kit with Sanctum authentication (previously required Laravel Breeze)
2. Configure CORS for API access
3. Set up frontend URL environment variable
4. Create the necessary migrations for roles and two-factor authentication
5. Install the Role model
6. Update the User model to support roles and two-factor authentication
7. Install repositories for users and roles
8. Install policies for authorization
9. Install middleware for API protection and email verification
10. Install database seeders
11. Install request validation classes
12. Install API controllers and routes
13. Configure IDE Helper

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

## API Authentication

LaraStarter sets up a complete API authentication system using Laravel Sanctum:

- Session-based authentication for browser clients
- Token-based authentication for mobile/SPA applications
- CSRF protection for browser requests
- Proper CORS configuration for cross-origin requests

### API Routes

- `POST /api/register` - Register a new user
- `POST /api/login` - Authenticate a user
- `POST /api/logout` - Log out the current user
- `GET /api/user` - Get the authenticated user's data
- `POST /api/forgot-password` - Send password reset link
- `POST /api/reset-password` - Reset the user's password
- `GET /api/verify-email/{id}/{hash}` - Verify email address
- `POST /api/email/verification-notification` - Resend verification email

### User Management Routes

**Admin User Management** (requires appropriate permissions):

- `GET /api/users` - List users with filtering, searching, and pagination
- `POST /api/users` - Create a new user (sends password reset email)
- `GET /api/users/{id}` - View a specific user
- `PUT/PATCH /api/users/{id}` - Update user details (name, email, phone, role)
- `DELETE /api/users/{id}` - Delete a specific user
- `POST /api/users/bulk-destroy` - Delete multiple users at once

**Profile Management** (for authenticated users):

- `PUT/PATCH /api/users/update-profile` - Update user profile (name, email, phone)
- `PUT/PATCH /api/users/update-password` - Update user password

### Avatar Management Routes

- `PUT/PATCH /api/users/upload-avatar` - Upload or update user avatar
- `DELETE /api/users/delete-avatar` - Delete user avatar

**Note:** The `avatar_url` is automatically included in all User JSON responses for easy frontend integration.

## User Profile Management

LaraStarter provides comprehensive user profile management capabilities:

### Phone Number Support

- **International format validation** - Supports various phone number formats
- **Optional field** - Phone numbers are not required
- **Search functionality** - Users can be searched by phone number
- **Validation patterns** - Accepts formats like `+1-234-567-8900`, `(555) 123-4567`, `+44 20 1234 5678`
- **Regex validation** - Uses pattern `/^[\+]?[0-9\-\(\)\s]+$/` for validation

### Profile Features

- Update name, email, and phone number
- Email uniqueness validation (excludes current user during updates)
- Secure password updates with proper authorization
- Avatar upload and management
- Role-based access control with policy authorization

### User Search and Filtering

LaraStarter provides comprehensive search and filtering capabilities for user management:

- **Text Search** - Search users by name, email, or phone number
- **Role Filtering** - Filter users by specific roles (supports multiple role IDs)
- **Sorting Options** - Sort by name, email, role, or creation date (ascending/descending)
- **Pagination** - Configurable per-page results with query string preservation
- **Combined Filters** - Use multiple filters simultaneously for precise results

**Supported Sort Options:**

- `name.asc` / `name.desc` - Sort by user name
- `email.asc` / `email.desc` - Sort by email address
- `role.asc` / `role.desc` - Sort by role name
- `created_at.asc` / `created_at.desc` - Sort by registration date

### Bulk Operations

- **Bulk User Deletion** - Delete multiple users at once with proper authorization
- **Self-deletion Protection** - Prevents users from accidentally deleting themselves
- **Detailed Response** - Returns count of successful/failed deletions and error details

## Two-Factor Authentication

LaraStarter includes a complete two-factor authentication system that works with both API and web routes:

- Users can enable/disable 2FA through their profile settings
- When 2FA is enabled, a verification code is sent via email during login
- The code expires after 10 minutes for security
- Supports both token-based (API) and session-based authentication
- Graceful fallback if email sending fails

### API Routes

- `POST /api/two-factor/toggle` - Enable/disable 2FA (requires authentication)
- `POST /api/two-factor/verify` - Verify the 2FA code during login

### Web Routes

- `POST /two-factor/toggle` - Enable/disable 2FA (requires authentication)
- `POST /two-factor/verify` - Verify the 2FA code during login

## IDE Helper Integration

LaraStarter automatically configures Laravel IDE Helper to improve your development experience. The package:

- Adds IDE Helper to your project's dependencies
- Configures post-update commands to generate helper files
- Adds IDE helper files to .gitignore

This provides better code completion and static analysis for your IDE.

## Configuration

You can publish the configuration file to customize the roles:

```bash
php artisan vendor:publish --tag=larastarter-config
```

This will publish a config file at `config/larastarter.php` where you can customize:

- Default role for new users
- Available roles and their descriptions

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
