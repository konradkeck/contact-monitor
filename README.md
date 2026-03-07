# contact-monitor — CRM

Internal CRM for managing companies, contacts, conversations and sales pipeline. Tracks brand product stages, conversation history across Slack/email/tickets, activities timeline, and campaign management.

---

## Table of Contents

- [Quick overview](#quick-overview)
- [Step-by-step: fresh server setup](#step-by-step-fresh-server-setup)
  - [1. Install Docker](#1-install-docker)
  - [2. Clone the repository](#2-clone-the-repository)
  - [3. Configure environment](#3-configure-environment)
  - [4. Start the stack](#4-start-the-stack)
  - [5. Run migrations](#5-run-migrations)
  - [6. (Optional) Load sample data](#6-optional-load-sample-data)
  - [7. Open the app](#7-open-the-app)
- [Deploying updates](#deploying-updates)
- [Architecture overview](#architecture-overview)
- [Data model](#data-model)
- [Running locally (development)](#running-locally-development)

---

## Quick overview

**Stack:** Laravel 12 · PHP 8.3 · PostgreSQL 16 · Tailwind CSS v4 · Vite

**What it does:**
- **Companies** — company profiles with domains, aliases, external account IDs (WHMCS, MetricsCube), brand product stages/scores, contacts, conversation history, activity timeline, notes
- **People** — contact profiles with Gravatar avatars, multi-type identities (email, Slack, Discord, phone, LinkedIn, Twitter), company links, activity timeline
- **Conversations** — per-channel message viewer: Slack/Discord (chat layout with threads), Email (bubble layout), Ticket (with status/priority badges)
- **Activities** — filterable activity feed across all companies and people, sourced from meta_json
- **Brand Products** — configure products to track (stage: lead/prospect/trial/active/churned, evaluation score 1–10)
- **Campaigns** — AI prompt-based campaigns with run history

**Runs on port 8090** (so it can coexist with Mielonka on 8080).

---

## Step-by-step: fresh server setup

These instructions assume a clean Linux server (Ubuntu 22.04 / Debian 12). Copy-paste each block exactly.

### 1. Install Docker

```bash
# Install Docker Engine
curl -fsSL https://get.docker.com | bash

# Add your user to the docker group (so you don't need sudo)
sudo usermod -aG docker $USER

# Apply group change without logging out
newgrp docker

# Verify
docker --version
docker compose version
```

> If `docker compose version` fails, your Docker is too old. Run `sudo apt-get update && sudo apt-get install docker-compose-plugin` to install the plugin.

### 2. Clone the repository

```bash
# Create the directory where the app will live
sudo mkdir -p /srv/contact-monitor
sudo chown $USER:$USER /srv/contact-monitor

# Clone
git clone git@github.com:your-org/contact-monitor.git /srv/contact-monitor

# Go into the directory — all following commands run from here
cd /srv/contact-monitor
```

### 3. Configure environment

```bash
# Copy the example config
cp .env.example .env
```

Now open the file and edit it:

```bash
nano .env
```

**Required changes — the file already has sensible defaults, you only need to set these two:**

```env
# Generate the APP_KEY in the next step — leave blank for now
APP_KEY=

# If you have a domain name or know the server's IP, set it here.
# Otherwise leave it as localhost — you can change it later.
APP_URL=http://YOUR_SERVER_IP:8090
```

**Database password** — optional but recommended in production. If you change it here, also change it in `docker-compose.yml` under the `db` service:

```env
DB_PASSWORD=change_me_to_something_strong
```

Save with `Ctrl+O`, then `Ctrl+X`.

### 4. Start the stack

```bash
# Build the Docker image (first time takes 2–3 minutes)
docker compose build --pull

# Install PHP dependencies
docker compose run --rm app composer install --no-dev --optimize-autoloader

# Install JS dependencies and build frontend assets
docker compose run --rm app npm ci
docker compose run --rm app npm run build

# Generate the application key (writes APP_KEY= into .env automatically)
docker compose run --rm app php artisan key:generate

# Start the app and database
docker compose up -d
```

Check that both containers are running:

```bash
docker compose ps
```

You should see `contact-monitor_app` and `contact-monitor_db` both with status `Up`.

### 5. Run migrations

```bash
# Wait 3 seconds for PostgreSQL to fully start, then migrate
sleep 3 && docker compose exec app php artisan migrate --force
```

### 6. (Optional) Load sample data

This seeds 4 companies, 5 people, sample conversations (email + Slack + ticket with realistic messages), and activities. Useful for testing — **do not run on an instance that already has real data**.

```bash
docker compose exec app php artisan migrate:fresh --seed --force
```

### 7. Open the app

Navigate to:

```
http://YOUR_SERVER_IP:8090
```

That's it — no login required, the app is open by default (internal tool).

---

## Deploying updates

Updates are deployed from your local machine with a single command. It pushes your latest commits, rebuilds on the server, and runs any new migrations.

**Prerequisites:**
- You can SSH into the server with `ssh production` (host configured in `~/.ssh/config`)
- The app is already set up on the server at `/srv/contact-monitor`

**Run from your local machine:**

```bash
./ops/deploy-production.sh
```

The script does the following steps automatically:
1. Checks for uncommitted local changes
2. Pushes your branch to origin
3. SSHs into the server, pulls the latest code
4. Rebuilds the Docker image
5. Reinstalls PHP and JS dependencies
6. Rebuilds frontend assets
7. Restarts containers
8. Runs any new migrations
9. Clears and rebuilds config/route/view caches

**If deploy fails mid-way** — the most common causes:
- `.env` not present on server → copy it: `ssh production "cp /srv/contact-monitor/.env.example /srv/contact-monitor/.env"` then edit it
- Port 8090 already in use → check `ssh production "ss -tlnp | grep 8090"`
- DB not ready → run `ssh production "cd /srv/contact-monitor && sleep 5 && docker compose exec app php artisan migrate --force"`

---

## Architecture overview

```
Browser
  └─ :8090 → contact-monitor_app (php artisan serve inside Docker)
               └─ PostgreSQL (contact-monitor_db container, :5434 on host)
```

**No queue worker, no scheduler.** All operations are synchronous — there are no background jobs. If you add features that require queued jobs in the future, add a `worker` service to `docker-compose.yml` (see Mielonka for reference).

**Frontend:** Tailwind CSS v4 compiled by Vite at deploy time. The compiled assets land in `public/build/`. The `@vite` directive in the layout serves them. In production the hot-module-reload server does not run — only the compiled manifest is used.

**Sessions and cache** are stored in the `sessions` and `cache` database tables (no Redis needed).

---

## Data model

18 tables. Key relationships:

```
companies
  ├─ company_domains        (domain names, one marked is_primary)
  ├─ company_aliases        (display name aliases, one marked is_primary)
  ├─ accounts               (external IDs: WHMCS, MetricsCube, etc.)
  ├─ company_brand_statuses (stage + score per brand product)
  ├─ company_person         (pivot: which people work here)
  ├─ conversations          (one per thread/email/ticket)
  │    ├─ conversation_participants
  │    └─ conversation_messages  (direction: customer/internal/system, thread_key for Slack threads)
  └─ activities             (timeline events with meta_json payload)

people
  ├─ identities   (email, slack_id, discord_id, phone, linkedin, twitter)
  └─ activities   (same table, person_id column)

notes            (standalone)
  └─ note_links  (polymorphic: company / person / conversation)

brand_products   (PanelAlpha Cloud, EasyDCIM, etc.)
campaigns
  └─ campaign_runs

audit_logs       (immutable action log)
```

**Conversation messages** have a `direction` enum: `customer` (inbound), `internal` (outbound/team), `system` (status change, bot message). Thread replies link to their parent via `thread_key = parent_message_id`.

**Activities** carry a `meta_json` column for arbitrary data. The `Activity` model has `timelineLabel()`, `timelineColor()`, and `dotColor()` methods that map type strings to display settings — extend those when adding new activity types.

**Notes** are standalone records linked to any entity via `note_links` (polymorphic). The `x-notes-section` component renders notes + add form for any entity.

---

## Running locally (development)

No Docker needed for local dev — the app talks directly to your local PostgreSQL.

**Prerequisites:**
- PHP 8.3 with `pdo_pgsql` extension (`php -m | grep pdo_pgsql`)
- Composer
- Node.js 20+
- PostgreSQL running locally

```bash
cd /path/to/contact-monitor

# Install dependencies
composer install
npm install

# Configure environment
cp .env.example .env
```

Edit `.env` for local use:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=contact-monitor
DB_USERNAME=contact-monitor       # or your local postgres user
DB_PASSWORD=contact-monitor
```

Create the database and user if they don't exist:

```bash
sudo -u postgres psql -c "CREATE USER contact-monitor WITH PASSWORD 'contact-monitor';"
sudo -u postgres psql -c "CREATE DATABASE contact-monitor OWNER contact-monitor;"
```

Run setup:

```bash
php artisan key:generate
php artisan migrate
php artisan db:seed          # load sample data

# In one terminal — run Vite dev server (hot reload)
npm run dev

# In another terminal — run Laravel
php artisan serve
```

Open `http://localhost:8000`.

**To reset and re-seed:**

```bash
php artisan migrate:fresh --seed
```

**Checking the database directly:**

```bash
# Connect to local PostgreSQL
psql -U contact-monitor -d contact-monitor

# Useful queries
\dt                                  -- list all tables
SELECT * FROM companies LIMIT 5;
SELECT * FROM conversation_messages ORDER BY occurred_at;
```
