# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

---

## Project Overview — BETAv1

**Contact Monitor** is a multi-channel contact hub that centralizes a company's communications with clients across email, Gmail, Slack, Discord, tickets, and other integrations. It links conversations and activities to company and person profiles, tracks brand product pipeline stages, and provides a unified activity timeline across all channels.

**Stack:** Laravel 12 · PHP 8.3 · PostgreSQL 16 · Tailwind CSS v4 · Vite · Alpine.js

**No queue worker, no scheduler** — all operations are synchronous. No Redis; sessions and cache use database tables.

**Authentication:** email + password. Email comparison is case-insensitive (`strtolower()` before `Auth::attempt()`). Password is case-sensitive.

**Current state:** BETAv1 — core data model and UI patterns are stable, integrations and segmentation features in active development.

---

## Common Commands

```bash
# Clear compiled views (use after Blade changes)
docker exec contact-monitor_app php artisan view:clear

# Run migrations
docker exec contact-monitor_app php artisan migrate

# Artisan tinker
docker exec contact-monitor_app php artisan tinker

# Run tests (SQLite in-memory)
docker exec contact-monitor_app php artisan test

# Rebuild frontend assets
docker exec contact-monitor_app npm run build

# Deploy to production
./ops/deploy-production.sh
```

---

## Architecture

### Docker

- `contact-monitor_app` — Laravel app, port 8090
- `contact-monitor_db` — PostgreSQL 16, port 5434 on host
- `contact-monitor-synchronizer_app` — separate synchronizer service on port 8080

### Route Middleware Stack

```
Public:
  GET/POST /login        → AuthController (rate-limited: 5 attempts / 300s per IP)
  POST     /logout       → auth required

Authenticated (auth + require.setup):
  browse_data {
    Dashboard, companies, people, conversations, activity, audit-log
    Search endpoints, filter-modal endpoints (AJAX)

    data_write {
      Company/Person CRUD + sub-resource management
        (domains, aliases, brand-statuses, accounts, identities, company links)
      Conversation bulk-archive, archive-with-rule, participant management
      People bulk: mark/unmark our-org, assign to company
    }
  }

  notes_write {               ← separate from data_write
    POST   /notes             → NoteController::store
    DELETE /notes/{note}      → NoteController::destroy
  }

  configuration {             ← no require.setup check
    /configuration/setup-assistant
    /configuration/team-access   (users + groups CRUD)
    /data-relations/*            (mapping, filtering, our-company)
    /synchronizer/*              (connections + servers CRUD, run/stop/logs)
  }

  Synchronizer wizard (no configuration permission required — first-run flow)

API (no session, header auth):
  POST /api/ingest/batch     → X-Ingest-Secret header validated against SynchronizerServer
```

---

## Data Model

### Key Relationships

```
companies
  ├─ company_domains        (one marked is_primary)
  ├─ company_aliases        (one marked is_primary)
  ├─ accounts               (system_type + system_slug + external_id triple key)
  ├─ company_brand_statuses (stage + score per brand product)
  ├─ company_person         (pivot: role, started_at, ended_at)
  ├─ conversations          → conversation_messages (direction: customer/internal/system)
  │                           conversation_participants (identity ↔ conversation)
  └─ activities             (meta_json payload, occurred_at)

people
  ├─ identities             (type: email / slack_user / discord_user; value_normalized auto-lowercased)
  ├─ company_person pivot
  └─ activities

notes → note_links          (polymorphic: Company / Person / Conversation)

brand_products → company_brand_statuses
synchronizer_servers        (url, api_token, ingest_secret)
system_settings             (key-value JSON store)
filter_contacts             (pivot: person_id, reason)
audit_logs                  (user_id, entity_type, entity_id, action, description)
```

### Models — Important Methods

**Activity**
- `direction()` — checks `meta_json['direction']` override, then `meta_json['is_outbound']`, then type-based fallback
- `timelineLabel()` / `timelineColor()` / `dotColor()` — extend when adding new activity types
- `timelineDisplayData(array $convSubjectMap)` — computes the full `_display` object for timeline partial
- `getDisplayAttribute()` — exposes `_display` as `$activity->display` (set by `prepareTimelineDisplay()` in controllers)

**Conversation**
- `resolveMentions(string $text, array $discordMap, array $slackMap)` — replaces `<@ID>` (Discord numeric) and `<@USERID>` (Slack uppercase) with display names. Pass both maps when rendering messages.

**Person**
- `getFullNameAttribute()` — trim concat first_name + last_name
- `personActivitiesQuery()` — **never use `.distinct()`** — breaks PostgreSQL `ORDER BY` with SQLSTATE[42P10]

**Identity**
- `value_normalized` — auto-lowercased on save via model boot; use this for all lookups
- `is_team_member` — derived/secondary; `Person.is_our_org` is the canonical flag

### Activity Direction Classification (priority order)
1. `meta_json['direction']` explicit override
2. `meta_json['is_outbound']` → `true` = internal, `false` = customer
3. Type-based fallback: payment/renewal/cancellation/ticket/conversation → customer; else internal

