<?php

declare(strict_types=1);

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string|null $api_key_id
 * @property string $blok_key
 * @property string $response_type
 * @property string $lang
 * @property int $requests
 * @property int|null $render_ms
 * @property string $log_date
 * @property Carbon|null $created_at
 */
final class UsageLog extends Model
{
    public $timestamps = false;

    protected $table = 'usage_logs';

    /** @var array<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requests' => 'integer',
            'render_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
