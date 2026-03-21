# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

---

## Project Overview — BETAv1

**Contact Monitor** is a multi-channel contact hub that centralizes a company's communications with clients across email, Gmail, Slack, Discord, tickets, and other integrations. It links conversations and activities to company and person profiles, tracks brand product pipeline stages, and provides a unified activity timeline across all channels.

**Stack:** Laravel 12 · PHP 8.3 · PostgreSQL 16 · Tailwind CSS v4 · Vite · **Vue 3 + Inertia.js** (all pages) · **Laravel Reverb** (WebSocket for Analyze chat streaming)

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

# Run tests (PostgreSQL — uses contact-monitor-test database)
docker exec contact-monitor_app php artisan test

# Run a specific test file
docker exec contact-monitor_app php artisan test tests/Feature/SetupTest.php

# Rebuild frontend assets (includes Vue compilation)
docker exec contact-monitor_app npm run build

# Start Reverb WebSocket server (dev only — production: run as daemon)
docker exec contact-monitor_app php artisan reverb:start

# Deploy to production
./ops/deploy-production.sh
```

---

## Architecture

### Docker

- `contact-monitor_app` — Laravel app, port 8090
- `contact-monitor_db` — PostgreSQL 16, port 5434 on host
- `contact-monitor-synchronizer_app` — separate synchronizer service on port 8080

### Installation Configuration Rule

**The `.env` file is the ONLY place a user touches before installation. No exceptions.**

- Never require editing `docker-compose.yml`, `Dockerfile`, or any other file
- Never duplicate configuration between `.env` and any other file
- All docker-compose values that a user might want to change **must** come from `.env` variables (e.g. `${APP_PORT:-8090}`)
- `APP_URL` has no port — port lives only in `APP_PORT`. `AppServiceProvider` appends it automatically on localhost.

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

  analyse {                   ← DB permission flag is 'analyse', URL prefix is /analyze
    /analyze/*                → Analyze chat UI (Inertia/Vue)
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
  ├─ activities             (meta_json payload, occurred_at)
  ├─ mergedInto             (BelongsTo Company — self-referential, nullable merged_into_id)
  └─ mergedCompanies        (HasMany Company — companies merged into this one)

people
  ├─ identities             (type: email / slack_id / discord_id / phone / linkedin / twitter; value_normalized auto-lowercased)
  ├─ company_person pivot
  ├─ activities
  ├─ mergedInto             (BelongsTo Person — self-referential, nullable merged_into_id)
  └─ mergedPeople           (HasMany Person — people merged into this one)

notes → note_links          (polymorphic: Company / Person / Conversation)

brand_products → company_brand_statuses
synchronizer_servers        (url, api_token, ingest_secret)
system_settings             (key-value JSON store)
filter_contacts             (pivot: person_id)
audit_logs                  (actor_user_id, entity_type, entity_id, action, message)
smart_note_filters          (type, criteria jsonb, as_internal_note, is_active)
smart_notes                 (filter_id, source_type, content, sender_*, status, segments_json jsonb, softDeletes)
```

### Merge (Companies & People)

Merge is **non-destructive and visual only** — data is never moved or deleted.

- `merged_into_id` FK (self-referential, nullOnDelete) on both `companies` and `people`
- `Company::scopeNotMerged()` / `Person::scopeNotMerged()` — `whereNull('merged_into_id')` — **always apply on list/count queries**
- `mergedInto()` BelongsTo, `mergedCompanies()` / `mergedPeople()` HasMany
- When showing a merged entity's page: display amber banner "This company/person has been merged into [Primary]" with link
- Conversations: resolve merged company → primary via `setRelation('company', $conv->company->mergedInto)` in controllers
- People index: eager-load `companies.mergedInto`, resolve in controller (`map(fn($c) => $c->mergedInto ?? $c)->unique('id')`)
- Company show: services (`$serviceSystems`) must include accounts from `$company->mergedCompanies` as well
- Activity search: add `whereNull('merged_into_id')` to `orWhereHas('company', ...)` and `orWhereHas('person', ...)`
- BuildsConvSubjectMap: `Person::notMerged()->where('is_our_org', true)` for our-org detection

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

**SmartNoteFilter**
- `typeLabel()` — human-readable type name
- `summaryLabel()` — short criteria description for list display

**SmartNote**
- `sourceLabel()` — human-readable source type
- `scopeUnrecognized()` / `scopeRecognized()` — filter by status

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
**Purpose:** Executive overview — 3 stat cards + most active people + team members + recent notes.

- Date range selector (default last 30 days)
- Stats: conversations (period), new companies, new people
- Most active people: top 8 by conversation count in period (excluding filtered contacts)
- Most active team members: top 8 `is_our_org=true` people by conversation activity
- Recent notes (10) with entity link

---

### Companies (`/companies`)

**Controller:** `CompanyController`
**Tabs:** Clients / Our Organization

**Index:**
- Sortable columns: name, primary domain, contacts count, last conversation, brand scores, updated_at
- Filter panel: domain text, min contacts, channel type, brand stage, brand score min/max, updated date range
- Filtered indicator badge (shows count of filtered companies, toggle to show/hide them)
- Create button (gated)

**Show (`/companies/{id}`):**
- Breadcrumb back-link (resolveBackLink from Referer)
- 3-column grid layout: left 1/3 (company card with analysis, contacts, accounts, notes, merged), right 2/3 (segmentation, services, activity timeline)
- Domains & Aliases managed via popup modals from the company card header
- Card sections:
  1. **Company Card** — name, primary domain, timezone; domains/aliases via popup modals (gated)
  2. **Company Analysis** — AI analysis card with key fields, entity counts, run metadata
  3. **Linked People** — company_person pivot table: role, started_at, ended_at; link/unlink (gated)
  4. **Accounts** — external system accounts (system_type, system_slug, external_id); add/remove (gated)
  5. **Notes** — notes-section component (gated write)
  6. **Brand Statuses** — per brand product: stage dropdown + score 1-10 + notes + last evaluated; edit popup (gated)
  7. **Services** — external system service accounts
  8. **Activity Timeline** — AJAX cursor pagination, `showCompanyLink=false`

**Forms:** Separate `/create` and `/{id}/edit` Vue pages — name, primary domain, timezone.

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
- Card header gradient: `from-brand-600 to-brand-800` for Our Org people, `from-[#1c2028] to-[#252d3b]` for regular people
- "Unmark Our Org" / "Our Org" button in header (reloads page on success)
- Card sections:
  1. **Identities** — type (email/slack_id/discord_id/phone/linkedin/twitter), value; add/remove (gated, add form has Type and Value fields only)
  2. **Companies** — linked via company_person pivot; manage links (gated)
  3. **Hourly Activity** — bar chart (last 90 days, by hour of day)
  4. **Activity Availability** — heatmap grid (day-of-week × hour)
  5. **Recent Conversations** — last 10
  6. **Notes** — notes-section component
  7. **Activity Timeline** — AJAX cursor pagination, `showPersonLink=false`