### Company Activities Scope
- Direct: `company_id` matches
- Via people: person in `company_person` pivot
- Via conversations: `meta_json['conversation_external_id']` matches `Conversation.external_thread_id`

---

## Synchronizer ↔ Main App Communication

### Overview

The synchronizer is a **separate Laravel service** at port 8080. It polls external systems and pushes batches to the main app's ingest API.

```
External Systems (WHMCS, MetricsCube, Slack, Discord, IMAP, Gmail)
    ↓ (polling, webhooks)
Synchronizer Service (port 8080)
    ↓ POST /api/ingest/batch  (X-Ingest-Secret header)
Main App IngestController
    → BatchProcessor → AccountProcessor / IdentityProcessor
                     → ConversationProcessor / MessageProcessor
                     → ActivityProcessor
    → AutoResolver::resolveAll()   (transitive account/identity linking)
    → models: accounts, identities, conversations, activities
```

### Ingest Batch Payload Structure
```json
{
    "batch_id": "...",
    "source_type": "whmcs|metricscube|slack|discord|imap|gmail",
    "source_slug": "my-instance-slug",
    "items": [
        {
            "idempotency_key": "...",
            "type": "account|identity|conversation|message|activity",
            "system_type": "...",
            "system_slug": "...",
            "external_id": "...",
            "payload_hash": "...",
            "payload": { ... }
        }
    ]
}
```

### Synchronizer API Calls (Main App → Synchronizer)

The `SynchronizerController` proxies HTTP calls using Laravel's HTTP client:

```php
Http::withToken($cfg['token'])->baseUrl($cfg['url'])->timeout(10)->acceptJson()
```

| Endpoint | Purpose |
|----------|---------|
| `GET  /api/connections` | List connections |
| `POST /api/connections` | Create connection |
| `PUT  /api/connections/{id}` | Update connection |
| `DELETE /api/connections/{id}` | Delete connection |
| `POST /api/connections/test` | Test connection validity |
| `POST /api/connections/{id}/run` | Start sync job |
| `POST /api/connections/{id}/stop` | Stop sync job |
| `GET  /api/connections/{id}/runs` | List job runs |
| `GET  /api/runs/{runId}/status` | Job status |
| `GET  /api/runs/{runId}/logs` | Job logs |

**Docker networking:** `localhost:port` in server URL is rewritten to `host.docker.internal:port` to allow main app container to reach synchronizer.

**Fallback:** If synchronizer is unreachable, Setup Assistant falls back to `Account::exists()` as proxy for "connections configured" state.

### Channel Type Mapping

| System | channel_type stored |
|--------|-------------------|
| WHMCS | `ticket` |
| MetricsCube | `ticket` |
| Slack | `slack` |
| Discord | `discord` |
| IMAP / Gmail | `email` |

### Integration Registry

`IntegrationRegistry::get($systemType)` returns the integration class instance. Each integration provides:
- `iconHtml(size, label)` — SVG icon for display in UI
- Connection config form schema (used by synchronizer)

Located in `app/Integrations/`. Known types: `WhmcsIntegration`, `MetricscubeIntegration`, `SlackIntegration`, `DiscordIntegration`, `ImapIntegration`, `GmailIntegration`.

---

## Application Sections (BETAv1)

### Dashboard (`/`)

**Controller:** `DashboardController::index()`
**Purpose:** Executive overview — 4 stat cards + most active contacts + team members + recent notes.

- Date range selector (default last 30 days)
- Stats: conversations (period), new companies, new people, active people
- Most active contacts: top 8 people by activity count in period (excluding filtered contacts)
- Most active team members: top 8 `is_team_member=true` identities
- Recent notes (10) with entity link
- Create buttons for Company / Person (gated `@can('data_write')`)

---

### Companies (`/companies`)

**Controller:** `CompanyController`
**Tabs:** Clients / Our Organization

**Index:**
- Sortable columns: name, primary domain, contacts count, channel types, brand scores, updated_at
- Filter panel: domain text, min contacts, channel type, brand stage, brand score min/max, updated date range
- Filtered indicator badge (shows count of filtered companies, toggle to show/hide them)
- Create button (gated)

**Show (`/companies/{id}`):**
- Breadcrumb back-link (resolveBackLink from Referer)
- Card sections:
  1. **Domains & Aliases** — list with "set primary" action, add/remove (gated)
  2. **Linked People** — company_person pivot table: role, started_at, ended_at; link/unlink (gated)
  3. **Brand Statuses** — per brand product: stage dropdown + score 0-100 + notes + last evaluated; edit popup (gated)
  4. **Accounts** — external system accounts (system_type, system_slug, external_id); add/remove (gated)
  5. **Recent Conversations** — last 10, with channel badge, subject, message count, date
  6. **Notes** — notes-section component (gated write)
  7. **Activity Timeline** — AJAX cursor pagination, `showCompanyLink=false`

**Forms:** `company-form.blade.php` — name, primary domain, timezone. Separate `/create` and `/{id}/edit` routes.

---

### People (`/people`)

**Controller:** `PersonController`
**Tabs:** Clients / Our Organization

