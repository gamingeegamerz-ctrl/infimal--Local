<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Template;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    /**
     * Display a listing of the templates.
     */
    public function index()
    {
        $templates = Template::latest()->paginate(10);
        return view('templates.index', compact('templates'));
    }

    /**
     * Show the form for creating a new template.
     */
    public function create()
    {
        return view('templates.create');
    }

    /**
     * Store a newly created template in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:email,campaign,notification',
            'is_active' => 'boolean'
        ]);

        try {
            Template::create([
                'name' => $request->name,
                'subject' => $request->subject,
                'content' => $request->content,
                'type' => $request->type,
                'is_active' => $request->is_active ?? true,
                'slug' => Str::slug($request->name) . '-' . Str::random(6)
            ]);

            return redirect()->route('templates.index')
                ->with('success', 'Template created successfully!');
                
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error creating template: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show the form for editing the specified template.
     */
    public function edit($id)
    {
        $template = Template::findOrFail($id);
        return view('templates.edit', compact('template'));
    }

    /**
     * Update the specified template in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:email,campaign,notification',
            'is_active' => 'boolean'
        ]);

        try {
            $template = Template::findOrFail($id);
            
            $template->update([
                'name' => $request->name,
                'subject' => $request->subject,
                'content' => $request->content,
                'type' => $request->type,
                'is_active' => $request->is_active ?? $template->is_active
            ]);

            return redirect()->route('templates.index')
                ->with('success', 'Template updated successfully!');
                
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error updating template: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified template from storage.
     */
    public function destroy($id)
    {
        try {
            $template = Template::findOrFail($id);
            $template->delete();

            return redirect()->route('templates.index')
                ->with('success', 'Template deleted successfully!');
                
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error deleting template: ' . $e->getMessage());
        }
    }

    /**
     * Duplicate the specified template.
     */
    public function duplicate($id)
    {
        try {
            $template = Template::findOrFail($id);
            
            $newTemplate = $template->replicate();
            $newTemplate->name = $template->name . ' (Copy)';
            $newTemplate->slug = Str::slug($newTemplate->name) . '-' . Str::random(6);
            $newTemplate->save();

            return redirect()->route('templates.index')
                ->with('success', 'Template duplicated successfully!');
                
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error duplicating template: ' . $e->getMessage());
        }
    }

    /**
     * Preview the specified template.
     */
    public function preview($id)
    {
        $template = Template::findOrFail($id);
        return view('templates.preview', compact('template'));
    }
}
