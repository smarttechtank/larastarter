# [LaraStarter Package](https://packagist.org/packages/smarttechtank/larastarter) &middot; [![Author Salimi](https://img.shields.io/badge/Author-Salimi-%3C%3E)](https://github.com/salimi-my)

A Laravel package that sets up a starter project with API stack, role-based authentication, API controllers, repositories, and more.

## Features

- Integrated API starter kit with Sanctum authentication (no need for Laravel Breeze)
- Cross-Origin Resource Sharing (CORS) configuration
- Frontend/Backend separation with proper API endpoints
- Role-based user authentication
- Configurable user registration control (enable/disable registration)
- **OAuth social authentication (Google & GitHub)**
  - Multiple provider support per account
  - Account linking/unlinking capabilities
  - Mobile app token authentication
  - OAuth-only users (no password required)
- Two-factor authentication via Google Authenticator
- User avatar upload and management system
- User phone number management with international format support
- Secure email change with verification flow
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
2. Install Google 2FA packages (bacon/bacon-qr-code, pragmarx/google2fa-laravel, pragmarx/recovery)
3. Install OAuth packages (laravel/socialite, google/apiclient)
4. Configure CORS for API access
5. Set up frontend URL environment variable
6. Add authentication environment variables (EMAIL_CHANGE_ALERT_DELAY, VERIFICATION_EXPIRE_MINUTES, REGISTRATION_ENABLED, SOCIAL_AUTH_ENABLED, OAuth credentials)
7. Create the necessary migrations for roles, Google Authenticator 2FA, avatar, phone, email change, and OAuth providers
8. Install the Role model
9. Update the User model to support roles, Google Authenticator 2FA, avatar, phone, email change verification, and OAuth providers
10. Install repositories for users and roles
11. Install policies for authorization
12. Install middleware for API protection, email verification, and registration control
13. Install database seeders
14. Install request validation classes
15. Install API controllers (including OAuth social authentication)
16. Install notification classes for email verification, password reset, and email change alerts
17. Update configuration files (services.php for OAuth, auth.php for social auth control)
18. Configure IDE Helper
19. Create storage symlink for public file access (avatars, etc.)

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

- `POST /api/register` - Register a new user (can be disabled via configuration)
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

- `PUT/PATCH /api/users/update-profile` - Update user profile (name, phone) - _Note: Email changes require separate verification_
- `PUT/PATCH /api/users/update-password` - Update user password

### Avatar Management Routes

- `PUT/PATCH /api/users/upload-avatar` - Upload or update user avatar
- `DELETE /api/users/delete-avatar` - Delete user avatar

**Note:** The `avatar_url` is automatically included in all User JSON responses for easy frontend integration.

### Email Change Routes

- `POST /api/users/email-change/request` - Request to change email address (requires password confirmation)
- `POST /api/users/email-change/resend` - Resend verification email to pending email address (throttled)
- `GET /api/email-change/verify/{id}/{token}/{email}` - Verify and complete email change (signed URL)
- `GET /api/users/email-change/status` - Get current pending email change status
- `DELETE /api/users/email-change/cancel` - Cancel pending email change request

**Note:** Email changes require verification via a signed URL sent to the new email address. The verification link expires after 60 minutes, and requests are throttled to prevent abuse (5-minute cooldown between initial requests). Users can resend verification emails if they didn't receive it, which generates a new token and resets the expiration timer.

## User Profile Management

LaraStarter provides comprehensive user profile management capabilities:

### Phone Number Support

- **International format validation** - Supports various phone number formats
- **Optional field** - Phone numbers are not required
- **Search functionality** - Users can be searched by phone number
- **Validation patterns** - Accepts formats like `+1-234-567-8900`, `(555) 123-4567`, `+44 20 1234 5678`
- **Regex validation** - Uses pattern `/^[\+]?[0-9\-\(\)\s]+$/` for validation

### Email Change with Verification

LaraStarter implements a secure email change workflow to prevent unauthorized email modifications:

- **Password Confirmation** - Users must confirm their current password to request an email change
- **Verification Email** - A verification email is sent to the new email address with a signed URL
- **Token Expiration** - Verification links expire after 60 minutes (configurable via `auth.verification.expire`)
- **Throttling Protection** - Requests are throttled to 5 minutes between attempts to prevent abuse
- **Cancel Pending Changes** - Users can cancel pending email change requests at any time
- **Resend Verification** - Users can request a new verification email if they didn't receive the original
- **Status Checking** - API endpoint to check if there's a pending email change
- **Automatic Verification** - Once verified, the new email is automatically marked as verified
- **Support for Both API and Web** - Works with both token-based (API) and session-based (web) authentication

**Security Features:**

- Tokens are securely hashed before storage
- Verification URLs are cryptographically signed
- Old email addresses remain unchanged until verification is complete
- Admin updates (via `/api/users/{id}`) can directly update email without verification
- **Dual notification system** for enhanced security:
  - Success notification sent to the new email address
  - Security alert sent to the old email address (delayed by 60 seconds)
  - Allows users to detect unauthorized changes
  - All notifications are queued for background processing

**Workflow:**

