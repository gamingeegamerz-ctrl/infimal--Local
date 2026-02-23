<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    public function index()
    {
        try {
            $contacts = Contact::where('user_id', auth()->id())->get();
            return view('contacts', compact('contacts'));
        } catch (\Exception $e) {
            // If contacts table doesn't exist, show empty page
            $contacts = collect();
            return view('contacts', compact('contacts'));
        }
    }

    public function create()
    {
        return view('contacts.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
        ]);

        try {
            // Check if email already exists for this user
            $existingContact = Contact::where('user_id', auth()->id())
                                    ->where('email', $request->email)
                                    ->first();

            if ($existingContact) {
                return back()->withErrors(['email' => 'This email already exists in your contacts.']);
            }

            Contact::create([
                'email' => $request->email,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'user_id' => auth()->id(),
            ]);

            return redirect()->route('contacts.index')->with('success', 'Contact added successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to add contact. Please try again.']);
        }
    }

    public function show($id)
    {
        $contact = Contact::where('user_id', auth()->id())->findOrFail($id);
        return view('contacts.show', compact('contact'));
    }

    public function edit($id)
    {
        $contact = Contact::where('user_id', auth()->id())->findOrFail($id);
        return view('contacts.edit', compact('contact'));
    }

    public function update(Request $request, $id)
    {
        $contact = Contact::where('user_id', auth()->id())->findOrFail($id);
        
        $request->validate([
            'email' => 'required|email|unique:contacts,email,' . $contact->id . ',id,user_id,' . auth()->id(),
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
        ]);

        $contact->update($request->all());

        return redirect()->route('contacts.index')->with('success', 'Contact updated successfully!');
    }

    public function destroy($id)
    {
        $contact = Contact::where('user_id', auth()->id())->findOrFail($id);
        $contact->delete();

        return redirect()->route('contacts.index')->with('success', 'Contact deleted successfully!');
    }
}
