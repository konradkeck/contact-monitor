# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

---

## Project Overview

Contact Monitor is a multi-channel contact hub that centralizes a company's communications with clients across email, Gmail, Slack, Discord, tickets, and other integrations. It links conversations and activities to company and person profiles, tracks brand product pipeline stages, and provides a unified activity timeline across all channels.

**Stack:** Laravel 12 · PHP 8.3 · PostgreSQL 16 · Tailwind CSS v4 · Vite

**No queue worker, no scheduler** — all operations are synchronous. No Redis; sessions and cache use database tables.

**Authentication is enabled** — login via email + password. Email comparison is case-insensitive (`strtolower()` before `Auth::attempt()`). Password is case-sensitive.

---

## Common Commands

All commands run inside Docker unless noted.

```bash
# Clear compiled views (use after Blade changes)
docker exec contact-monitor_app php artisan view:clear

# Run migrations
docker exec contact-monitor_app php artisan migrate

# Artisan tinker (main app)
docker exec contact-monitor_app php artisan tinker

# Artisan tinker (synchronizer)
docker exec contact-monitor-synchronizer_app php artisan tinker

# Run tests (SQLite in-memory)
docker exec contact-monitor_app php artisan test

# Run a single test file
docker exec contact-monitor_app php artisan test tests/Feature/SomeTest.php

# Rebuild frontend assets
docker exec contact-monitor_app npm run build

# Deploy to production (from local machine)
./ops/deploy-production.sh
```

---

## Architecture

### Docker

- `contact-monitor_app` — Laravel app, port 8090
- `contact-monitor_db` — PostgreSQL 16, port 5434 on host
- `contact-monitor-synchronizer_app` — separate synchronizer service on port 8080

### Data Flow

The synchronizer service polls external systems (WHMCS, MetricsCube, Slack, Discord, IMAP) and pushes batches to the ingest API:

```
Synchronizer → POST /api/ingest/batch → IngestController
  → BatchProcessor → AccountProcessor / IdentityProcessor
                   → ConversationProcessor / MessageProcessor
                   → ActivityProcessor
  → models: accounts, identities, conversations, activities
```

### Data Model (key relationships)

```
companies
  ├─ company_domains        (one marked is_primary)
  ├─ company_aliases        (one marked is_primary)
  ├─ accounts               (external IDs: system_type + system_slug + external_id)
  ├─ company_brand_statuses (stage + score per brand product)
  ├─ company_person         (pivot with role, started_at)
  ├─ conversations → conversation_messages (direction: customer/internal/system)
  │                        conversation_participants
  └─ activities             (meta_json payload)

people
  ├─ identities  (email, slack_id, discord_id, phone, linkedin, twitter)
  └─ activities

notes → note_links (polymorphic: company / person / conversation)
```

`Activity` has `timelineLabel()`, `timelineColor()`, and `dotColor()` methods — extend these when adding new activity types.

### Integrations

`app/Integrations/` contains integration classes per system type (WhmcsIntegration, MetricscubeIntegration, SlackIntegration, DiscordIntegration, etc.). `IntegrationRegistry` acts as a factory.

---

## ACL / Permissions System

### Overview

The app uses a group-based permission system. Users belong to groups; groups have permission flags. Middleware enforces access at the route level; Blade `@can` gates hide UI elements.

**Permission flags:** `browse_data`, `data_write`, `notes_write`, `configuration`

**Key models:** `App\Models\User`, `App\Models\Group` (pivot: `group_user`)
**Middleware:** `App\Http\Middleware\CheckPermission` — registered as `permission:{flag}`
**Gate definitions:** `App\Providers\AppServiceProvider` — defines `data_write`, `notes_write`, `configuration` gates based on authenticated user's group permissions.

### Route Middleware Groups

```
auth
└── permission:browse_data          ← all data-reading routes (companies, people, conversations, …)
    └── permission:data_write       ← write routes (create/store/edit/update/delete for companies, people, notes, filtering, …)
└── permission:configuration        ← all /configuration/* routes (data-relations, filtering, our-company, synchronizer, team-access, segmentation)
```

Routes that are **read-only** (index, show) are under `browse_data` only.
Routes that **mutate data** (create, store, edit, update, destroy) are nested inside `permission:data_write`.

### Blade Gating Rules

