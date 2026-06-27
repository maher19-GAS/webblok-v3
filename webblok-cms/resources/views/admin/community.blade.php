@extends('layouts.app')

@section('title', 'Admin · Community Review')

@section('content')
    <h1 class="text-2xl font-bold mb-6">Community review queue</h1>

    @if ($errors->any())
        <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-2 rounded mb-4">{{ $errors->first() }}</div>
    @endif

    <h2 class="text-lg font-semibold mb-3">Awaiting review</h2>
    <div class="space-y-4 mb-8">
        @forelse ($queue as $artifact)
            <div class="bg-white rounded-lg shadow-sm p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="font-semibold">{{ $artifact->name }}
                            <span class="ml-2 text-xs px-2 py-0.5 rounded bg-slate-100">{{ $artifact->type }}</span>
                            <span class="ml-1 text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-700">{{ $artifact->status->value }}</span>
                        </div>
                        <div class="text-sm text-slate-500 mt-1">by {{ $artifact->author?->name ?? 'Unknown' }} · v{{ $artifact->version }}</div>
                        @if ($artifact->description)
                            <p class="text-sm mt-2">{{ $artifact->description }}</p>
                        @endif
                    </div>
                </div>
                <details class="mt-3">
                    <summary class="text-sm text-blue-600 cursor-pointer">Inspect payload (sandboxed)</summary>
                    <pre class="mt-2 text-xs bg-slate-50 p-3 rounded overflow-x-auto">{{ json_encode(json_decode($artifact->payload), JSON_PRETTY_PRINT) }}</pre>
                </details>
                <form method="POST" action="{{ route('admin.community.approve', $artifact->id) }}" class="mt-3 flex gap-2 items-center">
                    @csrf
                    <input name="notes" placeholder="Review notes (optional)" class="flex-1 border rounded px-3 py-1.5 text-sm">
                    <button class="bg-green-600 text-white px-4 py-1.5 rounded text-sm hover:bg-green-700">Approve</button>
                </form>
                <form method="POST" action="{{ route('admin.community.reject', $artifact->id) }}" class="mt-2">
                    @csrf
                    <button class="text-red-600 text-sm hover:underline">Reject</button>
                </form>
            </div>
        @empty
            <p class="text-slate-400">Nothing awaiting review.</p>
        @endforelse
    </div>

    <h2 class="text-lg font-semibold mb-3">Approved (ready to publish)</h2>
    <div class="space-y-3">
        @forelse ($approved as $artifact)
            <div class="bg-white rounded-lg shadow-sm p-4 flex items-center justify-between">
                <div>
                    <span class="font-medium">{{ $artifact->name }}</span>
                    <span class="ml-2 text-xs px-2 py-0.5 rounded bg-green-100 text-green-700">approved</span>
                </div>
                <form method="POST" action="{{ route('admin.community.publish', $artifact->id) }}">
                    @csrf
                    <button class="bg-blue-600 text-white px-4 py-1.5 rounded text-sm hover:bg-blue-700">Publish to marketplace</button>
                </form>
            </div>
        @empty
            <p class="text-slate-400">No approved artifacts pending publish.</p>
        @endforelse
    </div>
@endsection
