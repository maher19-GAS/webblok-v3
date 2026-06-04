<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\PageStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $parent_id
 * @property string $slug
 * @property string $full_path
 * @property PageStatus $status
 * @property string $template
 * @property bool $is_homepage
 * @property bool $requires_auth
 * @property string|null $required_role
 * @property int $sort_order
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Page|null $parent
 * @property-read Collection<int, Page> $children
 * @property-read Collection<int, PageLocale> $locales
 * @property-read Collection<int, BlokInstance> $blokInstances
 * @property-read Collection<int, PageRevision> $revisions
 */
final class Page extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'pages';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'is_homepage' => 'boolean',
            'requires_auth' => 'boolean',
            'sort_order' => 'integer',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Page, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Page, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return HasMany<PageLocale, $this> */
    public function locales(): HasMany
    {
        return $this->hasMany(PageLocale::class, 'page_id');
    }

    /** @return HasMany<BlokInstance, $this> */
    public function blokInstances(): HasMany
    {
        return $this->hasMany(BlokInstance::class, 'page_id')->orderBy('sort_order');
    }

    /** @return HasMany<PageRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class, 'page_id')->orderByDesc('revision_number');
    }

    public function locale(string $locale): ?PageLocale
    {
        return $this->locales->firstWhere('locale', $locale);
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::PUBLISHED;
    }
}