**Forms:** Separate `/create` and `/{id}/edit` Vue pages — first_name, last_name, is_our_org checkbox.

**Our Org routes:**
- `POST people/bulk-unmark-our-org` → `PersonController::bulkUnmarkOurOrg()`
- `POST people/{person}/unmark-our-org` → `PersonController::unmarkOurOrg()`
- `POST people/bulk-mark-our-org` → `PersonController::bulkMarkOurOrg()`

---

### Conversations (`/conversations`)

**Controller:** `ConversationController`
**Tabs:** Assigned / Unassigned / Filtered

**Index:**
- Filter panel: date range (last_message_at), channels multi-select dropdown
- Bulk selection: checkbox column + select-all → bulk bar → "Filter…" action (gated)
- **Subject click** → opens conversation quick-view popup (`?preview=1`)
- Per-row "Filter" button (gated) → opens filter-rule modal

**Show (`/conversations/{id}`):**
- Messages partial: chat layout (Slack/Discord) or bubble layout (email/ticket)
- Slack messages: `<@USERID>` resolved to display names via `slackMentionMap`
- Discord messages: `<@ID>` resolved via `discordMentionMap`
- Notes section

**Quick-view modal (`/conversations/{id}/modal`):**
- `?preview=1` — last 3 messages (email/ticket) or last 20 top-level messages (Slack/Discord) + their replies
- `?date=YYYY-MM-DD` — messages for that date (used from activity timeline, limit 10)
- No params — 1 message (used from activity timeline for single-activity view)
- Modal auto-scrolls to bottom

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
- **Never** pass `showPersonLink` or `showCompanyLink` to the global activity timeline

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

- **Domains/Emails/Subjects:** TagInput component for domain, email, subject rule management, lowercase, unique, stored in `SystemSetting` JSON
- **Contacts:** filterable people list, add/remove from `filter_contacts` table
- `applyRule()` accepts `rule_values[]` array (multiple values); falls back to single `rule_value` for backwards compat
- Conversation `archiveWithRule()` same pattern

---

### Configuration: Our Organization (`/data-relations/our-company`)

**Controller:** `OurCompanyController`
**Tabs:** Members / Team Identities / Email Domains

- **Members:** people with `is_our_org=true` OR linked `is_team_member` identity
- **Team Identities:** identities marked as `is_team_member=true`
- **Email Domains:** text input, saves to SystemSetting, marks matching identities as `is_team_member=true`

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

### Configuration: Smart Notes (`/configuration/smart-notes`)

**Controller:** `SmartNotesConfigController`
**Tabs:** Notes Filtering | AI Recognition (disabled, "Coming soon")

**Purpose:** Configure which messages are automatically captured as Smart Notes.

**Filter types:**
| Type | Criteria |
|------|---------|
| `email_message` | `{mailbox_slugs, address, direction: any/to/from}` — matches email conversations with that address |
| `email_subject` | `{keyword}` — matches conversations (email/ticket) with subject containing keyword |
| `discord_any` | `{guild_id?, channel_id?}` — matches Discord conversations, optionally filtered |
| `slack_any` | `{channel_id?}` — matches Slack conversations, optionally filtered |

**Settings:** `SystemSetting::get/set('smart_notes_enabled', false)` — master on/off switch.

**Scan:** `POST /configuration/smart-notes/scan` — scans existing conversations against active filters and creates SmartNotes for matches. Synchronous, returns count in flash message.

**Sidebar:** "Smart Notes" appears in Configuration → Synchronization section with AI icon.

---

### Browse Data: Smart Notes (`/smart-notes`)

**Controller:** `SmartNotesController`
**Tabs:** Unrecognized | Recognized

**Workflow:**
1. Message matches a filter → captured as `SmartNote` with `status=unrecognized`
2. User clicks "Recognize" → opens recognition page
3. User splits content into segments, assigns each to Company or Person
4. On save: creates actual `Note` records (linked via `NoteLink`), sets `status=recognized`
5. Can unrecognize (reverts to unrecognized, deletes created notes)
6. Can delete (soft delete)

**Data model:**
- `smart_note_filters` — filter rules (`type`, `criteria` jsonb, `as_internal_note`, `is_active`)
- `smart_notes` — captured messages (`source_type`, `source_external_id`, `content`, `sender_*`, `occurred_at`, `as_internal_note`, `status`, `segments_json` jsonb, softDeletes)
- `segments_json` — array of `{content, company_id, person_id, note_id, company_name, person_name}`

**Models:** `SmartNoteFilter`, `SmartNote` (with `SoftDeletes`)

**Sidebar (Browse Data):** "Smart Notes" with AI icon. Disabled (with hover tooltip) when `smart_notes_enabled=false`. Shows unrecognized count badge when > 0.

**Notes created from Smart Notes:** `source='smart_note'`, `meta_json.smart_note_id` = SmartNote id, `meta_json.as_internal_note` = bool.

**Unrecognize:** Uses `whereJsonContains('meta_json->smart_note_id', $id)` + `forceDelete()` to permanently remove notes.

**Tests:** `tests/Feature/SmartNotesTest.php` (24 tests).

---

### Configuration: Team Access (`/configuration/team-access`)

**Controller:** `TeamAccessController` / `UsersController` / `GroupsController`
**Tabs:** Users / Groups

**Groups:** Manage permission flags:
- `browse_data` — read companies/people/conversations/activity
- `data_write` — create/edit/delete companies, people, conversations; manage identities/domains/accounts
- `notes_write` — create/delete notes on any entity
- `analyse` — access to Analyze chat UI (`/analyze/*`)
- `configuration` — all `/configuration/*` routes

**`GroupsController::permLabels()`** — static method, always pass as `$permLabels` to views.

**Rules:**
- User cannot demote themselves from Admin group
- Cannot delete group if users are assigned
- Sidebar order: Setup Checklist → General Settings → Team Access

---

## UI Architecture (Vue + Inertia)

### Rendering Stack

All application pages use **Vue 3 + Inertia.js**. There is a single Vite entry point (`resources/js/app.js`) and a single Inertia root Blade template (`resources/views/app.blade.php`). No Alpine.js — it has been fully removed.

**Remaining Blade views** (non-Inertia):
- `resources/views/auth/login.blade.php` — standalone, does not load `app.js`
- `resources/views/auth/setup.blade.php` — standalone, does not load `app.js`
- `resources/views/synchronizer/wizard/install-script.blade.php` — generates bash script, not a UI page

### Layout

`resources/js/layouts/AppLayout.vue` — unified layout for all sections:
- **TopBar** — dark header with section navigation (Browse Data / Analyze / Configuration)
- **Sidebar** — context-dependent, driven by `layout.section` from `HandleInertiaRequests` middleware
- **Main content** — padded wrapper for standard pages; full-height no-padding for Analyze chat

Sections are determined by `HandleInertiaRequests::layoutData()` which sets `layout.section` to `'browse_data'`, `'configuration'`, or `'analyze'`.

