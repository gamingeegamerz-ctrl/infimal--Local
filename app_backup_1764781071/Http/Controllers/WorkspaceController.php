<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Workspace;

class WorkspaceController extends Controller
{
    public function index()
    {
        $workspaces = Workspace::where('owner_id', auth()->id())->get();
        return view('workspaces', compact('workspaces'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        Workspace::create([
            'name' => $request->name,
            'description' => $request->description,
            'owner_id' => auth()->id()
        ]);

        return redirect()->route('workspaces.index')->with('success', 'Workspace created successfully!');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $workspace = Workspace::where('owner_id', auth()->id())->findOrFail($id);
        $workspace->update($request->only(['name', 'description']));

        return redirect()->route('workspaces.index')->with('success', 'Workspace updated successfully!');
    }

    public function destroy($id)
    {
        $workspace = Workspace::where('owner_id', auth()->id())->findOrFail($id);
        $workspace->delete();

        return redirect()->route('workspaces.index')->with('success', 'Workspace deleted successfully!');
    }
}
