<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - InfiMal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6">
<div class="w-full max-w-md bg-white shadow rounded-2xl p-6">
    <h1 class="text-2xl font-semibold mb-2">Verify your account</h1>
    <p class="text-slate-600 mb-6">Enter the 6-digit OTP sent to your email.</p>

    @if ($errors->any())
        <div class="mb-4 text-red-600 text-sm">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('otp.verify') }}" class="space-y-4">
        @csrf
        <input name="otp_code" maxlength="6" class="w-full border rounded-lg px-3 py-2" placeholder="123456" required>
        <button class="w-full bg-blue-600 text-white rounded-lg py-2">Verify OTP</button>
    </form>

    <form method="POST" action="{{ route('otp.resend') }}" class="mt-3">
        @csrf
        <button class="text-blue-600 text-sm">Resend OTP</button>
    </form>
</div>
</body>
</html>
