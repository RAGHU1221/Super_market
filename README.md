# Supermarket Suite

A complete supermarket management system: a PHP/MySQL web admin + POS (billing,
inventory, GST reports, staff roles) plus an offline-first Flutter Windows
desktop billing app that syncs with it. Built to deploy on shared hosting
(InfinityFree / AIC Cloud) the same way your other projects (TFS, School ERP,
CSC-AMS) are deployed — plain PDO, no Composer, bilingual Tamil/English UI.

```
supermarket-suite/
├── web/            → PHP/MySQL admin + POS + REST API (upload this to hosting)
└── flutter_app/     → Windows desktop billing app (build via GitHub Actions)
```

Every PHP file has been syntax-checked, and the whole flow (login → add
product → purchase stock in → bill a sale → GST invoice → return → staff
roles → reports → mobile API login/sync) was tested end-to-end against a
real MySQL database before delivery. See "What was tested" at the bottom.

---

## 1. Deploy the web app (InfinityFree / AIC Cloud)

1. **Create a MySQL database** in your hosting control panel. Note the host,
   database name, username, password it gives you.
2. **Upload the `web/` folder contents** (everything *inside* `web/`, not the
   folder itself) to your hosting account's `htdocs` / `public_html`.
3. **Edit `config/db.php`** and fill in your real DB host/name/user/password.
   If your site is deployed in a subfolder (e.g. `yoursite.com/store/`), also
   set `APP_BASE_URL` to `/store`.
4. **Run the installer**: visit `https://yourdomain.com/install/` in a
   browser. It creates all tables and lets you set your own admin username
   and password (don't rely on the placeholder in `seed.sql` — the installer
   generates a fresh password hash for you).
5. **Delete the `install/` folder** after installing (it's a security risk
   to leave it up).
6. Log in at `https://yourdomain.com/login.php`, go to **Settings** and fill
   in your store name, address, GSTIN, tax %, and receipt paper size
   (thermal 80mm or A4).
7. Add your **staff** (Staff page, admin-only) with the right role — admin /
   manager / cashier control what each person can see.

### Day-to-day use
- **Products → Purchases**: add stock by recording a purchase (this is the
  only way stock goes up — new products start at 0 until you record a
  purchase for them).
- **Billing**: the POS screen. A USB/Bluetooth barcode scanner that types
  like a keyboard + Enter works directly in the search box — no extra setup.
- **Reports**: Sales, GST, Profit, Stock, Staff-wise — each has an Excel
  export button.
- **Invoice/receipt**: printable, downloadable as PDF, and shareable on
  WhatsApp, same pattern as your TFS receipts.

---

## 2. Build the Flutter Windows billing app

This app lets a cashier keep billing even when the internet is down — it
saves bills to a local SQLite database and syncs them to the web app
automatically once online. It mirrors the offline-first pattern of your
SMFoods app and the GitHub Actions + Inno Setup pattern of your earlier
Windows supermarket app.

Since you build from mobile via the GitHub web editor (no local Flutter),
everything here builds through **GitHub Actions** — you don't need Flutter
installed anywhere:

1. Create a new GitHub repo and push the contents of `flutter_app/` to it
   (upload via the GitHub.com web editor, same as your other projects).
