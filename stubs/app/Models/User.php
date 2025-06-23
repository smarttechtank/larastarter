<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use App\Notifications\VerifyEmail;
use App\Notifications\TwoFactorCode;
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
        'two_factor_code',
        'two_factor_expires_at',
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
            'two_factor_expires_at' => 'datetime',
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
     * Generate a two-factor authentication code for the user.
     *
     * @return string
     */
    public function generateTwoFactorCode(): string
    {
        // Generate a random 6-digit code
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Save the code and set expiration time (10 minutes from now)
        $this->two_factor_code = $code;
        $this->two_factor_expires_at = now()->addMinutes(10);
        $this->save();

        return $code;
    }

    /**
     * Reset the two-factor authentication code.
     *
     * @return void
     */
    public function resetTwoFactorCode(): void
    {
        $this->two_factor_code = null;
        $this->two_factor_expires_at = null;
        $this->save();
    }

    /**
     * Verify if the provided two-factor authentication code is valid.
     *
     * @param string $code
     * @return bool
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        // Check if code matches and has not expired
        if (
            $this->two_factor_code === $code &&
            $this->two_factor_expires_at &&
            now()->lt($this->two_factor_expires_at)
        ) {

            // Reset the code after successful verification
            $this->resetTwoFactorCode();

            return true;
        }

        return false;
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
     * Send the two-factor authentication code to the user.
     *
     * @return void
     */
    public function sendTwoFactorCodeNotification(): void
    {
        $this->notify(new TwoFactorCode($this->two_factor_code));
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
}
