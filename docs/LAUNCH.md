# Launching the live site

The live site is a second copy of the app with its own folder, database and `.env`, deployed from `main` (GitHub builds `build-main`).

> **Hostinger allows only one Git connection.** At launch that connection moves from `build-dev` (dev folder) to `build-main` (live folder). Set up the dev FTP upload first ([DEPLOY-HOSTINGER.md → Keeping dev updated after launch](DEPLOY-HOSTINGER.md#keeping-dev-updated-after-launch-ftp)) so dev keeps updating.
>
> **Stripe can wait.** With no Stripe keys in the live `.env`, checkout simply doesn't offer card payment, and the launch check flags the missing keys. Add them (step 4) before you take real bookings.

Work through the steps in order. Steps 1–6 can be done days before launch; the site only goes public at step 8.

## 1. Test everything on dev first

- [ ] Book one transfer, one tour and one ticket on dev with Stripe test card `4242 4242 4242 4242`.
- [ ] Check the customer email, the supplier email (goes to `MAIL_TEST_RECIPIENT` on dev) and the one-click **Confirm** link.
- [ ] Refund one booking from the admin panel.
- [ ] Turn the sample data off (admin dashboard → **Sample data → Hide sample catalogue**) and enter your real products, prices and suppliers.

> The catalogue lives in the database, so products entered on dev are **not** copied to the live site. Enter the real catalogue on live after step 7, or ask me to export/import it.

## 2. Hostinger: live folder and database

1. **hPanel → Databases → MySQL Databases**: create `mtt_live` with its own user and a strong password.
2. Decide the live folder: the main domain's folder, e.g. `public_html` (or a subfolder such as `public_html/live` with the domain pointed at it). Leave the dev folder where it is. The Git connection is changed in step 7, not now.
3. Make sure **PHP 8.2+** is selected for the main domain, with the same extensions as dev.

## 3. The live `.env`

Create `domains/mytransfertours.com/mtt-config/<live folder name>/.env` (next to the dev one; the last folder name must match the deployed folder, e.g. `public_html` → `mtt-config/public_html/.env`). Copy the dev `.env`, then change:

| Setting | Live value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://mytransfertours.com` |
| `APP_KEY` | **empty** (generated on the first deploy; never copy dev's) |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | the `mtt_live` details from step 2 |
| `ADMIN_PATH` | a private address, different from dev, e.g. `ops-8h3k2q` |
| `ADMIN_EMAIL` | your email (first admin is created on the first deploy) |
| `DEPLOY_TOKEN` | a **new** long random value, different from dev |
| `MAIL_TEST_RECIPIENT` | **empty** (so suppliers get their bookings) |
| `STRIPE_KEY` / `STRIPE_SECRET` | live keys `pk_live_…` / `sk_live_…` (step 4) |
| `STRIPE_WEBHOOK_SECRET` | the live webhook's `whsec_…` (step 4) |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | same as dev |

## 4. Stripe live mode

1. Stripe dashboard: complete account activation (business details, bank account for MYR payouts).
2. Turn **Test mode off** → **Developers → API keys**: copy the publishable key (`pk_live_…`) and secret key (`sk_live_…`) into the live `.env`.
3. **Developers → Webhooks → Add endpoint**:
   - URL: `https://mytransfertours.com/stripe/webhook`
   - Events: `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.expired`, `checkout.session.async_payment_failed`
   - Copy the **Signing secret** (`whsec_…`) into `STRIPE_WEBHOOK_SECRET`.
4. **Settings → Payment methods**: enable cards, plus FPX / GrabPay / Apple Pay / Google Pay if you want them.
5. **Settings → Public details / Branding**: business name, support email and logo (shown on the Checkout page and receipts).

## 5. Google and email

- **Google Cloud → Credentials → your OAuth client**: make sure both live redirect URIs are listed:
  `https://mytransfertours.com/auth/google/callback` and `https://mytransfertours.com/auth/google/admin-callback`.
- Email: the same IONOS mailbox works for live. Send a test booking email in step 7 to confirm.

## 6. Cron job for live

**hPanel → Advanced → Cron Jobs → Custom**, every minute (`* * * * *`):

```
/usr/bin/php /home/<hostinger user>/domains/mytransfertours.com/<live folder>/artisan schedule:run
```

This runs deploy steps, emails, supplier reminders, exchange rates and the **daily database backup** (03:10 Malaysian time, last 14 kept in `domains/mytransfertours.com/mtt-backups/<live folder>/`).

## 7. First live deploy

1. On GitHub, merge `develop` into `main` (Pull request → Merge). About 2 minutes later `build-main` exists (**Actions** shows a green tick).
2. **hPanel → Advanced → Git**: edit the existing connection (you can only have one):
   - Branch: `build-dev` → **`build-main`** (never plain `main`: it has no `vendor/` folder and the site would show a 403).
   - Folder: the dev folder → the **live folder** from step 2.
   - Click **Deploy**.
3. Open `https://mytransfertours.com/_deploy/<live DEPLOY_TOKEN>`. You should see `OK`, the **temporary admin password** (shown once: save it) and the **Launch check** list.
4. Fix everything marked **✗**, then open the link again until it says *All essential checks passed*.
5. Sign in at `https://mytransfertours.com/<ADMIN_PATH>`, change the password, and fill in **Setup → Company & invoices**, currencies, suppliers and the real catalogue.
6. Optional: `https://mytransfertours.com/_deploy/<token>?backup=1` makes a backup immediately, to check backups work.

## 8. Go live

- [ ] Make one real booking with your own card, check every email, then refund it from the admin panel.
- [ ] **Google Search Console**: add `mytransfertours.com`, verify it (DNS record in hPanel), and submit `https://mytransfertours.com/sitemap.xml`.
- [ ] Share the site.

## After launch

- The one Git connection now serves the live site. Dev keeps updating through the FTP upload, provided the `DEV_FTP_*` secrets are set ([DEPLOY-HOSTINGER.md → Keeping dev updated after launch](DEPLOY-HOSTINGER.md#keeping-dev-updated-after-launch-ftp)).
- New work still goes to `develop`. To release it, merge `develop` into `main`; the live site updates by itself (then open the live `/_deploy/<token>` to apply it straight away).
- Unexpected errors on the live site are emailed to the company email (or the first admin), at most once per 30 minutes per error.
- Logs: `<live folder>/storage/logs/` in File Manager.
