<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * The attributes that should be sortable.
     *
     * @var list<string>
     */
    protected $sortable = [
        'name.asc',
        'name.desc',
        'description.asc',
        'description.desc',
        'users_count.asc',
        'users_count.desc',
        'created_at.asc',
        'created_at.desc'
    ];

    /**
     * Scope a query to filter the roles.
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
                    if ($sortArr[0] === 'users_count') {
                        return $query->withCount('users')
                            ->orderBy('users_count', $sortArr[1]);
                    }

                    // Regular column sorting
                    $query->orderBy($sortArr[0], $sortArr[1]);
                }
            )
            ->when(!empty($filters['search']), function ($query) use ($filters) {
                $query->where('roles.name', 'LIKE', "%{$filters['search']}%")
                    ->orWhere('roles.description', 'LIKE', "%{$filters['search']}%");
            });
    }

    /**
     * Get the users associated with this role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
