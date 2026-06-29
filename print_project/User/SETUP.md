# HyperPrint — Live Deployment Guide

## Files in this package

| File | Description |
|------|-------------|
| `create_order.php` | **NEW** — Creates a Razorpay order server-side before checkout |
| `verify_payment.php` | **UPDATED** — Full HMAC-SHA256 signature verification |
| `print_handler.php` | **UPDATED** — Auth-guarded, marks job Printed in DB |
| `upload.php` | **UPDATED** — Uses `create_order.php`, cleaner UPI/card flow |
| `db.php` | **UPDATED** — Better error handling |
| `schema.sql` | **NEW** — Complete DB schema with all Razorpay columns |

All other files (dashboard.php, history.php, settings.php, login.php, etc.) work
as-is — no changes needed there.

---

## Step 1 — Get your Live Razorpay Keys

1. Log in to [dashboard.razorpay.com](https://dashboard.razorpay.com)
2. Toggle **Live Mode** (top-left switch)
3. Go to **Settings → API Keys → Generate Key**
4. You will get:
   - **Key ID** — starts with `rzp_live_`
   - **Key Secret** — shown only once, copy it now

---

## Step 2 — Update credentials in 2 files

### `create_order.php` (lines 24–25)
```php
define('RAZORPAY_KEY_ID',     'rzp_live_XXXXXXXXXXXXXXXX');  // ← your Live Key ID
define('RAZORPAY_KEY_SECRET', 'XXXXXXXXXXXXXXXXXXXXXXXX');   // ← your Live Key Secret
```

### `verify_payment.php` (line 26)
```php
define('RAZORPAY_KEY_SECRET', 'XXXXXXXXXXXXXXXXXXXXXXXX');   // ← same Live Key Secret
```

### `upload.php` (line 15)
```php
define('RAZORPAY_KEY_ID', 'rzp_live_XXXXXXXXXXXXXXXX');      // ← your Live Key ID
```

### `dashboard.php` (find the existing key constant, same change)
```php
define('RAZORPAY_KEY_ID', 'rzp_live_XXXXXXXXXXXXXXXX');
```

### `db.php` (line 12)
```php
$password = "YOUR_DB_PASSWORD";  // ← your real MySQL password
```

---

## Step 3 — Update the Database

Run the schema upgrade (safe to run even if tables already exist):

```bash
mysql -u root -p print_system < schema.sql
```

This adds the missing columns:
- `razorpay_order_id` — links order created in `create_order.php`
- `razorpay_signature` — stored for audit trail
- `printed_at` — timestamp when printed

---

## Step 4 — Upload files to your server

Copy all PHP files to your web root (e.g. `/var/www/html/hyperprint/`).

Make sure the `uploads/` directory is writable:
```bash
mkdir -p uploads
chmod 755 uploads
```

---

## Step 5 — Enable HTTPS (required for live payments)

Razorpay **requires HTTPS** in live mode. Options:
- Use [Certbot](https://certbot.eff.org/) for a free Let's Encrypt cert
- Enable HTTPS in cPanel/Plesk if on shared hosting

---

## Step 6 — Test with a real card

Use a real card or UPI in live mode (a ₹1 transaction works).
- Razorpay will show the full payment sheet: UPI, GPay, PhonePe, Cards, NetBanking

---

## How the payment flow works

```
User uploads file
        ↓
upload_handler.php  → saves file to DB (payment_status = 'pending')
        ↓
create_order.php    → calls Razorpay API, gets order_id, stores in DB
        ↓
Razorpay checkout   → user pays (UPI / card / wallet)
        ↓
verify_payment.php  → verifies HMAC-SHA256 signature, sets payment_status = 'paid'
        ↓
print_handler.php   → validates paid status, sets status = 'Printed'
```

---

## Security notes

- **Signature verification** prevents anyone from faking a payment by calling
  `verify_payment.php` directly with a made-up payment ID.
- **Server-side amount** — `create_order.php` fetches cost from DB, never trusts
  the amount sent by the browser.
- **Auth guards** — all handlers check `$_SESSION['user_id']` first.
- **Job ownership** — every DB query includes `AND user_id = ?` to prevent
  cross-user attacks.

---

## Pricing (configurable in `upload_handler.php`)

| Type | Single-sided | Double-sided |
|------|-------------|--------------|
| B&W  | ₹3/page     | ₹2/page      |
| Color| ₹10/page    | ₹8/page      |

Edit the `$price_map` array in `upload_handler.php` to change prices.
