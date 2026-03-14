<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandProductController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataRelationsController;
use App\Http\Controllers\FilteringController;
use App\Http\Controllers\SetupAssistantController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\OurCompanyController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\SynchronizerController;
use App\Http\Controllers\SynchronizerServerController;
use App\Http\Controllers\SynchronizerWizardController;
use App\Http\Controllers\TeamAccess\GroupsController;
use App\Http\Controllers\TeamAccess\TeamAccessController;
use App\Http\Controllers\TeamAccess\UsersController;
use Illuminate\Support\Facades\Route;

// ── Auth routes (public) ──────────────────────────────────────────────────────
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');

// ── Authenticated routes ──────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/change-password', [AuthController::class, 'showChangePassword'])->name('auth.change-password');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('auth.change-password.post');

    // ── Browse Data ───────────────────────────────────────────────────────────
    Route::middleware(['permission:browse_data', 'require.setup'])->group(function () {

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Companies (read)
        Route::get('companies/search', [CompanyController::class, 'search'])->name('companies.search');
        Route::get('companies/filter-modal', [FilteringController::class, 'companyFilterModal'])->name('companies.filter-modal');
        Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
        Route::get('companies/{company}/timeline', [CompanyController::class, 'timeline'])->name('companies.timeline');

        // People (read)
        Route::get('people/search', [PersonController::class, 'search'])->name('people.search');
        Route::get('people/filter-modal', [FilteringController::class, 'personFilterModal'])->name('people.filter-modal');
        Route::get('people/assign-company-modal', [PersonController::class, 'assignCompanyModal'])->name('people.assign-company-modal');
        Route::get('people', [PersonController::class, 'index'])->name('people.index');
        Route::get('people/{person}', [PersonController::class, 'show'])->name('people.show');
        Route::get('people/{person}/timeline', [PersonController::class, 'timeline'])->name('people.timeline');

        // Conversations (read)
        Route::get('conversations/filter-modal', [ConversationController::class, 'filterModal'])->name('conversations.filter-modal');
        Route::resource('conversations', ConversationController::class)->only(['index', 'show']);
        Route::get('conversations/{conversation}/modal', [ConversationController::class, 'modal'])->name('conversations.modal');

        // Audit log
        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

        // Activities
        Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
        Route::get('activity/timeline', [ActivityController::class, 'timeline'])->name('activity.timeline');
        Route::get('activity/stats', [ActivityController::class, 'stats'])->name('activity.stats');

        // ── Data write routes ────────────────────────────────────────────────
        Route::middleware('permission:data_write')->group(function () {
            // Companies (write)
            Route::get('companies/create', [CompanyController::class, 'create'])->name('companies.create');
            Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
            Route::get('companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
            Route::put('companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
            Route::delete('companies/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');
            Route::post('companies/{company}/domains', [CompanyController::class, 'storeDomain'])->name('companies.domains.store');
            Route::delete('companies/{company}/domains/{domain}', [CompanyController::class, 'destroyDomain'])->name('companies.domains.destroy');
            Route::patch('companies/{company}/domains/{domain}/primary', [CompanyController::class, 'setPrimaryDomain'])->name('companies.domains.primary');
            Route::post('companies/{company}/aliases', [CompanyController::class, 'storeAlias'])->name('companies.aliases.store');
            Route::patch('companies/{company}/aliases/{alias}/primary', [CompanyController::class, 'setPrimaryAlias'])->name('companies.aliases.primary');
            Route::delete('companies/{company}/aliases/{alias}', [CompanyController::class, 'destroyAlias'])->name('companies.aliases.destroy');
            Route::post('companies/{company}/brand-statuses', [CompanyController::class, 'storeBrandStatus'])->name('companies.brand-statuses.store');
            Route::patch('companies/{company}/brand-statuses/{status}', [CompanyController::class, 'updateBrandStatus'])->name('companies.brand-statuses.update');
            Route::delete('companies/{company}/brand-statuses/{status}', [CompanyController::class, 'destroyBrandStatus'])->name('companies.brand-statuses.destroy');
            Route::post('companies/{company}/accounts', [CompanyController::class, 'storeAccount'])->name('companies.accounts.store');
            Route::delete('companies/{company}/accounts/{account}', [CompanyController::class, 'destroyAccount'])->name('companies.accounts.destroy');

            // People (write)
            Route::post('people/bulk-mark-our-org', [PersonController::class, 'bulkMarkOurOrg'])->name('people.bulk-mark-our-org');
            Route::post('people/bulk-assign-company', [PersonController::class, 'bulkAssignCompany'])->name('people.bulk-assign-company');
            Route::get('people/create', [PersonController::class, 'create'])->name('people.create');
            Route::post('people', [PersonController::class, 'store'])->name('people.store');
            Route::get('people/{person}/edit', [PersonController::class, 'edit'])->name('people.edit');
            Route::put('people/{person}', [PersonController::class, 'update'])->name('people.update');
            Route::delete('people/{person}', [PersonController::class, 'destroy'])->name('people.destroy');
            Route::post('people/{person}/mark-our-org', [PersonController::class, 'markOurOrg'])->name('people.mark-our-org');
            Route::post('people/{person}/assign-company', [PersonController::class, 'assignCompany'])->name('people.assign-company');
            Route::post('people/{person}/identities', [PersonController::class, 'storeIdentity'])->name('people.identities.store');
            Route::delete('people/{person}/identities/{identity}', [PersonController::class, 'destroyIdentity'])->name('people.identities.destroy');
            Route::post('people/{person}/companies', [PersonController::class, 'linkCompany'])->name('people.companies.link');
            Route::delete('people/{person}/companies/{company}', [PersonController::class, 'unlinkCompany'])->name('people.companies.unlink');

            // Conversations (write)
            Route::post('conversations/bulk-archive', [ConversationController::class, 'bulkArchive'])->name('conversations.bulk-archive');
            Route::post('conversations/archive-with-rule', [ConversationController::class, 'archiveWithRule'])->name('conversations.archive-with-rule');
            Route::post('conversations/{conversation}/participants', [ConversationController::class, 'storeParticipant'])->name('conversations.participants.store');
            Route::delete('conversations/{conversation}/participants/{participant}', [ConversationController::class, 'destroyParticipant'])->name('conversations.participants.destroy');
        });

        // Notes (notes_write permission)
        Route::post('notes', [NoteController::class, 'store'])->name('notes.store');

        // Audit log
        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

        // Activities
        Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
        Route::get('activity/timeline', [ActivityController::class, 'timeline'])->name('activity.timeline');
        Route::get('activity/stats', [ActivityController::class, 'stats'])->name('activity.stats');
    });

    // ── Configuration ─────────────────────────────────────────────────────────
    Route::middleware('permission:configuration')->group(function () {

        // Segmentation
        Route::resource('configuration/segmentation', BrandProductController::class)
            ->names('segmentation')
            ->parameters(['segmentation' => 'brandProduct'])
            ->except(['show']);

        // Data Relations
        Route::get('configuration/data-relations', [DataRelationsController::class, 'index'])->name('data-relations.index');
        Route::get('configuration/mapping', [DataRelationsController::class, 'mappingIndex'])->name('configuration.mapping');
        Route::get('configuration/mapping/{systemType}/{systemSlug}', [DataRelationsController::class, 'mapping'])->name('data-relations.mapping');
        Route::post('configuration/resolve-auto', [DataRelationsController::class, 'resolveAuto'])->name('data-relations.resolve-auto');
        Route::post('configuration/accounts/{account}/link', [DataRelationsController::class, 'linkAccount'])->name('data-relations.accounts.link');
        Route::delete('configuration/accounts/{account}/unlink', [DataRelationsController::class, 'unlinkAccount'])->name('data-relations.accounts.unlink');
        Route::post('configuration/identities/{identity}/link', [DataRelationsController::class, 'linkIdentity'])->name('data-relations.identities.link');
        Route::delete('configuration/identities/{identity}/unlink', [DataRelationsController::class, 'unlinkIdentity'])->name('data-relations.identities.unlink');
        Route::post('configuration/conversations/{conversation}/link', [DataRelationsController::class, 'linkConversation'])->name('data-relations.conversations.link');
        Route::delete('configuration/conversations/{conversation}/unlink', [DataRelationsController::class, 'unlinkConversation'])->name('data-relations.conversations.unlink');
        Route::post('configuration/identities/{identity}/toggle-team-member', [DataRelationsController::class, 'toggleTeamMember'])->name('data-relations.identities.toggle-team-member');
        Route::post('configuration/identities/{identity}/toggle-bot', [DataRelationsController::class, 'toggleBot'])->name('data-relations.identities.toggle-bot');

        // Filtering
        Route::post('configuration/filtering/apply-rule', [FilteringController::class, 'applyRule'])->name('filtering.apply-rule');
        Route::get('configuration/filtering/identity-filter-modal', [FilteringController::class, 'identityFilterModal'])->name('filtering.identity-filter-modal');
        Route::get('configuration/filtering', [FilteringController::class, 'index'])->name('filtering.index');
        Route::post('configuration/filtering/domains', [FilteringController::class, 'saveDomains'])->name('filtering.domains.save');
        Route::post('configuration/filtering/domains/remove', [FilteringController::class, 'removeDomain'])->name('filtering.domains.remove');
        Route::post('configuration/filtering/emails', [FilteringController::class, 'saveEmails'])->name('filtering.emails.save');
        Route::post('configuration/filtering/emails/remove', [FilteringController::class, 'removeEmail'])->name('filtering.emails.remove');
        Route::post('configuration/filtering/subjects', [FilteringController::class, 'saveSubjects'])->name('filtering.subjects.save');
        Route::post('configuration/filtering/subjects/remove', [FilteringController::class, 'removeSubject'])->name('filtering.subjects.remove');
        Route::post('configuration/filtering/contacts', [FilteringController::class, 'addContact'])->name('filtering.contacts.add');
        Route::post('configuration/filtering/contacts/bulk', [FilteringController::class, 'bulkAddContacts'])->name('filtering.contacts.bulk-add');
        Route::delete('configuration/filtering/contacts/{person}', [FilteringController::class, 'removeContact'])->name('filtering.contacts.remove');

        // Our Organization
        Route::get('configuration/our-organization', [OurCompanyController::class, 'index'])->name('our-company.index');
        Route::post('configuration/our-organization/domains', [OurCompanyController::class, 'saveTeamDomains'])->name('our-company.save-domains');
        Route::post('configuration/our-organization/remove-domain', [OurCompanyController::class, 'removeTeamDomain'])->name('our-company.remove-domain');
        Route::delete('configuration/our-organization/members/{person}', [OurCompanyController::class, 'removeMember'])->name('our-company.remove-member');

        // Synchronizer Connections
        Route::prefix('configuration/connections')->name('synchronizer.')->middleware('require.setup')->group(function () {
            Route::get('/', [SynchronizerController::class, 'index'])->name('index');
            Route::get('/connections/create', [SynchronizerController::class, 'create'])->name('connections.create');
            Route::get('/connections/statuses', [SynchronizerController::class, 'connectionStatuses'])->name('connections.statuses');
            Route::post('/connections', [SynchronizerController::class, 'store'])->name('connections.store');
            Route::get('/connections/{id}', [SynchronizerController::class, 'show'])->name('connections.show');
            Route::get('/connections/{id}/edit', [SynchronizerController::class, 'edit'])->name('connections.edit');
            Route::put('/connections/{id}', [SynchronizerController::class, 'update'])->name('connections.update');
            Route::delete('/connections/{id}', [SynchronizerController::class, 'destroy'])->name('connections.destroy');
            Route::post('/connections/{id}/duplicate', [SynchronizerController::class, 'duplicate'])->name('connections.duplicate');
            Route::post('/connections/test', [SynchronizerController::class, 'testConnection'])->name('connections.test');
            Route::post('/connections/{id}/run', [SynchronizerController::class, 'run'])->name('connections.run');
            Route::post('/connections/{id}/stop', [SynchronizerController::class, 'stop'])->name('connections.stop');
            Route::post('/kill-all', [SynchronizerController::class, 'killAll'])->name('kill-all');
            Route::post('/run-all', [SynchronizerController::class, 'runAll'])->name('run-all');
            Route::get('/runs', [SynchronizerController::class, 'runs'])->name('runs');
            Route::get('/runs/{runId}/status', [SynchronizerController::class, 'runStatus'])->name('runs.status');
            Route::get('/runs/{runId}/logs', [SynchronizerController::class, 'runLogs'])->name('runs.logs');
        });

        // Synchronizer Servers
        Route::post('configuration/synchronizer-servers/test', [SynchronizerServerController::class, 'test'])->name('synchronizer.servers.test');
        Route::get('configuration/synchronizer-servers/{server}/ping', [SynchronizerServerController::class, 'ping'])->name('synchronizer.servers.ping');
        Route::resource('configuration/synchronizer-servers', SynchronizerServerController::class)
            ->names('synchronizer.servers')
            ->parameters(['synchronizer-servers' => 'server']);

        // Setup Assistant
        Route::get('configuration/setup-assistant', [SetupAssistantController::class, 'index'])->name('setup-assistant.index');

        // Team Access
        Route::get('configuration/team-access', [TeamAccessController::class, 'index'])->name('team-access.index');
        Route::get('configuration/team-access/users/create', [UsersController::class, 'create'])->name('team-access.users.create');
        Route::post('configuration/team-access/users', [UsersController::class, 'store'])->name('team-access.users.store');
        Route::get('configuration/team-access/users/{user}/edit', [UsersController::class, 'edit'])->name('team-access.users.edit');
        Route::put('configuration/team-access/users/{user}', [UsersController::class, 'update'])->name('team-access.users.update');
        Route::delete('configuration/team-access/users/{user}', [UsersController::class, 'destroy'])->name('team-access.users.destroy');
        Route::get('configuration/team-access/groups/create', [GroupsController::class, 'create'])->name('team-access.groups.create');
        Route::post('configuration/team-access/groups', [GroupsController::class, 'store'])->name('team-access.groups.store');
        Route::get('configuration/team-access/groups/{group}/edit', [GroupsController::class, 'edit'])->name('team-access.groups.edit');
        Route::put('configuration/team-access/groups/{group}', [GroupsController::class, 'update'])->name('team-access.groups.update');
        Route::delete('configuration/team-access/groups/{group}', [GroupsController::class, 'destroy'])->name('team-access.groups.destroy');
    });

    // Synchronizer Wizard (no configuration permission required — needed for initial server setup)
    Route::get('configuration/synchronizer-servers/wizard', [SynchronizerWizardController::class, 'step1'])->name('synchronizer.wizard.step1');
    Route::get('configuration/synchronizer-servers/wizard/configure-new', [SynchronizerWizardController::class, 'configureNew'])->name('synchronizer.wizard.configure-new');
    Route::get('configuration/synchronizer-servers/wizard/install-script/{token}', [SynchronizerWizardController::class, 'installScript'])->name('synchronizer.wizard.install-script');
    Route::get('configuration/synchronizer-servers/wizard/poll/{token}', [SynchronizerWizardController::class, 'pollRegistration'])->name('synchronizer.wizard.poll');
    Route::get('configuration/synchronizer-servers/wizard/connect-existing', [SynchronizerWizardController::class, 'connectExisting'])->name('synchronizer.wizard.connect-existing');
    Route::post('configuration/synchronizer-servers/wizard/inspect', [SynchronizerWizardController::class, 'inspectExisting'])->name('synchronizer.wizard.inspect');
    Route::post('configuration/synchronizer-servers/wizard/connect-save', [SynchronizerWizardController::class, 'connectSave'])->name('synchronizer.wizard.connect-save');
});
