<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><script src="https://cdn.tailwindcss.com"></script><title>Checkout</title></head>
<body class="bg-slate-100 min-h-screen grid place-items-center">
<div class="bg-white p-8 rounded shadow max-w-lg w-full">
    <h1 class="text-2xl font-bold mb-2">{{ $productName ?? 'InfiMal Pro' }}</h1>
    <p class="text-slate-600 mb-6">One-time payment for lifetime access.</p>
    <p class="text-4xl font-extrabold mb-6">${{ number_format($amount ?? 299, 0) }}</p>

    <form action="/paypal/create-order" method="POST">
        @csrf
        <button class="w-full bg-blue-600 text-white py-3 rounded">Pay Now</button>
    </form>

    <p class="text-xs text-slate-500 mt-4">Access is activated only after server-side webhook verification and OTP validation.</p>
</div>
</body>
</html>