**Analyze section layout special case:** When `layout.section === 'analyze'`, AppLayout renders the main content area as full-height (`h-[calc(100vh-4rem)] overflow-hidden`) with no padding. The Analyze sidebar is injected via Vue named slot `#sidebar`.

### Shared Inertia Data

`HandleInertiaRequests` middleware provides to all pages:
- `auth.user` — id, name, email
- `auth.permissions` — browse_data, data_write, notes_write, analyse, configuration
- `flash` — success, error, api_key_plain
- `layout` — section, topSections, sidebarItems, setupStatus, hasAiCredentials, analyseEnabled, configNeedsAttention, mappingSystems, etc.

### Vue Page Components

All pages live in `resources/js/pages/`. Each page wraps content in `<AppLayout>`:
```vue
<template>
  <AppLayout>
    <!-- For Analyze pages, inject sidebar via named slot -->
    <template #sidebar>
      <AnalyseSidebar :sidebar="analyseSidebar" :active-chat-id="chatId" :users="users" />
    </template>

    <!-- Page content -->
    <div class="p-6">...</div>
  </AppLayout>
</template>
```

### Date Range Picker

`window.drp` IIFE defined in `resources/js/app.js` — wraps Easepick for date range selection. Used by Vue pages that need date filtering (they call `window.drp.init(...)` after mount).

### Key Frontend Rules

- **Tailwind v4 + Vue SFC:** `@apply` is NOT supported inside Vue `<style>` blocks — use plain CSS instead
- **Markdown rendering:** `marked.js` for AI assistant messages via `.prose-ai` CSS class (plain CSS, not @apply)
- **Permission gating in Vue:** use `usePage().props.auth.permissions` to conditionally show/hide UI elements
- **Date filters in controllers:** use `!empty($f_date_from)` not `$f_date_from !== ''` — `ConvertEmptyStringsToNull` middleware converts empty values to `null`

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
| `.sidebar-section-ai` | AI gradient text on section label (add to `.sidebar-section`) |
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
| `.topbar` | TopBar dark glass header — same dark styling as sidebar |
| `.modal-panel` | Standard modal dialog sizing (800px max-width, 95% width, 90vh max-height) |
| `.modal-panel-sm` | Smaller modal dialog sizing (700px max-width, 95% width, 90vh max-height) |

### Brand Colors

| Token | Hex | Usage |
|-------|-----|-------|
| **Primary** | `#e00078` | Logo, primary buttons, active states. `--color-brand-*` shades from brand-50 to brand-900. |
| **Dark base** | `#212731` | Top header, sidebar, dark UI elements. |
| **Light accent** | `#F1FFFA` | AI-related features. Light shades only. |

`--color-brand-600` = `#e00078` (anchor point of the scale).

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
8. **Disabled elements must always explain why** — show a short text near the disabled control explaining the prerequisite (e.g. "Add an AI credential to configure model assignments"). Sidebar disabled items use `title` tooltip; page-level disabled sections add visible explanatory text.
9. **AI accent** — use `.sidebar-section-ai` for AI section labels. Use `ai-icon.svg` sparkle for AI-powered items in nav. Keep accents subtle and limited to navigation/labels.

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
- **Never** pass `showPersonLink` / `showCompanyLink` to the global activity timeline.
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

### Breadcrumb Back-link
- `Controller::resolveBackLink()` uses HTTP Referer
- Returns `null` if Referer === current page (prevents self-link after page reload from form action)

### Our Org
- `Person.is_our_org` = canonical flag
- `Identity.is_team_member` = derived (set by team domain matching)
- Bulk + individual mark/unmark operates on `Person.is_our_org` only
- Per-row toggle removed from people/index — bulk only

### Person Show Card
- Header gradient: `from-brand-600 to-brand-800` for Our Org people, `from-[#1c2028] to-[#252d3b]` for regular people

### Conversation Preview Modal
- `?preview=1`: last 3 msgs (email/ticket), last 20 top-level (chat) + replies
- Messages in `orderByDesc` order (newest first) — **do not reverse**

---

## ACL / Permissions

### Permission Flags
`browse_data`, `data_write`, `notes_write`, `analyse`, `configuration`

**Key:** `App\Models\User` / `App\Models\Group` (pivot: `group_user`)
**Middleware:** `App\Http\Middleware\CheckPermission` — registered as `permission:{flag}`
**Gates:** Defined in `AppServiceProvider` based on user's group permissions.

### UI Permission Gating

**Never show write UI to restricted users — hide completely, never rely on 403.**

In Vue pages, use `usePage().props.auth.permissions` to conditionally render write-only UI:
- Create/edit/delete buttons → check `permissions.data_write`
- Notes write forms → check `permissions.notes_write`
- Configuration pages are route-protected; no extra UI gating needed.

### Tests

Helpers in `tests/TestCase.php`: `actingAsAdmin()`, `actingAsViewer()`, `actingAsAnalyst()`.
All feature tests call `$this->actingAsAdmin()` in `setUp()`.
ACL tests: `tests/Feature/AuthAclTest.php`.

---

## Frontend

Tailwind CSS v4 compiled by Vite. Single entry point: `resources/js/app.js`. Assets in `public/build/`.

**Key JS dependencies:**
- `@inertiajs/vue3` + `vue` — SPA routing and reactivity
- `laravel-echo` + `pusher-js` — WebSocket client (Reverb) for Analyze chat streaming
- `easepick` — date range pickers (accessed via `window.drp` IIFE)
- `marked` — markdown rendering for AI assistant messages (`ChatMessage.vue`)

---

## Tests

PHPUnit with two suites: `Unit` (`tests/Unit/`) and `Feature` (`tests/Feature/`)
Test database: PostgreSQL (`contact-monitor-test`) — configured in `phpunit.xml`. Tests run on the same engine as production to catch real SQL issues.

### Testing Rules

**Run tests before and after every change.** A test failure should block the change.

**Update tests when adding features.** Every new route, controller method, or auth rule needs a corresponding test. No exceptions.

**Test files by domain:**

| File | Covers |
|------|--------|
| `SetupTest.php` | First-run setup flow (`/setup`), login-to-setup redirect when no users |
| `AuthAclTest.php` | Login/logout, rate limiting, per-role permissions (Admin/Analyst/Viewer) |
| `WizardRouteTest.php` | Synchronizer wizard routes — regression for the 500 bug caused by `Route::resource` matching `wizard` as `{server}` parameter |
| `RoutesSmokeTest.php` | Every major page returns 200, not 500. Add new pages here when created. |
| `ApiIngestTest.php` | `/api/ingest/batch` — `X-Ingest-Secret` authentication |
| `DashboardTest.php` | Dashboard stats, require.setup middleware redirect |
| `SynchronizerTest.php` | Synchronizer connections page, server listing |
| `ActivitiesTest.php` | Activity timeline, search (PostgreSQL JSON), cursor pagination |
| `MergeTest.php` | Company/person merge, unmerge, scope exclusions, ACL |
| `SmartNotesTest.php` | Smart note filters CRUD, recognize/unrecognize flow, scan |
| `McpTest.php` | MCP auth, protocol, resources, tools, confirmation flow |
| `AiCredentialsTest.php` | AI credentials CRUD, model config, costs pages, ACL (16 tests) |
| `BrandProductsTest.php` | Brand product (segmentation) CRUD, optional fields |
| `CompanyAnalysisTest.php` | Company analysis config CRUD, step reorder, ACL, preview, show, domain classification, prompt renderer, result extractor, domain sync (23 tests) |
| `AnalyseTest.php` | Analyze chat UI — CRUD, messages, branching, sharing, projects, search, ACL (92 tests) |

