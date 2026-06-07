<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $url
 * @property list<string> $events
 * @property string|null $secret
 * @property bool $is_active
 * @property Carbon|null $last_triggered_at
 * @property int|null $last_status_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Webhook extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'webhooks';

    /** @var array<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }
}
