# Front-End Rewrite Plan: Blade + Alpine.js → Vue 3 + Inertia.js

---

## 1. Architecture Overview

### Current State
- **Layout:** `layouts/app.blade.php` (Blade + Alpine.js) — top bar, sidebar, content area, flash messages, modals
- **Analyse section:** Already Vue + Inertia with own layout (`analyse.blade.php` → `AnalyseLayout.vue`)
- **~83 Blade views** across Browse Data + Configuration + Auth + Modals
- **Alpine.js** for dropdowns, mobile sidebar toggle, bulk selection
- **Plain JS** for modals (AJAX-loaded), filter panels, tag inputs, timeline infinite scroll, date pickers (easepick)
- **No SPA navigation** — every page is a full reload

### Target State
- **Single Inertia root template** (replaces both `layouts/app.blade.php` and `analyse.blade.php`)
- **One Vue app entry point** with unified layout component
- **All pages are Vue SFC** rendered via Inertia
- **Shared component library** for tables, forms, modals, badges, filters, etc.
- **SPA navigation** between all sections (Browse Data, Configuration, Analyse)
- **No Alpine.js** — all reactivity handled by Vue
- **No AJAX-loaded HTML partials** — modals and infinite scroll use Vue components + JSON endpoints

---

## 2. Visual & Architectural Requirements (from CLAUDE.md)

### 2.1 CSS / Style System

All global styles in **`resources/css/app.css`**. Do not invent per-page inline styles — use the class. When a pattern appears 2+ times, add a class.

#### Class Inventory

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

#### Brand Colors

| Token | Hex | Usage |
|-------|-----|-------|
| **Primary** | `#A40057` | Logo, primary buttons, active states. `--color-brand-*` shades from brand-50 to brand-900. |
| **Dark base** | `#212731` | Top header, sidebar, dark UI elements. |
| **Light accent** | `#F1FFFA` | AI-related features. Light shades only. |

`--color-brand-600` = `#A40057` (anchor point of the scale).

#### Sidebar Rules
- Always `.sidebar-link` / `.sidebar-icon` / `.sidebar-section` / `.sidebar-divider` — never raw Tailwind color classes.
- Active state = add `is-active`. Disabled = `is-disabled`. Icon color managed by CSS, not conditional classes.

#### Style Rules
1. Never `style=` for layout/color a utility class covers.
2. Bulk bars → `.bulk-bar` + `.bulk-bar-text`. Never inline amber.
3. Card with table → `.card-xl-overflow`.
4. Modal centering → `.modal-center`.
5. Page titles → `.page-header` + `.page-title`.
6. Section headers → `.section-header` + `.section-header-title`.
7. New repeated pattern → add class to `app.css` before second use.
8. **Disabled elements must always explain why** — show a short text near the disabled control explaining the prerequisite (e.g. "Add an AI credential to configure model assignments"). Sidebar disabled items use `title` tooltip; page-level disabled sections add visible explanatory text.
9. **AI accent** — use `.sidebar-section-ai` for AI section labels. Use `ai-icon.svg` sparkle for AI-powered items in nav. Keep accents subtle and limited to navigation/labels.

### 2.2 Tailwind CSS v4 + Vue SFC Constraint

**`@apply` is NOT supported inside Vue `<style>` blocks.** Use plain CSS or Tailwind utility classes in templates. For component-scoped styles, write plain CSS in `<style scoped>`.

### 2.3 UI Patterns to Preserve

#### PATTERN 1 — Activity Modal (Quick-view Popup)
- Currently AJAX-loaded HTML into `#activity-modal-body`
- In Vue: `<Modal>` component + JSON endpoint, content rendered as Vue template
- Auto-scroll to bottom if content has `data-scroll-bottom` equivalent (prop)

