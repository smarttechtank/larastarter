<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use App\Notifications\VerifyEmail;
use App\Notifications\ExtendedPasswordReset;
use PragmaRX\Recovery\Recovery;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role_id',
        'two_factor_enabled',
        'avatar',
    ];

    /**
     * The attributes that should be sortable.
     *
     * @var list<string>
     */
    protected $sortable = [
        'name.asc',
        'name.desc',
        'email.asc',
        'email.desc',
        'role.asc',
        'role.desc',
        'created_at.asc',
        'created_at.desc'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret',
        'recovery_codes',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'recovery_codes' => 'array',
        ];
    }

    /**
     * The validation rules for the model.
     *
     * @var array<string, string>
     */
    public static array $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|string|max:255|email|unique:users,email',
        'phone' => 'nullable|string|max:20|regex:/^[\+]?[0-9\-\(\)\s]+$/',
        'password' => 'required|string|min:8|max:255',
        'role_id' => 'required|exists:roles,id',
    ];

    /**
     * Generate a Google 2FA secret for the user.
     *
     * @return string
     */
    public function generateGoogle2FASecret(): string
    {
        $google2fa = app('pragmarx.google2fa');
        $secret = $google2fa->generateSecretKey();

        $this->google2fa_secret = $secret;
        $this->save();

        return $secret;
    }

    /**
     * Get or generate the Google 2FA secret for the user.
     *
     * @return string
     */
    public function getGoogle2FASecret(): string
    {
        if (empty($this->google2fa_secret)) {
            return $this->generateGoogle2FASecret();
        }

        return $this->google2fa_secret;
    }

    /**
     * Get the Google 2FA QR code URL.
     *
     * @return string
     */
    public function getGoogle2FAQRCodeUrl(): string
    {
        $google2fa = app('pragmarx.google2fa');
        $companyName = config('app.name');
        $companyEmail = $this->email;
        $secret = $this->getGoogle2FASecret();

        return $google2fa->getQRCodeUrl(
            $companyName,
            $companyEmail,
            $secret
        );
    }

    /**
     * Verify the Google 2FA code.
     *
     * @param string $code
     * @return bool
     */
    public function verifyGoogle2FACode(string $code): bool
    {
        if (empty($this->google2fa_secret)) {
            return false;
        }

        $google2fa = app('pragmarx.google2fa');
        return $google2fa->verifyKey($this->google2fa_secret, $code);
    }

    /**
     * Reset the Google 2FA secret.
     *
     * @return void
     */
    public function resetGoogle2FASecret(): void
    {
        $this->google2fa_secret = null;
        $this->save();
    }

    /**
     * Generate recovery codes for the user.
     *
     * @param int $count Number of recovery codes to generate
     * @return array
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $recovery = new Recovery();
        $codes = $recovery->setCount($count)->toArray();

        // Encrypt the codes before storing
        $encryptedCodes = array_map(function ($code) {
            return [
                'code' => Crypt::encryptString($code),
                'used' => false,
                'used_at' => null
            ];
        }, $codes);

        $this->recovery_codes = $encryptedCodes;
        $this->save();

        return $codes; // Return plain codes for display to user
    }

    /**
     * Get recovery codes (decrypted).
     *
     * @return array
     */
    public function getRecoveryCodes(): array
    {
        if (empty($this->recovery_codes)) {
            return [];
        }

        return array_map(function ($codeData) {
            return [
                'code' => Crypt::decryptString($codeData['code']),
                'used' => $codeData['used'],
                'used_at' => $codeData['used_at']
            ];
        }, $this->recovery_codes);
    }

    /**
     * Get unused recovery codes.
     *
     * @return array
     */
    public function getUnusedRecoveryCodes(): array
    {
        return array_filter($this->getRecoveryCodes(), function ($codeData) {
            return !$codeData['used'];
        });
    }

    /**
     * Verify and use a recovery code.
     *
     * @param string $code
     * @return bool
     */
    public function verifyRecoveryCode(string $code): bool
    {
        if (empty($this->recovery_codes)) {
            return false;
        }

        $recoveryCodes = $this->recovery_codes;
        $codeFound = false;

        foreach ($recoveryCodes as $index => $codeData) {
            if (!$codeData['used']) {
                try {
                    $decryptedCode = Crypt::decryptString($codeData['code']);
                    if ($decryptedCode === $code) {
                        // Mark the code as used
                        $recoveryCodes[$index]['used'] = true;
                        $recoveryCodes[$index]['used_at'] = now()->toDateTimeString();
                        $this->recovery_codes = $recoveryCodes;
                        $this->save();
                        $codeFound = true;
                        break;
                    }
                } catch (\Exception $e) {
                    // Skip invalid encrypted codes
                    continue;
                }
            }
        }

        return $codeFound;
    }

    /**
     * Check if user has unused recovery codes.
     *
     * @return bool
     */
    public function hasUnusedRecoveryCodes(): bool
    {
        return count($this->getUnusedRecoveryCodes()) > 0;
    }

    /**
     * Reset all recovery codes.
     *
     * @return void
     */
    public function resetRecoveryCodes(): void
    {
        $this->recovery_codes = null;
        $this->save();
    }

    /**
     * Scope a query to filter the users.
     *
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                !array_key_exists('sort', $filters) ?? false,
                fn ($query) => $query->orderBy('created_at', 'desc')
            )
            ->when(
                $filters['sort'] ?? false,
                function ($query, $value) {
                    $sortArr = explode('.', $value);

                    // Handle relationship sorting
                    if ($sortArr[0] === 'role') {
                        return $query->join('roles', 'users.role_id', '=', 'roles.id')
                            ->select('users.*')
                            ->orderBy('roles.name', $sortArr[1]);
                    }

                    // Regular column sorting
                    $query->orderBy($sortArr[0], $sortArr[1]);
                }
            )
            ->when(!empty($filters['search']), function ($query) use ($filters) {
                $query->where('users.name', 'LIKE', "%{$filters['search']}%")
                    ->orWhere('users.email', 'LIKE', "%{$filters['search']}%")
                    ->orWhere('users.phone', 'LIKE', "%{$filters['search']}%");
            })
            ->when(
                isset($filters['roles']) && $filters['roles'],
                fn ($query) => $query->whereIn('role_id', array_map('intval', explode(',', $filters['roles'])))
            );
    }

    /**
     * Get the role that the user belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Check if the user has the given role.
     *
     * @param string $role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return $this->role?->name === $role;
    }

    /**
     * Get the avatar URL attribute.
     *
     * @return Attribute
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->avatar
                ? Storage::disk('public')->url($this->avatar)
                : null,
        );
    }

    /**
     * Send the email verification notification with option to specify API route usage.
     *
     * @param  bool  $useApiRoute
     * @return void
     */
    public function sendEmailVerificationNotification($useApiRoute = false)
    {
        $this->notify(new VerifyEmail($useApiRoute));
    }

    /**
     * Send a password reset notification with extended expiration time.
     *
     * @param  string  $token
     * @return void
     */
    public function sendExtendedPasswordResetNotification($token)
    {
        $this->notify(new ExtendedPasswordReset($token));
    }
}
