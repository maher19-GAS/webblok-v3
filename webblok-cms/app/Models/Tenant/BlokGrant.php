<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $blok_key
 * @property bool $is_enabled
 * @property array<string, mixed>|null $custom_data
 * @property Carbon $granted_at
 */
final class BlokGrant extends Model
{
    public $timestamps = false;

    protected $connection = 'tenant';

    protected $table = 'blok_grants';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'custom_data' => 'array',
            'granted_at' => 'datetime',
        ];
    }
}
