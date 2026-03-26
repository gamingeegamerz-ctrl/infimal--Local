@extends('layouts.saas')
@section('title','Dashboard')
@section('page-title','Dashboard Analytics')
@section('content')
<div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
    <div class="bg-white dark:bg-slate-800 p-4 rounded"><p>Total Campaigns</p><p class="text-2xl font-bold">{{ $campaignsTotal ?? 0 }}</p></div>
    <div class="bg-white dark:bg-slate-800 p-4 rounded"><p>Total Subscribers</p><p class="text-2xl font-bold">{{ $totalSubscribers ?? 0 }}</p></div>
    <div class="bg-white dark:bg-slate-800 p-4 rounded"><p>Emails Sent</p><p class="text-2xl font-bold">{{ $totalEmailsSent ?? 0 }}</p></div>
    <div class="bg-white dark:bg-slate-800 p-4 rounded"><p>Open Rate</p><p class="text-2xl font-bold">{{ $openRate ?? 0 }}%</p></div>
    <div class="bg-white dark:bg-slate-800 p-4 rounded"><p>Click Rate</p><p class="text-2xl font-bold">{{ $clickRate ?? 0 }}%</p></div>
    <div class="bg-white dark:bg-slate-800 p-4 rounded"><p>Bounce Rate</p><p class="text-2xl font-bold">{{ $bounceRate ?? 0 }}%</p></div>
</div>
@endsection
