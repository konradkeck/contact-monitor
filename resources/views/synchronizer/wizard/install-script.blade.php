#!/usr/bin/env bash
set -euo pipefail

export CM_INGEST_URL="{{ $cmUrl }}"
export CM_INGEST_SECRET="{{ $secret }}"
export CM_API_TOKEN="{{ $pending->api_token }}"
export CM_REG_TOKEN="{{ $pending->token }}"
export CM_REG_URL="{{ $regUrl }}"

INSTALL_DIR="${INSTALL_DIR:-./contact-monitor-synchronizer}"
REPO_URL="git@github.com:konradkeck/contact-monitor-synchronizer.git"

echo "==> Installing Contact Monitor Synchronizer to $INSTALL_DIR"

if [ -d "$INSTALL_DIR/.git" ]; then
  echo "==> Repository already exists, pulling latest..."
  git -C "$INSTALL_DIR" pull --ff-only
else
  echo "==> Cloning repository..."
  git clone "$REPO_URL" "$INSTALL_DIR"
fi

cd "$INSTALL_DIR"

APP_KEY="base64:$(openssl rand -base64 32 2>/dev/null || php -r "echo base64_encode(random_bytes(32));")"

echo "==> Writing .env..."
cat > .env <<ENVEOF
APP_NAME=contact-monitor-synchronizer
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=http://localhost:8080

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=sqlite

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

CONTACT_MONITOR_INGEST_URL=${CM_INGEST_URL}
CONTACT_MONITOR_INGEST_SECRET=${CM_INGEST_SECRET}

API_TOKEN=${CM_API_TOKEN}

CM_REGISTRATION_URL=${CM_REG_URL}
CM_REGISTRATION_TOKEN=${CM_REG_TOKEN}
ENVEOF

echo "==> Patching docker-compose.yml (host access)..."
# Add extra_hosts to app and worker services so they can reach the host machine
python3 - <<'PYEOF'
import re, sys
with open('docker-compose.yml', 'r') as f:
    content = f.read()
extra = '    extra_hosts:\n      - "host.docker.internal:host-gateway"\n'
# Insert after each service's `depends_on` or `restart` block if extra_hosts not already present
if 'host.docker.internal' not in content:
    content = re.sub(
        r'(    depends_on:\n      - db\n)',
        r'\1' + extra,
        content
    )
    with open('docker-compose.yml', 'w') as f:
        f.write(content)
    print("  extra_hosts added.")
else:
    print("  extra_hosts already present.")
PYEOF

echo "==> Starting containers..."
docker compose up -d --build

echo "==> Waiting for app to be ready..."
for i in $(seq 1 30); do
  STATUS=$(docker compose ps -q app | xargs docker inspect --format='{{.State.Status}}' 2>/dev/null || echo "")
  if [ "$STATUS" = "running" ]; then
    sleep 2  # give PHP server a moment to boot
    break
  fi
  sleep 2
done

echo "==> Registering with Contact Monitor..."
docker compose exec -T app php artisan synchronizer:register

echo ""
echo "✓ Done."
