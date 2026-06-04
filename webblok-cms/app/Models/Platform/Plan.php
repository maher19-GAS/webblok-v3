<?php

declare(strict_types=1);

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $display_name
 * @property int $max_pages
 * @property int $max_bloks
 * @property int $max_locales
 * @property int $max_media_mb
 * @property int $max_exports
 * @property int $max_api_rpm
 * @property float $price_monthly
 * @property float $price_yearly
 * @property array<string, mixed>|null $features
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Plan extends Model
{
    use HasUuids;

    protected $table = 'plans';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'max_pages' => 'integer',
            'max_bloks' => 'integer',
            'max_locales' => 'integer',
            'max_media_mb' => 'integer',
            'max_exports' => 'integer',
            'max_api_rpm' => 'integer',
            'price_monthly' => 'float',
            'price_yearly' => 'float',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Tenant, $this> */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'plan_id');
    }
}
