<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\SynchronizerController;
use App\Http\Controllers\SynchronizerServerController;
use App\Http\Controllers\SynchronizerWizardController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BrandProductController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DataRelationsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\FilteringController;
use App\Http\Controllers\OurCompanyController;
use App\Http\Controllers\PersonController;
use Illuminate\Support\Facades\Route;

Route::middleware('require.setup')->group(function () {

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Companies
Route::get('companies/search', [CompanyController::class, 'search'])->name('companies.search');
Route::resource('companies', CompanyController::class);
Route::post('companies/{company}/domains', [CompanyController::class, 'storeDomain'])->name('companies.domains.store');
Route::delete('companies/{company}/domains/{domain}', [CompanyController::class, 'destroyDomain'])->name('companies.domains.destroy');
Route::patch('companies/{company}/domains/{domain}/primary', [CompanyController::class, 'setPrimaryDomain'])->name('companies.domains.primary');
Route::post('companies/{company}/aliases', [CompanyController::class, 'storeAlias'])->name('companies.aliases.store');
Route::patch('companies/{company}/aliases/{alias}/primary', [CompanyController::class, 'setPrimaryAlias'])->name('companies.aliases.primary');
Route::delete('companies/{company}/aliases/{alias}', [CompanyController::class, 'destroyAlias'])->name('companies.aliases.destroy');
Route::post('companies/{company}/brand-statuses', [CompanyController::class, 'storeBrandStatus'])->name('companies.brand-statuses.store');
Route::patch('companies/{company}/brand-statuses/{status}', [CompanyController::class, 'updateBrandStatus'])->name('companies.brand-statuses.update');
Route::get('companies/{company}/timeline', [CompanyController::class, 'timeline'])->name('companies.timeline');
Route::post('companies/{company}/accounts', [CompanyController::class, 'storeAccount'])->name('companies.accounts.store');
Route::delete('companies/{company}/accounts/{account}', [CompanyController::class, 'destroyAccount'])->name('companies.accounts.destroy');

// People
Route::get('people/search', [PersonController::class, 'search'])->name('people.search');
Route::resource('people', PersonController::class);
Route::post('people/{person}/identities', [PersonController::class, 'storeIdentity'])->name('people.identities.store');
Route::delete('people/{person}/identities/{identity}', [PersonController::class, 'destroyIdentity'])->name('people.identities.destroy');
Route::post('people/{person}/companies', [PersonController::class, 'linkCompany'])->name('people.companies.link');
Route::delete('people/{person}/companies/{company}', [PersonController::class, 'unlinkCompany'])->name('people.companies.unlink');
Route::get('people/{person}/timeline', [PersonController::class, 'timeline'])->name('people.timeline');

// Brand Products
Route::resource('brand-products', BrandProductController::class)->middleware('require.setup');

// Notes
Route::post('notes', [NoteController::class, 'store'])->name('notes.store');

// Conversations — static routes BEFORE resource to avoid {conversation} param capture
Route::post('conversations/bulk-archive', [ConversationController::class, 'bulkArchive'])->name('conversations.bulk-archive');
Route::get('conversations/filter-modal', [ConversationController::class, 'filterModal'])->name('conversations.filter-modal');
Route::post('conversations/archive-with-rule', [ConversationController::class, 'archiveWithRule'])->name('conversations.archive-with-rule');
Route::resource('conversations', ConversationController::class)->only(['index', 'show']);
Route::get('conversations/{conversation}/modal', [ConversationController::class, 'modal'])->name('conversations.modal');
Route::post('conversations/{conversation}/participants', [ConversationController::class, 'storeParticipant'])->name('conversations.participants.store');
Route::delete('conversations/{conversation}/participants/{participant}', [ConversationController::class, 'destroyParticipant'])->name('conversations.participants.destroy');

// Audit log
Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

// Activities
Route::get('activities', [ActivityController::class, 'index'])->name('activities.index');

// Data Relations
Route::get('data-relations', [DataRelationsController::class, 'index'])->name('data-relations.index');
Route::get('data-relations/mapping/{systemType}/{systemSlug}', [DataRelationsController::class, 'mapping'])->name('data-relations.mapping');
Route::post('data-relations/resolve-auto', [DataRelationsController::class, 'resolveAuto'])->name('data-relations.resolve-auto');
Route::post('data-relations/accounts/{account}/link', [DataRelationsController::class, 'linkAccount'])->name('data-relations.accounts.link');
Route::delete('data-relations/accounts/{account}/unlink', [DataRelationsController::class, 'unlinkAccount'])->name('data-relations.accounts.unlink');
Route::post('data-relations/identities/{identity}/link', [DataRelationsController::class, 'linkIdentity'])->name('data-relations.identities.link');
Route::delete('data-relations/identities/{identity}/unlink', [DataRelationsController::class, 'unlinkIdentity'])->name('data-relations.identities.unlink');
Route::post('data-relations/conversations/{conversation}/link', [DataRelationsController::class, 'linkConversation'])->name('data-relations.conversations.link');
Route::delete('data-relations/conversations/{conversation}/unlink', [DataRelationsController::class, 'unlinkConversation'])->name('data-relations.conversations.unlink');
Route::post('data-relations/identities/{identity}/toggle-team-member', [DataRelationsController::class, 'toggleTeamMember'])->name('data-relations.identities.toggle-team-member');

// Filtering
Route::get('data-relations/filtering', [FilteringController::class, 'index'])->name('filtering.index');
Route::post('data-relations/filtering/domains', [FilteringController::class, 'saveDomains'])->name('filtering.domains.save');
Route::post('data-relations/filtering/domains/remove', [FilteringController::class, 'removeDomain'])->name('filtering.domains.remove');
Route::post('data-relations/filtering/emails', [FilteringController::class, 'saveEmails'])->name('filtering.emails.save');
Route::post('data-relations/filtering/emails/remove', [FilteringController::class, 'removeEmail'])->name('filtering.emails.remove');
Route::post('data-relations/filtering/subjects', [FilteringController::class, 'saveSubjects'])->name('filtering.subjects.save');
Route::post('data-relations/filtering/subjects/remove', [FilteringController::class, 'removeSubject'])->name('filtering.subjects.remove');
Route::post('data-relations/filtering/contacts', [FilteringController::class, 'addContact'])->name('filtering.contacts.add');
Route::post('data-relations/filtering/contacts/bulk', [FilteringController::class, 'bulkAddContacts'])->name('filtering.contacts.bulk-add');
Route::delete('data-relations/filtering/contacts/{person}', [FilteringController::class, 'removeContact'])->name('filtering.contacts.remove');

// Our Company
Route::get('data-relations/our-company', [OurCompanyController::class, 'index'])->name('our-company.index');
Route::post('data-relations/our-company/domains', [OurCompanyController::class, 'saveTeamDomains'])->name('our-company.save-domains');
Route::post('data-relations/our-company/remove-domain', [OurCompanyController::class, 'removeTeamDomain'])->name('our-company.remove-domain');
Route::delete('data-relations/our-company/members/{person}', [OurCompanyController::class, 'removeMember'])->name('our-company.remove-member');

}); // end require.setup

