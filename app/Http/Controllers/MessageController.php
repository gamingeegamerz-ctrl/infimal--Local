<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(): View
    {
        $messages = Message::where('user_id', Auth::id())->latest()->paginate(15);

        return view('messages.index', [
            'messages' => $messages,
            'totalMessages' => Message::where('user_id', Auth::id())->count(),
            'unreadMessages' => Message::where('user_id', Auth::id())->where('is_read', false)->count(),
        ]);
    }

    public function create(): View
    {
        return view('messages.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Message::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'type' => ['nullable', 'in:email,sms,notification'],
        ]) + [
            'user_id' => Auth::id(),
            'type' => $request->input('type', 'email'),
        ]);

        return redirect()->route('messages.index')->with('success', 'Message template saved.');
    }
}
