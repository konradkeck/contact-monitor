# Contact Monitor

All your client communication — support tickets, emails, Slack, Discord — in one place, mapped to company and person profiles.

---

## Requirements

- A Linux server (Ubuntu 22.04 or Debian 12 recommended)
- At least **1 GB RAM** (2 GB recommended)
- **Docker** installed (see step 1 below)

---

## Installation

### Step 1 — Install Docker

If Docker isn't installed yet, run this on your server:

```bash
curl -fsSL https://get.docker.com | bash
sudo usermod -aG docker $USER
newgrp docker
```

Verify it works:
```bash
docker --version
docker compose version
```

---

### Step 2 — Download Contact Monitor

```bash
git clone https://github.com/konradkeck/contact-monitor.git
cd contact-monitor
```

---

### Step 3 — Configure

```bash
cp .env.example .env
nano .env
```

Set these three values (everything else can stay as-is):

```env
APP_URL=http://YOUR_SERVER_IP:8090   # your server's IP or domain

DB_PASSWORD=pick_something_strong    # also update this same password
                                     # in docker-compose.yml under the db service
```

Save with `Ctrl+O`, exit with `Ctrl+X`.

---

### Step 4 — Start

```bash
docker compose build --pull
docker compose run --rm app composer install --no-dev --optimize-autoloader
docker compose run --rm app npm ci && docker compose run --rm app npm run build
docker compose run --rm app php artisan key:generate
docker compose up -d
sleep 3
docker compose exec app php artisan migrate --force
```

This takes 2–5 minutes on first run.

The containers are configured with `restart: unless-stopped` — they will automatically start after a server reboot.

---

### Step 5 — Open the app

Go to `http://YOUR_SERVER_IP:8090` in your browser.

The first screen asks you to create an admin account. Fill in your email and password — that's your login from now on.

After that, the **Setup Assistant** will walk you through connecting the Synchronizer and importing your first data.

---

## Updating

Run this on your server from the `contact-monitor` directory:

```bash
git pull
docker compose build --pull
docker compose run --rm app composer install --no-dev --optimize-autoloader
docker compose run --rm app npm ci && docker compose run --rm app npm run build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize:clear
docker compose restart app
```

That's it. Your data is not affected.

---

## SSL (HTTPS)

Running on plain HTTP is fine on a private network, but if the app is publicly accessible you should enable HTTPS.

The simplest way is **Caddy** — it handles SSL certificates automatically (via Let's Encrypt, free).

### Option A — Caddy (recommended, automatic SSL)

**1. Install Caddy:**
```bash
sudo apt install -y debian-keyring debian-archive-keyring apt-transport-https curl
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list
sudo apt update && sudo apt install caddy
```

**2. Create `/etc/caddy/Caddyfile`:**
```
your-domain.com {
    reverse_proxy localhost:8090
}
```

**3. Start Caddy:**
```bash
sudo systemctl enable --now caddy
```

Caddy automatically gets an SSL certificate for your domain. Done — the app is now available at `https://your-domain.com`.

**4. Update `.env`:**
```env
APP_URL=https://your-domain.com
```

Then restart: `docker compose restart app`

---

### Option B — nginx

```bash
sudo apt install nginx certbot python3-certbot-nginx
```

Create `/etc/nginx/sites-available/contact-monitor`:
```nginx
server {
    server_name your-domain.com;

    location / {
        proxy_pass http://localhost:8090;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/contact-monitor /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d your-domain.com
```

Update `.env`:
```env
APP_URL=https://your-domain.com
```

Then restart: `docker compose restart app`

---

## Troubleshooting

**App won't open / shows an error**
```bash
docker compose logs app --tail=50
```

**Something looks broken after an update**
```bash
docker compose exec app php artisan optimize:clear
docker compose restart app
```

**Database issues**
```bash
docker compose exec app php artisan migrate --force
```

**Containers not starting after server reboot**

The `docker-compose.yml` includes `restart: unless-stopped` so containers start automatically. If they still don't come up, make sure Docker itself starts on boot:
```bash
sudo systemctl enable docker
```

**Restart everything**
```bash
docker compose restart
```

**Full logs**
```bash
docker compose logs -f app
```
