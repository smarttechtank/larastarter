# OAuth Social Login Setup - Google & GitHub

## Overview

Social login functionality has been successfully implemented for Google and GitHub OAuth providers with the following key features:

- ✅ **Multiple Provider Support**: Users can link both Google and GitHub to the same account
- ✅ **Registration Control**: Respects `registration_enabled` config setting
- ✅ **Account Linking**: Existing users can link OAuth providers to their accounts
- ✅ **2FA Integration**: Works seamlessly with existing 2FA system
- ✅ **API & Web Support**: Supports both token-based API and session-based web authentication
- ✅ **Automatic Avatar Import**: Downloads and stores user avatars from OAuth providers

## Configuration

### 1. Environment Variables

Add the following variables to your `.env` file:

```env
# Google OAuth
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret

# GitHub OAuth
GITHUB_CLIENT_ID=your_github_client_id
GITHUB_CLIENT_SECRET=your_github_client_secret

# Registration Control
REGISTRATION_ENABLED=true

# Frontend URL (for OAuth redirects)
FRONTEND_URL=http://localhost:3000
```

### 2. OAuth Provider Setup

#### Google OAuth Setup:

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Open the console’s left side menu and select APIs & Services > Credentials
4. On the Credentials page, click Create Credentials > OAuth Client ID
5. Add authorized redirect URI: `http://your-domain.com/auth/google/callback`

#### GitHub OAuth Setup:

1. Go to GitHub Settings > Developer settings > OAuth Apps
2. Click "New OAuth App"
3. Set Authorization callback URL: `http://your-domain.com/auth/github/callback`
4. Note the Client ID and Client Secret

## Available Routes

### Authentication Routes (Guest Users)

- `GET /auth/{provider}/redirect` - Redirect to OAuth provider (google|github)
- `GET /auth/{provider}/callback` - Handle OAuth callback

### Account Linking Routes (Authenticated Users)

- `GET /auth/{provider}/link` - Link OAuth provider to existing account
- `DELETE /auth/{provider}/unlink` - Unlink OAuth provider from account

## Frontend Integration

OAuth redirects are configured to send users back to your frontend application using `config('app.frontend_url')`. After authentication:

### Success Redirects

- **Login Success**: `/dashboard?success=Successfully logged in via {Provider}`
- **Account Linked**: `/settings?tab=accounts&success={Provider} account linked successfully`

### Error Redirects

- **Authentication Failed**: `/login?error=OAuth authentication failed`
- **Registration Disabled**: `/login?error=Registration is disabled`
- **Account Linking Failed**: `/settings?tab=accounts&error={Error message}`

The frontend should parse these query parameters to display appropriate messages to the user.

## Usage Examples

### Web Authentication

```html
<!-- Login buttons -->
<a href="/auth/google/redirect" class="btn btn-google">Login with Google</a>
<a href="/auth/github/redirect" class="btn btn-github">Login with GitHub</a>

<!-- Account linking (for authenticated users) -->
<a href="/auth/google/link" class="btn btn-link">Link Google Account</a>
<a href="/auth/github/link" class="btn btn-link">Link GitHub Account</a>
```

### API Authentication

```javascript
// Get redirect URL
fetch("/auth/google/redirect", {
  headers: { "X-Request-Token": "true" },
})
  .then((response) => response.json())
  .then((data) => {
    // Redirect to data.redirect_url
    window.location.href = data.redirect_url;
  });
```

## Registration Control Logic

When `REGISTRATION_ENABLED=false`:

- ✅ **Existing users** can link OAuth providers to their accounts
- ✅ **Users with same email** will have OAuth provider linked automatically
- ❌ **New users** cannot register via OAuth (will get error message)

When `REGISTRATION_ENABLED=true`:

- ✅ **All OAuth authentication flows** work normally
- ✅ **New users** can register via OAuth providers

## User Model Methods

### OAuth Status Methods

```php
$user->hasGoogleLinked();  // Check if Google is linked
$user->hasGithubLinked();  // Check if GitHub is linked
```

### OAuth Management Methods

