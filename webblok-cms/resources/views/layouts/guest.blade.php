<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('webblok.name', 'WebBlok CMS'))</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen grid place-items-center">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <div class="inline-block w-12 h-12 rounded-lg bg-blue-600 text-white grid place-items-center text-lg font-bold">WB</div>
            <h1 class="mt-3 text-xl font-semibold text-slate-800">{{ config('webblok.name', 'WebBlok CMS') }}</h1>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            @yield('content')
        </div>
    </div>
</body>
</html>
