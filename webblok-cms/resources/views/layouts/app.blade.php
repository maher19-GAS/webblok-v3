<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('webblok.name', 'WebBlok CMS'))</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
    @stack('head')
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen">
    <nav class="bg-slate-900 text-white">
        <div class="max-w-7xl mx-auto px-4 h-14 flex items-center justify-between">
            <a href="{{ route('cms.dashboard') }}" class="flex items-center gap-2 font-semibold">
                <span class="inline-block w-7 h-7 rounded bg-blue-500 grid place-items-center text-sm">WB</span>
                {{ config('webblok.name', 'WebBlok CMS') }}
            </a>
            <div class="flex items-center gap-4 text-sm">
                <a href="{{ route('cms.dashboard') }}" class="hover:text-blue-300">Dashboard</a>
                <a href="{{ route('cms.pages.index') }}" class="hover:text-blue-300">Pages</a>
                <a href="{{ route('cms.export.index') }}" class="hover:text-blue-300">Export</a>
                <a href="{{ route('cms.studio.index') }}" class="hover:text-blue-300">Studio</a>
                @auth
                    @if (auth()->user()?->isSuperAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="text-amber-300 hover:text-amber-200">Admin</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-slate-300 hover:text-white">Logout</button>
                    </form>
                @endauth
            </div>
        </div>
    </nav>

    @if (session('status'))
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-2 rounded">
                {{ session('status') }}
            </div>
        </div>
    @endif

    <main class="max-w-7xl mx-auto px-4 py-6">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
