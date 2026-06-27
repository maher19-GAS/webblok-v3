@extends('layouts.app')

@section('title', 'Admin · Plans')

@section('content')
    <h1 class="text-2xl font-bold mb-6">Plans</h1>

    @if ($errors->any())
        <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-2 rounded mb-4">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid md:grid-cols-3 gap-6">
        <div class="md:col-span-2 bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-2">Plan</th>
                        <th class="px-4 py-2">Monthly</th>
                        <th class="px-4 py-2">Pages</th>
                        <th class="px-4 py-2">Bloks</th>
                        <th class="px-4 py-2">API rpm</th>
                        <th class="px-4 py-2 text-right">Active</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($plans as $plan)
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $plan->display_name }}</td>
                            <td class="px-4 py-2">${{ number_format($plan->price_monthly, 2) }}</td>
                            <td class="px-4 py-2">{{ $plan->max_pages }}</td>
                            <td class="px-4 py-2">{{ $plan->max_bloks }}</td>
                            <td class="px-4 py-2">{{ $plan->max_api_rpm }}</td>
                            <td class="px-4 py-2 text-right">
                                <form method="POST" action="{{ route('admin.plans.toggle', $plan->id) }}" class="inline">
                                    @csrf
                                    <button class="{{ $plan->is_active ? 'text-green-600' : 'text-slate-400' }} hover:underline">
                                        {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-5">
            <h2 class="font-semibold mb-3">New plan</h2>
            <form method="POST" action="{{ route('admin.plans.store') }}" class="space-y-3 text-sm">
                @csrf
                <div>
                    <label class="block text-slate-600 mb-1">Display name</label>
                    <input name="display_name" required class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-slate-600 mb-1">Monthly price</label>
                    <input name="price_monthly" type="number" step="0.01" value="0" required class="w-full border rounded px-3 py-2">
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-slate-600 mb-1">Pages</label>
                        <input name="max_pages" type="number" value="10" required class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-slate-600 mb-1">Bloks</label>
                        <input name="max_bloks" type="number" value="100" required class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-slate-600 mb-1">API rpm</label>
                        <input name="max_api_rpm" type="number" value="60" required class="w-full border rounded px-3 py-2">
                    </div>
                </div>
                <button class="w-full bg-blue-600 text-white rounded py-2 font-medium hover:bg-blue-700">Create plan</button>
            </form>
        </div>
    </div>
@endsection