**Never show write UI to restricted users — hide completely, never rely on 403.**

- All create/edit/delete buttons and forms → `@can('data_write') … @endcan`
- All "Add note" / notes forms → `@can('notes_write') … @endcan`
- Configuration-only pages are route-protected; no extra Blade gating needed there.

Gated elements include (non-exhaustive):
- "New Company" / "New Person" buttons (index pages + dashboard)
- Edit links on person/company rows and show pages
- Filter button, Our Org button, Assign Company button (people/index)
- Bulk action bar write buttons (Filter, Assign Company, Mark as our company)
- Per-row Filter button on conversations/index
- Identity add/destroy, company unlink, domain/alias management (show pages)
- Brand status edit/remove, account add/destroy (company show)
- Notes add forms (notes-section and notes-popup components)

### Tests

Test helpers in `tests/TestCase.php`: `actingAsAdmin()`, `actingAsViewer()`, `actingAsAnalyst()`.
All feature tests call `$this->actingAsAdmin()` in `setUp()`.
ACL-specific tests: `tests/Feature/AuthAclTest.php` (20 tests).

---

## Blade / Frontend Rules

### Sidebar dot standard

Every sidebar link that can show a status dot **must follow this exact pattern** — no exceptions:

```blade
<a href="..." class="flex items-center gap-2.5 px-2 py-1.5 ...">
    <svg ...>...</svg>
    <span class="flex-1">Label</span>          {{-- flex-1 pushes dot to the right --}}
    @if($hasDot)
        <span class="w-1.5 h-1.5 rounded-full bg-red-500 shrink-0"></span>
    @endif
</a>
```

Rules:
- Dot size: **`w-1.5 h-1.5`** always — never `w-2 h-2` or other sizes
- Label must be wrapped in **`<span class="flex-1">`** — never plain text — so the dot is pushed to the right edge
- No `ml-auto` on the dot — the `flex-1` span handles alignment
- Dot colors: `bg-red-500` (active), `bg-amber-400` (partially_active), `bg-green-500` (completed)

### No `@php` in Blade views

**Zero `@php` blocks in any view file.** All logic belongs in:
- Controllers (passed as view data)
- `App\Providers\AppServiceProvider` View Composer for `layouts.app`
- Model methods

The only exception: `resources/views/components/isolated-html.blade.php` uses one `@php uniqid()` call (acceptable).

### Layout View Composer

`AppServiceProvider` registers a View Composer for `layouts.app` that provides:
`$topSections`, `$sidebarItems`, `$syncItems`, `$drItems`, `$isConfigRoute`, `$onMapping`, `$currentMapping`, `$mainMargin`, `$disabledMsg`, `$taActive`, `$segActive`

Do **not** put this logic back into the Blade layout.

### UI Pattern for CRUD sections

**Separate pages for create/edit — never inline forms inside tables.**

Pattern (follow synchronizer servers as the reference implementation):
- Index page: table with an "Add …" / "Create …" button in the `page-header` div
- That button links to a dedicated `create` route → separate `*-form.blade.php` view
- Edit link in each row links to a dedicated `edit` route → same form view with model pre-filled
- Form page has a page-header with title + "← Back" link, a card with the form, Save/Cancel buttons

---

## Setup Assistant (`/configuration/setup-assistant`)

Checklist page that tracks whether the system is properly configured. Items have dependencies — if a prerequisite isn't done, dependent items are `disabled`.

### Item statuses
| Status | Color | Meaning |
|--------|-------|---------|
| `disabled` | gray | Blocked by incomplete previous step |
| `active` | red | Needs action to become completed |
| `partially_active` | yellow | Progress made but not complete |
| `completed` | green | Automatically verified as done |

### Items (in order, with dependency chain)

1. **System up to date** — always `completed` (versioning not implemented yet)
2. **Add connector server** — `active` if no `SynchronizerServer` row exists; `completed` otherwise
3. **Configure connections** — `disabled` if no server; tries HTTP GET to synchronizer `/api/connections` (5s timeout), falls back to `Account::exists()` proxy on failure; `active` if 0 connections; `completed` if ≥1
4. **Configure mapping** — `disabled` if no server OR `Account::exists()` is false; checks BOTH `accounts.company_id` (linked to companies) AND `identities.person_id` (linked to people, excluding bots), grouped per system_type/system_slug; worst ratio across all groups: <50% → `active`, <80% → `partially_active`, ≥80% → `completed`
5. **Set your organization contacts** — `disabled` if no server or no data; `active` if no `Person.is_our_org=true` AND no `Identity.is_team_member=true`; `completed` otherwise

