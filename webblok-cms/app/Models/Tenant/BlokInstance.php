<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\BlokSection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $page_id
 * @property string|null $parent_id
 * @property string|null $slot_name
 * @property string $blok_key
 * @property BlokSection $section
 * @property array<string, mixed> $config
 * @property array<string, array<string, mixed>> $locale_config
 * @property bool $is_visible
 * @property string|null $required_role
 * @property int $sort_order
 * @property string|null $css_classes
 * @property string|null $animation
 * @property string|null $anchor_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BlokInstance|null $parent
 * @property-read Collection<int, BlokInstance> $children
 */
final class BlokInstance extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'blok_instances';

    /** @var array<string> */
    protected $guarded = [];

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            'section' => BlokSection::class,
            'config' => 'array',
            'locale_config' => 'array',
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }

    /** @return BelongsTo<BlokInstance, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<BlokInstance, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }
}