### No SQLite — PostgreSQL only

All tests run on PostgreSQL (same engine as production). No `markTestSkipped` for DB compatibility — every test must pass. This catches real SQL issues (JSON operators, `DISTINCT ON`, window functions) that SQLite would silently ignore.

---

## MCP Server (AI Integration)

### Overview

A proper **Model Context Protocol** server (JSON-RPC 2.0) is built into contact-monitor.
It exposes read resources and write tools for AI clients (Claude Desktop, automated workflows, etc.).

**Entry point:** `POST /api/mcp` (single JSON-RPC endpoint)
**Controller:** `App\Http\Controllers\Api\McpController`
**Protocol:** MCP spec — `initialize`, `resources/list`, `resources/read`, `tools/list`, `tools/call`

---

### Access Control

| Caller origin | Auth required |
|---------------|--------------|
| localhost / 127.0.0.1 | None — auto-allowed |
| External | `Authorization: Bearer <api_key>` header |

**Settings** (in `SystemSetting`):
- `mcp_enabled` — master on/off toggle (bool)
- `mcp_external_enabled` — allow external connections (bool)
- `mcp_api_key` — hashed API key for external access (string)

**Middleware:** `App\Http\Middleware\McpAuth`
- Returns MCP error `-32001` (Unauthorized) when auth fails instead of HTTP 401

---

### Route Middleware Stack addition

```
API (no session, stateless):
  POST /api/mcp    → McpAuth middleware → McpController::handle()
```

---

### Confirmation System (chat context)

Write tools called with `"_context": "chat"` in params require a two-step confirmation:

1. First call → returns `{"confirmation_required": true, "description": "...", "confirm_token": "abc123"}`
2. Second call with `"_confirm_token": "abc123"` → executes the action

**Token storage:** Cache table (existing database cache driver), TTL 60 seconds, key `mcp_confirm_{token}`.
**Automated context:** `"_context": "automated"` or no `_context` → skips confirmation entirely.

---

### Resources (read)

| Resource URI | Description |
|-------------|-------------|
| `companies://list` | Paginated company list (name, primary domain, id). Params: `page`, `q` (search) |
| `companies://{id}` | Company + domains + aliases + accounts + brand statuses + linked people |
| `people://list` | Paginated people list. Params: `page`, `q`, `is_our_org` |
| `people://{id}` | Person + identities + linked companies |
| `conversations://list` | Conversation headers. Params: `page`, `company_id`, `person_id`, `channel_type` |
| `conversations://{id}` | 3-level depth controlled by `depth` param: `headers` (default) / `recent` (last 20 msgs) / `full` (all msgs) |
| `activity://search` | Activity search. Params: `q`, `from`, `to`, `type`, `systems[]`, `company_id`, `person_id` |
| `notes://list` | Notes. Params: `entity_type` (App\Models\Company etc.), `entity_id` |
| `smart_notes://list` | Smart notes. Params: `status` (unrecognized/recognized), `page` |
| `audit_log://list` | Audit log entries. Params: `entity_type`, `entity_id`, `action`, `from`, `to`, `page` |

---

### Tools (write)

**Companies:**
- `company_create` — `{name, primary_domain?}`
- `company_update` — `{id, name?, timezone?}`
- `company_add_domain` — `{company_id, domain, set_primary?}`
- `company_add_account` — `{company_id, system_type, system_slug, external_id}`
- `company_set_brand_status` — `{company_id, brand_product_id, stage, score?, notes?}`
- `company_merge` — `{source_id, target_id}` — merges source into target (sets merged_into_id)

**People:**
- `person_create` — `{first_name, last_name?, is_our_org?}`
- `person_update` — `{id, first_name?, last_name?}`
- `person_add_identity` — `{person_id, type, value, system_slug?}`
- `person_link_company` — `{person_id, company_id, role?, started_at?, ended_at?}`
- `person_mark_our_org` — `{person_id, is_our_org}` — sets/clears is_our_org flag
- `person_merge` — `{source_id, target_id}`

**Notes:**
- `note_create` — `{content, entity_type, entity_id, source?}` — source defaults to `'mcp'`; `meta_json.as_internal_note` can be passed
- `note_delete` — `{note_id}`

**Smart Notes:**
- `smart_note_recognize` — `{smart_note_id, segments: [{content, company_id?, person_id?}]}`

**Conversations:**
- `conversation_archive` — `{conversation_id}`

All write tools log to `ai_logs` table.

---

### AI Log

**Table:** `ai_logs`
```
id, user_id (nullable), tool_name, entity_type (nullable), entity_id (nullable),
input_json (jsonb), output_json (jsonb), context (chat/automated/unknown),
ip_address, created_at
```

**Configuration sidebar:** MCP Log is under Configuration → MCP Server, not in Browse Data sidebar.

---

### Configuration Pages

**Connect AI:** `GET /configuration/ai` → `AiConfigController::index()`
- Tabs: Credentials, Model Assignment (disabled when no credentials)

**MCP Server:** `GET /configuration/mcp-server` → `AiConfigController::mcpServer()`
- `POST /configuration/mcp-server/settings` → `AiConfigController::updateSettings()`
- `POST /configuration/mcp-server/regenerate-key` → `AiConfigController::regenerateKey()`
- Sections: Enable/Disable toggle, Endpoint URL, External access + API key

**Sidebar:** In Configuration → "AI Functionality" section with entries: Connect AI, Company Analysis (disabled), AI Costs (disabled when no credentials), MCP Server.

---

### Data Model

```
mcp_logs  (id, user_id→users nullable, tool_name, entity_type, entity_id,
           input_json jsonb, output_json jsonb, context, ip_address, created_at)
```

No `updated_at` — append-only log. **Note: was `ai_logs`, renamed to `mcp_logs`.**

---

### MCP Implementation Files

| Path | Purpose |
|------|---------|
| `app/Http/Middleware/McpAuth.php` | Auth middleware (localhost bypass + Bearer token check) |
| `app/Http/Controllers/Api/McpController.php` | JSON-RPC dispatcher |
| `app/Mcp/McpServer.php` | Protocol handler (initialize, resources/list, tools/list etc.) |
| `app/Mcp/Resources/` | One class per resource URI |
| `app/Mcp/Tools/` | One class per tool |
| `app/Models/McpLog.php` | McpLog model (was AiLog) |
| `app/Http/Controllers/AiConfigController.php` | Connect AI + MCP Server configuration |
| `app/Http/Controllers/McpLogController.php` | MCP Log browse (was AiLogController) |
| `tests/Feature/McpTest.php` | MCP auth, resources, tools tests |

