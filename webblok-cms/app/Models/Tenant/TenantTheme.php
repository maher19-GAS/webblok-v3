<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $item_slug
 * @property bool $is_active
 * @property array<string, mixed>|null $custom_vars
 * @property Carbon $installed_at
 * @property Carbon|null $updated_at
 */
final class TenantTheme extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $connection = 'tenant';

    protected $table = 'tenant_themes';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'custom_vars' => 'array',
            'installed_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
