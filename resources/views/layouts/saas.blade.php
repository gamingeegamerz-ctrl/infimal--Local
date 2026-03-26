<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'InfiMal')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-slate-50 text-slate-900 dark:bg-slate-900 dark:text-slate-100 transition-colors duration-200">
<div class="min-h-full flex">
    <aside class="w-64 bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 p-4">
        <h1 class="text-xl font-bold text-blue-600 mb-6">InfiMal</h1>
        <nav class="space-y-2 text-sm">
            <a class="block px-3 py-2 rounded hover:bg-blue-50 dark:hover:bg-slate-700" href="{{ route('dashboard') }}">Dashboard</a>
            <a class="block px-3 py-2 rounded hover:bg-blue-50 dark:hover:bg-slate-700" href="{{ route('campaigns.index') }}">Campaigns</a>
            <a class="block px-3 py-2 rounded hover:bg-blue-50 dark:hover:bg-slate-700" href="{{ route('subscribers.index') }}">Subscribers</a>
            <a class="block px-3 py-2 rounded hover:bg-blue-50 dark:hover:bg-slate-700" href="{{ route('messages.index') }}">Messages</a>
            <a class="block px-3 py-2 rounded hover:bg-blue-50 dark:hover:bg-slate-700" href="{{ route('smtp.index') }}">SMTP</a>
        </nav>
    </aside>
    <div class="flex-1">
        <header class="h-16 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 px-6 flex items-center justify-between">
            <h2 class="font-semibold">@yield('page-title', 'Dashboard')</h2>
            <div class="flex items-center gap-3">
                <button onclick="toggleDarkMode()" class="px-3 py-1 rounded bg-slate-100 dark:bg-slate-700 text-xs">Theme</button>
                <div class="w-8 h-8 rounded-full bg-blue-600 text-white grid place-items-center text-xs">{{ strtoupper(substr(auth()->user()->name ?? 'U',0,1)) }}</div>
            </div>
        </header>
        <main class="p-6">@yield('content')</main>
    </div>
</div>
<script>
(function () {
    const mode = localStorage.getItem('infimal.dark_mode');
    if (mode === '1') document.documentElement.classList.add('dark');
})();
function toggleDarkMode(){
    document.documentElement.classList.toggle('dark');
    localStorage.setItem('infimal.dark_mode', document.documentElement.classList.contains('dark') ? '1' : '0');
}
</script>
</body>
</html>