#### PATTERN 2 — Filter Panel
- Collapsible panel with filters button showing active count badge
- Filters button: brand-primary + count badge when active, secondary when inactive
- Grid layout: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`
- Clear filters + Apply buttons at bottom right
- **Date range critical:** Laravel's `ConvertEmptyStringsToNull` middleware converts empty `value=""` to `null`. Always use `!empty()` not `!== ''` in controllers.

#### PATTERN 3 — Bulk Selection
- Checkbox column + select-all header checkbox
- Bulk bar appears when rows selected (`.bulk-bar`)
- Count display + action buttons + clear selection
- In Vue: `DataTable` manages selection state, emits to `BulkBar`

#### PATTERN 4 — Tag-input Multi-value
- Enter or comma adds a tag (for domain/email); Enter only for subject
- Backspace on empty input removes last tag
- In Vue: dedicated `TagInput.vue` component with v-model

#### PATTERN 5 — Table Section
- Create/Add buttons go OUTSIDE the table card — in `page-header` or title row above card
- Table card uses `card-xl-overflow`
- Row actions: desktop inline + mobile "..." dropdown
- Empty state: `<td class="... empty-state italic">No items.</td>`

#### PATTERN 6 — Create/Edit Form Page
- Separate pages for create and edit — never inline forms inside tables
- Back link breadcrumb at top
- Card with form, `max-w-2xl`
- Save + Cancel buttons at bottom

#### PATTERN 7 — Sidebar Dot Status
- Dot size: **`w-1.5 h-1.5`** — never other sizes
- No `ml-auto` on dot — `flex-1` span handles alignment
- Colors: `bg-red-500` (active), `bg-amber-400` (partially_active), `bg-green-500` (completed)

#### PATTERN 8 — Activity Timeline + AJAX Cursor Pagination
- In Vue: `TimelineInfiniteScroll.vue` with JSON endpoint
- Sentinel div triggers fetch when visible (Intersection Observer)
- `prepareTimelineDisplay()` in controller sets `$activity->display` data
- **Never** pass `showPersonLink` / `showCompanyLink` to global activity timeline

#### PATTERN 9 — Notes Section
- Yellow-tinted card, scrollable list (max-h-72)
- Add note textarea + delete button
- All write UI gated by permissions

#### PATTERN 10 — Breadcrumb Back-link
- `resolveBackLink()` uses HTTP Referer
- Returns null if Referer === current page
- Supported referer patterns: `/companies/{id}`, `/people/{id}`, `/conversations/{id}`

#### PATTERN 11 — Alpine.js Dropdown Multi-select → Vue Component
- Channel/system selectors on filter panels
- Button text shows count or "All"
- Highlight selected options
- In Vue: `MultiSelectDropdown.vue` with v-model

### 2.4 Key Data/Rendering Rules

- **Merge:** `merged_into_id` FK (self-referential). Always apply `scopeNotMerged()` on list/count queries. Show amber banner on merged entity pages.
- **Discord Avatars:** Fallback `https://cdn.discordapp.com/embed/avatars/{N}.png` where `N = (int) substr($discordUserId, -1) % 5`
- **Slack Mention Resolution:** `<@U0AGR11DLE4>` → display name via `slackMentionMap`
- **Discord Mention Resolution:** `<@ID>` → display name via `discordMentionMap`
- **PersonController:** `personActivitiesQuery()` must NOT use `.distinct()` — breaks PostgreSQL ORDER BY
- **Conversations:** `$filteredQuery` must include `filter_subjects` from SystemSetting
- **Person Show Card:** Header gradient `from-brand-600 to-brand-800` (brand magenta)
- **Conversation Preview Modal:** Messages in `orderByDesc` order (newest first) — do NOT reverse

### 2.5 ACL / Permissions in Vue

- `@can('data_write')` → `v-if="can('data_write')"` via `usePermissions()` composable
- **Never show write UI to restricted users — hide completely, never rely on 403**
- Create/edit/delete buttons → gated by `data_write`
- Notes write forms → gated by `notes_write`

### 2.6 Markdown Rendering (Analyse Chat)

- `marked.js` for assistant messages via `.prose-ai` CSS class
- Plain CSS in `<style>` blocks (not `@apply`)
- Full `.prose-ai` style inventory already exists in `ChatMessage.vue`

### 2.7 No `@php` in Views → No Inline Logic in Components

All logic belongs in controllers (passed as Inertia props), composables, or model methods. Vue components should be purely presentational + reactive — no data fetching or business logic in component `<script setup>` beyond UI state management.

---

## 3. Shared Component Library

