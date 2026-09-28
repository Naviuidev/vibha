# Vibhaa Jewellery production deploy — vibhaajewellery.in

## Live URLs

- Website: https://vibhaajewellery.in
- Admin: https://vibhaajewellery.in/admin/
- API: https://vibhaajewellery.in/backend/api/health

This domain is an **addon domain** on the existing MilesWeb account.

| Folder | Site |
|---|---|
| `public_html` | **yulowear.in** — leave this alone |
| `vibhaajewellery.in` | **Vibhaa storefront + admin + API** |

Do not replace `public_html`. Deploy always targets `~/vibhaajewellery.in`.

---

## Deploy from your Mac

```bash
cd /Users/naveenreddy/Desktop/NaveenHosur/projects/vibhava
cp deploy.env.example deploy.env   # first time only
# deploy.env is already set for MilesWeb

chmod +x scripts/deploy.sh
./scripts/deploy.sh              # website + admin + api
./scripts/deploy.sh website      # only website
./scripts/deploy.sh admin        # only admin
./scripts/deploy.sh api          # only api
```

Builds locally, then uploads with `rsync` over SSH.  
It never overwrites the production API `.env` or `uploads/`.

---

## First-time server setup (once)

### 1. MySQL database

Created in MilesWeb:

- Database: `yulowear1_vibhaajewellery`
- User: `yulowear1_vibhaajewellery`

In phpMyAdmin:

1. Click **yulowear1_vibhaajewellery** on the left (not `yulowear1_123`)
2. Import → choose `backend/database/vibhaajewellery_import.sql`
3. Go

Do **not** import `seed.sql` (YULO demo catalog).

Then set `DB_NAME`, `DB_USER`, and `DB_PASS` in `~/vibhaajewellery.in/backend/.env`.

### 2. API `.env`

If `.env` is missing on the server, copy `backend/.env.production.example` to `~/vibhaajewellery.in/backend/.env` and fill secrets.

Set:

```env
APP_NAME="Vibhaa Jewellery"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://vibhaajewellery.in/backend
FRONTEND_URL=https://vibhaajewellery.in
ADMIN_URL=https://vibhaajewellery.in/admin
CORS_ALLOWED_ORIGINS=https://vibhaajewellery.in,https://www.vibhaajewellery.in
```

```bash
chmod -R 775 ~/vibhaajewellery.in/backend/uploads
```

### 3. SSL

HTTPS is already enabled for vibhaajewellery.in (and www).

---

## Test

1. https://vibhaajewellery.in/backend/api/health → JSON success
2. https://vibhaajewellery.in → storefront
3. https://vibhaajewellery.in/admin/ → admin login

---

## Local development

Keep using localhost. Production URLs live in `.env.production`.
