@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">Your Sites</h1>
        <a href="{{ route('cms.pages.index') }}"
           class="bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg px-4 py-2">Manage Pages</a>
    </div>

    @if ($tenants->isEmpty())
        <div class="bg-white rounded-xl shadow p-8 text-center text-slate-500">
            <p class="text-lg">You don't have any sites yet.</p>
            <p class="text-sm mt-2">A site (tenant) is provisioned for you by an administrator or via the API.</p>
        </div>
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($tenants as $tenant)
                <div class="bg-white rounded-xl shadow p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-slate-800">{{ $tenant->name }}</h2>
                        <span class="text-xs px-2 py-0.5 rounded-full
                            {{ $tenant->status->value === 'active' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ $tenant->status->label() }}
                        </span>
                    </div>
                    <p class="text-sm text-slate-500 mt-1">{{ $tenant->subdomain }}.{{ config('webblok.base_domain') }}</p>
                    <div class="mt-4 flex gap-2 text-sm">
                        <a href="{{ route('cms.pages.index') }}?tenant={{ $tenant->slug }}"
                           class="flex-1 text-center bg-slate-100 hover:bg-slate-200 rounded-lg py-1.5">Open</a>
                        <a href="{{ route('cms.export.index') }}?tenant={{ $tenant->slug }}"
                           class="flex-1 text-center bg-slate-100 hover:bg-slate-200 rounded-lg py-1.5">Export</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
