<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\EmailList;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $campaigns = Campaign::with('list')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return view('campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        $user = auth()->user();
        $lists = EmailList::where('user_id', $user->id)->get();
        return view('campaigns.create', compact('lists'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'list_id' => 'required|exists:email_lists,id',
            'scheduled_at' => 'nullable|date'
        ]);

        $subscriberCount = Subscriber::where('list_id', $request->list_id)
            ->where('status', 'active')
            ->count();

        Campaign::create([
            'user_id' => auth()->id(),
            'list_id' => $request->list_id,
            'name' => $request->name,
            'subject' => $request->subject,
            'content' => $request->content,
            'status' => $request->scheduled_at ? 'scheduled' : 'draft',
            'scheduled_at' => $request->scheduled_at,
            'total_recipients' => $subscriberCount
        ]);

        return redirect()->route('campaigns.index')->with('success', 'Campaign created successfully!');
    }

    public function show(Campaign $campaign)
    {
        $this->authorize('view', $campaign);
        return view('campaigns.show', compact('campaign'));
    }

    public function edit(Campaign $campaign)
    {
        $this->authorize('update', $campaign);
        $lists = EmailList::where('user_id', auth()->id())->get();
        return view('campaigns.edit', compact('campaign', 'lists'));
    }

    public function update(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'list_id' => 'required|exists:email_lists,id',
            'scheduled_at' => 'nullable|date'
        ]);

        $campaign->update($request->all());

        return redirect()->route('campaigns.index')->with('success', 'Campaign updated successfully!');
    }

    public function destroy(Campaign $campaign)
    {
        $this->authorize('delete', $campaign);
        $campaign->delete();
        return redirect()->route('campaigns.index')->with('success', 'Campaign deleted successfully!');
    }

    public function send(Campaign $campaign)
    {
        $this->authorize('update', $campaign);
        
        $campaign->update([
            'status' => 'sent',
            'sent_at' => now()
        ]);

        return redirect()->route('campaigns.index')->with('success', 'Campaign sent successfully!');
    }
}
