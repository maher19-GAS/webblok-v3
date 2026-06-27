@extends('layouts.app')

@section('title', 'Admin · Dashboard')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Platform overview</h1>
        <div class="flex gap-3 text-sm">
            <a href="{{ route('admin.tenants.index') }}" class="text-blue-600 hover:underline">Tenants</a>
            <a href="{{ route('admin.plans.index') }}" class="text-blue-600 hover:underline">Plans</a>
            <a href="{{ route('admin.marketplace.index') }}" class="text-blue-600 hover:underline">Marketplace</a>
            <a href="{{ route('admin.community.review') }}" class="text-blue-600 hover:underline">Review Queue</a>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        @foreach ([['Tenants', $tenantCount], ['Users', $userCount], ['Active plans', $planCount], ['Marketplace items', $marketplaceCount]] as [$label, $value])
            <div class="bg-white rounded-lg shadow-sm p-5">
                <div class="text-3xl font-bold">{{ $value }}</div>
                <div class="text-slate-500 text-sm mt-1">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    <h2 class="text-lg font-semibold mb-3">Recent tenants</h2>
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Owner</th>
                    <th class="px-4 py-2">Plan</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($recentTenants as $tenant)
                    <tr>
                        <td class="px-4 py-2 font-medium">{{ $tenant->name }}</td>
                        <td class="px-4 py-2">{{ $tenant->owner?->email ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $tenant->plan?->display_name ?? '—' }}</td>
                        <td class="px-4 py-2"><span class="px-2 py-0.5 rounded bg-slate-100">{{ $tenant->status->value }}</span></td>
                        <td class="px-4 py-2 text-slate-500">{{ $tenant->created_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">No tenants yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
