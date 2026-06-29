<?php

declare(strict_types=1);

namespace App\Actions\Community;

use App\Enums\ArtifactStatus;
use App\Enums\MarketplaceItemType;
use App\Exceptions\InvalidArtifactException;
use App\Models\Platform\CommunityArtifact;
use App\Models\Platform\MarketplaceItem;
use Illuminate\Support\Str;

/**
 * Publishes an approved artifact and mirrors it into marketplace_items with
 * source = 'community', so the existing MarketplaceService install pipeline
 * works unchanged.
 */
final class PublishArtifact
{
    public function execute(CommunityArtifact $artifact): void
    {
        if (! $artifact->status->canTransitionTo(ArtifactStatus::PUBLISHED)) {
            throw new InvalidArtifactException('Artifact is not approved for publishing.');
        }

        $artifact->status = ArtifactStatus::PUBLISHED;
        $artifact->published_at = now();
        $artifact->save();

        // Bloks install as part of templates; map their marketplace type accordingly.
        $type = $artifact->type === 'blok'
            ? MarketplaceItemType::TEMPLATE->value
            : $this->mapType($artifact->type);

        MarketplaceItem::query()->updateOrCreate(
            ['slug' => $artifact->slug, 'type' => $type],
            [
                'id' => (string) Str::uuid(),
                'source' => 'community',
                'name' => $artifact->name,
                'description' => $artifact->description,
                'author' => $artifact->author?->name,
                'version' => $artifact->version,
                'thumbnail_url' => $artifact->thumbnail_url,
                'tags' => $artifact->tags,
                'category' => $artifact->category,
                'locale_support' => $artifact->locale_support,
                'price' => $artifact->price,
                'install_count' => $artifact->install_count ?? 0,
                'is_active' => true,
            ],
        );
    }

    private function mapType(string $type): string
    {
        return match ($type) {
            'theme' => MarketplaceItemType::THEME->value,
            default => MarketplaceItemType::TEMPLATE->value,
        };
    }
}