---

### Tests for MCP

`tests/Feature/McpTest.php`:
- Auth: unauthenticated external request returns error `-32001`
- Auth: localhost bypasses auth
- Auth: valid Bearer token allows access
- `initialize` returns server info + capabilities
- `resources/list` returns all resource descriptors
- `tools/list` returns all tool descriptors
- `resources/read companies://list` returns company data
- `resources/read people://list` returns people data
- `tools/call company_create` creates company
- `tools/call note_create` creates note + logs to mcp_logs
- Chat context confirmation flow (two-step)
- MCP disabled returns error `-32002`

---

## AI Functionality

### Overview

AI features built into contact-monitor. Separate from MCP (which is an external protocol).
AI is invoked server-side using configured provider credentials.

**Sections in Configuration → AI Functionality:**
- **Connect AI** — credentials CRUD + model-per-action config + pricing overrides
- **Company Analysis** — config for analysis prompt + data scope (details TBD)
- **AI Costs** — usage log with token counts + estimated costs
- **MCP Server** — existing MCP server config (moved here)

**Top Navigation (TopBar):**
- **Browse Data** — CRM section with entity sidebar
- **Analyze** — AI chat interface, uses AppLayout with Analyze-specific sidebar. Enabled when `ai_credentials` exist AND `ai_model_configs` has `analyze` action. Otherwise disabled with tooltip.
- **Configuration** — settings section with configuration sidebar

**Configuration:**
- **MCP Log** — log of MCP tool calls (under MCP Server section)

---

### AI Action Types

| Constant | Purpose |
|----------|---------|
| `analyze` | Analyze chat (ChatGPT-like interface) |
| `company_analysis` | Automated company analysis |
| `conv_summary_message` | Summarize single conversation message |
| `conv_summary_company` | Summarize recent conversations for a company |
| `conv_summary_person` | Summarize recent conversations for a person |
| `notes_recognition` | Auto-recognize which company/person a Smart Note belongs to |

Each action type has: `credential_id + model_name` + optional `helper_credential_id + helper_model_name` (e.g. cheap model for pre-summarizing before main call).

---

### AI Write Confirmation Rules

| Context | Confirmation required? |
|---------|----------------------|
| Analyze chat (interactive) | Yes — AI summarizes planned action, user confirms |
| Company Analysis | No — writes to dedicated `analysis_*` tables only |
| Notes Recognition | No — automated, no user interface |
| Conversation Summary | No — writes to `conversation_summaries` table only |

---

### Data Model (AI)

```
ai_credentials  (id, name, provider: claude/openai/gemini/grok,
                 api_key encrypted, extra_config jsonb, is_active, timestamps)

ai_model_configs  (id, action_type, credential_id→ai_credentials,
                   model_name, helper_credential_id nullable, helper_model_name nullable,
                   extra_config jsonb, timestamps)

ai_usage_logs  (id, action_type, credential_id→ai_credentials, model_name,
                entity_type nullable, entity_id nullable,
                input_tokens, output_tokens, cost_input_usd, cost_output_usd,
                prompt_excerpt varchar(200), meta_json, created_at)

ai_chats  (id, user_id→users,                -- owner
           project_id→ai_projects nullable,
           title nullable, title_is_manual bool default false,
           is_archived bool default false,
           is_shared bool default false,
           source_chat_id→ai_chats nullable,       -- branched from
           source_message_id→ai_chat_messages nullable,
           last_message_at timestamp nullable,      -- for sidebar sorting
           created_at, updated_at)

ai_chat_messages  (id, chat_id→ai_chats, role: user/assistant/tool/system_event,
                   content text, tool_calls_json jsonb nullable, meta_json, created_at)

ai_projects  (id, user_id→users, name varchar(100), created_at, updated_at)

ai_chat_participants  (id, chat_id→ai_chats, user_id→users,
                       added_by→users nullable, added_at timestamp)

conversation_summaries  (id, conversation_id→conversations nullable,
                          company_id nullable, person_id nullable,
                          summary_type: message/company/person,
                          content text, model_name, created_at)
```

Pricing overrides stored in `system_settings` key `ai_pricing_overrides`:
`{"model-name": {"input_per_m": X, "output_per_m": Y}}`

---

### AI Provider Architecture

```
app/Ai/
  Providers/
    AiProviderInterface.php    testConnection(), fetchModels(), complete(), stream()
    ClaudeProvider.php
    OpenAiProvider.php
    GeminiProvider.php
    GrokProvider.php
  AiProviderFactory.php        credential_id → AiProviderInterface instance
  Pricing/
    PricingRegistry.php        hardcoded defaults + reads overrides from SystemSetting
  Actions/
    AnalyzeAction.php          (details TBD)
    CompanyAnalysisAction.php  (details TBD)
    ConversationSummaryAction.php  (details TBD)
    NotesRecognitionAction.php     (details TBD)
  StreamingChat.php            WebSocket broadcast streaming wrapper (used by Analyze)
```

**Credentials encrypted** using Laravel's `encrypt()`/`decrypt()`.

---

### Connect AI — Credentials Tab

- Table of saved credentials (name, provider)
- Add/Edit: name, provider dropdown, API key input, Save button
- **Validation on save:** test connection before persisting — if fails, show error, do not save
- Test Connection button on existing credentials
- Delete (confirm)

### Connect AI — Models Tab

- Per action type: credential dropdown → model dropdown (fetched from provider API)
- Pricing Overrides section: table of known models with default price, editable input/output per 1M tokens

### AI Costs Page

