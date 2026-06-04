<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $page_id
 * @property string $locale
 * @property string $title
 * @property string|null $description
 * @property string|null $content
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property bool $is_indexable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Page $page
 */
final class PageLocale extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'page_locales';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_indexable' => 'boolean',
        ];
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }
}