**Index:**
- Sortable: name, updated_at, identity count
- Filter panel: last contact date, has_company, channel type
- Bulk bar: "Mark as Our Org" (clients tab) / "Unmark Our Org" (our_org tab)
- Our Org rows: tinted `bg-brand-50/60` background
- No per-row Our Org toggle — bulk only

**Show (`/people/{id}`):**
- Card header gradient: `from-brand-600 to-brand-800` (brand magenta)
- "Unmark Our Org" / "Our Org" button in header (reloads page on success)
- Card sections:
  1. **Identities** — type (email/slack_user/discord_user), value, system_slug, is_team_member, is_bot; add/remove (gated)
  2. **Companies** — linked via company_person pivot; manage links (gated)
  3. **Hourly Activity** — bar chart (last 90 days, by hour of day)
  4. **Activity Availability** — heatmap grid (day-of-week × hour)
  5. **Recent Conversations** — last 10
  6. **Notes** — notes-section component
  7. **Activity Timeline** — AJAX cursor pagination, `showPersonLink=false`

**Forms:** `person-form.blade.php` — first_name, last_name, is_our_org checkbox.

**Our Org routes:**
- `POST people/bulk-unmark-our-org` → `PersonController::bulkUnmarkOurOrg()`
- `POST people/{person}/unmark-our-org` → `PersonController::unmarkOurOrg()`
- `POST people/bulk-mark-our-org` → `PersonController::bulkMarkOurOrg()`

---

### Conversations (`/conversations`)

**Controller:** `ConversationController`
**Tabs:** Assigned / Unassigned / Filtered

**Index:**
- Filter panel: date range (last_message_at), channels multi-select dropdown (Alpine.js dropdown with checkboxes)
- Bulk selection: checkbox column + select-all → bulk bar → "Filter…" action (gated)
- **Subject click** → opens conversation quick-view popup (`?preview=1`)
- Per-row "Filter" button (gated) → opens filter-rule modal

**Show (`/conversations/{id}`):**
- Messages partial: chat layout (Slack/Discord) or bubble layout (email/ticket)
- Slack messages: `<@USERID>` resolved to display names via `slackMentionMap`
- Discord messages: `<@ID>` resolved via `discordMentionMap`
- Participants list, notes section

**Quick-view modal (`/conversations/{id}/modal`):**
- `?preview=1` — last 3 messages (email/ticket) or last 20 top-level messages (Slack/Discord) + their replies
- `?date=YYYY-MM-DD` — messages for that date (used from activity timeline, limit 10)
- No params — 1 message (used from activity timeline for single-activity view)
- Modal auto-scrolls to bottom (`data-scroll-bottom` marker)

**Filter-rule modal (`/conversations/filter-modal?ids[]=...`):**
- Tag-input for domain, email, subject rule types
- Posts to `filtering.apply-rule` with `rule_values[]` array

**Filtering logic:**
- `$filteredQuery` includes `filter_subjects` from SystemSetting — **always include this**, tab counts derive from it
- "Filtered" tab = archived + matches filter rules
- Other tabs = non-archived, exclude filtered conversations

---

### Activity (`/activity`)

**Controller:** `ActivityController`
**Tabs:** All / Conversations / Activity (Activity tab adds `exclude_type=conversation`)

**Layout:**
- Always-visible search bar: `[Filters btn] [Search input] [Search btn] [Clear btn]`
- "Filters" button: brand-primary + count badge when active
- Collapsible filter panel: date range, channels (inline checkbox labels), activity type (inline checkbox labels)
- Timeline + infinite scroll (AJAX cursor pagination, 25 items)

**AJAX endpoint:** `GET /activity/timeline?cursor=...&q=...&type=...&systems[]=...&from=...&to=...&exclude_type=...`

**Search:** `q` param → searches description, company name, person name (ilike)

**Rules:**
- **Never** pass `showPersonLink` or `showCompanyLink` to `activity/partials/timeline-items.blade.php`

---

### Configuration: Setup Assistant (`/configuration/setup-assistant`)

**Controller:** `SetupAssistantController`
**Purpose:** Checklist to verify system is properly configured.

| # | Item | Status logic |
|---|------|-------------|
| 1 | System up to date | Always `completed` |
| 2 | Add connector server | `active` if no SynchronizerServer row; `completed` otherwise |
| 3 | Configure connections | Tries HTTP GET to synchronizer `/api/connections` (5s timeout), falls back to `Account::exists()`; `active` if 0 connections |
| 4 | Configure mapping | Checks link ratios for accounts→companies AND identities→people (excl. bots); <50%→`active`, <80%→`partially_active`, ≥80%→`completed` |
| 5 | Set your organization | `active` if no `Person.is_our_org=true` AND no `Identity.is_team_member=true`; `completed` otherwise |

**Display sections:**
- "Requires Your Attention" — non-completed items (hidden if empty → green "fully operational" banner)
- "Completed" — completed items

**Sidebar dot:**
- Any `active` → red dot in sidebar + red dot in top Configuration menu
- Only `partially_active` → yellow dot in sidebar only
- All completed → green dot

**Cache:** `layout.setup_status` (60s, DB-only). Clear with `php artisan cache:clear` after structural changes.