### 3.1 Layout Components

| Component | Replaces | Purpose |
|-----------|----------|---------|
| `AppLayout.vue` | `layouts/app.blade.php` + `AnalyseLayout.vue` | Unified shell: top bar, sidebar, content slot. Detects section (Browse/Config/Analyse) from route. |
| `TopBar.vue` | Header in `app.blade.php` | Logo, section nav tabs, user dropdown. |
| `Sidebar.vue` | Sidebar in `app.blade.php` | Dynamic sidebar: renders Browse Data, Configuration, or Analyse sidebar based on current section. |
| `BrowseDataSidebar.vue` | Browse Data sidebar block | Dashboard, Companies, People, Conversations, Activity, etc. |
| `ConfigSidebar.vue` | Configuration sidebar block | Setup Assistant, Team Access, Synchronization, Data Relations, AI, Segmentation |
| `MappingSubSidebar.vue` | Secondary sidebar (mapping connections) | Data relations mapping connection list |
| `AnalyseSidebar.vue` | Existing — **keep as-is** | Chat sidebar (already Vue) |

**Key change:** The current View Composer (`AppServiceProvider`) that computes `$topSections`, `$sidebarItems`, `$syncItems`, `$drItems`, `$aiItems` becomes **Inertia shared props** via `HandleInertiaRequests` middleware.

### 3.2 Data Display Components

| Component | Replaces | Purpose |
|-----------|----------|---------|
| `DataTable.vue` | All `<table>` + `tbl-header` + `tbl-row` patterns | Generic sortable table with header/row slots, empty state, sort arrows + URL params. |
| `Pagination.vue` | `{{ $items->links() }}` | Standard pagination (page-based and cursor-based). |
| `Badge.vue` | `<x-badge>` | Colored pill badge with variant prop. |
| `ChannelBadge.vue` | `<x-channel-badge>` | System type icon (WHMCS, Slack, Discord, etc.) |
| `ConvChannelIcon.vue` | `<x-conv-channel-icon>` | Conversation channel icon with label |
| `StageBadge.vue` | `<x-stage-badge>` | Brand status stage pill |
| `ScoreRing.vue` | `<x-score-ring>` | SVG circular progress for brand score |
| `BrandStatusCell.vue` | `<x-brand-status-cell>` | Combined stage + score in table cell |
| `LinkedPctBar.vue` | `<x-linked-pct-bar>` | Percentage bar for mapping completeness |
| `PersonAvatar.vue` | `<x-person-avatar>` | Avatar circle with initials |
| `IdentityIcon.vue` | `<x-identity-icon>` | Identity type icon (email, slack, discord) |
| `TypeIcon.vue` | `synchronizer/_type_icon.blade.php` | Integration system type icon |

### 3.3 Form Components

| Component | Replaces | Purpose |
|-----------|----------|---------|
| `FormInput.vue` | All `<input class="input w-full">` + `<label class="label">` + `@error` | Text/email/number input with label, error display, v-model. |
| `FormTextarea.vue` | All `<textarea class="input">` blocks | Textarea with label, error, v-model. |
| `FormSelect.vue` | All `<select class="input">` blocks | Dropdown with label, error, options prop. |
| `FormCheckbox.vue` | Checkboxes in forms | Styled checkbox with label. |
| `FormPage.vue` | Pattern 6 — create/edit form pages | Card wrapper with breadcrumb, title, form slot, save/cancel buttons. |
| `TagInput.vue` | Pattern 4 — filter modal tag-input JS | Multi-value tag input (enter/comma to add, backspace to remove). |
| `DateRangePicker.vue` | `drp.init()` easepick wrapper | Vue wrapper around easepick. Emits `update:from` / `update:to`. |
| `MultiSelectDropdown.vue` | Pattern 11 — Alpine.js dropdown with checkboxes | Channel/system multi-select. |

### 3.4 Interaction Components