### Display layout
- `active`, `partially_active`, `disabled` items → "Requires Your Attention" section (shown only if non-empty)
- `completed` items → "Completed" section
- All cards identical size and structure regardless of status — name, description, status badge, action button
- If attention section empty → green "fully operational" banner shown

### Dot indicators
- Any `active` item → **red dot** in sidebar + **red dot** in top "Configuration" menu (`$configNeedsAttention = true`)
- Only `partially_active` (no active) → **yellow dot** in sidebar only, no top menu dot
- Nothing in attention (all completed) → **green dot** in sidebar only

### Key implementation notes
- `$setupStatus` computed in `AppServiceProvider` View Composer, cached under `layout.setup_status` (60s, DB-only — no API call)
- `$configNeedsAttention` = `$serverNeedsAttention || $mappingNeedsAttention || $setupStatus === 'active'`
- `$saActive` bool passed to layout for sidebar active state
- `setup-assistant.*` included in `$isConfigRoute` pattern
- Controller: `App\Http\Controllers\SetupAssistantController` — passes `$items`, `$attention`, `$completed`, `$statusConfig` to view (no `@php` in Blade)
- **Cache must be cleared** (`php artisan cache:clear`) after structural changes to mapping data for dot to update immediately

---

## Team Access (`/configuration/team-access`)

Manages users and permission groups. Two tabs: Users / Groups.

- **Index** (`team-access.index`): table of users or groups depending on active tab. "Add User" / "Create Group" button in page-header links to separate create page.
- **User form** (`team-access.users.create` / `team-access.users.edit`): `resources/views/configuration/team-access/user-form.blade.php`
- **Group form** (`team-access.groups.create` / `team-access.groups.edit`): `resources/views/configuration/team-access/group-form.blade.php`
- `GroupsController::permLabels()` is a static method returning the permission labels array — pass it to views as `$permLabels`, do not inline it in Blade.
- Team Access appears **below General Settings** in the configuration sidebar (order: Setup Checklist → General Settings → Team Access).
- `team-access.*` routes must be included in the `$isConfigRoute` check in the View Composer so the config sidebar renders correctly.

---

## Critical Rules & Known Bugs Fixed

### Activity Widget (`/activity`)

- **Tabs**: All / Conversations / Activity (Activity tab uses `exclude_type=conversation`)
- **Never** pass `showPersonLink` or `showCompanyLink` to `activity/partials/timeline-items.blade.php` — they must not appear in the global activity list.
- AJAX pagination via `/activity/timeline?cursor=...` mirrors the initial SSR render.
- **Search + filters**: Always-visible search bar (input + Search + Clear buttons) with collapsible filter panel — same pattern as people/companies index. "Filters" button shows brand-primary color + count badge when filters are active. Channels and activity type filters are inline checkbox labels (not dropdowns). AJAX endpoint `/activity/timeline` accepts `q` param for text search (searches description, company name, person name).

### Conversations (`/conversations`)

- `$filteredQuery` in `ConversationController::index()` **must** include `filter_subjects` (from `SystemSetting`) — same logic as the main listing. Tab counts (All/Unread/Archived) are derived from `$filteredQuery`, not a plain query.
- **Bulk select**: Checkbox column + select-all in `<thead>`, table wrapped in `<form id="conv-bulk-form">`. Bulk bar has "Filter…" button (under `@can('data_write')`). JS: `convUpdateBulkBar()`, `convToggleAll()`, `convClearSelection()`, `convOpenFilterModal()`.

### Data Relations Mapping (`/data-relations/mapping/{type}/{slug}`)

- **WHMCS / MetricsCube** (account-based systems): contacts shown **inline** under account rows. There is **no** separate People/Contacts tab and **no** orphan contacts section.
- `$identitiesByExtId` must be built over **all** accounts (not the paginated subset) for inline matching to work correctly.
- Identity-to-account matching order: (1) `meta_json['account_external_id']`, (2) primary email fallback via `account.meta_json['email']`.

### PersonController

