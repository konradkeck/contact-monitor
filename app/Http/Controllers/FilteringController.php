<?php

namespace App\Http\Controllers;

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

        $filterDomains  = SystemSetting::get('filter_domains', []);
        $filterEmails   = SystemSetting::get('filter_emails', []);
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
        $raw     = $request->input('domains', '');
        $domains = array_values(array_filter(
            array_map('trim', preg_split('/[\r\n,]+/', $raw)),
            fn($d) => $d !== ''
        ));
        $domains = array_map('strtolower', $domains);
        SystemSetting::set('filter_domains', $domains);
        return redirect()->back()->with('success', 'Filter domains saved.');
    }

    public function removeDomain(Request $request): RedirectResponse
    {
        $domain  = strtolower(trim($request->input('domain', '')));
        $domains = array_values(array_filter(
            SystemSetting::get('filter_domains', []),
            fn($d) => $d !== $domain
        ));
        SystemSetting::set('filter_domains', $domains);
        return redirect()->back()->with('success', "Domain '{$domain}' removed.");
    }

    // ── Emails ──

    public function saveEmails(Request $request): RedirectResponse
    {
        $raw    = $request->input('emails', '');
        $emails = array_values(array_filter(
            array_map('trim', preg_split('/[\r\n,]+/', $raw)),
            fn($e) => $e !== ''
        ));
        $emails = array_map('strtolower', $emails);
        SystemSetting::set('filter_emails', $emails);
        return redirect()->back()->with('success', 'Filter emails saved.');
    }

    public function removeEmail(Request $request): RedirectResponse
    {
        $email  = strtolower(trim($request->input('email', '')));
        $emails = array_values(array_filter(
            SystemSetting::get('filter_emails', []),
            fn($e) => $e !== $email
        ));
        SystemSetting::set('filter_emails', $emails);
        return redirect()->back()->with('success', "Email '{$email}' removed.");
    }

    // ── Subjects ──

    public function saveSubjects(Request $request): RedirectResponse
    {
        $raw      = $request->input('subjects', '');
        $subjects = array_values(array_filter(
            array_map('trim', preg_split('/[\r\n]+/', $raw)),
            fn($s) => $s !== ''
        ));
        SystemSetting::set('filter_subjects', $subjects);
        return redirect()->back()->with('success', 'Filter subjects saved.');
    }

    public function removeSubject(Request $request): RedirectResponse
    {
        $subject  = trim($request->input('subject', ''));
        $subjects = array_values(array_filter(
            SystemSetting::get('filter_subjects', []),
            fn($s) => $s !== $subject
        ));
        SystemSetting::set('filter_subjects', $subjects);
        return redirect()->back()->with('success', "Subject removed.");
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
        return back()->with('success', count($ids) . ' person(s) added to filter list.');
    }

    public function removeContact(Person $person): RedirectResponse
    {
        DB::table('filter_contacts')->where('person_id', $person->id)->delete();
        return redirect()->route('filtering.index', ['tab' => 'contacts'])
            ->with('success', "{$person->full_name} removed from filter contacts.");
    }
}
