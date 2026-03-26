<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - InfiMal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-xl shadow p-6 space-y-4">
        <h1 class="text-2xl font-semibold">Verify your account</h1>
        <p class="text-sm text-slate-600">Enter the 6-digit OTP sent to your email after payment.</p>

        <form method="POST" action="{{ route('otp.verify.submit') }}" class="space-y-3">
            @csrf
            <input name="otp" maxlength="6" required class="w-full border rounded-lg px-3 py-2" placeholder="123456" />
            @error('otp')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
            <button class="w-full bg-blue-600 text-white py-2 rounded-lg">Verify OTP</button>
        </form>

        <form method="POST" action="{{ route('otp.verify.resend') }}">
            @csrf
            <button class="w-full bg-white border border-slate-300 text-slate-700 py-2 rounded-lg">Resend OTP</button>
        </form>
    </div>
</body>
</html>
