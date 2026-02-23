<?php

namespace App\Http\Controllers;

use App\Models\MailingList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ListController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $lists = MailingList::where('user_id', $user->id)
            ->withCount('subscribers')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        return view('lists.index', compact('lists'));
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $list = MailingList::create([
                'user_id' => Auth::id(),
                'name' => $request->name,
                'slug' => \Illuminate\Support\Str::slug($request->name),
                'description' => $request->description,
                'is_public' => $request->is_public ?? false
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'List created successfully!',
                'list' => $list
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $list = MailingList::where('user_id', Auth::id())
                ->where('id', $id)
                ->firstOrFail();
            
            $list->update([
                'name' => $request->name,
                'slug' => \Illuminate\Support\Str::slug($request->name),
                'description' => $request->description,
                'is_public' => $request->is_public ?? false
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'List updated successfully!',
                'list' => $list
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function destroy($id)
    {
        try {
            $list = MailingList::where('user_id', Auth::id())
                ->where('id', $id)
                ->firstOrFail();
            
            // Check if list has subscribers
            if ($list->subscribers()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete list that has subscribers. Please remove subscribers first.'
                ], 400);
            }
            
            $list->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'List deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}