```php
$user->linkGoogleAccount($googleId, $token, $refreshToken);
$user->linkGithubAccount($githubId, $token, $refreshToken);
$user->unlinkGoogleAccount();
$user->unlinkGithubAccount();
```

## Database Schema

The following fields have been added to the `users` table:

- `google_id` - Google OAuth user ID
- `github_id` - GitHub OAuth user ID
- `google_token` - Google access token (encrypted)
- `github_token` - GitHub access token (encrypted)
- `google_refresh_token` - Google refresh token (encrypted)
- `github_refresh_token` - GitHub refresh token (encrypted)

### Password Field

The `password` field is now **nullable** to support OAuth-only users:

- **OAuth-only users**: `password = null` (no password authentication)
- **Regular users**: `password = hashed_password` (can login with password)
- **Hybrid users**: OAuth users who set a password can use both methods

### Setting Password for OAuth Users

OAuth users have two options to create a password:

#### Option 1: Update Password Endpoint (Recommended)

```bash
PUT/PATCH /users/update-password

# For OAuth users (no old password required)
{
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}

# For regular users (old password required)
{
  "old_password": "currentpassword",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

The system automatically detects if the user has a password:

- **If `password = null`**: Old password is NOT required (OAuth user setting first password)
- **If `password != null`**: Old password IS required (regular password change)

#### Option 2: Password Reset Flow

OAuth users can also use the standard "Forgot Password" flow:

1. Request reset link: `POST /forgot-password` with their email
2. Receive email with reset link (valid 30 days)
3. Set password via reset link: `POST /reset-password`

## Security Features

1. **Protected Tokens**: OAuth tokens are hidden from serialization
2. **Unique Constraints**: Provider IDs have unique constraints
3. **Account Safety**: Users cannot unlink their only authentication method
4. **CSRF Protection**: All routes include CSRF protection
5. **Rate Limitations**: OAuth endpoints respect Laravel's rate limiting
6. **Email Verification**: OAuth users are automatically marked as verified since providers verify emails

## Automatic Avatar Import

LaraStarter automatically downloads and stores user avatars from OAuth providers during authentication to enhance the user experience.

### How It Works

When a user authenticates via OAuth (Google or GitHub), the system:

1. Checks if the user already has an avatar
2. If no avatar exists, downloads the profile picture from the OAuth provider
3. Validates the image (size, format)
4. Stores it locally in `storage/app/public/avatars/`
5. Updates the user's `avatar` field with the stored path

### When Avatars Are Imported

Avatar import happens automatically in three scenarios:

- **New User Registration**: Profile picture is downloaded when a new account is created via OAuth
- **Account Linking**: Avatar is downloaded when an existing user (without avatar) links an OAuth provider
- **Email Matching**: Avatar is downloaded when logging in with OAuth if a user with the same email exists but doesn't have an avatar

### Technical Specifications

**Validation Rules:**

- Maximum file size: 5MB
- Supported formats: JPEG, PNG, GIF, WebP
- HTTP timeout: 10 seconds
- MIME type verification required

**Storage:**

- Location: `storage/app/public/avatars/`
- Naming pattern: `avatar_{provider}_{unique_id}.{extension}`
- Accessible via: `Storage::url($user->avatar)` or `$user->avatar_url` attribute
- Requires storage symlink (automatically created during installation via `php artisan storage:link`)

**Error Handling:**

- Download failures are logged but don't block authentication
- Invalid formats or oversized images are rejected gracefully
- Network timeouts fail silently without affecting user creation
- All errors are logged for troubleshooting

### Example Response

When a user authenticates with OAuth and an avatar is imported, the user object includes:

```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "avatar": "avatars/avatar_google_abc123.jpg",
  "avatar_url": "http://your-app.com/storage/avatars/avatar_google_abc123.jpg",
  "google_id": "123456789",
  "role": {
    "id": 2,
    "name": "user"
  }
}
```

### Updating Avatars

Users can later update their avatar using the avatar management endpoints:

```bash
# Upload new avatar
PUT/PATCH /api/users/upload-avatar