- `PersonController::personActivitiesQuery()` must **not** use `.distinct()` — it breaks PostgreSQL `ORDER BY` with `SQLSTATE[42P10]`.

### Our Org system (People)

- `Person.is_our_org` is the canonical flag; `Identity.is_team_member` is derived/secondary.
- **people/index**: No per-row Our Org toggle — bulk management only.
  - Clients tab bulk bar: "Mark as Our Org" button.
  - Our Org tab bulk bar: "Unmark Our Org" button (POSTs to `people.bulk-unmark-our-org`).
  - Our Org rows get `bg-brand-50/60` tinted background.
- **people/show**: "Unmark Our Org" button (muted) when `is_our_org = true`; "Our Org" button (org style) when false. Both reload the page on success.
- Routes: `POST people/bulk-unmark-our-org` → `PersonController::bulkUnmarkOurOrg()`, `POST people/{person}/unmark-our-org` → `PersonController::unmarkOurOrg()`.
- Person card header gradient: `from-brand-600 to-brand-800` (brand magenta) — **not** dark indigo or any other color.

### Discord avatars

- When `meta_json['avatar']` is empty for a Discord identity, fall back to the Discord default avatar:
  ```
  https://cdn.discordapp.com/embed/avatars/{N}.png
  ```
  where `N = (int) substr($discordUserId, -1) % 5`.
- Custom avatar URL format: `https://cdn.discordapp.com/avatars/{user_id}/{hash}.webp?size=128`
- Implemented in: `DataRelationsController`, `ConversationMessage::chatAvatarUrl()`, `ConversationController` (participant avatars).

### Filter modals (people / companies / conversations) — tag-input

- All filter modals use a **tag-input** UI for multi-value fields (domain, email, subject).
- Backend accepts `rule_values[]` array; falls back to single `rule_value` for backwards compat. Both `FilteringController::applyRule()` and `ConversationController::archiveWithRule()` use this pattern.
- **JS rule**: Modal content is AJAX-loaded — Alpine.js does **not** auto-init inside it. All tag-input JS must use plain DOM (`createElement`, `addEventListener`). **Never** build `onkeydown="handler(event, 'value')"` inline attributes using `JSON.stringify` — double-quoted strings inside double-quoted HTML attributes silently break the handler. Always attach event listeners via `addEventListener` in a closure.
- Suggested values rendered as chip buttons with `data-add-tag` / `data-val` attributes; click handler adds tag and switches type tab.
- Submit: `fmBeforeSubmit()` / `pfmBeforeSubmit()` / `cfmBeforeSubmit()` sync hidden `rule_values[]` inputs before form posts.

### Breadcrumb back-link (`resolveBackLink`)

- `Controller::resolveBackLink(Request $request)` reads the HTTP `Referer` header, matches `/companies/{id}`, `/people/{id}`, `/conversations/{id}`, and returns `['url' => ..., 'label' => ...]`.
- **Self-link guard**: if the referer path equals the current request path (`'/' . $request->path()`), returns `null` — prevents a link to the page you're already on (e.g. after a reload triggered by a form action).
- Used in `PersonController::show()`, `CompanyController::show()`, `ConversationController::show()`.

---

## CSS / Style System

All global styles live in **`resources/css/app.css`**. Do **not** invent per-page inline styles for things that already have a class — use the class. When a new pattern appears in 2+ places, add a class to `app.css`.

### Class inventory