// Synchronizer Wizard
Route::get('synchronization/servers/wizard',                              [SynchronizerWizardController::class, 'step1'])->name('synchronizer.wizard.step1');
Route::get('synchronization/servers/wizard/configure-new',               [SynchronizerWizardController::class, 'configureNew'])->name('synchronizer.wizard.configure-new');
Route::get('synchronization/servers/wizard/install-script/{token}',      [SynchronizerWizardController::class, 'installScript'])->name('synchronizer.wizard.install-script');
Route::get('synchronization/servers/wizard/poll/{token}',                [SynchronizerWizardController::class, 'pollRegistration'])->name('synchronizer.wizard.poll');
Route::get('synchronization/servers/wizard/connect-existing',            [SynchronizerWizardController::class, 'connectExisting'])->name('synchronizer.wizard.connect-existing');
Route::post('synchronization/servers/wizard/inspect',                    [SynchronizerWizardController::class, 'inspectExisting'])->name('synchronizer.wizard.inspect');
Route::post('synchronization/servers/wizard/connect-save',               [SynchronizerWizardController::class, 'connectSave'])->name('synchronizer.wizard.connect-save');

// Synchronizer Servers
Route::post('synchronization/servers/test', [SynchronizerServerController::class, 'test'])->name('synchronizer.servers.test');
Route::get('synchronization/servers/{server}/ping', [SynchronizerServerController::class, 'ping'])->name('synchronizer.servers.ping');
Route::resource('synchronization/servers', SynchronizerServerController::class)
    ->names('synchronizer.servers')
    ->parameters(['servers' => 'server']);

// Synchronizer (Connections page requires at least one server)
Route::prefix('synchronization')->name('synchronizer.')->middleware('require.setup')->group(function () {
    Route::get('/',                              [SynchronizerController::class, 'index'])->name('index');
    Route::get('/connections/create',            [SynchronizerController::class, 'create'])->name('connections.create');
    Route::post('/connections',                  [SynchronizerController::class, 'store'])->name('connections.store');
    Route::get('/connections/{id}',              [SynchronizerController::class, 'show'])->name('connections.show');
    Route::get('/connections/{id}/edit',         [SynchronizerController::class, 'edit'])->name('connections.edit');
    Route::put('/connections/{id}',              [SynchronizerController::class, 'update'])->name('connections.update');
    Route::delete('/connections/{id}',           [SynchronizerController::class, 'destroy'])->name('connections.destroy');
    Route::post('/connections/{id}/duplicate',   [SynchronizerController::class, 'duplicate'])->name('connections.duplicate');
    Route::post('/connections/{id}/run',         [SynchronizerController::class, 'run'])->name('connections.run');
    Route::post('/connections/{id}/stop',        [SynchronizerController::class, 'stop'])->name('connections.stop');
    Route::post('/kill-all',                     [SynchronizerController::class, 'killAll'])->name('kill-all');
    Route::post('/run-all',                      [SynchronizerController::class, 'runAll'])->name('run-all');
    Route::get('/runs',                          [SynchronizerController::class, 'runs'])->name('runs');
    Route::get('/runs/{runId}/status',           [SynchronizerController::class, 'runStatus'])->name('runs.status');
    Route::get('/runs/{runId}/logs',             [SynchronizerController::class, 'runLogs'])->name('runs.logs');
});