- Period selector (no default date range — shows all time when no dates selected)
- Summary cards: total input tokens, total output tokens, estimated total cost
- Table: action type, model, entity link (e.g. Company #5 "Acme"), prompt excerpt, input tokens, output tokens, cost, date

---

### PricingRegistry Defaults

| Model | Input $/1M | Output $/1M |
|-------|-----------|------------|
| claude-opus-4-6 | 15.00 | 75.00 |
| claude-sonnet-4-6 | 3.00 | 15.00 |
| claude-haiku-4-5-20251001 | 0.80 | 4.00 |
| gpt-4o | 2.50 | 10.00 |
| gpt-4o-mini | 0.15 | 0.60 |
| gpt-4-turbo | 10.00 | 30.00 |
| o1 | 15.00 | 60.00 |
| gemini-1.5-pro | 1.25 | 5.00 |
| gemini-1.5-flash | 0.075 | 0.30 |
| gemini-2.0-flash | 0.10 | 0.40 |
| grok-2 | 2.00 | 10.00 |

Note: The registry contains additional models beyond those listed here.

---

### Sidebar (Configuration)

```
── AI Functionality ─────────────
  Connect AI
  Company Analysis
  AI Costs              (disabled when no AI credentials)
  MCP Server            (separate page, not a tab)

── Data Relations ───────────────
  ...

── Segmentation ─────────────────
  ...
```

### AI Feature Availability Rule

AI-dependent features are **disabled in the UI** when no AI credentials exist (`ai_credentials` table empty):
- **Sidebar:** AI Costs shows as `is-disabled` with tooltip "Add an AI credential first"
- **Connect AI page:** Model Assignment tab is visually disabled (grayed out, not clickable)
- **MCP Server** is independent of AI credentials — it has its own separate page (`/configuration/mcp-server`)

This is checked via `$hasAiCredentials = AiCredential::exists()` (cached 60s in `layout.has_ai_credentials`).

### AI Visual Accent

AI-related sections use the AI gradient (`#0AFFA9 → #EBFFF8 → #E00078`) for subtle visual distinction:
- **Sidebar section label** "AI Functionality" uses `.sidebar-section-ai` (gradient text, 70% opacity)
- **Top nav** Analyze link shows the `ai-icon.svg` sparkle icon
- **Browse Data sidebar** AI-powered items (Smart Notes) show a small `ai-icon.svg` next to the label

Use these accents sparingly — only on section labels and navigation badges, never on content areas or buttons.

### Implementation Status

- [x] MCP Server (JSON-RPC 2.0) — complete
- [x] MCP Log — complete
- [x] AI Credentials + provider abstraction — complete
- [x] AI Model configs — complete
- [x] AI Costs — complete
- [x] Connect AI UI (credentials + models tabs) — complete
- [x] AI Functionality sidebar section — complete
- [x] Company Analysis config page — complete
- [x] Company Analysis logic + UI — complete (see Company Analysis section below)
- [ ] Notes Recognition logic — TBD
- [ ] Conversation Summary — TBD
- [x] **Analyze chat UI** — complete (see Analyze section below)

### Tests

`tests/Feature/AiCredentialsTest.php` — 16 tests covering credentials CRUD, model config, costs pages, ACL.
`tests/Feature/CompanyAnalysisTest.php` — 23 tests covering config CRUD, step reorder, ACL, preview, analysis show, domain classification, prompt renderer, result extractor, domain sync.

---

## Company Analysis

### Overview

Configurable, manual, multi-step AI analysis for company records. Triggered from Company Show page via "Analyse Company" button. Each step sends a rendered prompt to the configured AI provider, parses structured JSON results, and stores discrete fields + entities.

**Execution:** Synchronous (no queue). `set_time_limit(120)`. Uses `AiModelConfig::forAction('company_analysis')` for provider selection.

---

### Data Model

```
analysis_steps        (key, name, description, prompt_template, is_enabled, sort_order)
analysis_runs         (company_id, user_id, status, base_context_json, started_at, completed_at)
analysis_step_runs    (run_id, step_id, step_key, status, prompt_template_used, rendered_prompt,
                       raw_response, parsed_response jsonb, error_message, model_name,
                       input_tokens, output_tokens, started_at, completed_at)
analysis_fields       (company_id, run_id, step_run_id, field_group, field_key, field_value,
                       field_type, confidence, is_inferred, sort_order)
                       UNIQUE(run_id, field_key)
analysis_entities     (company_id, run_id, step_run_id, entity_type, display_name,
                       data_json jsonb, confidence, sort_order)
domain_classifications (domain, type: free_email/disposable, source)
                       UNIQUE(domain, type)
```

---

### Services — `app/Ai/CompanyAnalysis/`

| Service | Purpose |
|---------|---------|
| `ContextBuilder` | Builds variable context from Company + relations (domains, people, conversations, brand statuses, domain classification) |
| `PromptRenderer` | `{{var}}` → base context, `{{previous.step_key.field}}` → prior step output, `{{previous.step_key}}` → full JSON |
| `ResultExtractor` | Strips markdown fences, parses JSON, extracts fields + entities into DB records |
| `AnalysisPipeline` | Orchestrates: create run → build context → iterate steps → render → AI call → parse → persist |
| `DomainSyncService` | Lazy sync (>24h) of free/disposable email domain lists from configurable URLs |

---

### Default Seeded Steps

1. `company_identity_resolution` (sort_order 10) — Resolve company identity from weak signals
2. `company_profile_enrichment` (sort_order 20) — Build commercial profile
3. `gap_fill_missing_fields` (sort_order 30) — Fill missing/low-confidence fields

---

### Routes

**Configuration (permission:configuration):**
```
GET    /configuration/company-analysis                    → config.index
POST   /configuration/company-analysis/steps              → steps.store
PUT    /configuration/company-analysis/steps/{step}       → steps.update
DELETE /configuration/company-analysis/steps/{step}       → steps.destroy
POST   /configuration/company-analysis/steps/reorder      → steps.reorder
POST   /configuration/company-analysis/domain-sync        → domain-sync
POST   /configuration/company-analysis/domain-settings    → domain-settings
```

**Browse Data (permission:browse_data + require.setup):**
```
GET    /companies/{company}/analysis/preview              → preview (JSON)
GET    /companies/{company}/analysis/latest               → latestSummary (JSON)
GET    /companies/{company}/analysis/history              → history (JSON)
POST   /companies/{company}/analysis/run                  → run (data_write)
GET    /companies/{company}/analysis/{run}                → show (Inertia)
```

---

### Vue Files

| Path | Purpose |
|------|---------|
| `pages/CompanyAnalysisConfig/Index.vue` | Tabs: Steps (CRUD + reorder) and Domain Classification (sync, settings) |
| `pages/CompanyAnalysis/Show.vue` | Full run detail: fields table, entities, step runs with prompt/response |
| `components/CompanyAnalysisCard.vue` | Compact card on Company Show — key fields, entity counts, run metadata |
| `components/CompanyAnalysisModal.vue` | Pre-run modal: step selection, prompt preview/edit, context summary |

---

### Key Rules

- **Prompt template variables** use `{{double_braces}}` — in Vue templates, use `wrapVar(v)` helper (`'{' + '{' + v + '}' + '}'`) to avoid Vue interpolation conflicts
- **Per-run prompt overrides** stored on `analysis_step_runs.prompt_template_used`, never modify the step template
- **Field uniqueness** per `(run_id, field_key)` — later steps can overwrite earlier fields
- **Confidence** as varchar: `high`/`medium`/`low`
- **Domain sync** is lazy — checks `>24h` on config page load and before analysis run
- **Failed steps** don't block subsequent steps in the pipeline

---

## Analyze (AI Chat)

### Overview

Full-screen ChatGPT-like interface. Top-level nav section (alongside Browse Data and Configuration). Uses `AppLayout.vue` with the Analyze sidebar injected via `#sidebar` slot. Active when `ai_credentials` exist AND `ai_model_configs` has `analyze` entry.

**Stack:** Vue 3 + Inertia.js · Laravel Reverb (WebSocket) · PostgreSQL FTS

**Route prefix:** `/analyze` · **Route name prefix:** `analyze.` · **DB permission flag:** `analyse` (unchanged)

---

### Layout

- Uses the shared `AppLayout.vue` — same TopBar, sidebar shell, and styling as all other sections
- Main content area renders full-height with no padding (`h-[calc(100vh-4rem)] overflow-hidden`)
- Analyze-specific sidebar (`AnalyseSidebar.vue`) injected via `<template #sidebar>` slot in each Analyze page
- No separate Blade template or Vite entry point — uses the same `app.blade.php` and `app.js` as everything else

---

### Sidebar Structure (top → bottom)

1. **New Conversation** — creates private chat, opens immediately
2. **Search Conversations** — FTS across titles + message content, live as you type
3. **Shared With Me** — collapsible section; chats owned by others, shared with current user
4. **Projects** — collapsible section; folders owned by current user, + add button
5. **Conversations** — private chats owned by current user; cursor pagination; sorted by `last_message_at` desc

**Row rules (all sections):** single-line, ellipsis overflow, full title on tooltip hover, active highlight, three-dot menu on hover only.

**Shared With Me rows:** badge/indicator showing it's not owned. Actions: Open, Add to my project, Remove from my sidebar, Branch to private.

**Project rows:** Actions: Rename, Delete. Clicking opens project view (list of conversations in project).

**Conversation rows (private):** Actions: Rename, Move to project, Share with user, Archive, Delete.

---

### Main Area Structure

1. **Top bar** — title (inline rename on click), ownership/sharing badge, project breadcrumb if assigned, actions menu (right)
2. **Message list** — scrollable, chronological, auto-scroll to bottom on open/new message
3. **Composer** — sticky bottom, multiline textarea (auto-grows, max-height with internal scroll), Enter=send, Shift+Enter=newline, Send/Stop button

---

### Message Types

| Role | Presentation |
|------|-------------|
| `user` | Brand-50 bg bubble, gravatar avatar (md5 of email from `meta.user_email`). In shared chats: show author name. |
| `assistant` | White bg bubble with shadow, `/ai-icon.svg` in gray circle avatar. Rich text (markdown via marked.js). |
| `system_event` | Quiet single-line: "Shared with Anna", "Branch created from this point" etc. |

**Message hover actions:**
- User message: Edit (own messages only, auto-branch in shared), Copy, Branch from here
- Assistant message: Copy, Retry (auto-branch in shared), Branch from here

**Editing:** replaces message with textarea, Save+resubmit / Cancel. In shared thread → auto-branch.

---

### WebSocket Architecture (Laravel Reverb)

**Channel:** `private-chat.{chatId}` per conversation.

**Events broadcast:**

| Event | When | Payload |
|-------|------|---------|
| `AiMessageChunk` | Each streaming chunk | chatId, messageId, chunk, index |
| `AiMessageComplete` | Stream done | chatId, messageId, inputTokens, outputTokens |
| `UserMessageAdded` | Another participant sends | chatId, message object |
| `ChatTitleGenerated` | Auto-title ready | chatId, title |
| `ParticipantUpdated` | Share/leave | chatId, participants array |

**AI Streaming flow:**
1. Vue POST `/analyze/chats/{id}/messages`
2. Controller saves user message, broadcasts `UserMessageAdded` (for other participants)
3. Starts AI provider stream (synchronous, same process)
4. Each chunk → broadcast `AiMessageChunk` via Reverb
5. Stream end → broadcast `AiMessageComplete`, save full message + usage log
6. If first message in chat → fire title-generation AI call, broadcast `ChatTitleGenerated`
7. HTTP POST returns `{ok: true}` when done (client ignores response body, reads via WS)
8. Vue assembles message progressively from chunks received on WS channel

**Stop generation:**
- Vue POST `/analyze/chats/{id}/stop` → sets `Cache::put("analyse.stop.{$chatId}", true, 60)`
- Streaming loop checks this flag between chunks, breaks cleanly
- `AiMessageComplete` broadcast with partial content on stop

---

### Branching

- Any message (user or assistant) has "Branch from here" action
- Creates new `ai_chat` with `source_chat_id` + `source_message_id`
- Copies all messages up to and including the branch point
- New chat is private, owned by current user, appears at top of Conversations list
- Original chat unchanged
- **Auto-branch triggers:** editing a message in shared thread, regenerating in shared thread
- Branch metadata stored but shown subtly in UI (system_event message: "Branched from [original title]")

---

### Sharing

- Owner POSTs to `/analyze/chats/{id}/share` with `user_id`
- Creates `ai_chat_participants` record, sets `ai_chats.is_shared = true`
- Shared user sees chat in "Shared With Me" section (NOT in their own Conversations list)
- All participants share the same live thread, messages broadcast in real-time
- Owner can remove participants: DELETE `/analyze/chats/{id}/participants/{user}`
- Participant can hide from sidebar: DELETE `/analyze/chats/{id}/leave` (removes from their Shared With Me, does not delete)
- Shared conversation added to a project: `ai_chats.project_id` not changed (owned by original owner); stored in `ai_chat_project_pins` table (user_id, chat_id, project_id) — allows participant to pin shared chat into their project without changing ownership

---

### Projects

- Simple folders owned by a user
- A private chat is "in" a project when `ai_chats.project_id = project.id` AND `ai_chats.user_id = current user`
- A shared chat is "pinned" to a project via `ai_chat_project_pins` (does not change ownership)
- Project view shows both types with visual distinction

---

### Title Generation

1. After first `AiMessageComplete`, fire separate AI call to same credential+model:
   - System: "You generate concise conversation titles. Respond with only the title, max 6 words, no quotes."
   - User: first user message content (truncated to 500 chars)
2. Update `ai_chats.title`, set `title_is_manual = false`
3. Broadcast `ChatTitleGenerated`
4. If `title_is_manual = true` → skip (never overwrite manual rename)

---

### Search (FTS)

- `tsvector` columns: `ai_chats.title_tsv` (GIN indexed) and `ai_chat_messages.content_tsv` (GIN indexed)
- Triggers maintain tsvectors on insert/update
- Query: `ts_rank` for relevance, `ts_headline` for snippet
- Scope: chats where `user_id = me` OR `id IN (SELECT chat_id FROM ai_chat_participants WHERE user_id = me)`
- Returns: title, snippet, last_message_at, owner name, project name

---

### Data Model (Analyze additions)

```
ai_chats  (extended from base)
  + project_id → ai_projects nullable
  + title_is_manual bool default false
  + is_archived bool default false
  + is_shared bool default false
  + source_chat_id → ai_chats nullable (self-ref, branch source)
  + source_message_id bigint nullable
  + last_message_at timestamp nullable
  + title_tsv tsvector (maintained by trigger)

ai_chat_messages  (extended)
  + content_tsv tsvector (maintained by trigger)
  -- role gains 'system_event' value

ai_projects
  id, user_id → users, name varchar(100), created_at, updated_at

ai_chat_participants
  id, chat_id → ai_chats (cascadeDelete), user_id → users (cascadeDelete),
  added_by → users nullable, added_at timestamp default now()
  UNIQUE(chat_id, user_id)

ai_chat_project_pins
  id, user_id → users, chat_id → ai_chats, project_id → ai_projects
  UNIQUE(user_id, chat_id)
```

---

### Routes

```
GET    /analyze                             → AnalyseController::index      (redirect to last chat or empty state)
GET    /analyze/c/{chat}                    → AnalyseController::show       (Inertia: Chat page)
GET    /analyze/p/{project}                 → AnalyseController::project    (Inertia: Project page)

POST   /analyze/chats                       → AiChatController::store       (create new chat)
PATCH  /analyze/chats/{chat}                → AiChatController::update      (rename, archive, move to project)
DELETE /analyze/chats/{chat}                → AiChatController::destroy
POST   /analyze/chats/{chat}/messages       → AiChatController::sendMessage (long-running WS stream)
POST   /analyze/chats/{chat}/stop           → AiChatController::stop        (cancel generation)
POST   /analyze/chats/{chat}/branch         → AiChatController::branch
POST   /analyze/chats/{chat}/share          → AiChatController::share
DELETE /analyze/chats/{chat}/participants/{user} → AiChatController::removeParticipant
DELETE /analyze/chats/{chat}/leave          → AiChatController::leave       (participant hides from sidebar)

GET    /analyze/chats                       → AiChatController::list        (cursor pagination, JSON)
GET    /analyze/shared                      → AiChatController::shared      (JSON)
GET    /analyze/search                      → AiChatController::search      (FTS, JSON)

POST   /analyze/projects                    → AiProjectController::store
PATCH  /analyze/projects/{project}          → AiProjectController::update
DELETE /analyze/projects/{project}          → AiProjectController::destroy
POST   /analyze/projects/{project}/pin-chat → AiProjectController::pinChat
DELETE /analyze/projects/{project}/pin-chat/{chat} → AiProjectController::unpinChat
```

---

### Implementation Files

| Path | Purpose |
|------|---------|
| `resources/js/pages/Analyze/Index.vue` | Index page (no chat selected) — uses AppLayout with AnalyseSidebar in #sidebar slot |
| `resources/js/pages/Analyze/Chat.vue` | Conversation page — message list, composer, streaming, share/archive/delete panels |
| `resources/js/pages/Analyze/Project.vue` | Project view with owned + pinned chats, delete confirmation |
| `resources/js/analyze/components/AnalyseSidebar.vue` | Sidebar shell — search, shared/projects/conversations sections, modals |
| `resources/js/analyze/components/sidebar/SearchBar.vue` | Live FTS search with debounce, snippet + owner display |
| `resources/js/analyze/components/sidebar/ConversationRow.vue` | Row with three-dot hover menu (Rename, Move, Share, Archive, Delete) |
| `resources/js/analyze/components/sidebar/ProjectRow.vue` | Project row with three-dot hover menu (Rename, Delete) |
| `resources/js/analyze/components/sidebar/SharedRow.vue` | Shared-with-me row with hover menu (Add to project, Branch, Leave) |
| `resources/js/analyze/components/sidebar/ContextMenu.vue` | Reusable teleported context menu component |
| `resources/js/analyze/components/ChatMessage.vue` | Message with gravatar avatars, AI icon assistant avatar, markdown rendering (`marked.js`) |
| `app/Http/Controllers/AnalyseController.php` | Inertia page renders (Analyze/Index, Analyze/Chat, Analyze/Project) |
| `app/Http/Controllers/AiChatController.php` | Chat CRUD + message sending + streaming |
| `app/Http/Controllers/AiProjectController.php` | Project CRUD + pin management |
| `app/Events/AiMessageChunk.php` | Streaming chunk WS event |
| `app/Events/AiMessageComplete.php` | Stream done WS event |
| `app/Events/UserMessageAdded.php` | New message from another user |
| `app/Events/ChatTitleGenerated.php` | Auto-title ready |
| `app/Events/ParticipantUpdated.php` | Share/leave notification |
| `app/Models/AiChat.php` | Chat model with access control, sidebar serialization |
| `app/Models/AiChatMessage.php` | Message model with FTS tsvector |
| `app/Models/AiProject.php` | Project model |
| `app/Models/AiChatParticipant.php` | Participant model |
| `app/Models/AiChatProjectPin.php` | Shared-chat-in-project pin model |
| `app/Broadcasting/ChatChannel.php` | Private channel auth |

---

### Critical Rules (Analyze)

- **Never** auto-overwrite title when `title_is_manual = true`
- **Shared thread editing/retry** → always auto-branch, never mutate shared history
- **Stop flag** lives in cache key `analyse.stop.{chatId}`, TTL 60s; cleared after streaming ends
- **owner_id** = `ai_chats.user_id`; participants in `ai_chat_participants`
- **`last_message_at`** updated on every new message (user or assistant); drives sidebar sort order
- **FTS triggers** must fire on INSERT/UPDATE of `ai_chat_messages.content` and `ai_chats.title`
- Uses shared `AppLayout.vue` — no separate Inertia root view or Vite entry point
- Laravel Echo configured with Reverb in `resources/js/app.js` (global bootstrap)
- **Tailwind v4 + Vue SFC:** `@apply` is NOT supported inside Vue `<style>` blocks — use plain CSS instead
- **Markdown rendering:** `marked.js` for assistant messages via `.prose-ai` CSS class (plain CSS, not @apply)
- **Avatars:** User messages show gravatar (from `message.meta.user_email`), assistant messages show `/ai-icon.svg` in gray circle. Use `:src="'/ai-icon.svg'"` (bind syntax) to prevent Vite treating it as module import.

---

### Tests

`tests/Feature/AnalyseTest.php` — 92 tests:
- **ACL:** viewer blocked, analyst/admin allowed, unauthenticated redirected
- **Chat CRUD:** create (with/without title, with project), rename (sets manual flag), archive/unarchive, delete (cascades messages + participants)
- **Chat show (Inertia):** loads for owner, forbidden for non-participant, accessible to participant, includes auth data, project_id, messages, participants
- **Messages:** send stores user message, validation (required, max length), updates last_message_at, archived chat blocked
- **Streaming:** stop sets cache flag, user message stored even if AI fails
- **Branching:** creates new chat with source reference, copies messages up to branch point, adds system_event, leaves original unchanged, participant can branch shared, rejects cross-chat message_id
- **Sharing:** adds participant + sets is_shared, appears in shared list, idempotent (no duplicate), non-owner blocked, remove participant, remove last clears is_shared, participant leave, owner cannot leave
- **Projects:** create (validates name), rename, delete (unassigns chats, removes pins), forbidden for non-owner, pin/unpin chat (idempotent), project page shows owned + pinned chats
- **Move to project:** assign/remove chat from project, cannot assign to another user's project
- **Search:** empty for short query, matches by title, only accessible chats, includes shared, empty for no matches
- **Sidebar data:** includes projects, shared chats, users for sharing
- **Index:** redirects to last chat when enabled, shows empty state when no chats
