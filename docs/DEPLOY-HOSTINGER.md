# Deploying to Hostinger (no SSH needed)

## How it works

```
I push to develop ──► GitHub builds it (installs vendor/) ──► build-dev branch ──► Hostinger deploys to the dev folder
                                                                                          │
                       hPanel cron, every minute: php artisan schedule:run ◄──────────────┘
                       └─ sees a new build ─► generates APP_KEY (first time) ─► migrates ─► seeds Malaysia data (first time) ─► caches
```

At launch the same happens for `main` → `build-main` → live folder. Hostinger allows one Git connection, so at launch it is switched from `build-dev` to `build-main` (see [LAUNCH.md](LAUNCH.md), step 7).

## One-time setup for dev

### 1. Check PHP
hPanel → **Advanced → PHP Configuration** → choose **PHP 8.2 or newer** for the site. Make sure these extensions are on: `pdo_mysql`, `mbstring`, `bcmath`, `intl`, `fileinfo`, `openssl`.

### 2. Database
hPanel → **Databases → MySQL Databases** → create e.g. `mtt_dev` with its own user. Note the **database name, username and password** (Hostinger prefixes them, e.g. `u123456789_mtt_dev`).

### 3. Point Git at the build branch
hPanel → **Advanced → Git** → on the dev connection change the branch from `develop` to **`build-dev`** and click **Redeploy**.

> `build-dev` only exists after the first GitHub build finishes (about 2 minutes after a push). Check **github.com/moeen-leo/mytransfertours → Actions**: a green tick means it's ready.

### 4. Create the `.env` file (outside public_html)
hPanel → **Files → File Manager**. Find the folder the dev site is deployed to, for example:

```
domains/mytransfertours.com/public_html/dev
```

Then, **next to `public_html`** (not inside it), create:

```
domains/mytransfertours.com/mtt-config/dev/.env
```

The last folder name (`dev`) must match the deployed folder's name. Paste the contents of [`.env.example`](../.env.example) and fill in:

| Setting | Value |
|---|---|
| `APP_URL` | `https://dev.mytransfertours.com` |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | from step 2 |
| `APP_KEY` | **leave empty**: generated automatically |
| `MAIL_*`, `STRIPE_*`, `GOOGLE_*` | can wait until those features are built |

### 5. Cron job
hPanel → **Advanced → Cron Jobs** → **Custom**, every minute (`* * * * *`):

```
/usr/bin/php /home/u123456789/domains/mytransfertours.com/public_html/dev/artisan schedule:run
```

Replace `u123456789` with your Hostinger username and adjust the path to the dev folder (File Manager shows the full path). If `/usr/bin/php` gives an error, Hostinger's cron page shows the PHP path to use.

### 6. Check
Wait 1–2 minutes, then open **https://dev.mytransfertours.com**. You should see the green "Coming soon" page with `staging · Laravel 12.x` at the bottom.

## Google sign-in

The "Continue with Google" button appears on the sign-in and sign-up pages only once these are set in the site's `.env`.

1. Go to **console.cloud.google.com**, create a project (e.g. *MyTransferTours*).
2. **APIs & Services → OAuth consent screen**: user type **External**, app name *MyTransferTours*, support email, logo optional. Scopes: just the defaults (`email`, `profile`, `openid`). Click **Publish app** so anyone can sign in (no Google review is needed for these basic scopes).
3. **APIs & Services → Credentials → Create credentials → OAuth client ID** → *Web application*.
   - **Authorised JavaScript origins:** `https://dev.mytransfertours.com` (and `https://mytransfertours.com` for live)
   - **Authorised redirect URIs** (website and admin login, for each site):
     - `https://dev.mytransfertours.com/auth/google/callback`
     - `https://dev.mytransfertours.com/auth/google/admin-callback`
     - `https://mytransfertours.com/auth/google/callback`
     - `https://mytransfertours.com/auth/google/admin-callback`
4. Copy the **Client ID** and **Client secret** into `.env`:

```
GOOGLE_CLIENT_ID=xxxxxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxx
```

`GOOGLE_REDIRECT_URI` can stay as in `.env.example` (or be left out: it defaults to `APP_URL/auth/google/callback`). Then open the post-deploy link `/_deploy/<token>` so the cached config picks up the new values.

**Admin login:** the admin login page gets a "Sign in with Google" button too. It only signs in people who already have an **Admin** or **Staff** account with that exact email (added under **Team → Users**); it never creates accounts, and customers or unknown Google accounts are refused. Password login stays as a backup. To allow only company addresses, set `ADMIN_GOOGLE_DOMAIN=mytransfertours.com`.

How website accounts are matched: a Google sign-in with an email that already has an account signs into that account (Google has confirmed the email, so it also counts as email verification and brings in earlier guest bookings). A new email creates a customer account with no password.

## If something goes wrong

| Symptom | Cause / fix |
|---|---|
| "500 Server Error" | The `.env` is missing or in the wrong folder, or the cron hasn't run yet (the key is generated on the first run). |
| Folder listing or 403 | Git is still on `develop` instead of `build-dev`, so `vendor/` is missing. |
| Database error | DB name, user or password in `.env` don't match hPanel (remember the `u123456789_` prefix). |
| Google: "redirect_uri_mismatch" | The redirect URI in Google Cloud must match exactly, including `https://` and no trailing slash. |
| Google button missing | `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` empty, or config still cached: open `/_deploy/<token>`. |
| Logs | `public_html/dev/storage/logs/` in File Manager. Send me the latest file. |

## Keeping dev updated after launch (FTP)

At launch the one Git connection moves to `build-main` and the live folder. From then on GitHub uploads every `develop` build to the dev folder over FTP, so both sites update by themselves. Set this up **before** step 7 of [LAUNCH.md](LAUNCH.md):

1. hPanel → **Files → FTP Accounts** → create an account whose directory is the **dev folder** (e.g. `public_html/dev`). Note the FTP host (e.g. `ftp.mytransfertours.com` or the server IP), username and password.
2. github.com/moeen-leo/mytransfertours → **Settings → Secrets and variables → Actions → New repository secret**, add:

| Secret | Value |
|---|---|
| `DEV_FTP_SERVER` | the FTP host |
| `DEV_FTP_USERNAME` | the FTP username |
| `DEV_FTP_PASSWORD` | the FTP password |
| `DEV_FTP_DIR` | `./` if the FTP account opens straight in the dev folder, otherwise the path to it, ending in `/` (e.g. `public_html/dev/`) |

3. **Actions → Build for Hostinger → Run workflow** on `develop`. The first upload sends everything (about 15,000 files, 10–20 minutes); later uploads only send what changed.

Until `DEV_FTP_SERVER` is set, the upload step is skipped and nothing changes. `storage/` is never uploaded or deleted, so logs, sessions and uploads on the server are safe. The cron job on the dev folder applies each upload the same way it applies a Git deploy.

## Live site (at launch)

Same steps with: branch **`build-main`**, a separate database `mtt_live`, `.env` at `mtt-config/<live folder>/.env` with `APP_ENV=production`, `APP_URL=https://mytransfertours.com` and **live** Stripe keys, plus a second cron job pointing at the live folder.
