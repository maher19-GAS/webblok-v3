@extends('layouts.app')
@section('title', 'Pages')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">Pages</h1>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl shadow divide-y">
            @forelse ($pages as $page)
                <div class="flex items-center justify-between px-5 py-3">
                    <div>
                        <div class="font-medium text-slate-800">
                            /{{ $page->full_path }}
                            @if ($page->is_homepage)
                                <span class="ml-2 text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Home</span>
                            @endif
                        </div>
                        <div class="text-xs text-slate-500 mt-0.5">
                            {{ $page->status->value }} · template: {{ $page->template }}
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <a href="{{ route('cms.builder.edit', $page->id) }}"
                           class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-3 py-1.5">Build</a>
                        <form method="POST" action="{{ route('cms.pages.publish', $page->id) }}">
                            @csrf
                            <button class="bg-slate-100 hover:bg-slate-200 rounded-lg px-3 py-1.5">Publish</button>
                        </form>
                        <form method="POST" action="{{ route('cms.pages.homepage', $page->id) }}">
                            @csrf
                            <button class="bg-slate-100 hover:bg-slate-200 rounded-lg px-3 py-1.5">Set Home</button>
                        </form>
                        <form method="POST" action="{{ route('cms.pages.destroy', $page->id) }}"
                              onsubmit="return confirm('Delete this page?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:text-red-700 rounded-lg px-2 py-1.5">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-slate-500">No pages yet. Create your first page →</div>
            @endforelse
        </div>

        <div class="bg-white rounded-xl shadow p-5 h-fit">
            <h2 class="font-semibold mb-3">New Page</h2>
            @if ($errors->any())
                <div class="mb-3 text-sm text-red-600">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('cms.pages.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700">Title</label>
                    <input name="title" required
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Slug</label>
                    <input name="slug" required placeholder="about-us"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Parent</label>
                    <select name="parent_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">— None (top level) —</option>
                        @foreach ($pages as $p)
                            <option value="{{ $p->id }}">/{{ $p->full_path }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded-lg py-2 text-sm font-medium">
                    Create &amp; Build
                </button>
            </form>
        </div>
    </div>
@endsection
