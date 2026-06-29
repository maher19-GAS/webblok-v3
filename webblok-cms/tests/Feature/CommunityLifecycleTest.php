<?php

declare(strict_types=1);

use App\Actions\Community\PublishArtifact;
use App\Actions\Community\ReviewArtifact;
use App\Actions\Community\SubmitArtifact;
use App\Enums\ArtifactStatus;
use App\Exceptions\InvalidArtifactException;
use App\Models\Platform\CommunityArtifact;
use App\Models\Platform\MarketplaceItem;
use App\Models\Platform\User;
use Illuminate\Support\Str;

function makeAuthor(): User
{
    return User::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Author',
        'email' => Str::lower(Str::random(8)).'@example.test',
        'password' => bcrypt('secret'),
        'role' => 'tenant_owner',
    ]);
}

function makeDraft(string $payload): CommunityArtifact
{
    return CommunityArtifact::query()->create([
        'id' => (string) Str::uuid(),
        'author_id' => makeAuthor()->id,
        'type' => 'blok',
        'name' => 'My Blok',
        'slug' => 'my-blok-'.Str::lower(Str::random(5)),
        'version' => '1.0.0',
        'payload' => $payload,
        'locale_support' => ['en'],
        'license' => 'free',
        'price' => 0.0,
        'status' => ArtifactStatus::DRAFT,
    ]);
}

$validPayload = json_encode([
    'blok_key' => 'my_blok',
    'label' => 'My Blok',
    'schema' => ['steps' => []],
    'template' => '{{ field.title }}',
], JSON_THROW_ON_ERROR);

it('takes an artifact through the full lifecycle to the marketplace', function () use ($validPayload): void {
    $artifact = makeDraft($validPayload);

    app(SubmitArtifact::class)->execute($artifact);
    expect($artifact->fresh()?->status)->toBe(ArtifactStatus::IN_REVIEW);

    $reviewer = makeAuthor();
    app(ReviewArtifact::class)->approve($artifact, $reviewer->id, 'looks good');
    expect($artifact->fresh()?->status)->toBe(ArtifactStatus::APPROVED);

    app(PublishArtifact::class)->execute($artifact);
    expect($artifact->fresh()?->status)->toBe(ArtifactStatus::PUBLISHED);

    expect(MarketplaceItem::query()->where('slug', $artifact->slug)->where('source', 'community')->exists())
        ->toBeTrue();
});

it('blocks submission of an unsafe artifact', function (): void {
    $artifact = makeDraft('{"blok_key":"x","label":"x","schema":{},"template":"<script>x</script>"}');

    app(SubmitArtifact::class)->execute($artifact);
})->throws(InvalidArtifactException::class);

it('cannot publish an artifact that was not approved', function () use ($validPayload): void {
    $artifact = makeDraft($validPayload);

    app(PublishArtifact::class)->execute($artifact);
})->throws(InvalidArtifactException::class);
