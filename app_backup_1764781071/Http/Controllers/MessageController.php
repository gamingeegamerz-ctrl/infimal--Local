<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MessageTemplate;

class MessageController extends Controller
{
    public function index()
    {
        $templates = MessageTemplate::where('user_id', auth()->id())->get();
        return view('messages', compact('templates'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'type' => 'required|in:newsletter,promotional,transactional'
        ]);

        MessageTemplate::create([
            'name' => $request->name,
            'subject' => $request->subject,
            'type' => $request->type,
            'user_id' => auth()->id()
        ]);

        return redirect()->route('messages.index')->with('success', 'Template created successfully!');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'type' => 'required|in:newsletter,promotional,transactional'
        ]);

        $template = MessageTemplate::where('user_id', auth()->id())->findOrFail($id);
        $template->update($request->only(['name', 'subject', 'type']));

        return redirect()->route('messages.index')->with('success', 'Template updated successfully!');
    }

    public function destroy($id)
    {
        $template = MessageTemplate::where('user_id', auth()->id())->findOrFail($id);
        $template->delete();

        return redirect()->route('messages.index')->with('success', 'Template deleted successfully!');
    }
}
