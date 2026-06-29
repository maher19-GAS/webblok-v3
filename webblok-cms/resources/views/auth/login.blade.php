@extends('layouts.guest')
@section('title', 'Sign in')

@section('content')
    <h2 class="text-lg font-semibold mb-4">Sign in to your account</h2>

    @if ($errors->any())
        <div class="mb-4 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="mt-1 w-full rounded-lg border-slate-300 border px-3 py-2 focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Password</label>
            <input type="password" name="password" required
                   class="mt-1 w-full rounded-lg border-slate-300 border px-3 py-2 focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded-lg py-2 font-medium">
            Sign in
        </button>
    </form>

    @if (config('auth_driver.driver') === 'breeze')
        <p class="mt-4 text-center text-sm text-slate-500">
            No account? <a href="{{ route('register') }}" class="text-blue-600 hover:underline">Register</a>
        </p>
    @endif
@endsection
