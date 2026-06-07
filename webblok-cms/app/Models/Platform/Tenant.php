<?php

declare(strict_types=1);

namespace App\Models\Platform;

use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $plan_id
 * @property string $owner_id
 * @property string $name
 * @property string $slug
 * @property string|null $domain
 * @property string $subdomain
 * @property string $database_path
 * @property string $storage_path
 * @property TenantStatus $status
 * @property Carbon|null $trial_ends_at
 * @property string $billing_email
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Plan $plan
 * @property-read User $owner
 * @property-read Collection<int, ApiKey> $apiKeys
 */
final class Tenant extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'tenants';

    /** @var array<string> */
    protected $guarded = [];

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'trial_ends_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<ApiKey, $this> */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class, 'tenant_id');
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::ACTIVE;
    }
}
