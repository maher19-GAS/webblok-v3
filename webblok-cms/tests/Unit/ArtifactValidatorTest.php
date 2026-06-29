<?php

declare(strict_types=1);

use App\Exceptions\InvalidArtifactException;
use App\Models\Platform\CommunityArtifact;
use App\Services\Community\ArtifactValidator;

function makeArtifact(string $type, string $payload): CommunityArtifact
{
    $artifact = new CommunityArtifact;
    $artifact->type = $type;
    $artifact->payload = $payload;

    return $artifact;
}

it('accepts a valid blok payload', function (): void {
    $validator = new ArtifactValidator;
    $payload = json_encode([
        'blok_key' => 'my_blok',
        'label' => 'My Blok',
        'schema' => ['steps' => []],
        'template' => '{{ field.title }}',
    ], JSON_THROW_ON_ERROR);

    $validator->assertValid(makeArtifact('blok', $payload));

    expect(true)->toBeTrue();
});

it('rejects payloads containing PHP', function (): void {
    $validator = new ArtifactValidator;
    $payload = '{"blok_key":"x","label":"x","schema":{},"template":"<?php echo 1; ?>"}';

    $validator->assertValid(makeArtifact('blok', $payload));
})->throws(InvalidArtifactException::class);

it('rejects payloads containing script tags', function (): void {
    $validator = new ArtifactValidator;
    $payload = '{"blok_key":"x","label":"x","schema":{},"template":"<script>alert(1)</script>"}';

    $validator->assertValid(makeArtifact('blok', $payload));
})->throws(InvalidArtifactException::class);

it('rejects inline event handlers', function (): void {
    $validator = new ArtifactValidator;
    $payload = '{"blok_key":"x","label":"x","schema":{},"template":"<div onclick=\"x()\"></div>"}';

    $validator->assertValid(makeArtifact('blok', $payload));
})->throws(InvalidArtifactException::class);

it('rejects invalid JSON', function (): void {
    $validator = new ArtifactValidator;

    $validator->assertValid(makeArtifact('blok', 'not json'));
})->throws(InvalidArtifactException::class);

it('rejects a blok missing required fields', function (): void {
    $validator = new ArtifactValidator;

    $validator->assertValid(makeArtifact('blok', '{"blok_key":"x"}'));
})->throws(InvalidArtifactException::class);

it('requires css_variables on a theme', function (): void {
    $validator = new ArtifactValidator;

    $validator->assertValid(makeArtifact('theme', '{"name":"t"}'));
})->throws(InvalidArtifactException::class);
