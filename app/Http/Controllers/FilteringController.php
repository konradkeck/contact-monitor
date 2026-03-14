<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Person;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FilteringController extends Controller
{
    public function index(Request $request): View
    {
        $activeTab = $request->get('tab', 'domains');

        $filterDomains = SystemSetting::get('filter_domains', []);
        $filterEmails = SystemSetting::get('filter_emails', []);
        $filterSubjects = SystemSetting::get('filter_subjects', []);
        $filterContacts = Person::whereIn('id', DB::table('filter_contacts')->pluck('person_id'))
            ->orderBy('first_name')->orderBy('last_name')->get();

        return view('data-relations.filtering', compact(
            'activeTab', 'filterDomains', 'filterEmails', 'filterSubjects', 'filterContacts'
        ));
    }

    // ── Domains ──

    public function saveDomains(Request $request): RedirectResponse
    {
        $raw = $request->input('domains', '');
        $domains = array_values(array_filter(
            array_map('trim', preg_split('/[\r\n,]+/', $raw)),
            fn ($d) => $d !== ''
        ));
        $domains = array_map('strtolower', $domains);
        SystemSetting::set('filter_domains', $domains);

        return redirect()->back()->with('success', 'Filter domains saved.');
    }

    public function removeDomain(Request $request): RedirectResponse
    {
        $domain = strtolower(trim($request->input('domain', '')));
        $domains = array_values(array_filter(
            SystemSetting::get('filter_domains', []),
            fn ($d) => $d !== $domain
        ));
        SystemSetting::set('filter_domains', $domains);

        return redirect()->back()->with('success', "Domain '{$domain}' removed.");
    }

    // ── Emails ──

    public function saveEmails(Request $request): RedirectResponse
    {
        $raw = $request->input('emails', '');
        $emails = array_values(array_filter(
            array_map('trim', preg_split('/[\r\n,]+/', $raw)),
            fn ($e) => $e !== ''
        ));
        $emails = array_map('strtolower', $emails);
        SystemSetting::set('filter_emails', $emails);

        return redirect()->back()->with('success', 'Filter emails saved.');
    }

    public function removeEmail(Request $request): RedirectResponse
    {
        $email = strtolower(trim($request->input('email', '')));
        $emails = array_values(array_filter(
            SystemSetting::get('filter_emails', []),
            fn ($e) => $e !== $email
        ));
        SystemSetting::set('filter_emails', $emails);

        return redirect()->back()->with('success', "Email '{$email}' removed.");
    }

    // ── Subjects ──

    public function saveSubjects(Request $request): RedirectResponse
    {
        $raw = $request->input('subjects', '');
        $subjects = array_values(array_filter(
            array_map('trim', preg_split('/[\r\n]+/', $raw)),
            fn ($s) => $s !== ''
        ));
        SystemSetting::set('filter_subjects', $subjects);

        return redirect()->back()->with('success', 'Filter subjects saved.');
    }

    public function removeSubject(Request $request): RedirectResponse
    {
        $subject = trim($request->input('subject', ''));
        $subjects = array_values(array_filter(
            SystemSetting::get('filter_subjects', []),
            fn ($s) => $s !== $subject
        ));
        SystemSetting::set('filter_subjects', $subjects);

        return redirect()->back()->with('success', 'Subject removed.');
    }

    // ── Contacts ──

    public function addContact(Request $request): RedirectResponse
    {
        $personId = (int) $request->input('person_id');
        if ($personId) {
            DB::table('filter_contacts')->insertOrIgnore(['person_id' => $personId, 'created_at' => now(), 'updated_at' => now()]);
        }

        return redirect()->back()->with('success', 'Contact added to filter list.');
    }

    public function bulkAddContacts(Request $request): RedirectResponse
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        foreach ($ids as $personId) {
            DB::table('filter_contacts')->insertOrIgnore(['person_id' => $personId, 'created_at' => now(), 'updated_at' => now()]);
        }

        return back()->with('success', count($ids).' person(s) added to filter list.');
    }

    public function removeContact(Person $person): RedirectResponse
    {
        DB::table('filter_contacts')->where('person_id', $person->id)->delete();

        return redirect()->route('filtering.index', ['tab' => 'contacts'])
            ->with('success', "{$person->full_name} removed from filter contacts.");
    }

    // ── Filter modals ──

    public function personFilterModal(Request $request): View
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        $people = Person::with('identities')->whereIn('id', $ids)->get();

        $emails = collect();
        $domains = collect();
        $contacts = collect(); // person_id => name

        foreach ($people as $person) {
            $contacts->put($person->id, $person->full_name);
            foreach ($person->identities as $identity) {
                if ($identity->type === 'email') {
                    $emails->push($identity->value);
                    $domain = substr(strrchr($identity->value, '@'), 1);
                    if ($domain) {
                        $domains->push($domain);
                    }
                }
            }
        }

        $emails = $emails->unique()->values();
        $domains = $domains->unique()->values();
        $contacts = $contacts->unique();

        return view('people.filter-modal', compact('ids', 'emails', 'domains', 'contacts'));
    }

    public function companyFilterModal(Request $request): View
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        $companies = Company::with('domains')->whereIn('id', $ids)->get();

        $domains = collect();
        foreach ($companies as $company) {
            foreach ($company->domains as $domain) {
                if ($domain->domain) {
                    $domains->push($domain->domain);
                }
            }
        }
        $domains = $domains->unique()->values();

        return view('companies.filter-modal', compact('ids', 'domains'));
    }

    public function applyRule(Request $request): RedirectResponse
    {
        $ruleType = $request->input('rule_type', 'none');
        $ruleValue = trim($request->input('rule_value', ''));

        if ($ruleType !== 'none' && $ruleValue !== '') {
            match ($ruleType) {
                'domain' => $this->addFilterRuleDomain($ruleValue),
                'email' => $this->addFilterRuleEmail($ruleValue),
                'contact' => $this->addFilterRuleContact((int) $ruleValue),
                default => null,
            };

            return back()->with('success', "Filter rule added ({$ruleType}: {$ruleValue}).");
        }

        return back()->with('success', 'No rule added.');
    }

    public function identityFilterModal(Request $request): View
    {
        // email, domain pre-filled from query params (passed by mapping view per-row)
        $email = trim($request->input('email', ''));
        $domain = trim($request->input('domain', ''));
        $name = trim($request->input('name', ''));

        $emails = $email ? collect([$email]) : collect();
        $domains = $domain ? collect([$domain]) : collect();

        // Derive domain from email if not given
        if ($email && ! $domain) {
            $d = substr(strrchr($email, '@'), 1);
            if ($d) {
                $domains = collect([$d]);
            }
        }

        return view('filtering.identity-filter-modal', compact('email', 'domain', 'name', 'emails', 'domains'));
    }

    private function addFilterRuleDomain(string $domain): void
    {
        $domains = SystemSetting::get('filter_domains', []);
        $domain = strtolower(trim($domain));
        if (! in_array($domain, $domains, true)) {
            $domains[] = $domain;
            SystemSetting::set('filter_domains', $domains);
        }
    }

    private function addFilterRuleEmail(string $email): void
    {
        $emails = SystemSetting::get('filter_emails', []);
        $email = strtolower(trim($email));
        if (! in_array($email, $emails, true)) {
            $emails[] = $email;
            SystemSetting::set('filter_emails', $emails);
        }
    }

    private function addFilterRuleContact(int $personId): void
    {
        if ($personId) {
            DB::table('filter_contacts')->insertOrIgnore([
                'person_id' => $personId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
