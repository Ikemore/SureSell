# Nokware Market

**Nokware** (Twi: *truth/honesty*) — a hyper-local marketplace built around one idea:
trust should be earned and visible, not assumed. Every trader verifies their phone
number before posting, builds a public track record from *confirmed* deals only,
and can add ID verification for the strongest trust badge on the platform.

---

## 1. Setup in XAMPP (local)

1. Copy the whole `nokware` folder into your XAMPP `htdocs` directory, e.g.
   `C:\xampp\htdocs\nokware` (Windows) or `/Applications/XAMPP/htdocs/nokware` (Mac).
2. Start **Apache** and **MySQL** from the XAMPP control panel.
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`), click **Import**, and import
   `database/schema.sql`. This creates the `nokware_market` database and all tables,
   plus 8 starter categories.
4. Open `config/config.php` and check:
   - `APP_URL` matches your local path (default `http://localhost/nokware` is correct
     if you copied the folder as `nokware`).
   - `DB_USER` / `DB_PASS` match your MySQL login (XAMPP default is user `root`,
     empty password — already set).
5. Make sure PHP's **GD extension** is enabled (it is by default in XAMPP) — it's used
   to safely re-process every uploaded image and strip hidden location metadata.
6. Visit `http://localhost/nokware/` in your browser. You should see the homepage.

### Setting the real admin password
The schema ships with a **placeholder** admin password hash that will not work.
Generate a real one before logging in as admin:

Open a terminal in your XAMPP `php` folder (or anywhere `php` is on your PATH) and run:
```
php -r "echo password_hash('YourNewStrongPassword!23', PASSWORD_DEFAULT);"
```
Copy the output hash, then in phpMyAdmin run:
```sql
UPDATE users SET password_hash = 'PASTE_THE_HASH_HERE' WHERE email = 'admin@nokware.local';
```
Log in at `/login.php` with `admin@nokware.local` and your new password, then visit
`/admin/index.php`.

---

## 2. What's included

- **Trader registration** with phone-number OTP verification (required before posting)
- **ID verification** upload + admin approval flow → "Fully Verified" badge
- **Listings**: create, edit, mark sold, remove — with up to 5 photos each
- **Browse/search** with category, town, price range, and "verified traders only" filters
- **Masked contact reveal**: phone numbers are hidden until a logged-in user clicks
  reveal (every reveal is logged for audit purposes)
- **Deal confirmation loop**: buyer marks "I bought this," seller confirms, and the
  seller's public deal count + trust score go up — reputation only comes from real,
  confirmed transactions
- **Reviews** tied to confirmed deals only (can't be faked by unrelated accounts)
- **Reporting system** for scam/abuse flags, with an admin queue to resolve or dismiss
- **Admin panel**: approve/reject ID verifications, manage open reports
- Fully responsive layout (mobile, tablet, desktop)

---

## 3. Security measures already built in

| Risk | How it's handled |
|---|---|
| SQL injection | Every query uses PDO **prepared statements** with bound parameters — no user input is ever concatenated into SQL. `PDO::ATTR_EMULATE_PREPARES` is disabled so binding is real, not just string-escaped. |
| XSS (stored/reflected) | All output passed through `e()` (`htmlspecialchars`) before rendering. Listing descriptions, names, bios — everything a user can type — is escaped on output. |
| CSRF | Every state-changing form includes a per-session CSRF token, verified with `hash_equals()` before any write happens. |
| Password security | `password_hash()` / `password_verify()` (bcrypt), never plain text or reversible encryption. |
| Brute-force login | Failed logins are counted per account; after 5 failures the account locks for 15 minutes. All attempts are logged to `login_audit`. |
| Session hijacking | Sessions use `httponly`, `SameSite=Lax` cookies, strict mode, and are regenerated on every login to prevent fixation. |
| Malicious file upload | Uploaded images are validated by real file content (not just extension), re-encoded through GD (which also strips EXIF/GPS metadata), renamed to random filenames, and the entire uploads folder has script execution disabled via `.htaccess`. |
| Exposed sensitive data | Phone numbers are masked until explicitly revealed (and logged); password hashes and ID documents are never rendered to the public; ID photos are only viewable by admins. |
| Clickjacking / MIME sniffing | `X-Frame-Options: DENY` and `X-Content-Type-Options: nosniff` sent on every response. |
| Direct access to internals | `.htaccess` files block browser access to `/config`, `/database`, and disable execution inside `/assets/uploads`. |
| Ownership bypass (IDOR) | Every edit/delete/report action re-checks that the logged-in user actually owns the record server-side — never trusts a form field alone. |

---

## 4. Before you take this live (production checklist)

This app is fully functional locally, but a few things are placeholders that need
real services once you leave XAMPP:

1. **Real SMS delivery for OTP codes** — right now, the verification code is shown
   on-screen for local testing (`verify-phone.php`). Wire up a real gateway (Hubtel,
   mNotify, or Twilio all have simple HTTP APIs) inside `register.php` where the
   comment says `// In production, send $otp via an SMS gateway`.
2. **HTTPS** — set `APP_ENV` to `production` in `config/config.php` once you have an
   SSL certificate; this switches session cookies to `secure` mode.
3. **A real mail/SMS-based password reset flow** — not yet built; currently a user
   who forgets their password needs an admin to reset it manually in the database.
4. **Move `config/config.php` outside the public web root** if your host allows it,
   for an extra layer beyond the `.htaccess` block.
5. **Regular database backups** — set up a cron job or your host's backup tool.
6. **Rate limiting at the web-server level** (e.g., via Cloudflare or `mod_evasive`)
   as a second layer on top of the app-level limits already built in.

---

## 5. Suggested next features

- Payments/escrow (Mobile Money via Paystack or Hubtel) once trust volume is proven
- In-app messaging instead of only phone reveal
- Featured/boosted listings as a monetization layer
- Push/SMS notifications for deal requests