---

### Configuration: Data Relations (`/data-relations`)

**Controller:** `DataRelationsController`

**Overview:** Stats on unlinked accounts/identities/conversations per system.

**Mapping (`/data-relations/mapping/{type}/{slug}`):**
- **WHMCS / MetricsCube** (account-based): contacts shown **inline** under account rows. No separate People tab. No orphan contacts section.
- **Slack / Discord / IMAP** (identity-based): identities paginated, link to person modal
- `$identitiesByExtId` must be built over **all** accounts (not paginated subset)
- Identity-to-account matching: (1) `meta_json['account_external_id']`, (2) primary email via `account.meta_json['email']`

**Actions:**
- Link account → company (search company, create new option)
- Link identity → person (search person, create new option)
- Toggle bot flag on identity
- Auto-resolve (runs `AutoResolver::resolveAll()`)

---

### Configuration: Filtering (`/data-relations/filtering`)

**Controller:** `FilteringController`
**Tabs:** Domains / Emails / Subjects / Contacts

- **Domains/Emails/Subjects:** textarea with newline/comma parsing, lowercase, unique, stored in `SystemSetting` JSON
- **Contacts:** filterable people list, add/remove from `filter_contacts` table
- `applyRule()` accepts `rule_values[]` array (multiple values); falls back to single `rule_value` for backwards compat
- Conversation `archiveWithRule()` same pattern

---

### Configuration: Our Organization (`/data-relations/our-company`)

**Controller:** `OurCompanyController`
**Tabs:** Team Domains / Team Members

- **Team domains:** text input, saves to SystemSetting, marks matching identities as `is_team_member=true`
- **Team members:** people with `is_our_org=true` OR linked `is_team_member` identity

---

### Configuration: Synchronizer (`/synchronizer`)

**Controller:** `SynchronizerController`

- **index:** Lists connections from synchronizer `/api/connections`, handles timeout gracefully
- **Connection CRUD:** Create/edit/delete proxied to synchronizer; server picks first registered `SynchronizerServer`
- **Test:** POST to synchronizer `/connections/test`
- **Run/Stop/Kill-all/Run-all:** Execute/manage sync jobs
- **Runs/Logs:** Job history and log viewer

**Servers (`/synchronizer/servers`):**
- CRUD for `SynchronizerServer` model (url, api_token, ingest_secret, name)
- Test connectivity (ping)
- Registration wizard (step-by-step: server URL → install script → poll registration)

---

### Configuration: Team Access (`/configuration/team-access`)

**Controller:** `TeamAccessController` / `UsersController` / `GroupsController`
**Tabs:** Users / Groups

**Groups:** Manage permission flags:
- `browse_data` — read companies/people/conversations/activity
- `data_write` — create/edit/delete companies, people, conversations; manage identities/domains/accounts
- `notes_write` — create/delete notes on any entity
- `analyse` — (reserved)
- `configuration` — all `/configuration/*` routes

**`GroupsController::permLabels()`** — static method, always pass as `$permLabels` to views.

**Rules:**
- User cannot demote themselves from Admin group
- Cannot delete group if users are assigned
- Sidebar order: Setup Checklist → General Settings → Team Access

---

## Unified UI Patterns

### PATTERN 1 — Activity Modal (Quick-view Popup)

**When to use:** Any list row that opens a quick-view without navigating away.

**Component:** `resources/views/components/activity-modal.blade.php` (global, included once in layout)

**Activation:**
```blade
<button type="button"
        onclick="openActivityModal(this)"
        data-modal-src="{{ route('some.modal', $item) }}">
    Label
</button>
```

Or programmatically:
```js
openActivityModal({ dataset: { modalSrc: '/some/url?params=...' } });
```

**Behavior:**
1. AJAX fetch `data-modal-src`
2. Insert HTML into `#activity-modal-body`
3. Re-execute `<script>` tags (Alpine.js does NOT auto-init in AJAX content)
4. Scroll to bottom if content has `[data-scroll-bottom]` attribute
5. Close on overlay click, ESC key, or `closeActivityModal()`

**Modal content template:**
```blade
<div class="p-5" data-scroll-bottom>   {{-- data-scroll-bottom optional: scrolls modal to bottom --}}
    <h3>...</h3>
    {{-- content --}}
</div>
```

**Important:** Alpine.js does NOT auto-initialize inside AJAX-loaded modal content. Use plain JS with `addEventListener` only.

---

### PATTERN 2 — Filter Panel

**When to use:** Any index page with filterable data.

**Structure:**
```blade
{{-- Filters button (turns brand-primary with count badge when active) --}}
<button type="button" onclick="toggleFilterPanel()"
        class="btn {{ $activeFilterCount > 0 ? 'btn-primary' : 'btn-secondary' }}">
    Filters
    @if($activeFilterCount > 0)
        <span class="ml-0.5 bg-white/25 text-white text-xs ...">{{ $activeFilterCount }}</span>
    @endif
</button>

{{-- Collapsible panel (shown if filters active) --}}
<div id="filter-panel" class="{{ $activeFilterCount > 0 ? '' : 'hidden' }} card p-4 mb-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        {{-- date range, dropdowns, checkboxes --}}
    </div>
    <div class="mt-3 flex justify-end gap-2">
        {{-- Clear filters link + Apply button --}}
    </div>
</div>
```

