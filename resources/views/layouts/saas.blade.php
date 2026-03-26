<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'InfiMal')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
        (function () {
            const mode = localStorage.getItem('infimal_theme') || 'light';
            if (mode === 'dark') document.documentElement.classList.add('dark');
        })();
        function toggleTheme() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('infimal_theme', isDark ? 'dark' : 'light');
        }
    </script>
</head>
<body class="h-full bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100">
<div class="min-h-screen flex">
    <aside class="w-64 bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 p-4">
        <a href="{{ route('dashboard') }}" class="font-bold text-xl text-blue-600">InfiMal</a>
        <nav class="mt-6 space-y-2 text-sm">
            <a href="{{ route('dashboard') }}" class="block">Dashboard</a>
            <a href="{{ route('campaigns.index') }}" class="block">Campaigns</a>
            <a href="{{ route('subscribers.index') }}" class="block">Subscribers</a>
            <a href="{{ route('smtp.index') }}" class="block">SMTP</a>
            <a href="{{ route('messages.index') }}" class="block">Messages</a>
        </nav>
    </aside>
    <div class="flex-1">
        <header class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 p-4 flex justify-end gap-4 items-center">
            <button onclick="toggleTheme()" class="text-sm px-3 py-1 rounded bg-blue-100 dark:bg-slate-700">Theme</button>
            <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center">{{ strtoupper(substr(auth()->user()->name ?? 'U',0,1)) }}</div>
        </header>
        <main class="p-6">@yield('content')</main>
    </div>
</div>
</body>
</html>
