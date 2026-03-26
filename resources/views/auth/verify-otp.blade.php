@extends('layouts.saas')

@section('title', 'Verify OTP')

@section('content')
<div class="max-w-md mx-auto bg-white dark:bg-slate-800 rounded-xl shadow p-6 mt-10">
    <h1 class="text-xl font-semibold mb-4">Verify your OTP</h1>
    <form method="POST" action="{{ route('otp.verify') }}" class="space-y-4">
        @csrf
        <input type="text" name="otp" maxlength="6" required class="w-full border rounded p-2" placeholder="Enter 6 digit code">
        @error('otp')
            <p class="text-red-600 text-sm">{{ $message }}</p>
        @enderror
        <button class="w-full bg-blue-600 text-white rounded p-2">Verify & Continue</button>
    </form>
</div>
@endsection
