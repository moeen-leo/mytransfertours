# Deploying to Hostinger (no SSH needed)

## How it works

```
I push to develop ──► GitHub builds it (installs vendor/) ──► build-dev branch ──► Hostinger deploys to the dev folder
                                                                                          │
                       hPanel cron, every minute: php artisan schedule:run ◄──────────────┘
                       └─ sees a new build ─► generates APP_KEY (first time) ─► migrates ─► seeds Malaysia data (first time) ─► caches
```

At launch the same happens for `main` → `build-main` → live folder.

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
   - **Authorised redirect URIs:** `https://dev.mytransfertours.com/auth/google/callback` (and `https://mytransfertours.com/auth/google/callback`)
4. Copy the **Client ID** and **Client secret** into `.env`:

```
GOOGLE_CLIENT_ID=xxxxxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxx
```

`GOOGLE_REDIRECT_URI` can stay as in `.env.example` (or be left out: it defaults to `APP_URL/auth/google/callback`). Then open the post-deploy link `/_deploy/<token>` so the cached config picks up the new values.

How accounts are matched: a Google sign-in with an email that already has an account signs into that account (Google has confirmed the email, so it also counts as email verification and brings in earlier guest bookings). A new email creates a customer account with no password.

## If something goes wrong

| Symptom | Cause / fix |
|---|---|
| "500 Server Error" | The `.env` is missing or in the wrong folder, or the cron hasn't run yet (the key is generated on the first run). |
| Folder listing or 403 | Git is still on `develop` instead of `build-dev`, so `vendor/` is missing. |
| Database error | DB name, user or password in `.env` don't match hPanel (remember the `u123456789_` prefix). |
| Google: "redirect_uri_mismatch" | The redirect URI in Google Cloud must match exactly, including `https://` and no trailing slash. |
| Google button missing | `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` empty, or config still cached: open `/_deploy/<token>`. |
| Logs | `public_html/dev/storage/logs/` in File Manager. Send me the latest file. |

## Live site (at launch)

Same steps with: branch **`build-main`**, a separate database `mtt_live`, `.env` at `mtt-config/<live folder>/.env` with `APP_ENV=production`, `APP_URL=https://mytransfertours.com` and **live** Stripe keys, plus a second cron job pointing at the live folder.
