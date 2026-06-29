<?php

declare(strict_types=1);

use App\Models\Platform\BlokDefinition;
use App\Services\BlokRenderer;
use Illuminate\Support\Str;

beforeEach(function (): void {
    BlokDefinition::query()->create([
        'id' => (string) Str::uuid(),
        'blok_key' => 'heading',
        'label' => 'Heading',
        'category' => 'content',
        'accepts_children' => false,
        'schema' => [],
        'blade_component' => 'bloks.content.heading',
        'langs' => ['en'],
        'is_active' => true,
        'sort_order' => 0,
    ]);
});

it('renders a known blok through its Blade view', function (): void {
    $renderer = app(BlokRenderer::class);

    $html = $renderer->render('heading', ['text' => 'Hello World', 'level' => 'h2']);

    expect($html)->toContain('Hello World')
        ->and($html)->toContain('<h2');
});

it('escapes user content in bloks', function (): void {
    $renderer = app(BlokRenderer::class);

    $html = $renderer->render('heading', ['text' => '<script>x</script>']);

    expect($html)->not->toContain('<script>x</script>')
        ->and($html)->toContain('&lt;script&gt;');
});

it('falls back gracefully for an unknown blok', function (): void {
    $renderer = app(BlokRenderer::class);

    $html = $renderer->render('does_not_exist', ['foo' => 'bar']);

    expect($html)->toContain('webblok-blok');
});
