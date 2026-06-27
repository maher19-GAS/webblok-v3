@extends('layouts.app')

@section('title', 'Creator Studio')

@section('content')
    <h1 class="text-2xl font-bold mb-6">Creator Studio</h1>

    @if ($errors->any())
        <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-2 rounded mb-4">{{ $errors->first() }}</div>
    @endif

    <div class="grid md:grid-cols-3 gap-6">
        <div class="md:col-span-2">
            <h2 class="text-lg font-semibold mb-3">My contributions</h2>
            <div class="space-y-3">
                @forelse ($artifacts as $artifact)
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-medium">{{ $artifact->name }}</span>
                                <span class="ml-2 text-xs px-2 py-0.5 rounded bg-slate-100">{{ $artifact->type }}</span>
                                <span class="ml-1 text-xs px-2 py-0.5 rounded
                                    @class([
                                        'bg-slate-200 text-slate-600' => $artifact->status->value === 'draft',
                                        'bg-amber-100 text-amber-700' => in_array($artifact->status->value, ['submitted', 'in_review'], true),
                                        'bg-green-100 text-green-700' => in_array($artifact->status->value, ['approved', 'published'], true),
                                        'bg-red-100 text-red-700' => $artifact->status->value === 'rejected',
                                    ])">
                                    {{ $artifact->status->value }}
                                </span>
                            </div>
                            @if ($artifact->status->value === 'draft' || $artifact->status->value === 'rejected')
                                <form method="POST" action="{{ route('cms.studio.submit', $artifact->id) }}">
                                    @csrf
                                    <button class="text-blue-600 text-sm hover:underline">Submit for review</button>
                                </form>
                            @endif
                        </div>
                        @if ($artifact->review_notes)
                            <p class="text-sm text-slate-500 mt-2"><strong>Reviewer:</strong> {{ $artifact->review_notes }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-slate-400">You haven't created any artifacts yet.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-5">
            <h2 class="font-semibold mb-3">New artifact</h2>
            <form method="POST" action="{{ route('cms.studio.store') }}" class="space-y-3 text-sm">
                @csrf
                <div>
                    <label class="block text-slate-600 mb-1">Name</label>
                    <input name="name" required class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-slate-600 mb-1">Type</label>
                    <select name="type" class="w-full border rounded px-3 py-2">
                        <option value="blok">Blok</option>
                        <option value="template">Template</option>
                        <option value="site">Site</option>
                        <option value="theme">Theme</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-600 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full border rounded px-3 py-2"></textarea>
                </div>
                <div>
                    <label class="block text-slate-600 mb-1">Payload (JSON)</label>
                    <textarea name="payload" rows="6" required class="w-full border rounded px-3 py-2 font-mono text-xs" placeholder='{"blok_key":"my_blok","label":"My Blok","schema":{},"template":"{{ field.title }}"}'></textarea>
                    <p class="text-xs text-slate-400 mt-1">No PHP/scripts. Validated against the contribution sandbox on submit.</p>
                </div>
                <button class="w-full bg-blue-600 text-white rounded py-2 font-medium hover:bg-blue-700">Save draft</button>
            </form>
        </div>
    </div>
@endsection
