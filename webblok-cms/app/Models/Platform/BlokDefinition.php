<?php

declare(strict_types=1);

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $blok_key
 * @property string $label
 * @property string|null $description
 * @property string $category
 * @property int|null $chapter
 * @property int|null $volume
 * @property string|null $icon
 * @property bool $accepts_children
 * @property array<int, array<string, mixed>>|null $slots
 * @property array<string, mixed> $schema
 * @property array<string, mixed>|null $default_config
 * @property string $blade_component
 * @property list<string> $langs
 * @property bool $has_skeleton
 * @property bool $has_interactive
 * @property bool $cdn_ready
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class BlokDefinition extends Model
{
    use HasUuids;

    protected $table = 'blok_definitions';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'accepts_children' => 'boolean',
            'slots' => 'array',
            'schema' => 'array',
            'default_config' => 'array',
            'langs' => 'array',
            'tags' => 'array',
            'has_skeleton' => 'boolean',
            'has_interactive' => 'boolean',
            'cdn_ready' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'chapter' => 'integer',
            'volume' => 'integer',
        ];
    }
}