**Filter input types used in this project:**
- **Date range:** flatpickr via `drp.init()`, two hidden inputs (`f_date_from`, `f_date_to`)
- **Multi-select dropdown:** Alpine.js dropdown with checkboxes (used for Channels in conversations)
- **Inline checkboxes:** label chips that highlight on check (used for activity types, channels in activity)
- **Text input:** domain, email, name search

**Date range — critical bug prevention:**
Laravel's `ConvertEmptyStringsToNull` middleware converts empty `value=""` hidden inputs to `null`.
```php
// WRONG — $f_date_from may be null, null !== '' is true → crashes with whereDate(null)
if ($f_date_from !== '') { ... }

// CORRECT
if (!empty($f_date_from)) { ... }
```

---

### PATTERN 3 — Bulk Selection

**When to use:** Any table where actions can be applied to multiple rows.

**Structure:**
```blade
{{-- Wrap table in form --}}
<form id="entity-bulk-form" method="POST" action="#">
@csrf

{{-- Bulk bar (hidden until rows selected) --}}
<div id="entity-bulk-bar" class="hidden items-center gap-3 px-4 py-2 border-b bulk-bar">
    <span id="entity-bulk-count" class="text-sm font-medium bulk-bar-text"></span>
    @can('data_write')
    <button type="button" onclick="entityBulkAction()" class="btn btn-danger btn-sm">Action…</button>
    @endcan
    <button type="button" onclick="entityClearSelection()" class="text-xs text-gray-500">Clear</button>
</div>

{{-- Table --}}
<table>
    <thead class="tbl-header">
        <tr>
            <th class="px-3 py-2.5 w-8">
                <input type="checkbox" id="entity-select-all" onchange="entityToggleAll(this)">
            </th>
            {{-- other headers --}}
        </tr>
    </thead>
    <tbody>
        @foreach($items as $item)
        <tr class="tbl-row">
            <td class="px-3 py-3">
                <input type="checkbox" name="ids[]" value="{{ $item->id }}"
                       class="entity-row-check" onchange="entityUpdateBulkBar()">
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
</form>

<script>
function entityUpdateBulkBar() {
    const checked = document.querySelectorAll('.entity-row-check:checked');
    const bar = document.getElementById('entity-bulk-bar');
    if (checked.length > 0) {
        bar.classList.remove('hidden'); bar.classList.add('flex');
        document.getElementById('entity-bulk-count').textContent = checked.length + ' selected';
    } else {
        bar.classList.add('hidden'); bar.classList.remove('flex');
    }
}
function entityToggleAll(cb) {
    document.querySelectorAll('.entity-row-check').forEach(c => c.checked = cb.checked);
    entityUpdateBulkBar();
}
function entityClearSelection() {
    document.querySelectorAll('.entity-row-check, #entity-select-all').forEach(c => c.checked = false);
    entityUpdateBulkBar();
}
</script>
```

---

### PATTERN 4 — Tag-input Multi-value (Filter Modals)

**When to use:** Any modal where users enter multiple values (domains, emails, subjects).

**Key rules:**
- AJAX-loaded modal content → **no Alpine.js** → use plain DOM `createElement` + `addEventListener`
- **Never** build `onkeydown="handler(event, 'value')"` inline attributes — `JSON.stringify` produces double-quoted strings that break HTML attribute parsing
- Enter or comma adds a tag (for domain/email); Enter only for subject (no comma-split)
- Backspace on empty input removes last tag
- `fmBeforeSubmit()` syncs hidden `rule_values[]` inputs before form POST

**Backend:** `FilteringController::applyRule()` and `ConversationController::archiveWithRule()` accept `rule_values[]` array, fall back to single `rule_value` for backwards compat.

---

### PATTERN 5 — Table Section

**Standard table in a card:**
```blade
<div class="card-xl-overflow">    {{-- use card-xl-overflow when card contains a table --}}
    <div class="card-header">
        <span class="section-header-title">Title</span>
        @can('data_write')
        <a href="{{ route('entity.create') }}" class="btn btn-sm btn-primary">Add</a>
        @endcan
    </div>
    <table class="w-full text-sm">
        <thead class="tbl-header">
            <tr>
                <th class="px-4 py-2.5 text-left">Column</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
            <tr class="tbl-row">
                <td class="px-4 py-3">...</td>
            </tr>
            @empty
            <tr>
                <td class="px-4 py-8 text-center empty-state italic">No items.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @if($items->hasPages())
    <div class="px-4 py-3 border-t border-gray-100">{{ $items->links() }}</div>
    @endif
</div>
```

