<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmailList;

class ListController extends Controller
{
    public function index()
    {
        $lists = EmailList::where('user_id', auth()->id())->get();
        return view('lists', compact('lists'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        EmailList::create([
            'name' => $request->name,
            'description' => $request->description,
            'user_id' => auth()->id()
        ]);

        return redirect()->route('lists.index')->with('success', 'List created successfully!');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $list = EmailList::where('user_id', auth()->id())->findOrFail($id);
        $list->update($request->only(['name', 'description']));

        return redirect()->route('lists.index')->with('success', 'List updated successfully!');
    }

    public function destroy($id)
    {
        $list = EmailList::where('user_id', auth()->id())->findOrFail($id);
        $list->delete();

        return redirect()->route('lists.index')->with('success', 'List deleted successfully!');
    }
}