# Delete avatar
DELETE /api/users/delete-avatar
```

## Testing

You can test the OAuth functionality by:

1. Setting up OAuth applications with proper redirect URIs
2. Adding the credentials to your `.env` file
3. Visiting `/auth/google/redirect` or `/auth/github/redirect`
4. Completing the OAuth flow

## Error Handling

The implementation includes comprehensive error handling for:

- Invalid OAuth states
- Provider authentication failures
- Registration disabled scenarios
- Account linking conflicts
- Missing authentication methods

All errors return appropriate HTTP status codes and user-friendly messages for both web and API requests.

## Mobile App Integration (Flutter & React Native)

### Overview

For mobile applications (Flutter, React Native, etc.), we use a **token-based authentication flow** instead of redirect-based OAuth. The mobile app uses native SDKs to obtain OAuth tokens, then sends those tokens to the backend for verification.

### How It Works

1. **Mobile app** initiates OAuth using native SDK (Google Sign-In, GitHub OAuth)
2. **Provider** returns access token (and optionally ID token for Google)
3. **Mobile app** sends token to backend API endpoint: `POST /auth/{provider}/token`
4. **Backend** verifies token with provider's API
5. **Backend** creates/authenticates user and returns Sanctum token
6. **Mobile app** stores Sanctum token for subsequent API requests

### API Endpoint

#### `POST /auth/{provider}/token`

**Supported Providers:** `google`, `github`

**Request Headers:**

```
Content-Type: application/json
```

**Request Body:**

```json
{
  "access_token": "ya29.a0AfH6SMBx...",
  "id_token": "eyJhbGciOiJSUzI1NiIs..." // Optional, recommended for Google
}
```

**Success Response (200):**

```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role_id": 2,
      "role": {
        "id": 2,
        "name": "user"
      }
    },
    "token": "1|abc123xyz..."
  },
  "message": "Login successful via Google"
}
```

**2FA Required Response (200):**

```json
{
  "success": true,
  "data": {
    "two_factor_auth_required": true,
    "email": "john@example.com",
    "message": "Please enter the 6-digit code from your Google Authenticator app"
  },
  "message": "Google 2FA verification required."
}
```

**Error Response (401):**

```json
{
  "success": false,
  "message": "Token verification failed: Invalid token"
}
```

**Rate Limit:** 6 requests per minute

### Flutter Integration

#### 1. Install Dependencies

Add to `pubspec.yaml`:

```yaml
dependencies:
  google_sign_in: ^6.1.5
  http: ^1.1.0
  flutter_secure_storage: ^9.0.0
```

For GitHub OAuth, add:

```yaml
dependencies:
  flutter_web_auth_2: ^3.0.0
```

#### 2. Google Sign-In Implementation

```dart
import 'package:google_sign_in/google_sign_in.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

class AuthService {
  final GoogleSignIn _googleSignIn = GoogleSignIn(
    scopes: ['email', 'profile'],
    // Add your Google Client ID for iOS
    // clientId: 'YOUR_IOS_CLIENT_ID.apps.googleusercontent.com',
  );

  Future<Map<String, dynamic>?> signInWithGoogle() async {
    try {
      // Sign in with Google
      final GoogleSignInAccount? googleUser = await _googleSignIn.signIn();
      if (googleUser == null) return null;

      // Get authentication
      final GoogleSignInAuthentication googleAuth =
          await googleUser.authentication;

      // Send tokens to backend
      final response = await http.post(
        Uri.parse('https://your-api.com/auth/google/token'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'access_token': googleAuth.accessToken,
          'id_token': googleAuth.idToken, // Recommended for Google
        }),
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);

        // Check for 2FA requirement
        if (data['data']['two_factor_auth_required'] == true) {
          // Navigate to 2FA screen
          return {'requires_2fa': true, 'email': data['data']['email']};
        }

        // Store the Sanctum token securely
        final token = data['data']['token'];
        await secureStorage.write(key: 'auth_token', value: token);

        return data['data'];
      } else {
        throw Exception('Authentication failed');
      }
    } catch (e) {
      print('Error signing in with Google: $e');
      return null;
    }
  }

  Future<void> signOut() async {
    await _googleSignIn.signOut();
    await secureStorage.delete(key: 'auth_token');
  }
}
```

#### 3. GitHub OAuth Implementation (Flutter)

```dart
import 'package:flutter_web_auth_2/flutter_web_auth_2.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