**Row actions (desktop + mobile "..." dropdown):**
```blade
<td class="px-4 py-3 text-right">
    {{-- Desktop --}}
    <div class="row-actions-desktop items-center justify-end gap-1.5">
        <a href="{{ route('entity.edit', $item) }}" class="row-action text-xs">Edit</a>
        @can('data_write')
        <button onclick="..." class="row-action-danger text-xs">Delete</button>
        @endcan
    </div>
    {{-- Mobile --}}
    <div class="row-actions-mobile relative" x-data="{open:false}" @click.outside="open=false">
        <button @click="open=!open" class="...">···</button>
        <div x-show="open" x-cloak class="absolute right-0 top-full mt-1 ...">
            <a href="...">Edit</a>
        </div>
    </div>
</td>
```

---

### PATTERN 6 — Create/Edit Form Page

**Separate pages for create and edit — never inline forms inside tables.**

```blade
{{-- page-header with back link --}}
<div class="page-header">
    <div>
        <a href="{{ route('entity.index') }}" class="page-breadcrumb-back">← Back</a>
        <h1 class="page-title">Create Entity</h1>
    </div>
</div>

<div class="card p-6 max-w-2xl">
    <form method="POST" action="{{ route('entity.store') }}">
        @csrf
        {{-- For edit: --}}
        {{-- @method('PUT') --}}

        <div class="space-y-4">
            <div>
                <label class="label">Field Name</label>
                <input type="text" name="field" class="input w-full" value="{{ old('field', $entity->field ?? '') }}">
                @error('field')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex gap-2 mt-6">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('entity.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
```

---

### PATTERN 7 — Sidebar Dot Status

**Every sidebar link that can show a status dot:**

```blade
<a href="..." class="sidebar-link {{ $isActive ? 'is-active' : '' }}">
    <svg class="sidebar-icon">...</svg>
    <span class="flex-1">Label</span>    {{-- flex-1 pushes dot to right edge --}}
    @if($hasDot)
        <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }} shrink-0"></span>
    @endif
</a>
```

- Dot size: **`w-1.5 h-1.5`** — never `w-2 h-2` or other sizes
- No `ml-auto` on dot — `flex-1` span handles alignment
- Colors: `bg-red-500` (active), `bg-amber-400` (partially_active), `bg-green-500` (completed)

---

### PATTERN 8 — Activity Timeline + AJAX Cursor Pagination

**Initial render (SSR):**
```blade
<div class="timeline-grid ...">
    @include('partials.timeline-items', [
        'activities' => $timelinePage->items(),
        'nextCursor' => $timelinePage->nextCursor()?->encode(),
    ])
</div>
```

**AJAX load-more:**
```js
// Sentinel div triggers fetch when visible
// Fetch: GET /entity/{id}/timeline?cursor=ENCODED_CURSOR
// Response: partial HTML with next items + new sentinel
```

**Timeline partial rules:**
- Never pass `showPersonLink` or `showCompanyLink` to global activity timeline
- The partial uses `$activity->display->propertyName` (accessor set by `prepareTimelineDisplay()`)
- `prepareTimelineDisplay()` is in `BuildsConvSubjectMap` concern, call it before passing items to view

---

### PATTERN 9 — Notes Section

**Component:** `<x-notes-section :notes="$notes" linkableType="App\Models\Company" :linkableId="$company->id" />`

```blade
<x-notes-section
    :notes="$notes"
    linkable-type="App\Models\Company"
    :linkable-id="$company->id"
/>
```

- Yellow-tinted card, scrollable list (max-h-72)
- Add note: textarea POST to `notes.store`
- Delete: X button POST DELETE to `notes.destroy`
- All write UI gated `@can('notes_write')`

---

### PATTERN 10 — Breadcrumb Back-link

**Controller:**
```php
$backLink = $this->resolveBackLink($request);
// Returns ['url' => ..., 'label' => 'Entity Name'] or null
// Returns null if referer === current page (prevents self-link after reload)
```

**Blade:**
```blade
<nav aria-label="Breadcrumb" class="page-breadcrumb">
    @if($backLink ?? null)
        <a href="{{ $backLink['url'] }}">{{ $backLink['label'] }}</a>
        <span class="sep">/</span>
    @endif
    <a href="{{ route('entity.index') }}">Entities</a>
    <span class="sep">/</span>
    <span class="cur" aria-current="page">{{ $entity->name }}</span>
</nav>
```

Supported referer patterns: `/companies/{id}`, `/people/{id}`, `/conversations/{id}`

---

### PATTERN 11 — Alpine.js Dropdown Multi-select

**For channel/system selectors on index filter panels (not in AJAX-loaded modals):**

```blade
<div x-data="{
    open: false,
    selected: {{ json_encode($activeValues) }},
    label() {
        if (!this.selected.length) return 'All';
        return this.selected.length + ' selected';
    },
    toggle(val) {
        const i = this.selected.indexOf(val);
        if (i === -1) this.selected.push(val); else this.selected.splice(i, 1);
    }
}" @click.outside="open = false">
    <label class="label mb-1">Label</label>
    <div class="relative">
        <button type="button" @click="open = !open"
                class="input w-full flex items-center justify-between gap-2 cursor-pointer"
                :class="selected.length ? 'border-brand-400 bg-brand-50 text-brand-800' : ''">
            <span x-text="label()"></span>
            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform">...</svg>
        </button>
        <div x-show="open" x-cloak class="absolute z-30 mt-1 w-full bg-white border ... rounded-xl shadow-lg py-1">
            @foreach($options as $opt)
            <label class="flex items-center gap-2.5 px-3 py-2 cursor-pointer hover:bg-gray-50"
                   :class="selected.includes('{{ $opt->value }}') ? 'bg-brand-50' : ''">
                <input type="checkbox" name="field[]" value="{{ $opt->value }}"
                       {{ in_array($opt->value, $activeValues) ? 'checked' : '' }}
                       @change="toggle('{{ $opt->value }}')">
                <span>{{ $opt->label }}</span>
            </label>
            @endforeach
        </div>
    </div>
</div>
```

