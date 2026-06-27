@extends('layouts.app')
@section('title', 'Static Export')

@section('content')
    <h1 class="text-2xl font-semibold mb-6">Static Site Export</h1>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl shadow divide-y">
            @forelse ($exports as $export)
                <div class="flex items-center justify-between px-5 py-3">
                    <div>
                        <div class="font-medium text-slate-800">{{ $export->id }}</div>
                        <div class="text-xs text-slate-500">
                            {{ $export->status->label() }} ·
                            {{ $export->created_at?->diffForHumans() }} ·
                            {{ $export->zip_size ? number_format($export->zip_size / 1024, 1).' KB' : '—' }}
                        </div>
                    </div>
                    @if ($export->status->value === 'complete')
                        <a href="{{ route('cms.export.download', $export->id) }}"
                           class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-3 py-1.5 text-sm">Download ZIP</a>
                    @else
                        <span class="text-sm text-slate-400">{{ $export->status->label() }}</span>
                    @endif
                </div>
            @empty
                <div class="px-5 py-8 text-center text-slate-500">No exports yet.</div>
            @endforelse
        </div>

        <div class="bg-white rounded-xl shadow p-5 h-fit">
            <h2 class="font-semibold mb-3">New Export</h2>
            <form method="POST" action="{{ route('cms.export.store') }}" class="space-y-3">
                @csrf
                <p class="text-sm text-slate-500">
                    Builds a portable static bundle (one HTML file per page per locale) plus
                    <code>sitemap.xml</code> and the live-data API bridge.
                </p>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="include_api_bridge" value="1" checked>
                    Include live-data API bridge
                </label>
                <button class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded-lg py-2 text-sm font-medium">
                    Start Export
                </button>
            </form>
        </div>
    </div>
@endsection
