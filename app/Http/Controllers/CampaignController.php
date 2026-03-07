<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(): View
    {
        $campaigns = Campaign::withCount('runs')->orderByDesc('created_at')->paginate(20);

        return view('campaigns.index', compact('campaigns'));
    }

    public function create(): View
    {
        return view('campaigns.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'prompt' => 'required|string',
        ]);

        $campaign = Campaign::create($data);

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign created.');
    }

    public function show(Campaign $campaign): View
    {
        $campaign->load('runs');

        return view('campaigns.show', compact('campaign'));
    }

    public function edit(Campaign $campaign): View
    {
        return view('campaigns.edit', compact('campaign'));
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'prompt' => 'required|string',
        ]);

        $campaign->update($data);

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign updated.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campaign deleted.');
    }

    public function run(Campaign $campaign): RedirectResponse
    {
        CampaignRun::create([
            'campaign_id' => $campaign->id,
            'status' => 'queued',
        ]);

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Run queued.');
    }
}