---

## Blade / Frontend Rules

### No `@php` in Blade Views

**Zero `@php` blocks in any view file.** All logic belongs in:
- Controllers (passed as view data)
- `App\Providers\AppServiceProvider` View Composer for `layouts.app`
- Model methods / accessors

There are **no exceptions** — `isolated-html` was converted to a proper component class (`App\View\Components\IsolatedHtml`) which generates `uniqid()` in the constructor.

### Layout View Composer

`AppServiceProvider` registers a View Composer for `layouts.app` that provides:
`$topSections`, `$sidebarItems`, `$syncItems`, `$drItems`, `$isConfigRoute`, `$onMapping`, `$currentMapping`, `$mainMargin`, `$disabledMsg`, `$taActive`, `$segActive`, `$configNeedsAttention`

Do **not** put this logic back into the Blade layout.

### Alpine.js Scope

Alpine.js **only works on page-rendered content**. It does **not** auto-initialize inside AJAX-loaded content (modals, partial reloads). For interactive elements in AJAX content, use plain JS with `createElement` / `addEventListener`.

### Inline Attribute Handler Quoting Bug

Never do this in Blade:
```blade
{{-- BROKEN: JSON.stringify produces "value" (double-quoted) inside a double-quoted attribute --}}
<input onkeydown="handler(event, {{ json_encode($val) }})">
```

Always use `addEventListener` in a closure instead.

---

## CSS / Style System

All global styles in **`resources/css/app.css`**. Do not invent per-page inline styles — use the class. When a pattern appears 2+ times, add a class.

### Class Inventory

| Class | Use |
|-------|-----|
| `.card` | White bg, gray border, `0.5rem` radius |
| `.card-xl` | Larger card — show-page main sections |
| `.card-xl-overflow` | `.card-xl` + `overflow: hidden` — use when card contains a table |
| `.card-header` | Top row inside card — flex, space-between, border-bottom |
| `.card-inner` | Inner section divider — top border only |
| `.section-header` | Title row inside card section — flex, space-between, border-bottom |
| `.section-header-title` | Text inside `.section-header` — `0.875rem`, semibold |
| `.page-header` | Top row of every page — flex, space-between, `1.25rem` bottom margin |
| `.page-title` | Text inside `.page-header` — `1.125rem`, bold |
| `.page-breadcrumb` | Breadcrumb nav — `<nav aria-label="Breadcrumb">` |
| `.tbl-header` | `<thead>` style — gray bg, small caps |
| `.tbl-row` | `<tbody><tr>` — top border, hover bg with left accent |
| `.bulk-bar` | Bulk action bar — amber tinted |
| `.bulk-bar-text` | Text inside bulk bar |
| `.divider` | Horizontal rule — top border only |
| `.modal-center` | Absolutely centered modal |
| `.modal-overlay` | Full-screen glassmorphism scrim |
| `.sidebar` | Dark sidebar shell |
| `.sidebar-section` | Muted uppercase section label |
| `.sidebar-link` | Nav link — `is-active` for active, `is-disabled` for disabled |
| `.sidebar-icon` | Icon inside `.sidebar-link` — color managed by parent state |
| `.sidebar-divider` | Horizontal rule between sidebar sections |
| `.alert-warning` | Amber notice box |
| `.alert-success` | Green notice box |
| `.alert-danger` | Red notice box |
| `.code-block` | Dark monospace block |
| `.btn` | Base button |
| `.btn-sm` | Smaller button |
| `.btn-primary` | Brand-filled button |
| `.btn-secondary` | White/bordered button |
| `.btn-danger` | Red outlined (Filter, destructive) |
| `.btn-muted` | Gray muted button |
| `.btn-org` | Indigo tint (Our Organization actions) |
| `.input` | Text input |
| `.label` | Input label — small caps, `0.75rem` |
| `.badge` | Inline pill badge |
| `.badge-{gray,blue,green,red,yellow,brand}` | Colored badge variants |
| `.row-action` | Gray text, hover brand |
| `.row-action-danger` | Gray text, hover red |
| `.empty-state` | Gray italic empty message |

### Brand Colors

| Token | Hex | Usage |
|-------|-----|-------|
| **Primary** | `#A40057` | Logo, primary buttons, active states. `--color-brand-*` shades from brand-50 to brand-900. |
| **Dark base** | `#212731` | Top header, sidebar, dark UI elements. |
| **Light accent** | `#F1FFFA` | AI-related features. Light shades only. |

