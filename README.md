# JMedi – Smart Medical Platform

A full-featured SaaS-style medical management system built with **PHP 8.2** and **PostgreSQL**. It provides a patient-facing public portal and a powerful admin/doctor backend for managing appointments, schedules, departments, and site content.

---

## Features

**Public Portal**
- Homepage with dynamic hero sliders, department showcase, doctor listings, and testimonials
- Doctor profiles with specialization, schedule, and online/clinic booking
- Multi-step appointment booking with real-time slot availability
- Patient login, registration, and personal dashboard
- Blog, contact page, and dynamic CMS-managed pages

**Admin Panel**
- Full CRUD for doctors, departments, patients, blog posts, testimonials, and hero slides
- Appointment workflow: Pending → Confirmed → Completed / Cancelled / Rescheduled
- WhatsApp and email quick-action links per appointment
- Doctor schedule builder (per day, per session, with slot duration)
- Role-based access control: Super Admin, Admin, Doctor
- Site settings, menu manager, homepage section editor (CKEditor)
- Database and full-site backup/download tools

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.2 or higher |
| PostgreSQL | 13 or higher |
| PHP Extensions | `pdo_pgsql`, `fileinfo`, `zip`, `mbstring`, `json` |
| Apache | 2.4+ with `mod_rewrite` enabled |

> **Note:** Most shared cPanel hosts provide MySQL by default. You will need a host that supports **PostgreSQL** (e.g., via phpPgAdmin in cPanel). Popular options: A2 Hosting, Hostinger VPS, DigitalOcean, or any VPS with cPanel/WHM installed.

---

## cPanel Setup Guide

### Step 1 — Download the Project Files

Clone or download the repository as a ZIP:

```
https://github.com/JNVWEBin/jmedi-smart-medical-platform
```

Extract the ZIP on your local machine — you should have a folder with the full project structure.

---

### Step 2 — Upload Files to cPanel

