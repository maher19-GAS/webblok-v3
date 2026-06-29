@extends('layouts.guest')
@section('title', 'Create account')

@section('content')
    <h2 class="text-lg font-semibold mb-4">Create your account</h2>

    @if ($errors->any())
        <div class="mb-4 text-sm text-red-600">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required autofocus
                   class="mt-1 w-full rounded-lg border-slate-300 border px-3 py-2 focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   class="mt-1 w-full rounded-lg border-slate-300 border px-3 py-2 focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Password</label>
            <input type="password" name="password" required
                   class="mt-1 w-full rounded-lg border-slate-300 border px-3 py-2 focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Confirm Password</label>
            <input type="password" name="password_confirmation" required
                   class="mt-1 w-full rounded-lg border-slate-300 border px-3 py-2 focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded-lg py-2 font-medium">
            Create account
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-slate-500">
        Already registered? <a href="{{ route('login') }}" class="text-blue-600 hover:underline">Sign in</a>
    </p>
@endsection