| Component | Replaces | Purpose |
|-----------|----------|---------|
| `Modal.vue` | `<x-activity-modal>` + all AJAX modals | Teleported modal with overlay, close on ESC/click-outside, content slot. |
| `ConfirmDialog.vue` | `confirm()` calls in JS | "Are you sure?" modal with custom message. |
| `BulkBar.vue` | Pattern 3 — bulk selection bar | Selected count + action buttons. Works with DataTable selection state. |
| `FilterPanel.vue` | Pattern 2 — collapsible filter panel | Collapsible card with filter slots, active count badge, clear/apply buttons. |
| `FlashMessages.vue` | Flash messages in layout | Auto-dismissing success/error/warning banners from Inertia shared props. |
| `Tabs.vue` | All tab bars (companies, conversations, etc.) | Horizontal tab bar with counts, active state, URL-driven. |
| `Breadcrumb.vue` | Pattern 10 — breadcrumb back-link | Dynamic breadcrumb from props. |
| `NotesSection.vue` | `<x-notes-section>` Blade component | Notes list + add form + delete, permission gating via props. |
| `TimelineInfiniteScroll.vue` | Pattern 8 — AJAX cursor pagination | Activity timeline with auto-loading on scroll via JSON endpoints. |
| `TimelineItem.vue` | `partials/timeline-items.blade.php` | Single timeline entry: icon, label, description, entity links, date, modal trigger. |
| `IsolatedHtml.vue` | `<x-isolated-html>` (Shadow DOM) | Renders raw HTML in shadow root / iframe. For email body_html. |
| `MessageBody.vue` | `<x-message-body>` Blade component | Renders message content: HTML (shadow DOM), markdown, or plain text. |
| `EntitySearch.vue` | `_ac-company.blade.php` + `_ac-person.blade.php` | Autocomplete search for companies/people. Used in many places. |
| `MergeModal.vue` | `companies/merge-modal` + `people/merge-modal` | Shared merge modal for companies and people. |
| `AssignCompanyModal.vue` | `people/assign-company-modal.blade.php` | Modal with company search for assigning to person. |

### 3.5 Existing Analyse Components (Keep / Extend)

Already exist, no rewrite needed:
- `ChatMessage.vue`, `AnalyseSidebar.vue`, `SearchBar.vue`, `ConversationRow.vue`, `ProjectRow.vue`, `SharedRow.vue`, `ContextMenu.vue`

---

## 4. Page-by-Page Migration Map

### 4.1 Auth (no sidebar, minimal layout)

| Blade View | Vue Page | Notes |
|------------|----------|-------|
| `auth/login.blade.php` | `pages/Auth/Login.vue` | Standalone page, no sidebar. Uses guest layout variant. |
| `auth/setup.blade.php` | `pages/Auth/Setup.vue` | First-run admin creation. Guest layout. |
| `auth/change-password.blade.php` | `pages/Auth/ChangePassword.vue` | Authenticated, uses AppLayout. |

### 4.2 Browse Data Section

