@extends('layouts.app')

@section('title', 'Admin · Marketplace')

@section('content')
    <h1 class="text-2xl font-bold mb-6">Marketplace</h1>

    <h2 class="text-lg font-semibold mb-3">Pending community submissions</h2>
    <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-8">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Type</th>
                    <th class="px-4 py-2">Author</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($pending as $item)
                    <tr>
                        <td class="px-4 py-2 font-medium">{{ $item->name }}</td>
                        <td class="px-4 py-2">{{ $item->type->value }}</td>
                        <td class="px-4 py-2">{{ $item->author ?? '—' }}</td>
                        <td class="px-4 py-2 text-right space-x-3">
                            <form method="POST" action="{{ route('admin.marketplace.approve', $item->id) }}" class="inline">
                                @csrf<button class="text-green-600 hover:underline">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.marketplace.reject', $item->id) }}" class="inline">
                                @csrf<button class="text-red-600 hover:underline">Reject</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">No pending submissions.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2 class="text-lg font-semibold mb-3">Live items</h2>
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Type</th>
                    <th class="px-4 py-2">Source</th>
                    <th class="px-4 py-2">Installs</th>
                    <th class="px-4 py-2 text-right">Featured</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($live as $item)
                    <tr>
                        <td class="px-4 py-2 font-medium">{{ $item->name }}</td>
                        <td class="px-4 py-2">{{ $item->type->value }}</td>
                        <td class="px-4 py-2">{{ $item->source }}</td>
                        <td class="px-4 py-2">{{ $item->install_count }}</td>
                        <td class="px-4 py-2 text-right">
                            <form method="POST" action="{{ route('admin.marketplace.feature', $item->id) }}" class="inline">
                                @csrf
                                <button class="{{ $item->is_featured ? 'text-amber-500' : 'text-slate-400' }} hover:underline">
                                    {{ $item->is_featured ? 'Featured' : 'Feature' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
