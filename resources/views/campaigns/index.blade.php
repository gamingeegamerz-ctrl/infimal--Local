@extends('layouts.saas')
@section('title','Campaigns')
@section('page-title','Campaigns')
@section('content')
<div class="grid grid-cols-3 gap-4 mb-6">
<div class="bg-white dark:bg-slate-800 p-4 rounded">Total: {{ $totalCampaigns ?? 0 }}</div>
<div class="bg-white dark:bg-slate-800 p-4 rounded">Sent: {{ $sentCampaigns ?? 0 }}</div>
<div class="bg-white dark:bg-slate-800 p-4 rounded">Active: {{ ($scheduledCampaigns ?? 0) + ($campaigns->where('status','sending')->count() ?? 0) }}</div>
</div>
<table class="w-full bg-white dark:bg-slate-800 rounded"><tr><th class="text-left p-3">Name</th><th>Status</th></tr>@foreach($campaigns as $c)<tr><td class="p-3">{{ $c->name }}</td><td>{{ $c->status }}</td></tr>@endforeach</table>
@endsection