1. Log in to your **cPanel** account.
2. Open **File Manager** and navigate to `public_html` (or a subdomain folder if you're installing on a subdomain like `clinic.yourdomain.com`).
3. Upload the entire project folder contents directly into `public_html`. After upload your structure should look like:

```
public_html/
├── .htaccess
├── router.php
├── admin/
├── assets/
├── database/
├── includes/
└── public/
```

Make sure the `.htaccess` file is included — it handles all URL routing.

---

### Step 3 — Create a PostgreSQL Database

1. In cPanel, scroll to the **Databases** section and open **PostgreSQL Databases** (if not visible, contact your host — some hosts require enabling it).
2. Create a new database, e.g. `jmedi_db`.
3. Create a database user with a strong password.
4. Grant the user **All Privileges** on the database.
5. Note down: **hostname**, **port** (default `5432`), **database name**, **username**, and **password**.

---

### Step 4 — Import the Database Schema

1. Open **phpPgAdmin** from cPanel (under the Databases section).
2. Connect using the credentials you just created.
3. Select your database from the left panel.
4. Click the **SQL** tab and paste the contents of `database/schema.sql`, then execute it.

Alternatively, via SSH:
```bash
psql -h localhost -U your_db_user -d jmedi_db -f /home/youraccount/public_html/database/schema.sql
```

This creates all tables, indexes, and inserts default data including sample doctors, departments, blog posts, and a default admin account.

---

### Step 5 — Configure the Database Connection

The application reads database credentials from environment variables. On cPanel, the easiest method is to define them in a `.env`-style PHP config.

**Option A — cPanel Environment Variables (recommended for WHM/VPS)**

In cPanel → **PHP Configuration** (or `.htaccess`), add:

```apache
SetEnv DATABASE_URL "pgsql://your_db_user:your_password@localhost:5432/jmedi_db"
```

Add this line to the `.htaccess` file already in `public_html`.

**Option B — Direct `.htaccess` environment variables**

Open `.htaccess` and add these lines near the top:

```apache
SetEnv PGHOST     localhost
SetEnv PGPORT     5432
SetEnv PGDATABASE jmedi_db
SetEnv PGUSER     your_db_user
SetEnv PGPASSWORD your_strong_password
```

**Option C — Edit `includes/db.php` directly** (shared hosting fallback)

If environment variables aren't supported, open `includes/db.php` and replace the env-variable block with your credentials directly:

```php
$host   = 'localhost';
$port   = 5432;
$dbname = 'jmedi_db';
$user   = 'your_db_user';
$pass   = 'your_strong_password';
```

> **Security tip:** If using Option C, make sure `includes/db.php` is not publicly accessible. The `.htaccess` in the project already blocks direct access to the `includes/` directory.

---

### Step 6 — Set Folder Permissions

Via **File Manager** or SSH, set the following permissions:

| Path | Permission |
|---|---|
| `assets/uploads/` | `755` |
| `assets/avatars/` | `755` |
| `backups/` | `755` |
| All `.php` files | `644` |
| All directories | `755` |

To create the upload directories if they don't exist:
```bash
mkdir -p public_html/assets/uploads
mkdir -p public_html/assets/avatars
mkdir -p public_html/backups
chmod 755 public_html/assets/uploads public_html/assets/avatars public_html/backups
```

---

### Step 7 — Configure PHP Version

1. In cPanel, go to **MultiPHP Manager** or **Select PHP Version**.
2. Set the PHP version for your domain to **PHP 8.2**.
3. Enable these PHP extensions: `pdo_pgsql`, `fileinfo`, `zip`, `mbstring`, `json`, `gd`.

---

### Step 8 — Verify the Installation

Open your browser and navigate to your domain. You should see the JMedi homepage.

To access the **Admin Panel**:

```
https://yourdomain.com/admin/
```

**Default credentials:**

| Field | Value |
|---|---|
| Username | `admin` |
| Password | `password` |

> **Important:** Change the default admin password immediately after your first login via Admin Panel → Profile.

---

## Directory Structure

```
jmedi/
├── .htaccess               # Apache URL routing and security rules
├── router.php              # PHP built-in server router (dev only)
├── admin/                  # Admin panel pages
│   ├── login.php
│   ├── dashboard.php
│   ├── appointments.php
│   ├── doctors.php
│   ├── departments.php
│   ├── backup.php
│   └── ...
├── assets/
│   ├── css/                # Stylesheets
│   ├── js/                 # JavaScript files
│   └── uploads/            # User-uploaded images
├── database/
│   └── schema.sql          # Full PostgreSQL schema + seed data
├── includes/
│   ├── db.php              # Database connection (PDO)
│   ├── auth.php            # Session, CSRF, RBAC
│   └── functions.php       # Shared helper functions
└── public/
    ├── index.php           # Homepage
    ├── appointment.php     # Booking page
    ├── doctors.php
    ├── departments.php
    └── api/                # JSON API endpoints
```

---

## User Roles

| Role | Access |
|---|---|
| **Super Admin** | Full access to everything including backups, user management, and system settings |
| **Admin** | Configurable permission set for doctors, appointments, blog, CMS, etc. |
| **Doctor** | Own appointment list and schedule only — cannot see other doctors' patients |

---

## Troubleshooting

**Blank page or 500 error**
- Check that PHP 8.2 is selected in cPanel MultiPHP Manager.
- Temporarily enable error display by adding `php_flag display_errors On` to `.htaccess`.
- Confirm all required PHP extensions are enabled (`pdo_pgsql`, `fileinfo`, `zip`).

**Database connection failed**
- Double-check credentials in `includes/db.php` or your environment variable setup.
- Confirm PostgreSQL is running and the user has access to the database.
- On shared hosting, the host may be `127.0.0.1` instead of `localhost`.

**404 on all pages except homepage**
- Confirm `mod_rewrite` is enabled in Apache.
- Check that `.htaccess` was uploaded and is in the `public_html` root.
- Add `AllowOverride All` to your Apache virtual host config if on a VPS.

**Images not uploading**
- Check that `assets/uploads/` exists and has `755` permissions.
- Confirm `fileinfo` PHP extension is enabled.

**Backup not working**
- The backup feature requires `pg_dump` to be available on the server.
- This is typically available on VPS setups but may not be on shared hosting.

---

## Security Notes

- All form submissions are protected with CSRF tokens.
- Passwords are hashed with `password_hash()` (bcrypt).
- All database queries use PDO prepared statements.
- File uploads are validated against real MIME type (not browser-supplied type).
- Doctor accounts can only access their own appointments (IDOR protection).
- Session cookies are set with `httponly` and `samesite=Strict` flags.
- Directory listing is disabled via `Options -Indexes`.

---

## Local Development

For local development using the PHP built-in server:

```bash
# Requires PHP 8.2 and a PostgreSQL database
export DATABASE_URL="pgsql://user:password@localhost:5432/jmedi_db"

php -S 0.0.0.0:5000 router.php
```

Then visit `http://localhost:5000`.

---

## License

This project is proprietary software developed by **JNVWeb**. All rights reserved.

---

*Built with PHP 8.2, PostgreSQL, Bootstrap 5.3, Chart.js, Swiper.js, and CKEditor.*