| Class | Use |
|-------|-----|
| `.card` | Standard content card — white bg, gray border, `0.5rem` radius |
| `.card-xl` | Larger card variant (`0.75rem` radius) for show-page main sections |
| `.card-xl-overflow` | Same as `.card-xl` but with `overflow: hidden` (use when card contains a table) |
| `.card-header` | Top row inside a `.card` — flex, space-between, border-bottom |
| `.card-inner` | Inner section divider inside a card — just a top border |
| `.section-header` | Title row inside a card section — flex, space-between, border-bottom |
| `.section-header-title` | Text inside `.section-header` — `0.875rem`, semibold |
| `.page-header` | Top row of every page — flex, space-between, `1.25rem` bottom margin |
| `.page-title` | Text inside `.page-header` — `1.125rem`, bold |
| `.tbl-header` | `<thead>` style — gray bg, small caps text |
| `.tbl-row` | `<tbody> <tr>` style — top border, hover bg |
| `.bulk-bar` | Bulk action bar — amber tinted bg/border |
| `.bulk-bar-text` | Text color inside bulk bar — amber/brown |
| `.divider` | Simple horizontal rule between sections — top border only |
| `.modal-center` | Absolutely-centered modal overlay — `position:absolute; top:50%; left:50%; transform:translate(-50%,-50%)` |
| `.modal-overlay` | Full-screen glassmorphism scrim — `rgba(0,0,0,0.3)` + `backdrop-filter: blur(4px)` |
| `.sidebar` | Dark sidebar shell — `#161b22` bg, `#30363d` border |
| `.sidebar-section` | Section label inside sidebar — muted uppercase text |
| `.sidebar-link` | Nav link in sidebar — add `is-active` for active state, `is-disabled` for disabled |
| `.sidebar-icon` | Icon inside a `.sidebar-link` — color managed via parent's state |
| `.sidebar-divider` | Horizontal rule between sidebar sections |
| `.alert-warning` | Amber tinted notice box — amber-50 bg, amber-300 border |
| `.alert-success` | Green tinted notice box — green-50 bg, green-300 border |
| `.alert-danger` | Red tinted notice box — red bg, red border, dark red text |
| `.code-block` | Dark monospace code block — `#1e2430` bg, light text, monospace font |
| `.btn` | Base button — inline-flex, rounded, padded |
| `.btn-sm` | Smaller button variant |
| `.btn-primary` | Blue filled button |
| `.btn-secondary` | White/bordered button |
| `.btn-danger` | Red outlined button (used for Filter / destructive) |
| `.btn-muted` | Gray muted button |
| `.input` | Text input |
| `.label` | Input label — small caps, `0.75rem` |
| `.badge` | Inline pill badge |
| `.badge-{gray,blue,green,red,yellow}` | Colored badge variants |

### Brand colors

Three system colors — always use these, never arbitrary blues/teals:

| Token | Hex | Usage |
|-------|-----|-------|
| **Primary** | `#A40057` | Logo, primary buttons, active states, key accents. Used sparingly — high impact only. `--color-brand-*` shades radiate from this. |
| **Dark base** | `#212731` | Top header background. Shades of this color for sidebar, navigation backgrounds, dark typography, dark UI elements. |
| **Light accent** | `#F1FFFA` | Reserved for AI-related features and themes. Light shades only. |

`--color-brand-*` CSS vars run from brand-50 (very light pink) to brand-900 (very dark magenta) centered on `#A40057` at brand-600.

Sidebar active state uses brand color tinted bg + brighter brand shade for text (readable on dark bg).

### Sidebar

Always use `.sidebar-link` / `.sidebar-icon` / `.sidebar-section` / `.sidebar-divider` for sidebar nav — never raw Tailwind color classes. Active state = add `is-active` to `.sidebar-link`. Disabled = `is-disabled`. The icon color is managed by CSS via parent state — no conditional classes on `sidebar-icon`.

### Rules

1. **Never use inline `style=` for layout/color that a utility class covers.** Use the class.
2. **Bulk action bars** → always `<div class="... bulk-bar">` + `<span class="... bulk-bar-text">`. Never inline amber/yellow colors.
3. **Card with table** → use `.card-xl-overflow` (not `.card` or `.card-xl`). `.card` is for smaller sections.
4. **Modal centering** → use `.modal-center`. Never repeat `position:absolute; top:50%...` inline.
5. **Page titles** → always `<div class="page-header">` + `<span class="page-title">`. Never inline font-size/font-weight for a page title.
6. **Section headers inside cards** → `.section-header` + `.section-header-title`. Not ad-hoc padding classes.
7. **New repeated pattern?** → add a class to `app.css` before writing it a second time in a view.

---

## Frontend

Tailwind CSS v4 compiled by Vite. Compiled assets land in `public/build/`. The `@vite` directive in `resources/views/layouts/app.blade.php` serves them. In production only the compiled manifest is used — no HMR server.

`flatpickr` is the only runtime JS dependency (date pickers).

---

## Tests

- PHPUnit with two suites: `Unit` (`tests/Unit/`) and `Feature` (`tests/Feature/`)
- Test database: SQLite in-memory (`:memory:`) — configured in `phpunit.xml`
