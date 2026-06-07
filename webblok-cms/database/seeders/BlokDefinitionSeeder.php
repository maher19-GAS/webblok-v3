<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Platform\BlokDefinition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Imports blok definitions from JSON schema files in `blok_definitions/*.schema.json`.
 * Each file describes one blok type (key, category, schema, slots, etc.). This keeps
 * the catalogue declarative and version-controllable.
 */
final class BlokDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $dir = base_path('blok_definitions');

        if (! File::isDirectory($dir)) {
            return;
        }

        $files = File::glob("{$dir}/*.schema.json");

        $sort = 0;
        foreach ($files as $file) {
            if (! is_string($file)) {
                continue;
            }

            /** @var array<string, mixed> $data */
            $data = (array) json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR);

            $blokKey = isset($data['blok_key']) && is_string($data['blok_key'])
                ? $data['blok_key']
                : pathinfo($file, PATHINFO_FILENAME);
            $blokKey = str_replace('.schema', '', $blokKey);

            BlokDefinition::query()->updateOrCreate(
                ['blok_key' => $blokKey],
                [
                    'label' => $this->stringField($data, 'label', $blokKey),
                    'description' => $this->nullableStringField($data, 'description'),
                    'category' => $this->stringField($data, 'category', 'content'),
                    'icon' => $this->nullableStringField($data, 'icon'),
                    'accepts_children' => (bool) ($data['accepts_children'] ?? false),
                    'slots' => is_array($data['slots'] ?? null) ? $data['slots'] : null,
                    'schema' => is_array($data['schema'] ?? null) ? $data['schema'] : [],
                    'default_config' => is_array($data['default_config'] ?? null) ? $data['default_config'] : null,
                    'blade_component' => $this->stringField($data, 'blade_component', "bloks.content.{$blokKey}"),
                    'langs' => $this->stringList($data, 'langs', ['en']),
                    'has_skeleton' => (bool) ($data['has_skeleton'] ?? false),
                    'has_interactive' => (bool) ($data['has_interactive'] ?? false),
                    'cdn_ready' => (bool) ($data['cdn_ready'] ?? true),
                    'is_active' => (bool) ($data['is_active'] ?? true),
                    'sort_order' => $sort++,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function stringField(array $data, string $key, string $default): string
    {
        return isset($data[$key]) && is_string($data[$key]) ? $data[$key] : $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function nullableStringField(array $data, string $key): ?string
    {
        return isset($data[$key]) && is_string($data[$key]) ? $data[$key] : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $default
     * @return list<string>
     */
    private function stringList(array $data, string $key, array $default): array
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
