<?php

declare(strict_types=1);

// Architecture rules (Section 27) — enforce the design constraints in CI.

arch('strict types are declared everywhere')
    ->expect('App')
    ->toUseStrictTypes();

arch('Community actions are final')
    ->expect('App\Actions\Community')
    ->toBeFinal();

arch('AI actions are final')
    ->expect('App\Actions\Ai')
    ->toBeFinal();

arch('Site generator drivers implement the contract')
    ->expect('App\Services\Ai\Drivers')
    ->toImplement('App\Services\Ai\Contracts\SiteGeneratorDriver');

arch('Artifact validator is final')
    ->expect('App\Services\Community\ArtifactValidator')
    ->toBeFinal();

arch('Builder avoids Livewire and Filament')
    ->expect('App')
    ->not->toUse(['Livewire\Component', 'Filament\Panel']);

arch('Controllers are final')
    ->expect('App\Http\Controllers')
    ->toBeFinal()
    ->ignoring('App\Http\Controllers\Controller');

arch('Middleware are final')
    ->expect('App\Http\Middleware')
    ->toBeFinal();

arch('Service classes are final')
    ->expect('App\Services')
    ->classes()
    ->toBeFinal();

arch('Enums are backed enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('Models extend Eloquent')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('no debug helpers leak into the codebase')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
