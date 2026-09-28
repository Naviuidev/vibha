#!/usr/bin/env bash
set -euo pipefail

# One-command deploy: build locally → rsync to MilesWeb (vibhaajewellery.in)
# Usage:
#   ./scripts/deploy.sh              # deploy all
#   ./scripts/deploy.sh website      # website only
#   ./scripts/deploy.sh admin        # admin only
#   ./scripts/deploy.sh api          # api only
#   ./scripts/deploy.sh website admin

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -f "$ROOT/deploy.env" ]]; then
  echo "Missing deploy.env"
  echo "Run:  cp deploy.env.example deploy.env"
  echo "Then edit deploy.env with your MilesWeb SSH user/host."
  exit 1
fi

# shellcheck disable=SC1091
source "$ROOT/deploy.env"

: "${DEPLOY_HOST:?Set DEPLOY_HOST in deploy.env}"
: "${DEPLOY_USER:?Set DEPLOY_USER in deploy.env}"
: "${DEPLOY_PORT:=22}"
: "${REMOTE_WEBSITE:?Set REMOTE_WEBSITE in deploy.env}"
: "${REMOTE_ADMIN:?Set REMOTE_ADMIN in deploy.env}"
: "${REMOTE_API:?Set REMOTE_API in deploy.env}"

# Guard: unquoted ~/... in deploy.env expands to your Mac home and breaks rsync
if [[ "$REMOTE_WEBSITE" == /Users/* || "$REMOTE_ADMIN" == /Users/* || "$REMOTE_API" == /Users/* ]]; then
  echo "ERROR: Remote paths look like Mac paths (got $REMOTE_WEBSITE)."
  echo "In deploy.env use single quotes: REMOTE_WEBSITE='~/vibhaajewellery.in'"
  echo "Or absolute server paths: REMOTE_WEBSITE=/home/$DEPLOY_USER/vibhaajewellery.in"
  exit 1
fi

CONTROL_PATH="/tmp/vibhaa-ssh-%C"
SSH_OPTS=(
  -p "$DEPLOY_PORT"
  -o StrictHostKeyChecking=accept-new
  -o ServerAliveInterval=30
  -o ServerAliveCountMax=4
  -o ControlMaster=auto
  -o ControlPath="$CONTROL_PATH"
  -o ControlPersist=10m
)

SSH_PREFIX=()
if [[ -n "${DEPLOY_PASS:-}" ]]; then
  if ! command -v sshpass >/dev/null 2>&1; then
    echo "DEPLOY_PASS is set but sshpass is missing."
    echo "Install: brew install hudochenkov/sshpass/sshpass"
    exit 1
  fi
  export SSHPASS="$DEPLOY_PASS"
  SSH_PREFIX=(sshpass -e)
  SSH_OPTS+=(
    -o PreferredAuthentications=password
    -o PubkeyAuthentication=no
  )
fi

SSH=("${SSH_PREFIX[@]}" ssh "${SSH_OPTS[@]}")
RSYNC_RSH="${SSH_PREFIX[*]} ssh ${SSH_OPTS[*]}"
RSYNC=(rsync -avz --delete --exclude '.env' --exclude '.DS_Store' -e "$RSYNC_RSH")
TARGET="$DEPLOY_USER@$DEPLOY_HOST"

cleanup_ssh() {
  "${SSH_PREFIX[@]}" ssh -O exit -o ControlPath="$CONTROL_PATH" -p "$DEPLOY_PORT" "$TARGET" 2>/dev/null || true
}
trap cleanup_ssh EXIT

echo "==> Opening SSH session to $TARGET..."
"${SSH[@]}" -o ControlMaster=yes -fN "$TARGET" || {
  echo "SSH login failed. Check DEPLOY_PASS / SSH Access in MilesWeb."
  exit 1
}
echo "✓ SSH connected (reused for all uploads)"

"${SSH[@]}" "$TARGET" "mkdir -p $REMOTE_ADMIN $REMOTE_API/uploads"

TARGETS=("$@")
if [[ ${#TARGETS[@]} -eq 0 ]]; then
  TARGETS=(website admin api)
fi

deploy_website() {
  echo ""
  echo "==> Building website..."
  (cd "$ROOT/frontend" && npm run build)

  echo "==> Uploading website → $REMOTE_WEBSITE"
  "${RSYNC[@]}" \
    --exclude 'uploads' \
    --exclude 'admin' \
    --exclude 'backend' \
    --exclude 'error_pages' \
    --exclude 'api' \
    "$ROOT/frontend/dist/" \
    "$TARGET:$REMOTE_WEBSITE/"
  echo "✓ Website deployed → https://vibhaajewellery.in"
}

deploy_admin() {
  echo ""
  echo "==> Building admin (base /admin/)..."
  (cd "$ROOT/admin" && VITE_BASE=/admin/ npm run build)

  echo "==> Uploading admin → $REMOTE_ADMIN"
  "${RSYNC[@]}" \
    "$ROOT/admin/dist/" \
    "$TARGET:$REMOTE_ADMIN/"
  echo "✓ Admin deployed → https://vibhaajewellery.in/admin/"
}

deploy_api() {
  echo ""
  echo "==> Uploading API → $REMOTE_API"
  "${RSYNC[@]}" \
    --exclude '.env' \
    --exclude 'uploads/***' \
    --exclude 'vendor/***' \
    --exclude '.git' \
    --exclude 'database/*.sql' \
    --exclude 'diag_boot.php' \
    --exclude 'storage/rate_limit/*.json' \
    "$ROOT/backend/" \
    "$TARGET:$REMOTE_API/"

  if [[ -d "$ROOT/backend/vendor" ]]; then
    rsync -avz -e "$RSYNC_RSH" \
      "$ROOT/backend/vendor/" \
      "$TARGET:$REMOTE_API/vendor/"
  else
    echo "==> Copying PHP vendor from existing API on the server..."
    "${SSH[@]}" "$TARGET" "if [ -d ~/api.yulowear.in/vendor ]; then mkdir -p $REMOTE_API/vendor && cp -a ~/api.yulowear.in/vendor/. $REMOTE_API/vendor/; fi"
  fi

  echo "✓ API deployed → https://vibhaajewellery.in/backend/api/health"
  echo "  (production .env on server was kept — not overwritten)"
}

for t in "${TARGETS[@]}"; do
  case "$t" in
    website|web|frontend) deploy_website ;;
    admin) deploy_admin ;;
    api|backend) deploy_api ;;
    *)
      echo "Unknown target: $t"
      echo "Use: website | admin | api"
      exit 1
      ;;
  esac
done

echo ""
echo "All done."
echo "Test:"
echo "  https://vibhaajewellery.in"
echo "  https://vibhaajewellery.in/admin/"
echo "  https://vibhaajewellery.in/backend/api/health"