| Blade View | Vue Page | Key Components Used |
|------------|----------|---------------------|
| `dashboard.blade.php` | `pages/Dashboard.vue` | DateRangePicker, stat cards, DataTable for active contacts/team |
| `companies/index.blade.php` | `pages/Companies/Index.vue` | Tabs, FilterPanel, DataTable, BulkBar, Badge, BrandStatusCell, Pagination |
| `companies/show.blade.php` | `pages/Companies/Show.vue` | Breadcrumb, NotesSection, TimelineInfiniteScroll, Modal (domains/aliases/accounts/brand status/merge), ChannelBadge |
| `companies/create.blade.php` | `pages/Companies/Create.vue` | FormPage, FormInput |
| `companies/edit.blade.php` | `pages/Companies/Edit.vue` | FormPage, FormInput |
| `companies/filter-modal.blade.php` | Inline in `Companies/Index.vue` | Modal, TagInput — no longer AJAX-loaded |
| `companies/merge-modal.blade.php` | `components/MergeModal.vue` | Shared between companies and people |
| `people/index.blade.php` | `pages/People/Index.vue` | Tabs, FilterPanel, DataTable, BulkBar, PersonAvatar, Pagination |
| `people/show.blade.php` | `pages/People/Show.vue` | Breadcrumb, NotesSection, TimelineInfiniteScroll, Modal, IdentityIcon, activity charts (bar + heatmap) |
| `people/create.blade.php` | `pages/People/Create.vue` | FormPage, FormInput, FormCheckbox |
| `people/edit.blade.php` | `pages/People/Edit.vue` | FormPage, FormInput, FormCheckbox |
| `people/filter-modal.blade.php` | Inline in `People/Index.vue` | Modal, TagInput |
| `people/merge-modal.blade.php` | Uses shared `MergeModal.vue` | Same component as companies merge |
| `people/assign-company-modal.blade.php` | `components/AssignCompanyModal.vue` | Modal with company search |
| `conversations/index.blade.php` | `pages/Conversations/Index.vue` | Tabs, FilterPanel, MultiSelectDropdown, DataTable, BulkBar, ChannelBadge, Modal |
| `conversations/show.blade.php` | `pages/Conversations/Show.vue` | MessageBody, IsolatedHtml, participants list, NotesSection |
| `conversations/modal.blade.php` | `components/ConversationQuickView.vue` | Modal — loaded via JSON, not AJAX HTML |
| `conversations/filter-modal.blade.php` | Inline in `Conversations/Index.vue` | Modal, TagInput |
| `conversations/partials/messages.blade.php` | `components/ConversationMessages.vue` | Message list, channel-specific rendering, mention resolution |
| `activity/index.blade.php` | `pages/Activity/Index.vue` | Tabs, FilterPanel, MultiSelectDropdown, TimelineInfiniteScroll, DateRangePicker |
| `activity/partials/timeline-items.blade.php` | Uses `TimelineItem.vue` | No longer Blade partial |
| `smart-notes/index.blade.php` | `pages/SmartNotes/Index.vue` | Tabs, DataTable, Pagination |
| `smart-notes/recognize.blade.php` | `pages/SmartNotes/Recognize.vue` | Segment editor, company/person search, save/cancel |
| `audit-log/index.blade.php` | `pages/AuditLog/Index.vue` | DataTable, Pagination, FilterPanel |
| `ai-costs/index.blade.php` | `pages/AiCosts/Index.vue` | DateRangePicker, stat cards, DataTable |
| `ai-costs/pricing.blade.php` | `pages/AiCosts/Pricing.vue` | DataTable with editable cells |
| `partials/timeline-items.blade.php` | `TimelineItem.vue` component | Unified timeline item renderer |
| `partials/activity-stats.blade.php` | Inline in relevant pages | Bar chart + heatmap (person show) |
| `filtering/identity-filter-modal.blade.php` | Inline in DataRelations Mapping | Modal for filtering identity |
| `integrations/whmcs/services-widget.blade.php` | `components/WhmcsServicesWidget.vue` | WHMCS services display on company show |

### 4.3 Configuration Section

