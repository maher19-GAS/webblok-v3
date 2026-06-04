<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $collection_name
 * @property string $name
 * @property string $file_name
 * @property string $mime_type
 * @property string $disk
 * @property int $size
 * @property array<string, mixed> $custom_properties
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Media extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'media';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'manipulations' => 'array',
            'custom_properties' => 'array',
            'responsive_images' => 'array',
        ];
    }
}
