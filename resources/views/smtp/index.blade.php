@extends('layouts.saas')
@section('title','SMTP')
@section('page-title','SMTP Settings')
@section('content')
<div class="grid grid-cols-3 gap-4 mb-6">
<div class="bg-white dark:bg-slate-800 p-4 rounded">Total SMTP: {{ $totalSmtp ?? 0 }}</div>
<div class="bg-white dark:bg-slate-800 p-4 rounded">Active SMTP: {{ $activeSmtp ?? 0 }}</div>
<div class="bg-white dark:bg-slate-800 p-4 rounded">Status: {{ ($activeSmtp ?? 0) > 0 ? 'Active' : 'Not Connected' }}</div>
</div>
@endsection
