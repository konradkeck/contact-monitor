<?php

namespace App\Http\Controllers;

use App\DataRelations\AutoResolver;
use App\Models\Identity;
use App\Models\Person;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OurCompanyController extends Controller
{
    public function index()
    {
        $teamDomains = SystemSetting::get('team_domains', []);

        // People in Our Organization — by is_our_org flag OR linked team member identity
        $teamPeople = Person::notMerged()->where(fn ($q) => $q->where('is_our_org', true)
            ->orWhereHas('identities', fn ($i) => $i->where('is_team_member', true))
        )
            ->with(['identities'])
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'full_name' => $p->full_name,
                'gravatar_hash' => md5(strtolower(trim($p->identities->firstWhere('type', 'email')?->value ?? ''))),
                'team_identities' => $p->identities->where('is_team_member', true)->map(fn ($i) => [
                    'type' => $i->type, 'value' => $i->value,
                ])->values(),
            ]);

        // Identities marked as team member but not linked to a person
        $unlinkedTeamIdentities = Identity::where('is_team_member', true)
            ->whereNull('person_id')
            ->orderBy('type')
            ->orderBy('value')
            ->get();

        $activeTab = request('tab', 'members');

        return Inertia::render('DataRelations/OurCompany', compact(
            'teamDomains', 'teamPeople', 'unlinkedTeamIdentities', 'activeTab'
        ));
    }

    public function saveTeamDomains(Request $request): RedirectResponse
    {
        $raw = $request->input('domains', '');
        $domains = array_values(array_filter(
            array_map('trim', preg_split('/[\r\n,]+/', $raw)),
            fn ($d) => $d !== ''
        ));
        $domains = array_map('strtolower', $domains);

        SystemSetting::set('team_domains', $domains);

        // Auto-mark existing identities with these domains
        $marked = 0;
        foreach ($domains as $domain) {
            $count = Identity::where('type', 'email')
                ->whereRaw('value_normalized LIKE ?', ['%@'.$domain])
                ->where('is_team_member', false)
                ->update(['is_team_member' => true]);
            $marked += $count;
        }

        (new AutoResolver)->resolveAll();

        $msg = 'Team domains saved.';
        if ($marked > 0) {
            $msg .= " Marked {$marked} identities as team members.";
        }

        return redirect()->back()->with('success', $msg.' Auto-resolve done.');
    }

    public function removeTeamDomain(Request $request): RedirectResponse
    {
        $domain = strtolower(trim($request->input('domain', '')));
        $domains = array_values(array_filter(
            SystemSetting::get('team_domains', []),
            fn ($d) => $d !== $domain
        ));
        SystemSetting::set('team_domains', $domains);

        return redirect()->back()->with('success', "Domain '{$domain}' removed from team.");
    }

    public function removeMember(Person $person): RedirectResponse
    {
        $person->identities()->update(['is_team_member' => false]);
        $person->update(['is_our_org' => false]);

        return redirect()->route('our-company.index', ['tab' => 'members'])
            ->with('success', "{$person->full_name} removed from Our Organization.");
    }
}
