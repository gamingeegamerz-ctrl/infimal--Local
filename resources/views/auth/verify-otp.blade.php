<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Verify OTP</title>
</head>
<body class="bg-slate-100 min-h-screen grid place-items-center">
<form method="POST" action="{{ route('otp.verify.submit') }}" class="bg-white p-8 rounded shadow w-full max-w-md">
    @csrf
    <h1 class="text-xl font-semibold mb-4">Verify your OTP</h1>
    <input type="text" name="otp" maxlength="6" class="w-full border rounded px-3 py-2" placeholder="Enter 6-digit OTP" required>
    @error('otp') <p class="text-red-600 text-sm mt-2">{{ $message }}</p> @enderror
    <button class="mt-4 w-full bg-blue-600 text-white rounded py-2">Verify & Continue</button>
</form>
</body>
</html>
