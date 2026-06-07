<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $page_id
 * @property string $locale
 * @property string|null $referrer
 * @property string|null $country_code
 * @property Carbon $viewed_at
 */
final class PageView extends Model
{
    public $timestamps = false;

    protected $connection = 'tenant';

    protected $table = 'page_views';

    /** @var array<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }
}
