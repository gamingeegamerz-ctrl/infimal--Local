@extends('layouts.saas')
@section('title','Messages')
@section('page-title','Messages')
@section('content')
<div class="grid grid-cols-2 gap-4 mb-6">
<div class="bg-white dark:bg-slate-800 p-4 rounded">Emails Sent: {{ $totalMessages ?? 0 }}</div>
<div class="bg-white dark:bg-slate-800 p-4 rounded">Queue Status: {{ ($unreadMessages ?? 0) > 0 ? 'Pending items' : 'Healthy' }}</div>
</div>
@endsection
