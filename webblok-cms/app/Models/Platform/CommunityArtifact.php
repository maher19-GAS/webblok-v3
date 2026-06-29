<?php

declare(strict_types=1);

namespace App\Models\Platform;

use App\Enums\ArtifactStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A community-authored, reviewable artifact (blok, template, site or theme).
 * Lives in the platform database; once published it is mirrored into
 * marketplace_items so the existing install pipeline works unchanged.
 *
 * @property string $id
 * @property string $author_id
 * @property string|null $author_tenant_id
 * @property string $type
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $version
 * @property string $payload
 * @property string|null $thumbnail_url
 * @property list<string>|null $tags
 * @property string|null $category
 * @property list<string> $locale_support
 * @property string $license
 * @property float $price
 * @property ArtifactStatus $status
 * @property string|null $review_notes
 * @property string|null $reviewed_by
 * @property int $install_count
 * @property int $rating_sum
 * @property int $rating_count
 * @property bool $is_verified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $published_at
 */
final class CommunityArtifact extends Model
{
    use HasUuids;

    protected $table = 'community_artifacts';

    /** @var array<string> */
    protected $guarded = [];

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            'status' => ArtifactStatus::class,
            'tags' => 'array',
            'locale_support' => 'array',
            'price' => 'float',
            'install_count' => 'integer',
            'rating_sum' => 'integer',
            'rating_count' => 'integer',
            'is_verified' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function averageRating(): float
    {
        return $this->rating_count > 0
            ? round($this->rating_sum / $this->rating_count, 2)
            : 0.0;
    }
}
