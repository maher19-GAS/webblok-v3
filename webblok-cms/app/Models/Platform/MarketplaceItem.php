<?php

declare(strict_types=1);

namespace App\Models\Platform;

use App\Enums\MarketplaceItemType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property MarketplaceItemType $type
 * @property string $source
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $author
 * @property string $version
 * @property string|null $thumbnail_url
 * @property string|null $preview_url
 * @property string|null $download_url
 * @property string|null $local_path
 * @property list<string>|null $tags
 * @property string|null $category
 * @property list<string> $locale_support
 * @property float $price
 * @property int $install_count
 * @property float|null $rating
 * @property bool $is_featured
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class MarketplaceItem extends Model
{
    use HasUuids;

    protected $table = 'marketplace_items';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            'type' => MarketplaceItemType::class,
            'tags' => 'array',
            'locale_support' => 'array',
            'price' => 'float',
            'install_count' => 'integer',
            'rating' => 'float',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