| Blade View | Vue Page | Key Components Used |
|------------|----------|---------------------|
| `configuration/setup-assistant/index.blade.php` | `pages/Config/SetupAssistant.vue` | Checklist cards with status dots |
| `configuration/team-access/index.blade.php` | `pages/Config/TeamAccess/Index.vue` | Tabs (Users/Groups), DataTable |
| `configuration/team-access/user-form.blade.php` | `pages/Config/TeamAccess/UserForm.vue` | FormPage, FormInput, FormSelect |
| `configuration/team-access/group-form.blade.php` | `pages/Config/TeamAccess/GroupForm.vue` | FormPage, FormInput, FormCheckbox |
| `data-relations/index.blade.php` | `pages/Config/DataRelations/Index.vue` | Stats cards, system breakdown table |
| `data-relations/mapping.blade.php` | `pages/Config/DataRelations/Mapping.vue` | Account/identity tables, inline editing, link modals, bot toggle. DataTable, Modal, EntitySearch. |
| `data-relations/filtering.blade.php` | `pages/Config/DataRelations/Filtering.vue` | Tabs, TagInput (domains/emails/subjects), contacts table |
| `data-relations/our-company.blade.php` | `pages/Config/DataRelations/OurCompany.vue` | Tabs, FormInput (team domains), DataTable (team members) |
| `data-relations/_ac-company.blade.php` | `EntitySearch.vue` | Reusable company autocomplete |
| `data-relations/_ac-person.blade.php` | `EntitySearch.vue` | Reusable person autocomplete |
| `data-relations/_link-person-panel.blade.php` | Inline in Mapping.vue | Link person panel |
| `data-relations/_people-toolbar.blade.php` | Inline in Mapping.vue | People toolbar |
| `synchronizer/index.blade.php` | `pages/Config/Synchronizer/Index.vue` | DataTable for connections, run/stop/status |
| `synchronizer/show.blade.php` | `pages/Config/Synchronizer/Show.vue` | Connection detail, runs list, logs viewer |
| `synchronizer/form.blade.php` | `pages/Config/Synchronizer/Form.vue` | FormPage, dynamic fields per integration type |
| `synchronizer/servers/index.blade.php` | `pages/Config/Synchronizer/Servers/Index.vue` | DataTable, test button |
| `synchronizer/servers/form.blade.php` | `pages/Config/Synchronizer/Servers/Form.vue` | FormPage |
| `synchronizer/wizard/step1.blade.php` | `pages/Config/Synchronizer/Wizard/Step1.vue` | Wizard step |
| `synchronizer/wizard/connect-existing.blade.php` | `pages/Config/Synchronizer/Wizard/ConnectExisting.vue` | Wizard step |
| `synchronizer/wizard/install-script.blade.php` | `pages/Config/Synchronizer/Wizard/InstallScript.vue` | Code block + polling |
| `synchronizer/wizard/configure-new.blade.php` | `pages/Config/Synchronizer/Wizard/ConfigureNew.vue` | Dynamic form |
| `configuration/smart-notes/index.blade.php` | `pages/Config/SmartNotes/Index.vue` | Tabs, DataTable, toggle switch |
| `configuration/smart-notes/create-filter.blade.php` | `pages/Config/SmartNotes/CreateFilter.vue` | FormPage, dynamic fields per filter type |
| `configuration/ai/index.blade.php` | `pages/Config/Ai/Index.vue` | Tabs (Credentials/Models), DataTable, FormInput |
| `configuration/ai/credential-form.blade.php` | `pages/Config/Ai/CredentialForm.vue` | FormPage, FormSelect (provider), FormInput (API key) |
| `configuration/ai/mcp-server.blade.php` | `pages/Config/Ai/McpServer.vue` | Toggle, code block, API key display |
| `brand-products/index.blade.php` | `pages/Config/BrandProducts/Index.vue` | DataTable |
| `brand-products/create.blade.php` | `pages/Config/BrandProducts/Create.vue` | FormPage |
| `brand-products/edit.blade.php` | `pages/Config/BrandProducts/Edit.vue` | FormPage |

### 4.4 Analyse Section (Already Vue — Minimal Changes)

| Current Vue Page | Changes Needed |
|-----------------|----------------|
| `pages/Analyse.vue` | Switch from standalone `AnalyseLayout` to `AppLayout` with `section="analyse"` prop |
| `pages/Chat.vue` | Same |
| `pages/Project.vue` | Same |

The `AnalyseSidebar.vue` becomes a child rendered by `Sidebar.vue` when `section === 'analyse'`.

---

## 5. Controller Changes

### 5.1 Pattern: Blade → Inertia

Every controller method that does `return view('blade.template', $data)` changes to:

```php
return Inertia::render('PageName', $data);
```

### 5.2 HandleInertiaRequests Middleware

Create `app/Http/Middleware/HandleInertiaRequests.php` providing **shared props**:

```
auth.user          → {id, name, email, group, permissions}
auth.permissions   → {browse_data, data_write, notes_write, configuration, analyse}
flash.success      → session('success')
flash.error        → session('error')
layout.topSections → current top nav sections
layout.sidebarData → sidebar items for current section
layout.setupStatus → setup assistant status
layout.hasAiCredentials → boolean
layout.configNeedsAttention → boolean
```

Replaces the View Composer in `AppServiceProvider`.

### 5.3 AJAX Endpoints → JSON

| Current Endpoint | Current Return | New Return |
|-----------------|---------------|------------|
| `GET /companies/{id}/timeline` | HTML partial | JSON `{items: [...], nextCursor: ...}` |
| `GET /people/{id}/timeline` | HTML partial | JSON |
| `GET /activity/timeline` | HTML partial | JSON |
| `GET /conversations/{id}/modal` | HTML partial | JSON (messages array) |
| Company/Person/Conversation filter modals | HTML (AJAX-loaded) | No longer needed — Vue components |

