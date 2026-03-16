# Contact Monitor

**Clarity of communication in your business. All in one place.**

Connect all your communication channels into a single system. Plug in AI and enjoy a new level of understanding of how your organization communicates with clients.

---

## What is this?

Contact Monitor is a self-hosted hub that pulls together every conversation your company has with clients — support tickets, emails, Slack messages, Discord threads — and maps them to company and person profiles. Instead of switching between five tools to understand a client relationship, you open one screen.

**In plain terms:**

- You stop asking "did anyone talk to this client recently?" — the answer is always in front of you
- You see the full history of a company: who they are, what they bought, every message thread, every support ticket
- You know when your team replied and when the client did — across every channel simultaneously
- You track where clients are in your sales pipeline without manually updating a spreadsheet
- New team members can get full context on any client in seconds, not hours

**Integrations supported:**
- **WHMCS** — clients, contacts, services, tickets
- **Slack** — workspace channels and threads
- **Discord** — server channels and threads
- **Email (IMAP)** — any standard mailbox
- **Gmail** — via Google OAuth
- **MetricsCube** — client activity data

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
  - [1. Create your first user account](#1-create-your-first-user-account)
  - [2. Connect the Synchronizer](#2-connect-the-synchronizer)
  - [3. Add connections (WHMCS, Slack, Discord, Email)](#3-add-connections)
  - [4. Run your first import](#4-run-your-first-import)
  - [5. Map accounts and identities to profiles](#5-map-accounts-and-identities-to-profiles)
  - [6. Set up your organization](#6-set-up-your-organization)
  - [7. Filter out noise](#7-filter-out-noise)
  - [8. Configure brand products](#8-configure-brand-products)
- [Browsing the app](#browsing-the-app)
- [Deploying updates](#deploying-updates)
- [Running locally (development)](#running-locally-development)
- [Troubleshooting](#troubleshooting)

---

## Requirements

- A Linux server (Ubuntu 22.04 or Debian 12 recommended)
- Docker Engine + Docker Compose plugin
- 1 GB RAM minimum, 2 GB recommended
- The **Contact Monitor Synchronizer** service running (see [contact-monitor-synchronizer](../contact-monitor-synchronizer))

The main app (this repo) stores and displays data. The synchronizer is a separate service that pulls data from your external tools and pushes it here. Both need to be running.

---

## Installation

### 1. Install Docker

```bash
curl -fsSL https://get.docker.com | bash
sudo usermod -aG docker $USER
newgrp docker

# Verify
docker --version
docker compose version
```

> If `docker compose version` fails: `sudo apt-get install docker-compose-plugin`

### 2. Clone the repository

```bash
sudo mkdir -p /srv/contact-monitor
sudo chown $USER:$USER /srv/contact-monitor
git clone git@github.com:your-org/contact-monitor.git /srv/contact-monitor
cd /srv/contact-monitor
```

### 3. Configure environment

```bash
cp .env.example .env
nano .env
```

Set these values:

```env
# Leave blank — filled in next step
APP_KEY=

# Your server's IP or domain name
APP_URL=http://YOUR_SERVER_IP:8090

# Recommended: change the DB password (also change in docker-compose.yml under the db service)
DB_PASSWORD=choose_something_strong
```

Everything else can stay as-is for a standard setup. Save with `Ctrl+O`, exit with `Ctrl+X`.

### 4. Build and start

```bash
# Build the Docker image (first time: 2–3 minutes)
docker compose build --pull

# Install dependencies
docker compose run --rm app composer install --no-dev --optimize-autoloader
docker compose run --rm app npm ci
docker compose run --rm app npm run build

# Generate application key (writes APP_KEY into .env automatically)
docker compose run --rm app php artisan key:generate

# Start
docker compose up -d
```

Check both containers are up:
```bash
docker compose ps
# contact-monitor_app   Up
# contact-monitor_db    Up
```

### 5. Run database migrations

```bash
sleep 3 && docker compose exec app php artisan migrate --force
```

### 6. Open the app

```
http://YOUR_SERVER_IP:8090
```

The app is ready. The first thing you'll see is the Setup Assistant — it will guide you through connecting the synchronizer and configuring your first data source.

> **Optional:** Load sample data to explore the UI before connecting real sources:
> ```bash
> docker compose exec app php artisan migrate:fresh --seed --force
> ```
> This creates demo companies, people, and conversations. **Do not run on an instance with real data.**

---

## Configuration

Follow these steps in order. Each step unlocks the next.

---

### 1. Create your first user account

Go to **Configuration → Team Access → Users → Add User**.

Set a name, email, and password. Assign the user to the **Admin** group (which has all permissions by default).

The app uses email + password login. Passwords are case-sensitive; emails are not.

---

### 2. Connect the Synchronizer

The synchronizer is a separate service that imports data from your external tools. Contact Monitor needs to know where it is.

**Prerequisites:** The synchronizer must already be running. See [contact-monitor-synchronizer README](../contact-monitor-synchronizer/README.md) for setup instructions. It runs on port 8080.

**In Contact Monitor:**

1. Go to **Configuration → Synchronizer → Servers → Add Server**
2. Enter:
   - **Server URL:** `http://localhost:8080` (or the server's IP if running on a different machine)
   - **API Token:** the `API_TOKEN` value from the synchronizer's `.env`
3. Click **Test Connection** — you should see a green confirmation
4. Save

Contact Monitor will automatically exchange tokens with the synchronizer during the registration handshake. The synchronizer's `CONTACT_MONITOR_INGEST_SECRET` will be set automatically.

> **Docker networking note:** If both services run as Docker containers on the same host, use `http://host.docker.internal:8080` instead of `localhost`.

---

### 3. Add connections

Connections tell the synchronizer which external systems to pull data from. You manage them here, in Contact Monitor's admin panel — no need to touch the synchronizer directly.

Go to **Configuration → Synchronizer → Connections → New Connection**.

**WHMCS**

Requires the **Contact Monitor for WHMCS** addon installed on your WHMCS instance. See [contact-monitor-for-whmcs README](../contact-monitor-for-whmcs/README.md).

1. Choose type: **WHMCS**
2. Enter a name (e.g. "My WHMCS") — this becomes the system slug
3. Base URL: your WHMCS root URL (`https://billing.example.com`)
4. API Token: the Bearer token from the WHMCS addon settings
5. Select which entities to import: clients, contacts, services, tickets (default: all)
6. Click **Test Connection**, then **Save**

**Slack**

1. Create a Slack App at [api.slack.com/apps](https://api.slack.com/apps). Add bot scopes: `channels:read`, `channels:history`, `groups:read`, `groups:history`, `files:read`. Install to workspace. Copy the Bot Token (`xoxb-...`).
2. For private channels: run `/invite @your-bot-name` inside each channel.
3. In Contact Monitor: choose type **Slack**, paste the Bot Token, optionally restrict to specific channel IDs.

**Discord**

1. Create a bot at [discord.com/developers](https://discord.com/developers/applications). Enable **Message Content Intent**. Invite the bot to your server with `Read Messages` and `Read Message History` permissions.
2. Enable Developer Mode in Discord (User Settings → Advanced) to get guild/channel IDs.
3. In Contact Monitor: choose type **Discord**, paste the Bot Token, optionally restrict to specific guild/channel IDs.

**IMAP (any email)**

Choose type **IMAP**. Enter host, port, username, password. Common setups:

| Setup | Port | Encryption |
|-------|------|------------|
| IMAPS (standard) | 993 | ssl |
| STARTTLS | 143 | tls |

Optionally exclude specific mailboxes (e.g. Spam, Trash).

**Gmail**

Gmail requires OAuth and a publicly accessible HTTPS callback URL (use Cloudflare Tunnel — see [synchronizer README](../contact-monitor-synchronizer/README.md#cloudflare-tunnel-required-for-gmail-oauth)).

1. Create an OAuth 2.0 Web Client in [Google Cloud Console](https://console.cloud.google.com/). Enable the Gmail API. Add the redirect URI shown in the connection form.
2. In Contact Monitor: choose type **Gmail**, enter Client ID and Client Secret, enter the Gmail account email.
3. After saving, open Edit → click **▶ Authorize with Google** and complete the consent flow.

**Schedule**

For every connection, configure a sync schedule (bottom of the connection form). Recommended starting points:
- WHMCS: every 6 hours for partial, daily for full
- Email/IMAP: every 15–30 minutes
- Slack/Discord: every 30–60 minutes

---

### 4. Run your first import

After adding a connection, run a **Full** import to pull all historical data.

1. Go to **Configuration → Synchronizer → Connections**
2. Click **▶ Run → Full** on your connection
3. A log popup opens with real-time output — you can watch the import progress
4. The first full import can take minutes to hours depending on data volume

Once the import finishes, the synchronizer automatically pushes normalized data to Contact Monitor. You'll start seeing companies and people appear in the app.

> Subsequent scheduled runs use **Partial** mode and only fetch data newer than the last checkpoint — these are fast (seconds to a few minutes).

---

### 5. Map accounts and identities to profiles

After import, raw external records exist in the system but may not yet be linked to company/person profiles. The mapping step creates those connections.

Go to **Configuration → Data Relations → Mapping**.

The overview shows how many accounts and identities are unlinked per source system.

**Mapping accounts → companies (WHMCS clients)**

1. Click on your WHMCS system slug
2. Each WHMCS client row shows the client data. If a matching company exists, it will be suggested. If not, you can create one.
3. Click **Link** to associate the account with a company, or **Create company** to create a new one on the spot
4. Sub-contacts appear inline under each client row — link them to people profiles the same way

**Mapping identities → people (Slack, Discord, Email)**

1. Click on your Slack/Discord/IMAP slug
2. Each identity (email address, Slack user, Discord user) is listed
3. Link to an existing person or create a new one
4. Use **Auto-resolve** to let the system automatically link identities that share email addresses with already-linked profiles

**Auto-resolve**

The Auto-resolve button runs transitive matching across all sources — if a Slack user shares an email with a WHMCS contact, they get linked to the same person automatically. Run this after each major import.

---

### 6. Set up your organization

Tell Contact Monitor who is on your team so internal communications are correctly classified.

Go to **Configuration → Data Relations → Our Organization**.

**Team Domains**

Add your company's email domains (e.g. `example.com`). Any identity with a matching email domain will be automatically marked as a team member. This classifies their messages as internal/outbound in conversation views.

**Team Members**

Review the list of people flagged as your team. You can also go to **People → Our Organization** and use the bulk "Mark as Our Org" action to flag specific individuals.

---

### 7. Filter out noise

Not all imported data is relevant. The filtering system lets you exclude specific contacts, companies, email domains, addresses, and conversation subjects from the main views.

Go to **Configuration → Data Relations → Filtering**.

| Tab | What it does |
|-----|-------------|
| **Domains** | Exclude entire email domains (e.g. `gmail.com`, `hotmail.com` for generic addresses) |
| **Emails** | Exclude specific email addresses (e.g. `noreply@example.com`) |
| **Subjects** | Exclude conversations matching specific subject lines |
| **Contacts** | Exclude specific people profiles from appearing in activity feeds |

Filtered conversations move to the **Filtered** tab in the Conversations section. They don't disappear — they're just separated from your main working view.

You can also filter directly from the Conversations list: select conversations with the checkbox and click **Filter…** to create a rule from the current selection.

---

### 8. Configure brand products

Brand products let you track where each client company is in your sales pipeline, with a stage and an evaluation score.

Go to **Configuration → General Settings → Brand Products** (or wherever it appears in your setup).

Add each product your company sells. For each company, you can then set:
- **Stage**: Lead → Prospect → Trial → Active → Churned
- **Score**: 0–100 evaluation
- **Notes** and last evaluation date

These appear on every company profile and in the companies index for quick overview.

---

## Browsing the app

### Dashboard

The landing page. Shows:
- **Stats** for a configurable date range: conversations, new companies, new people, active people
- **Most active contacts** — top 8 clients by activity volume in the period
- **Most active team members** — top 8 team identities
- **Recent notes** — the 10 most recent notes across all entities

Use the date range picker (top right) to adjust the period.

### Companies

Your client list. Sortable by name, domain, number of contacts, channel types present, brand scores, and last update.

Click any company to open its profile:
- **Domains & Aliases** — all known domains and display names for this company
- **Linked People** — all contacts with their role, start/end dates
- **Brand Statuses** — pipeline stage + score per product
- **Accounts** — external system IDs (WHMCS client ID, MetricsCube ID, etc.)
- **Recent Conversations** — last 10 threads across all channels
- **Notes** — internal notes visible only to your team
- **Activity Timeline** — complete chronological feed of everything that happened with this company

Click any conversation subject to get a quick preview popup without leaving the page.

### People

Your contacts list. Split into **Clients** and **Our Organization** tabs.

Click any person to open their profile:
- **Identities** — all known contact points (emails, Slack user, Discord user)
- **Companies** — which companies they're linked to
- **Activity charts** — hourly activity bar chart and day-of-week heatmap (useful for spotting when a client is most responsive)
- **Recent Conversations**, **Notes**, **Activity Timeline**

### Conversations

All imported conversation threads in one place. Split into **Assigned** (linked to companies), **Unassigned** (not yet mapped), and **Filtered** (excluded from main view) tabs.

Filter by date range and channel type. Click any subject to preview the conversation. Click **Show full discussion** to open the full message view with all messages, thread replies, and participant list.

Message display adapts to the channel:
- **Slack / Discord**: chat-style layout with avatar, username, timestamps, and thread replies
- **Email / Tickets**: email-bubble layout with From/To/CC headers and status badges

### Activity

The global activity feed — everything that happened across all companies and people, in chronological order. Infinite scroll with cursor pagination.

Filter by: date range, channel type, activity type.

Search by company name, person name, or description text.

Click the activity source to open the related conversation preview.

---

## Deploying updates

Run from your local machine:

```bash
./ops/deploy-production.sh
```

The script: checks for uncommitted changes → pushes to origin → SSHs to server → pulls latest code → rebuilds Docker image → reinstalls dependencies → rebuilds frontend assets → restarts containers → runs new migrations → clears caches.

**Prerequisites:**
- SSH alias `production` configured in `~/.ssh/config` pointing to your server
- App deployed at `/srv/contact-monitor` on the server

**If deploy fails mid-way:**

```bash
# .env missing on server
ssh production "cp /srv/contact-monitor/.env.example /srv/contact-monitor/.env"
# then edit it: ssh production "nano /srv/contact-monitor/.env"

# Port 8090 already in use
ssh production "ss -tlnp | grep 8090"

# Migrations didn't run
ssh production "cd /srv/contact-monitor && docker compose exec app php artisan migrate --force"

# Containers not starting
ssh production "cd /srv/contact-monitor && docker compose logs app"
```

---

## Running locally (development)

No Docker needed for local dev.

**Prerequisites:** PHP 8.3 with `pdo_pgsql`, Composer, Node.js 20+, PostgreSQL.

```bash
composer install
npm install
cp .env.example .env
```

Edit `.env` for local:
```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_HOST=127.0.0.1
DB_DATABASE=contact-monitor
DB_USERNAME=contact-monitor
DB_PASSWORD=contact-monitor
```

```bash
# Create DB
sudo -u postgres psql -c "CREATE USER \"contact-monitor\" WITH PASSWORD 'contact-monitor';"
sudo -u postgres psql -c "CREATE DATABASE \"contact-monitor\" OWNER \"contact-monitor\";"

# Setup
php artisan key:generate
php artisan migrate
php artisan db:seed    # load sample data

# Run (two terminals)
npm run dev            # Vite hot reload
php artisan serve      # Laravel on http://localhost:8000
```

Reset and re-seed: `php artisan migrate:fresh --seed`

---

## Troubleshooting

### Containers

**Check what's running**
```bash
docker compose ps
# Both contact-monitor_app and contact-monitor_db should show "Up"
```

**Start everything**
```bash
docker compose up -d
```

**Stop everything**
```bash
docker compose down
```

**Restart the app only (after code/config change)**
```bash
docker compose restart app
```

**Rebuild image from scratch (after Dockerfile change or dep update)**
```bash
docker compose down
docker compose build --no-cache --pull
docker compose up -d
```

---

### App won't start / crashes on startup

**Check logs**
```bash
docker compose logs app
docker compose logs app --tail=50    # last 50 lines
docker compose logs -f app           # live follow
```

**Common causes:**

`APP_KEY` missing or empty:
```bash
docker compose run --rm app php artisan key:generate
docker compose restart app
```

`.env` not found or misconfigured — verify it exists and has the right values:
```bash
cat .env | grep -E "APP_KEY|APP_URL|DB_"
```

DB not ready yet (app starts before Postgres is accepting connections):
```bash
docker compose restart app
# or wait 5s after `docker compose up -d` before checking
```

Port 8090 already in use by another process:
```bash
ss -tlnp | grep 8090
# kill the conflicting process or change the port in docker-compose.yml
```

---

### Database issues

**Check DB is running**
```bash
docker compose ps db
docker compose logs db
```

**Connect to the database directly**
```bash
docker exec -it contact-monitor_db psql -U contact-monitor -d contact-monitor
# inside psql:
\dt          # list tables
\q           # quit
```

Or from the host (DB exposed on port 5434):
```bash
psql -h localhost -p 5434 -U contact-monitor -d contact-monitor
```

**Run pending migrations**
```bash
docker exec contact-monitor_app php artisan migrate --force
```

**Check migration status**
```bash
docker exec contact-monitor_app php artisan migrate:status
```

**DB data is gone after restart**

Data is stored in a named Docker volume (`contact-monitor_pgdata`). It persists across `docker compose down/up`. It is destroyed only by:
```bash
docker compose down -v    # ← this deletes the volume — never run this in production
```

Check the volume exists:
```bash
docker volume ls | grep contact-monitor
```

---

### App returns 500 / blank page

Clear caches (usually fixes it after a bad deploy or `.env` change):
```bash
docker exec contact-monitor_app php artisan view:clear
docker exec contact-monitor_app php artisan config:clear
docker exec contact-monitor_app php artisan route:clear
docker exec contact-monitor_app php artisan cache:clear
```

Check for PHP errors in logs:
```bash
docker compose logs app | grep -i error
docker compose logs app | grep -i exception
```

Run any missing migrations:
```bash
docker exec contact-monitor_app php artisan migrate --force
```

---

### After code changes (no full redeploy)

Just clear views and restart the app:
```bash
docker exec contact-monitor_app php artisan view:clear
docker compose restart app
```

If you changed CSS/JS:
```bash
docker compose run --rm app npm run build
docker compose restart app
```

---

### Full reset (nuclear option — destroys all data)

Only use if you want to start completely fresh:
```bash
docker compose down -v          # stop containers AND delete DB volume
docker compose up -d
sleep 3
docker exec contact-monitor_app php artisan migrate --force
```

---

### Useful one-liners

```bash
# View last 100 app log lines
docker compose logs app --tail=100

# Open Laravel tinker (interactive PHP shell)
docker exec -it contact-monitor_app php artisan tinker

# Check what PHP extensions are loaded
docker exec contact-monitor_app php -m

# See environment variables the app sees
docker exec contact-monitor_app php artisan env

# Force-clear all compiled caches at once
docker exec contact-monitor_app php artisan optimize:clear

# Check DB connection from inside the app container
docker exec contact-monitor_app php artisan tinker --execute="DB::select('SELECT 1');"
```
