# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

---

## Project Overview

Contact Monitor is a multi-channel contact hub that centralizes a company's communications with clients across email, Gmail, Slack, Discord, tickets, and other integrations. It links conversations and activities to company and person profiles, tracks brand product pipeline stages, and provides a unified activity timeline across all channels.

**Stack:** Laravel 12 · PHP 8.3 · PostgreSQL 16 · Tailwind CSS v4 · Vite

**No queue worker, no scheduler** — all operations are synchronous. No Redis; sessions and cache use database tables.

**No authentication** — the app is open by default (internal tool).

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

## Critical Rules & Known Bugs Fixed

### Activity Widget (`/activity`)

- **Tabs**: All / Conversations / Activity (Activity tab uses `exclude_type=conversation`)
- **Never** pass `showPersonLink` or `showCompanyLink` to `activity/partials/timeline-items.blade.php` — they must not appear in the global activity list.
- AJAX pagination via `/activity/timeline?cursor=...` mirrors the initial SSR render.

### Conversations (`/conversations`)

- `$filteredQuery` in `ConversationController::index()` **must** include `filter_subjects` (from `SystemSetting`) — same logic as the main listing. Tab counts (All/Unread/Archived) are derived from `$filteredQuery`, not a plain query.

### Data Relations Mapping (`/data-relations/mapping/{type}/{slug}`)

- **WHMCS / MetricsCube** (account-based systems): contacts shown **inline** under account rows. There is **no** separate People/Contacts tab and **no** orphan contacts section.
- `$identitiesByExtId` must be built over **all** accounts (not the paginated subset) for inline matching to work correctly.
- Identity-to-account matching order: (1) `meta_json['account_external_id']`, (2) primary email fallback via `account.meta_json['email']`.

### PersonController

- `PersonController::personActivitiesQuery()` must **not** use `.distinct()` — it breaks PostgreSQL `ORDER BY` with `SQLSTATE[42P10]`.

---

## Frontend

Tailwind CSS v4 compiled by Vite. Compiled assets land in `public/build/`. The `@vite` directive in `resources/views/layouts/app.blade.php` serves them. In production only the compiled manifest is used — no HMR server.

`flatpickr` is the only runtime JS dependency (date pickers).

---

## Tests

- PHPUnit with two suites: `Unit` (`tests/Unit/`) and `Feature` (`tests/Feature/`)
- Test database: SQLite in-memory (`:memory:`) — configured in `phpunit.xml`
