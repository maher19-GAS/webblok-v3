<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\ExportStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property ExportStatus $status
 * @property list<string> $locale_set
 * @property bool $include_api_bridge
 * @property string|null $zip_path
 * @property int|null $zip_size
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class StaticExport extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'static_exports';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            'status' => ExportStatus::class,
            'locale_set' => 'array',
            'include_api_bridge' => 'boolean',
            'zip_size' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
