<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MarketplaceItemType;
use App\Models\Platform\MarketplaceItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Registers official marketplace items (themes + templates) by scanning the
 * `marketplace/official/{themes,templates}` directories for `manifest.json`
 * descriptors. Idempotent: re-running updates existing records by slug.
 */
final class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        $this->importType(MarketplaceItemType::THEME, base_path('marketplace/official/themes'));
        $this->importType(MarketplaceItemType::TEMPLATE, base_path('marketplace/official/templates'));
    }

    private function importType(MarketplaceItemType $type, string $dir): void
    {
        if (! File::isDirectory($dir)) {
            return;
        }

        foreach (File::directories($dir) as $itemDir) {
            if (! is_string($itemDir)) {
                continue;
            }

            $manifestPath = "{$itemDir}/manifest.json";

            if (! File::exists($manifestPath)) {
                continue;
            }

            /** @var array<string, mixed> $manifest */
            $manifest = (array) json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);

            $name = isset($manifest['name']) && is_string($manifest['name'])
                ? $manifest['name']
                : basename($itemDir);
            $slug = Str::slug($name);

            MarketplaceItem::query()->updateOrCreate(
                ['slug' => $slug, 'type' => $type->value],
                [
                    'source' => 'official',
                    'name' => $name,
                    'description' => isset($manifest['description']) && is_string($manifest['description']) ? $manifest['description'] : null,
                    'author' => isset($manifest['author']) && is_string($manifest['author']) ? $manifest['author'] : 'WebBlok',
                    'version' => isset($manifest['version']) && is_string($manifest['version']) ? $manifest['version'] : '1.0.0',
                    'thumbnail_url' => isset($manifest['thumbnail']) && is_string($manifest['thumbnail']) ? $manifest['thumbnail'] : null,
                    'preview_url' => isset($manifest['preview']) && is_string($manifest['preview']) ? $manifest['preview'] : null,
                    'local_path' => $itemDir,
                    'tags' => $this->stringList($manifest, 'tags'),
                    'category' => isset($manifest['category']) && is_string($manifest['category']) ? $manifest['category'] : null,
                    'locale_support' => $this->stringList($manifest, 'locales', ['en']),
                    'price' => 0.0,
                    'install_count' => 0,
                    'is_featured' => (bool) ($manifest['featured'] ?? false),
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $default
     * @return list<string>
     */
    private function stringList(array $data, string $key, array $default = []): array
    {
        if (! isset($data[$key]) || ! is_array($data[$key])) {
            return $default;
        }

        $result = [];
        foreach ($data[$key] as $value) {
            if (is_string($value)) {
                $result[] = $value;
            }
        }

        return $result === [] ? $default : $result;
    }
}