1. User requests email change with password confirmation
2. System validates new email is not already in use
3. Verification email sent to new address
4. User clicks verification link (or can request resend if email wasn't received)
5. System verifies token and updates email
6. Success notification sent to new email address
7. Security alert sent to old email address with instructions for unauthorized changes
8. Old tokens are automatically cleaned up

**Resend Feature Details:**

- Generates a new verification token when resending
- Resets the 60-minute expiration timer
- Throttled to prevent spam (6 attempts per minute via rate limiting)
- Fails if the original request has already expired (requires new request instead)

### Profile Features

- Update name and phone number via profile endpoint
- Email changes require separate verification flow (see Email Change section)
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

## OAuth Social Authentication

LaraStarter includes comprehensive OAuth social authentication support for Google and GitHub:

- **Multiple Provider Support**: Users can link both Google and GitHub to the same account
- **Registration Control**: Respects `registration_enabled` config setting
- **Account Linking**: Existing users can link OAuth providers to their accounts
- **2FA Integration**: Works seamlessly with existing 2FA system
- **API & Web Support**: Supports both token-based API and session-based web authentication
- **Mobile App Support**: Token-based authentication for native mobile apps (Flutter, React Native)
- **OAuth-only Users**: Users can authenticate without setting a password
- **Automatic Avatar Import**: Automatically downloads and stores user avatars from OAuth providers if the user doesn't have one

### OAuth Routes

**Authentication Routes (Guest Users):**

- `GET /auth/{provider}/redirect` - Redirect to OAuth provider (google|github)
- `GET /auth/{provider}/callback` - Handle OAuth callback

**Account Linking Routes (Authenticated Users):**

- `GET /auth/{provider}/link` - Link OAuth provider to existing account
- `DELETE /auth/{provider}/unlink` - Unlink OAuth provider from account

**Mobile OAuth Route (Native Apps):**

- `POST /auth/{provider}/token` - Authenticate with OAuth token from mobile SDK

### OAuth Configuration

Add the following to your `.env` file:

```env
# OAuth Settings
SOCIAL_AUTH_ENABLED=true
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GITHUB_CLIENT_ID=your_github_client_id
GITHUB_CLIENT_SECRET=your_github_client_secret
```

For detailed OAuth setup instructions, mobile app integration guides, and security best practices, see [OAUTH_SETUP.md](OAUTH_SETUP.md).

### Automatic Avatar Import

LaraStarter automatically imports user avatars from OAuth providers during authentication:

**When Avatars are Imported:**

- **New User Registration**: When a new user registers via OAuth, their profile picture is automatically downloaded and stored locally
- **Account Linking**: When an existing user links an OAuth provider and doesn't have an avatar yet
- **Existing User Login**: When a user with the same email logs in via OAuth and doesn't have an avatar

**Technical Details:**

- Supports Google and GitHub profile pictures
- Images are validated (5MB max size)
- Accepted formats: JPEG, PNG, GIF, WebP
- Stored in `storage/app/public/avatars/` directory
- Only imports if user doesn't already have an avatar
- Fails gracefully if download fails (user account is still created/authenticated)

**Security & Performance:**

- HTTP timeout: 10 seconds
- File size validation to prevent abuse
- MIME type verification for supported image formats
- Error logging for troubleshooting
- Unique filenames to prevent conflicts

## Two-Factor Authentication

LaraStarter includes a complete Google Authenticator-based two-factor authentication system that works with both API and web routes:

- Users can enable/disable 2FA through their profile settings
- When 2FA is enabled, users scan a QR code with Google Authenticator app
- Time-based One-Time Passwords (TOTP) are generated by the authenticator app
- Recovery codes are provided for backup access
- Supports both token-based (API) and session-based authentication
- Secure secret key generation and storage

### Dependencies

LaraStarter automatically installs the following packages for Google 2FA functionality:

- `bacon/bacon-qr-code` - QR code generation for authenticator app setup
- `pragmarx/google2fa-laravel` - Google 2FA implementation for Laravel
- `pragmarx/recovery` - Recovery codes management

### API Routes

- `POST /api/two-factor/setup` - Generate QR code and enable 2FA (requires authentication)
- `POST /api/two-factor/verify` - Verify the 2FA code during login
- `POST /api/two-factor/toggle` - Enable/disable 2FA (requires authentication)

### Web Routes

- `POST /two-factor/setup` - Generate QR code and enable 2FA (requires authentication)
- `POST /two-factor/verify` - Verify the 2FA code during login
- `POST /two-factor/toggle` - Enable/disable 2FA (requires authentication)

## IDE Helper Integration

LaraStarter automatically configures Laravel IDE Helper to improve your development experience. The package:

- Adds IDE Helper to your project's dependencies
- Configures post-update commands to generate helper files
- Adds IDE helper files to .gitignore

This provides better code completion and static analysis for your IDE.

## Configuration

### Role Configuration

You can publish the configuration file to customize the roles:

```bash
php artisan vendor:publish --tag=larastarter-config
```

This will publish a config file at `config/larastarter.php` where you can customize:

- Default role for new users
- Available roles and their descriptions

### Registration Control

LaraStarter provides the ability to enable or disable user registration. This is useful for applications where you want to restrict public registration and only allow administrators to create users.

**Configuration:**

Registration can be controlled via the `REGISTRATION_ENABLED` environment variable in your `.env` file:

```env
# Authentication Settings
REGISTRATION_ENABLED=true  # Set to false to disable registration
```

When registration is disabled (`REGISTRATION_ENABLED=false`), the registration endpoint will return a `403 Forbidden` response with the message "Registration is currently disabled."

**Note:** This only affects the public registration endpoint (`POST /api/register`). Administrators can still create users through the admin user management endpoint (`POST /api/users`).

### Authentication Environment Variables

The following environment variables are automatically added during installation:

```env
# Authentication Settings
EMAIL_CHANGE_ALERT_DELAY=60                        # Delay in seconds before sending alert to old email
VERIFICATION_EXPIRE_MINUTES=60                     # Minutes before email verification link expires
REGISTRATION_ENABLED=true                          # Enable/disable public user registration
SOCIAL_AUTH_ENABLED=true                           # Enable/disable social OAuth authentication
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GITHUB_CLIENT_ID=your_github_client_id
GITHUB_CLIENT_SECRET=your_github_client_secret
```

You can customize these values in your `.env` file to match your application's requirements.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