2. **Before your first build**, add the Tamil font so receipts print
   correctly: see `assets/fonts/README.txt` — download "Noto Sans Tamil"
   from Google Fonts, add the two `.ttf` files, and uncomment the `fonts:`
   block in `pubspec.yaml`. (The app still builds and runs without this
   step, but Tamil text on the printed receipt won't render until you do.)
3. Push to the `main` branch, or run the workflow manually from the
   **Actions** tab (`Build Windows App`).
4. When it finishes, download either:
   - **SupermarketPOS-windows** (a zip of the raw app folder), or
   - **SupermarketPOS-Setup** (a proper Windows installer built with Inno
     Setup, same as your earlier supermarket app).
5. On first launch, the app asks for your **Server URL** (your deployed web
   app's URL), plus a staff **username/password** — the same login as the
   web app. Settings → "Mobile / Desktop App Sync" on the web admin shows
   the exact API URL to use.

### How the sync works
- Products, prices, stock and categories are managed from the **web admin**
  and pulled down to the app on every sync.
- Bills made in the app (online or offline) are saved locally first, so
  billing never stops for a shaky connection.
- Each offline bill gets a unique ID on the device; when it syncs up, the
  server checks that ID so the same bill can never be double-counted even if
  the sync retries.
- A manual **Sync** button is in the top bar and in Settings; it also
  syncs automatically right after every checkout when online.

---

## 3. Project structure reference

### `web/` (PHP/MySQL)
- `install/` — one-time DB installer (delete after use)
- `config/db.php` — your hosting DB credentials (edit this first)
- `includes/` — auth (bcrypt + CSRF + sessions), RBAC, shared functions, i18n
- `lang/ta.json`, `lang/en.json` — UI text, switch anytime from the top bar
- `products/` — categories, suppliers, product CRUD, purchases (stock in),
  manual stock adjustments
- `billing.php` + `billing_process.php` + `billing_hold.php` — the POS
  screen and its AJAX handlers
- `invoice.php` — GST invoice view/print/PDF/WhatsApp share
- `sales_history.php`, `returns.php`, `staff.php`, `settings.php`
- `reports/index.php` — Sales, GST, Profit, Stock, Staff-wise reports with
  Excel export
- `api/mobile/` — the REST API the Flutter app talks to (token-based auth)

### `flutter_app/` (Dart/Flutter, Windows desktop)
- `lib/db/database_helper.dart` — local SQLite schema (offline cache +
  outbox queue for unsynced bills)
- `lib/services/api_service.dart` — talks to `api/mobile/*`
- `lib/services/sync_service.dart` — pull catalogue, push queued bills,
  idempotent retries
- `lib/screens/` — login, billing (POS), sales history, settings
- `lib/utils/receipt_printer.dart` — builds a receipt PDF (thermal 80mm or
  A4) and sends it to the system print dialog
- `.github/workflows/build_windows.yml` — CI build (scaffolds the Windows
  platform folder fresh each run, builds, zips, and builds the installer)
- `installer/setup.iss` — Inno Setup script for the installer build

---

## 4. Roles

| Role | Can access |
|---|---|
| **Cashier** | Billing, Sales History (view own) |
| **Manager** | + Products, Categories, Suppliers, Purchases, Stock, Returns, Reports |
| **Admin** | Everything, including Staff management and Settings |

---

## 5. What was tested before delivery

Since this had to work on the first try, the whole thing was actually run
against a real MySQL database (not just checked for syntax) before being
handed to you:

- Every PHP file passed `php -l` (no syntax errors).
- Schema imported cleanly into MySQL/MariaDB — all 16 tables, foreign keys,
  and indexes created without error.
- Full login → dashboard → add product → record a purchase (stock in) →
  bill a sale via the actual POS AJAX endpoint → view/print the GST invoice
  → process a return → add a staff member → all 5 report types + Excel
  export → settings save — all exercised for real and checked against the
  database afterwards (correct GST math, correct stock increments/decrements,
  correct invoice numbering).
- Role-based access verified: a cashier login gets `403 Forbidden` on
  Products and Staff pages, and `200 OK` on Billing, as designed.
- The mobile REST API was tested end-to-end: login issuing a token, an
  unauthenticated request correctly rejected with `401`, a full products+
  categories pull, and — the part that matters most for offline safety — an
  offline sale pushed once (stock decremented correctly) and the *exact same*
  push retried (correctly recognized as a duplicate, stock **not**
  double-decremented).
- One real bug was actually caught this way (a stray line in `dashboard.php`
  calling a helper function before it was loaded, and a nested-transaction
  bug in invoice numbering that would have failed under real MySQL) — both
  are fixed in the delivered code, not just noted.

The Flutter app could not be compiled in this environment (no Flutter SDK
here, matching your own setup) — it will build via the GitHub Actions
workflow included, the same way your existing Flutter apps do. Each file was
written and reviewed carefully, but treat the first CI build as the real
verification step for that half of the project, and let me know if the
Actions log shows any error — it's straightforward to fix from the log.

---

## 6. Known simplifications (good to know, not bugs)

- Returns refund the item's own price (including its tax share) for the
  quantity returned; if a discount was applied to the whole bill, the
  refund isn't pro-rated down for that discount. Fine for typical use;
  flag it if you want exact discount-adjusted refunds instead.
- Stock adjustments, purchases and sales all use simple row-level locking
  (`FOR UPDATE`) rather than a queue — plenty for a single-store setup;
  for a very high-traffic multi-till store you'd want to revisit this.
- The Flutter app currently targets Windows desktop only (matching your
  proven pattern with barcode scanners and thermal printers); the same
  codebase can add an Android target later with one more `flutter create
  --platforms=android .` step in CI if you ever want a phone/tablet till too.