### 5.4 Unified Inertia Root View

One root view `app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="h-full antialiased">
    @inertia
</body>
</html>
```

---

## 6. Vue App Entry Point

Merge `resources/js/app.js` and `resources/js/analyse/app.js` into single entry:

```
resources/js/app.js        → Vue + Inertia + Echo setup
resources/js/pages/         → All page components
resources/js/components/    → Shared component library
resources/js/layouts/       → AppLayout.vue
resources/js/composables/   → Shared logic (usePermissions, useFlash, useInfiniteScroll, useDateRange)
```

Analyse pages move to `resources/js/pages/Analyse/`.

---

## 7. Key Translation Patterns

### 7.1 Alpine.js → Vue

| Alpine Pattern | Vue Equivalent |
|---------------|----------------|
| `x-data="{ open: false }"` | `const open = ref(false)` in `<script setup>` |
| `@click="open = !open"` | `@click="open = !open"` (same) |
| `x-show="open"` | `v-show="open"` |
| `x-cloak` | Not needed |
| `@click.outside="open = false"` | `v-click-outside` directive or composable |
| `x-transition` | `<Transition>` component |

### 7.2 Blade → Vue

| Blade Pattern | Vue Equivalent |
|--------------|----------------|
| `@extends('layouts.app')` | `<AppLayout>` component wrapping page |
| `@section('content')` | Default slot of `<AppLayout>` |
| `@yield('title', 'default')` | `<Head><title>` from `@inertiajs/vue3` |
| `@can('data_write')` | `v-if="can('data_write')"` via `usePermissions()` |
| `{{ $variable }}` | `{{ variable }}` |
| `{!! $html !!}` | `v-html="html"` |
| `@foreach` / `@forelse` / `@empty` | `v-for` + `v-if="items.length === 0"` |
| `@if(session('success'))` | `v-if="$page.props.flash.success"` |
| `@error('field')` | `v-if="form.errors.field"` (Inertia form helper) |
| `old('field', $default)` | Inertia `useForm({field: props.entity.field})` |
| `route('name', $params)` | `route()` from Ziggy |

### 7.3 Forms

Inertia's `useForm()` replaces all `<form method="POST">` + `@csrf` + `old()`:

```vue
const form = useForm({ name: props.company?.name ?? '', domain: '' })
form.post(route('companies.store'), { onSuccess: () => ... })
```

Handles CSRF, validation errors, `old()` values, loading state automatically.

### 7.4 Route Generation

Install **Ziggy** (`tightenco/ziggy`) to use Laravel named routes in Vue:

```vue
import { route } from 'ziggy-js'
// <Link :href="route('companies.show', company.id)">
```

---

## 8. Migration Phases

### Phase 0 — Foundation (do first)
1. Create `HandleInertiaRequests` middleware with shared props
2. Create unified Inertia root view (`app.blade.php`)
3. Create unified `AppLayout.vue` with section detection
4. Create `TopBar.vue` + `Sidebar.vue` + section-specific sidebar components
5. Create `FlashMessages.vue`
6. Register global components
7. Set up section detection from Inertia page URL
8. Move Analyse pages from `resources/js/analyse/pages/` to `resources/js/pages/Analyse/`
9. Verify Analyse section works identically with unified layout

### Phase 1 — Shared Component Library
1. `DataTable.vue` + `Pagination.vue`
2. `FormInput.vue` + `FormTextarea.vue` + `FormSelect.vue` + `FormCheckbox.vue` + `FormPage.vue`
3. `Modal.vue` + `ConfirmDialog.vue`
4. `Tabs.vue` + `Breadcrumb.vue`
5. `FilterPanel.vue` + `DateRangePicker.vue` + `MultiSelectDropdown.vue` + `TagInput.vue`
6. `Badge.vue` + `ChannelBadge.vue` + all display components
7. `BulkBar.vue`
8. `NotesSection.vue`
9. `TimelineInfiniteScroll.vue` + `TimelineItem.vue`
10. `IsolatedHtml.vue` + `MessageBody.vue`
11. `EntitySearch.vue` (company/person autocomplete)
12. `MergeModal.vue`

