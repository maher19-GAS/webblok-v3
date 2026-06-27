@extends('layouts.app')

@section('title', 'Admin · Tenants')

@section('content')
    <h1 class="text-2xl font-bold mb-6">Tenants</h1>

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Subdomain</th>
                    <th class="px-4 py-2">Owner</th>
                    <th class="px-4 py-2">Plan</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($tenants as $tenant)
                    <tr>
                        <td class="px-4 py-2 font-medium">{{ $tenant->name }}</td>
                        <td class="px-4 py-2 text-slate-500">{{ $tenant->subdomain }}</td>
                        <td class="px-4 py-2">{{ $tenant->owner?->email ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $tenant->plan?->display_name ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-0.5 rounded {{ $tenant->status->value === 'active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $tenant->status->value }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            @if ($tenant->status->value === 'suspended')
                                <form method="POST" action="{{ route('admin.tenants.reactivate', $tenant->id) }}" class="inline">
                                    @csrf
                                    <button class="text-green-600 hover:underline">Reactivate</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.tenants.suspend', $tenant->id) }}" class="inline">
                                    @csrf
                                    <button class="text-red-600 hover:underline">Suspend</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tenants->links() }}</div>
@endsection