class AuthService {
  static const String githubClientId = 'YOUR_GITHUB_CLIENT_ID';
  static const String githubRedirectUri = 'yourapp://callback';

  Future<Map<String, dynamic>?> signInWithGitHub() async {
    try {
      // Step 1: Get authorization code
      final result = await FlutterWebAuth2.authenticate(
        url: 'https://github.com/login/oauth/authorize'
            '?client_id=$githubClientId'
            '&redirect_uri=$githubRedirectUri'
            '&scope=user:email',
        callbackUrlScheme: 'yourapp',
      );

      // Extract code from callback URL
      final code = Uri.parse(result).queryParameters['code'];
      if (code == null) throw Exception('No code returned');

      // Step 2: Exchange code for access token (via your backend)
      // Note: For security, you should exchange code on your backend
      // This is a simplified example
      final tokenResponse = await http.post(
        Uri.parse('https://github.com/login/oauth/access_token'),
        headers: {'Accept': 'application/json'},
        body: {
          'client_id': githubClientId,
          'client_secret': 'YOUR_CLIENT_SECRET', // Should be on backend!
          'code': code,
          'redirect_uri': githubRedirectUri,
        },
      );

      final tokenData = jsonDecode(tokenResponse.body);
      final accessToken = tokenData['access_token'];

      // Step 3: Send access token to your backend
      final response = await http.post(
        Uri.parse('https://your-api.com/auth/github/token'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({'access_token': accessToken}),
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        final token = data['data']['token'];
        await secureStorage.write(key: 'auth_token', value: token);
        return data['data'];
      } else {
        throw Exception('Authentication failed');
      }
    } catch (e) {
      print('Error signing in with GitHub: $e');
      return null;
    }
  }
}
```

### React Native Integration

#### 1. Install Dependencies

```bash
npm install @react-native-google-signin/google-signin
npm install react-native-app-auth
npm install @react-native-async-storage/async-storage
npm install axios
```

#### 2. Google Sign-In Implementation

```javascript
import { GoogleSignin } from "@react-native-google-signin/google-signin";
import AsyncStorage from "@react-native-async-storage/async-storage";
import axios from "axios";

// Configure Google Sign-In
GoogleSignin.configure({
  webClientId: "YOUR_WEB_CLIENT_ID.apps.googleusercontent.com",
  iosClientId: "YOUR_IOS_CLIENT_ID.apps.googleusercontent.com",
  offlineAccess: false,
});

export const signInWithGoogle = async () => {
  try {
    // Check if device supports Google Play
    await GoogleSignin.hasPlayServices();

    // Sign in
    const userInfo = await GoogleSignin.signIn();

    // Get tokens
    const tokens = await GoogleSignin.getTokens();

    // Send to backend
    const response = await axios.post(
      "https://your-api.com/auth/google/token",
      {
        access_token: tokens.accessToken,
        id_token: tokens.idToken, // Recommended for Google
      }
    );

    // Check for 2FA
    if (response.data.data.two_factor_auth_required) {
      return {
        requires2FA: true,
        email: response.data.data.email,
      };
    }

    // Store Sanctum token
    const sanctumToken = response.data.data.token;
    await AsyncStorage.setItem("auth_token", sanctumToken);

    return {
      user: response.data.data.user,
      token: sanctumToken,
    };
  } catch (error) {
    console.error("Google Sign-In Error:", error);
    throw error;
  }
};

export const signOut = async () => {
  try {
    await GoogleSignin.signOut();
    await AsyncStorage.removeItem("auth_token");
  } catch (error) {
    console.error("Sign Out Error:", error);
  }
};
```

#### 3. GitHub OAuth Implementation (React Native)

```javascript
import { authorize } from "react-native-app-auth";
import AsyncStorage from "@react-native-async-storage/async-storage";
import axios from "axios";

const githubConfig = {
  clientId: "YOUR_GITHUB_CLIENT_ID",
  clientSecret: "YOUR_GITHUB_CLIENT_SECRET",
  redirectUrl: "com.yourapp://oauth",
  scopes: ["user", "user:email"],
  serviceConfiguration: {
    authorizationEndpoint: "https://github.com/login/oauth/authorize",
    tokenEndpoint: "https://github.com/login/oauth/access_token",
  },
};

export const signInWithGitHub = async () => {
  try {
    // Get OAuth token
    const result = await authorize(githubConfig);

    // Send to backend
    const response = await axios.post(
      "https://your-api.com/auth/github/token",
      {
        access_token: result.accessToken,
      }
    );

    // Check for 2FA
    if (response.data.data.two_factor_auth_required) {
      return {
        requires2FA: true,
        email: response.data.data.email,
      };
    }

    // Store Sanctum token
    const sanctumToken = response.data.data.token;
    await AsyncStorage.setItem("auth_token", sanctumToken);

    return {
      user: response.data.data.user,
      token: sanctumToken,
    };
  } catch (error) {
    console.error("GitHub Sign-In Error:", error);
    throw error;
  }
};
```

### Using Sanctum Token for API Requests

After successful authentication, use the Sanctum token for subsequent API requests:

**Flutter:**

```dart
final token = await secureStorage.read(key: 'auth_token');
final response = await http.get(
  Uri.parse('https://your-api.com/api/user'),
  headers: {
    'Authorization': 'Bearer $token',
    'Accept': 'application/json',
  },
);
```

**React Native:**

```javascript
const token = await AsyncStorage.getItem("auth_token");
const response = await axios.get("https://your-api.com/api/user", {
  headers: {
    Authorization: `Bearer ${token}`,
    Accept: "application/json",
  },
});
```

### Security Best Practices for Mobile

1. **Never expose client secrets in mobile apps** - Use PKCE (Proof Key for Code Exchange) when possible
2. **Store tokens securely** - Use platform-specific secure storage (Keychain for iOS, Keystore for Android)
3. **Implement token refresh** - Handle token expiration gracefully
4. **Use HTTPS only** - Never send tokens over unencrypted connections
5. **Validate SSL certificates** - Implement certificate pinning for production apps
6. **Handle 2FA** - Properly redirect users to 2FA verification when required
7. **Clear tokens on logout** - Remove all stored tokens when user signs out
8. **Rate limiting awareness** - Handle 429 Too Many Requests responses appropriately

### Handling 2FA in Mobile Apps

When a user has 2FA enabled, the API will return a response indicating 2FA is required instead of a token:

```dart
// Flutter example
if (authResponse['requires_2fa'] == true) {
  // Navigate to 2FA verification screen
  Navigator.push(
    context,
    MaterialPageRoute(
      builder: (context) => TwoFactorScreen(email: authResponse['email']),
    ),
  );
}
```

Then submit the 2FA code:

```dart
final response = await http.post(
  Uri.parse('https://your-api.com/two-factor/verify'),
  headers: {'Content-Type': 'application/json'},
  body: jsonEncode({
    'email': email,
    'code': twoFactorCode,
  }),
);
```

### Troubleshooting

**Google Sign-In Issues:**

- Ensure SHA-1 fingerprints are configured in Firebase Console (Android)
- Verify bundle ID matches in Google Cloud Console (iOS)
- Check that `webClientId` matches your backend's `GOOGLE_CLIENT_ID`

**GitHub OAuth Issues:**

- Ensure redirect URI is registered in GitHub OAuth App settings
- For mobile apps, use custom URL schemes (e.g., `com.yourapp://oauth`)
- Verify callback URL scheme matches in both GitHub settings and app config

**Token Verification Failures:**

- Check that tokens haven't expired before sending to backend
- Ensure backend can reach Google/GitHub APIs (not blocked by firewall)
- Verify `GOOGLE_CLIENT_ID` in backend matches the client ID used in mobile app

### Testing Mobile OAuth Flow

You can test the mobile OAuth endpoints without building a mobile app using several methods:

#### Method 1: Using Postman/Insomnia (Quick Testing)

**Get Test Tokens:**

**For Google:**

1. Visit [Google OAuth 2.0 Playground](https://developers.google.com/oauthplayground/)
2. Click gear icon (⚙️) → Check "Use your own OAuth credentials"
3. Enter your `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`
4. Select scopes: `https://www.googleapis.com/auth/userinfo.email` and `https://www.googleapis.com/auth/userinfo.profile`
5. Click "Authorize APIs"
6. Click "Exchange authorization code for tokens"
7. Copy `access_token` and `id_token`

**For GitHub:**

1. Go to [GitHub → Settings → Developer settings → Personal access tokens](https://github.com/settings/tokens)
2. Click "Generate new token (classic)"
3. Select scopes: `user` and `user:email`
4. Generate and copy the token

**Test the Endpoint:**

```http
POST http://localhost:8000/auth/google/token
Content-Type: application/json

{
    "access_token": "ya29.a0AfH6SMBx...",
    "id_token": "eyJhbGciOiJSUzI1NiIs..."
}
```

Expected successful response:

```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "name": "Test User",
            "email": "test@example.com",
            "role": {...}
        },
        "token": "1|abc123..."
    },
    "message": "Login successful via Google"
}
```

#### Method 2: Using cURL

```bash
# Google OAuth
curl -X POST http://localhost:8000/auth/google/token \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "access_token": "ya29.a0AfH6SMBx...",
    "id_token": "eyJhbGciOiJSUzI1NiIs..."
  }'

# GitHub OAuth
curl -X POST http://localhost:8000/auth/github/token \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "access_token": "ghp_xxxxxxxxxxxxx"
  }'
```

#### Testing Checklist

**Basic Flow:**

- [ ] User creation with new Google account
- [ ] User creation with new GitHub account
- [ ] Existing user authentication
- [ ] Account linking by email match
- [ ] Token validation and return

**Error Handling:**

- [ ] Invalid access token
- [ ] Expired token
- [ ] Wrong audience (Google)
- [ ] Missing email from provider
- [ ] Network errors to provider APIs

**Configuration:**

- [ ] Registration disabled prevents new users
- [ ] Social auth disabled blocks all OAuth
- [ ] 2FA enabled requires verification

**Security:**

- [ ] Rate limiting (6 requests/minute)
- [ ] Token verification with provider
- [ ] Audience/client ID validation

#### Monitoring Logs

Check Laravel logs for detailed information:

```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log

# Filter for OAuth-related logs
tail -f storage/logs/laravel.log | grep -i "oauth\|token verification"
```

#### Common Test Scenarios

**1. New User Registration:**

```json
POST /auth/google/token
{
    "access_token": "valid_token_for_new_email"
}

Expected: 200 OK with user + token
```

**2. Existing User Login:**

```json
POST /auth/google/token
{
    "access_token": "valid_token_for_existing_email"
}

Expected: 200 OK with user + token
```

**3. User with 2FA Enabled:**

```json
POST /auth/google/token
{
    "access_token": "valid_token_for_2fa_user"
}

Expected: 200 OK with two_factor_auth_required: true
```

**4. Invalid Token:**

```json
POST /auth/google/token
{
    "access_token": "invalid_or_expired_token"
}

Expected: 401 Unauthorized
```

**5. Rate Limit Exceeded:**

```
Make 7 requests within 1 minute

Expected: 6 succeed (200), 7th returns 429 Too Many Requests
```

#### Debugging Tips

1. **Check provider credentials:**

   ```bash
   php artisan tinker
   >>> config('services.google.client_id')
   >>> config('services.github.client_id')
   ```

2. **Verify token manually:**

   ```bash
   # Google token verification
   curl "https://oauth2.googleapis.com/tokeninfo?access_token=YOUR_TOKEN"

   # GitHub token verification
   curl -H "Authorization: Bearer YOUR_TOKEN" https://api.github.com/user
   ```

3. **Test database connection:**

   ```bash
   php artisan tinker
   >>> \App\Models\User::count()
   ```

4. **Clear cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

#### Testing in Production

Before deploying to production:

1. Test with real OAuth apps (not development/localhost redirects)
2. Verify HTTPS is working properly
3. Test rate limiting behavior
4. Monitor error rates and response times
5. Test with different user scenarios (new, existing, 2FA)
6. Verify email verification flow
7. Test account linking and unlinking