### Phase 2 — Simple Pages (validate patterns)
1. Auth pages (Login, Setup, ChangePassword)
2. Dashboard
3. Audit Log
4. AI Costs + Pricing
5. Brand Products (Index, Create, Edit)

### Phase 3 — Configuration Pages
1. Setup Assistant
2. Team Access (Index, UserForm, GroupForm)
3. MCP Server
4. Connect AI (Index, CredentialForm)
5. Smart Notes Config (Index, CreateFilter)
6. Synchronizer Servers (Index, Form)
7. Synchronizer (Index, Show, Form)
8. Synchronizer Wizard (4 steps)

### Phase 4 — Data Relations (medium complexity)
1. Data Relations Index
2. Filtering
3. Our Company
4. Mapping (most complex config page)

### Phase 5 — Core Browse Data (highest complexity)
1. Activity Index (tabs, filters, infinite scroll timeline)
2. People Index + Show + Create/Edit
3. Companies Index + Show + Create/Edit
4. Smart Notes (Index, Recognize)
5. Conversations Index + Show (most complex — message rendering, mention resolution, filter modals, quick-view)

### Phase 6 — Cleanup
1. Remove all Blade views (except single Inertia root template)
2. Remove Alpine.js dependency
3. Remove View Composer from `AppServiceProvider`
4. Remove `@stack('scripts')` and inline `<script>` blocks
5. Remove easepick global `drp` object
6. Remove `openActivityModal()` global JS
7. Clean up unused CSS classes
8. Update all tests to `$response->assertInertia(...)`

---

## 9. Special Considerations

### 9.1 WHMCS Services Widget
Move to Vue component receiving services data as props from controller.

### 9.2 Activity Charts (Person Show)
Hourly bar chart + heatmap — convert to Vue components. Keep as SVG with Vue reactivity or use lightweight chart lib.

### 9.3 Easepick Date Picker
Wrap easepick in `DateRangePicker.vue` component (quickest approach). Alternative: switch to VCalendar or vue-datepicker.

### 9.4 Shadow DOM (IsolatedHtml)
Create `<iframe>` with srcdoc or Shadow DOM wrapper via `onMounted` in `IsolatedHtml.vue`.

### 9.5 Conversation Messages
View Composer data (`$channelCfg`, `$isSlack`, `$isEmail`, `$usesMarkdown`, `$replies`, `$discordMentionMap`) comes from controller as Inertia props, passed down to `ConversationMessages.vue`.

### 9.6 Global Modals → Local Modals
`<x-activity-modal>` (once in layout, AJAX-loaded) → each page includes `<Modal>` locally, loads data via JSON fetch.

### 9.7 WebSocket (Laravel Reverb)
Echo initialized once in `app.js`. Pages needing real-time subscribe in `onMounted`.

### 9.8 Permissions Composable

```vue
// composables/usePermissions.js
import { usePage } from '@inertiajs/vue3'
export function usePermissions() {
  const page = usePage()
  return {
    can: (perm) => page.props.auth.permissions[perm] ?? false
  }
}
```

### 9.9 Test Updates
All `$response->assertViewIs(...)` / `$response->assertSee(...)` → `$response->assertInertia(fn($page) => $page->component('PageName')->has('propName'))`.

---

## 10. File Count Summary

| Category | Count |
|----------|-------|
| **Shared components to create** | ~30 |
| **Page components to create** | ~55 |
| **Blade views to delete** | ~83 |
| **Controllers to modify** | ~20 |
| **Test files to update** | ~12 |
| **New middleware** | 1 (HandleInertiaRequests) |
| **Layout/entry point files** | 3 (root blade, app.js, AppLayout.vue) |

---

## 11. Dependencies

### Add
- `tightenco/ziggy` — Laravel route generation in JS

### Already Installed
- `@inertiajs/vue3`
- `inertiajs/inertia-laravel`
- `vue` (3.x)
- `laravel-echo` + `pusher-js`

### Remove (after full migration)
- Alpine.js
