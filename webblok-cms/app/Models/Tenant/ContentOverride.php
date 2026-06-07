<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $blok_key
 * @property string $lang
 * @property string $key_path
 * @property string $value
 * @property Carbon $updated_at
 */
final class ContentOverride extends Model
{
    public $timestamps = false;

    protected $connection = 'tenant';

    protected $table = 'content_overrides';

    /** @var array<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'updated_at' => 'datetime',
        ];
    }
}
