# Like HOME — deploy și setup

Documentație versionată. Fișierele `.env`, `.env.example` și `migrations/` rămân **doar local** (nu în Git).

## Deploy cPanel

Neschimbat: [`.cpanel.yml`](../.cpanel.yml) rulează [`scripts/cpanel-deploy.sh`](../scripts/cpanel-deploy.sh).

Copiază în `public_html`: PHP rădăcină, `admin`, `ajax`, `assets`, `components`, `cron`, `ical`, `includes`, `lang`, `en`, `ru`, `vendor`, `.htaccess`, `robots.txt`.

**Nu** copiază: `.env`, `uploads/` (imagini), `migrations/`, `scripts/`, `docs/`.

## Fișier `.env` (manual)

1. Copiază local `.env.example` → `.env` (ambele ignorate de Git).
2. **Producție:** plasează `.env` în afara `public_html`, ex. `/home/likehome/.env`.
3. Opțional: `LH_ENV_PATH=/home/likehome` în cPanel Environment Variables.

### Variabile (fără valori secrete aici)

| Variabilă | Rol |
|-----------|-----|
| `APP_ENV`, `APP_DEBUG`, `APP_TIMEZONE` | mediu aplicație |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET` | MySQL |
| `SITE_BASE_PATH` | gol sau `/` pe producție; `/likehome.md` pe MAMP local |
| `PUBLIC_SITE_URL` | URL canonic, ex. `https://www.likehome.md` |
| `APP_DEFAULT_LOCALE`, `APP_LOCALES` | `ro` + `ro,en,ru` |
| `MAILJET_API_KEY`, `MAILJET_API_SECRET`, `BOOKING_MAIL_FROM`, `ADMIN_NOTIFICATION_EMAIL` | email rezervări |
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID` | notificări |
| `ICAL_SYNC_SECRET`, `CHECKIN_REMINDER_SECRET` | cron securizat |
| `ICAL_ALLOW_HTTP`, `ICAL_FETCH_MAX_BYTES` | sync iCal |
| `BOOKING_RATE_LIMIT_*`, `BOOKING_PRICE_PREVIEW_RATE_LIMIT_*` | rate limit |
| `UPLOAD_MAX_IMAGE_BYTES` | upload imagini admin |
| `APP_BASE_CURRENCY`, `APP_CURRENCY_DISPLAY` | monedă afișată |
| `GA_MEASUREMENT_ID`, `CLARITY_PROJECT_ID`, `GOOGLE_MAPS_EMBED_API_KEY` | analytics / hartă |
| `BOOKING_GUEST_SUPPORT_PHONES` | telefoane în email oaspeți |
| `LH_DEBUG_SAVE_TIMINGS` | debug admin |

## MySQL — rulare o singură dată per mediu

Păstrează SQL-ul local în `migrations/` și rulează în phpMyAdmin **înainte** sau **după** primul deploy cu i18n:

### 1. `property_translations`

Tabel pentru titlu/descriere EN și RU (`property_id` trebuie să fie `INT` signed ca `properties.id`).

Vezi fișierul local `migrations/001_property_translations.sql`.

### 2. `bookings.locale`

```sql
ALTER TABLE bookings
  ADD COLUMN locale VARCHAR(5) NOT NULL DEFAULT 'ro' AFTER status;
```

(Vezi `migrations/002_bookings_locale.sql` — omită dacă coloana există deja.)

## Checklist producție după push

- [ ] `.env` pe server cu `SITE_BASE_PATH=` gol, `APP_LOCALES=ro,en,ru`
- [ ] `mod_rewrite` activ sau stubs `en/*.php`, `ru/*.php` prezente
- [ ] `.htaccess` în `public_html`
- [ ] SQL (1) și (2) aplicate
- [ ] Smoke: `/`, `/en/`, `/ru/`, flux rezervare, `bookings.locale` la o rezervare test

## Uploads

Folderul `uploads/` nu se versionează și nu se suprascrie la deploy. La primul deploy se creează doar structura goală dacă lipsește.
