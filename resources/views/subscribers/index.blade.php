@extends('layouts.saas')
@section('title','Subscribers')
@section('page-title','Subscribers')
@section('content')
<div class="grid grid-cols-2 gap-4 mb-6">
<div class="bg-white dark:bg-slate-800 p-4 rounded">Total Subscribers: {{ $subscribers->total() ?? ($subscribers->count() ?? 0) }}</div>
<div class="bg-white dark:bg-slate-800 p-4 rounded">Active Subscribers: {{ $activeSubscribers ?? 0 }}</div>
</div>
@endsection
