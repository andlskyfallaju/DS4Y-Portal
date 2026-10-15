# DS4Y Attendance & Registration Portal

A full-stack web application for managing student enrolment, daily attendance and laptop accountability in the **Digital Skills 4 Youth (DS4Y)** programme at **Impact Hub Harare**.

I built it during my ICT service internship at Impact Hub Harare after noticing gaps in how student records and laptops were being tracked. Students use two simple public forms. Staff and administrators use a protected dashboard to monitor attendance, track laptops and export records.

**Stack:** PHP · SQLite · JavaScript · HTML · CSS

---

## Features

### Public (students)

| Page | What it does |
| --- | --- |
| **Daily Register** (`index.php`) | Students record their attendance for the day together with their laptop details. Hub laptops are checked against the laptop assigned to that student on their enrolment record. Personal laptops are entered as `Personal - <initials>` and checked against the enrolment record too. |
| **DS4Y Registration** (`registration.php`) | One-time enrolment form (name, contact details, laptop, gender, location, ID number, date of birth, marital status). Duplicate names, emails, phone numbers, ID numbers and laptop numbers are rejected. |

### Staff dashboard (login required)

- **Dashboard** with live stats: check-ins today, total records, laptops not yet returned today, and number of session days.
- **Search and date filter**, plus instant client-side search on the table.
- **Laptop returns:** mark a laptop as returned, recording which staff member received it and the time.
- **Archive** of older registrations (older than 2 days, configurable), grouped by date and searchable.
- **Excel export** of the attendance register.
- **My Account:** update your display name and change your password.

### Administrator tools

- **Enrolled students:** view, edit and export all enrolment records.
- **User management:** create and delete staff accounts, assign roles, reset passwords.
- **Role-based access control:** two roles, `staff` and `admin`. Admin-only pages return an access-denied page to everyone else.

---

## Security

Security was a priority from the start, since the portal handles personal information.

- **SQL injection prevention:** all database access uses PDO prepared statements.
- **CSRF protection:** every form carries a per-session token that is verified on submission.
- **XSS protection:** output is escaped through a shared `h()` helper.
- **Password hashing:** passwords are stored with `password_hash()` and checked with `password_verify()`.
- **Brute-force lockout:** 5 failed logins lock that username for 5 minutes, with added delays on failed attempts.
- **Rate limiting:** the public registration forms accept at most 20 submissions per 5 minutes.
- **Session management:** HttpOnly cookies, strict mode, session ID regeneration on login and every 5 minutes, and a 15-minute inactivity timeout. The Secure flag is set automatically over HTTPS.
- **Security headers:** `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options` and `Referrer-Policy`.
- **Random first-run admin password:** the first administrator account gets a randomly generated password, shown once on the login page.

---

## Getting Started

### Requirements

- [XAMPP](https://www.apachefriends.org/) (or any Apache/PHP setup) with **PHP 7.4 or higher** (PHP 8.x recommended)
- The `pdo_sqlite` PHP extension (enabled by default in XAMPP)

### Installation

1. Clone the repository into your web root (for example `C:/xampp/htdocs/`):
   ```bash
   git clone https://github.com/andlskyfallaju/DS4Y-Portal.git
   ```
2. Create the folder that will hold the database: `C:/xampp/ds4y_data/`.
   The database location is set in `db.php` (`$db_file`). Change it there if you want it somewhere else, ideally **outside** the web root.
3. Start Apache from the XAMPP Control Panel.
4. Open `http://localhost/DS4Y-Portal/home.php` in your browser.

The database file and all tables are created automatically on first run.

### First login

1. Go to **Staff Login** (`login.php`).
2. On the very first visit, a randomly generated password for the `admin` account is displayed **once** on the login page. Copy it immediately.
3. Sign in as `admin`, open **My Account**, and change the password straight away.
4. Create staff accounts from the **Users** page.

> **Note:** `init_db.php` is a legacy helper that creates an `admin` user with a fixed, well-known password. Do not use it or deploy it. Delete it from any real installation.

---

## User Roles

| Capability | Public | Staff | Admin |
| --- | :---: | :---: | :---: |
| Daily Register and DS4Y Registration forms | ✅ | ✅ | ✅ |
| Dashboard, archive, mark laptops returned, delete records | | ✅ | ✅ |
| Export the attendance register | | ✅ | ✅ |
| Manage own account and password | | ✅ | ✅ |
| View, edit and export enrolled students | | | ✅ |
| Create, edit and delete users | | | ✅ |

---

## Project Structure

```
├── home.php            Landing page
├── index.php           Daily Register (public)
├── registration.php    DS4Y enrolment form (public)
├── login.php           Staff login
├── logout.php          Ends the session
├── dashboard.php       Staff dashboard, laptop returns, Excel export
├── archive.php         Older registrations grouped by date
├── account.php         Profile and password change
├── enrolled.php        Enrolled students list and export (admin)
├── enrolled_edit.php   Edit an enrolment record (admin)
├── users.php           User management (admin)
├── users_edit.php      Edit user role, name, password (admin)
├── access_denied.php   403 page
├── db.php              Database connection, schema, security headers, helpers
├── header.php          Shared page header and navigation
├── footer.php          Shared page footer
├── main.js             Navigation toggle, live search, alert dismissal
└── style.css           Styling
```

---

## Configuration

A few settings can be changed in the code:

| Setting | File | Default |
| --- | --- | --- |
| Database path | `db.php` (`$db_file`) | `C:/xampp/ds4y_data/ds4y.db` |
| Days before records move to the archive | `db.php` (`ARCHIVE_DAYS`) | 2 |
| Session inactivity timeout | `db.php` | 15 minutes |
| Failed login attempts before lockout | `login.php` (`$max_attempts`) | 5 |
| Lockout duration | `login.php` (`$lockout_time`) | 5 minutes |
| Registration rate limit | `index.php`, `registration.php` | 20 per 5 minutes |

---

## Data Protection

The enrolment form collects personal information, including ID numbers, dates of birth and contact details. If you run this portal:

- Keep the database **outside the web root** and never commit `.db` files to version control.
- Serve it over **HTTPS** in any real deployment.
- Restrict admin accounts to the people who need them.
- Follow your organisation's data protection policy and the privacy law that applies to you.

---

## Known Limitations

Being upfront about what could be improved:

- Login lockout and form rate limiting are tracked **per session**, so they slow down casual abuse but are not a substitute for server-level or IP-based limits.
- The Content-Security-Policy allows inline scripts and styles.
- The Excel export produces an `.xls` file, not a modern `.xlsx` workbook.
- The database path is hard-coded for a Windows XAMPP setup.

---

## Author

**Andile Makuyana**
BSc ICT (Software Engineering) student, Arrupe Jesuit University
GitHub: [@andlskyfallaju](https://github.com/andlskyfallaju)

Built for the Digital Skills 4 Youth programme at [Impact Hub Harare](https://www.impacthubharare.net).
