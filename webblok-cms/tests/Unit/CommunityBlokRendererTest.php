<?php

declare(strict_types=1);

use App\Services\Community\CommunityBlokRenderer;

it('interpolates and escapes fields', function (): void {
    $renderer = new CommunityBlokRenderer;

    $html = $renderer->render('<h1>{{ field.title }}</h1>', ['title' => '<b>Hi</b>']);

    expect($html)->toContain('&lt;b&gt;Hi&lt;/b&gt;')
        ->and($html)->not->toContain('<b>Hi</b>');
});

it('renders loops over a list field', function (): void {
    $renderer = new CommunityBlokRenderer;
    $template = '{% for item in field.items %}<li>{{ item.name }}</li>{% endfor %}';

    $html = $renderer->render($template, ['items' => [['name' => 'A'], ['name' => 'B']]]);

    expect($html)->toContain('<li>A</li>')
        ->and($html)->toContain('<li>B</li>');
});

it('renders conditionals', function (): void {
    $renderer = new CommunityBlokRenderer;
    $template = '{% if field.show %}<p>visible</p>{% endif %}';

    expect($renderer->render($template, ['show' => true]))->toContain('visible')
        ->and($renderer->render($template, ['show' => false]))->not->toContain('visible');
});

it('scopes output to the blok instance', function (): void {
    $renderer = new CommunityBlokRenderer;

    $html = $renderer->render('x', [], 'abc123');

    expect($html)->toContain('wb-c-abc123');
});
