<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Models\EmailList;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $subscribers = Subscriber::with('list')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        $lists = EmailList::where('user_id', $user->id)->get();
        
        return view('subscribers.index', compact('subscribers', 'lists'));
    }

    public function create()
    {
        $user = auth()->user();
        $lists = EmailList::where('user_id', $user->id)->get();
        return view('subscribers.create', compact('lists'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:subscribers,email',
            'name' => 'nullable|string|max:255',
            'list_id' => 'required|exists:email_lists,id'
        ]);

        Subscriber::create([
            'user_id' => auth()->id(),
            'list_id' => $request->list_id,
            'email' => $request->email,
            'name' => $request->name,
            'status' => 'active',
            'subscribed_at' => now()
        ]);

        return redirect()->route('subscribers.index')->with('success', 'Subscriber added successfully!');
    }

    public function show(Subscriber $subscriber)
    {
        $this->authorize('view', $subscriber);
        return view('subscribers.show', compact('subscriber'));
    }

    public function edit(Subscriber $subscriber)
    {
        $this->authorize('update', $subscriber);
        $lists = EmailList::where('user_id', auth()->id())->get();
        return view('subscribers.edit', compact('subscriber', 'lists'));
    }

    public function update(Request $request, Subscriber $subscriber)
    {
        $this->authorize('update', $subscriber);
        
        $request->validate([
            'email' => 'required|email|unique:subscribers,email,' . $subscriber->id,
            'name' => 'nullable|string|max:255',
            'list_id' => 'required|exists:email_lists,id',
            'status' => 'required|in:active,unsubscribed,bounced'
        ]);

        $subscriber->update($request->all());

        return redirect()->route('subscribers.index')->with('success', 'Subscriber updated successfully!');
    }

    public function destroy(Subscriber $subscriber)
    {
        $this->authorize('delete', $subscriber);
        $subscriber->delete();
        return redirect()->route('subscribers.index')->with('success', 'Subscriber deleted successfully!');
    }
}