`--color-brand-600` = `#A40057` (anchor point of the scale).

### Sidebar

Always `.sidebar-link` / `.sidebar-icon` / `.sidebar-section` / `.sidebar-divider` — never raw Tailwind color classes. Active state = add `is-active`. Disabled = `is-disabled`. Icon color managed by CSS, not conditional classes.

### Rules

1. Never `style=` for layout/color a utility class covers.
2. Bulk bars → `.bulk-bar` + `.bulk-bar-text`. Never inline amber.
3. Card with table → `.card-xl-overflow`.
4. Modal centering → `.modal-center`.
5. Page titles → `.page-header` + `.page-title`.
6. Section headers → `.section-header` + `.section-header-title`.
7. New repeated pattern → add class to `app.css` before second use.

---

## Critical Rules & Known Gotchas

### PersonController
- `personActivitiesQuery()` must **not** use `.distinct()` — breaks PostgreSQL `ORDER BY` (SQLSTATE[42P10]).

### Conversations
- `$filteredQuery` in `ConversationController::index()` **must** include `filter_subjects` from `SystemSetting`. Tab counts derive from it.
- Date filters: use `!empty($f_date_from)` not `$f_date_from !== ''` — `ConvertEmptyStringsToNull` middleware converts empty hidden inputs to `null`.

### Data Relations Mapping
- WHMCS / MetricsCube: contacts shown **inline** under account rows. No separate People tab. No orphan contacts section.
- `$identitiesByExtId` built over **all** accounts, not paginated subset.

### Activity Widget
- **Never** pass `showPersonLink` / `showCompanyLink` to `activity/partials/timeline-items.blade.php`.
- Global activity list must not show entity links — these are for entity-specific timelines only.

### Discord Avatars
- Fallback when `meta_json['avatar']` empty: `https://cdn.discordapp.com/embed/avatars/{N}.png`
- `N = (int) substr($discordUserId, -1) % 5`
- Implemented in: `DataRelationsController`, `ConversationMessage::chatAvatarUrl()`, `ConversationController` participant loop.

### Slack Mention Resolution
- Pattern: `<@U0AGR11DLE4>` (uppercase user ID)
- DB: `identities.type = 'slack_user'`, `value_normalized = 'u0agr11dle4'` (lowercase)
- `slackMentionMap`: `value_normalized → display_name` (from `meta_json.display_name`)
- Built in `show()` and `modal()` of `ConversationController`, passed to messages partial as `$slackMentionMap`
- `resolveMentions($text, $discordMap, $slackMap)` — always pass both maps

### Filter Modal JS
- Modals are AJAX-loaded → Alpine.js does not initialize inside them
- All tag-input JS must use DOM `createElement` + `addEventListener`
- `fmBeforeSubmit()` / `pfmBeforeSubmit()` / `cfmBeforeSubmit()` sync `rule_values[]` hidden inputs before form submit

### Breadcrumb Back-link
- `Controller::resolveBackLink()` uses HTTP Referer
- Returns `null` if Referer === current page (prevents self-link after page reload from form action)

### Our Org
- `Person.is_our_org` = canonical flag
- `Identity.is_team_member` = derived (set by team domain matching)
- Bulk + individual mark/unmark operates on `Person.is_our_org` only
- Per-row toggle removed from people/index — bulk only

### Person Show Card
- Header gradient: `from-brand-600 to-brand-800` (brand magenta) — never dark indigo or arbitrary colors

### Conversation Preview Modal
- `?preview=1`: last 3 msgs (email/ticket), last 20 top-level (chat) + replies
- Messages in `orderByDesc` order (newest first) — **do not reverse**
- Auto-scrolls to bottom via `data-scroll-bottom` marker

---

## ACL / Permissions

### Permission Flags
`browse_data`, `data_write`, `notes_write`, `configuration`

**Key:** `App\Models\User` / `App\Models\Group` (pivot: `group_user`)
**Middleware:** `App\Http\Middleware\CheckPermission` — registered as `permission:{flag}`
**Gates:** Defined in `AppServiceProvider` based on user's group permissions.

### Blade Gating Rules

**Never show write UI to restricted users — hide completely, never rely on 403.**

- Create/edit/delete buttons → `@can('data_write') … @endcan`
- Notes write forms → `@can('notes_write') … @endcan`
- Configuration pages are route-protected; no extra Blade gating needed.

### Tests

Helpers in `tests/TestCase.php`: `actingAsAdmin()`, `actingAsViewer()`, `actingAsAnalyst()`.
All feature tests call `$this->actingAsAdmin()` in `setUp()`.
ACL tests: `tests/Feature/AuthAclTest.php`.

---

## Frontend

Tailwind CSS v4 compiled by Vite. Assets in `public/build/`. `@vite` directive in `layouts/app.blade.php`.

`flatpickr` — date pickers (only runtime JS dependency outside of Alpine.js which is bundled).

---

## Tests

PHPUnit with two suites: `Unit` (`tests/Unit/`) and `Feature` (`tests/Feature/`)
Test database: SQLite in-memory (`:memory:`) — configured in `phpunit.xml`
