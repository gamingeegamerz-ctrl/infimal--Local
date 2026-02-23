<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkspaceController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        $workspaces = Workspace::where('user_id', $user->id)
            ->withCount(['campaigns', 'subscribers', 'lists', 'messages'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('workspaces', [
            'workspaces' => $workspaces,
            'totalWorkspaces' => Workspace::where('user_id', $user->id)->count(),
            'totalCampaigns' => Workspace::where('user_id', $user->id)->sum('campaign_count'),
            'totalSubscribers' => Workspace::where('user_id', $user->id)->sum('subscriber_count')
        ]);
    }
}
