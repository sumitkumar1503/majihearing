# Maji Hearing Aids — Clinic Management (PHP)

A pure-PHP port of the Next.js clinic app, built to run on **Hostinger shared/Premium hosting** (no Node.js, no Composer, no build step). Same Google Sheets data, same UI, same calculations, same features and role-based access.

## Tech
- Plain PHP 8.1+ (front-controller `index.php`)
- Google Sheets REST API via a service-account JWT (signed with `openssl`) — no Composer
- Tailwind CSS via CDN, Chart.js via CDN
- PHP sessions for auth (existing bcrypt passwords stay compatible)

## Setup (local or Hostinger)
1. Copy `config.sample.php` → `config.php` and fill in:
   - `google_service_account_email`, `google_private_key` (same as the old `GOOGLE_*` env vars)
   - the four spreadsheet IDs (`master`, `operations`, `sales`, `hr`)
2. Add your clinic logo as `assets/logo.jpg` (the pages reference `assets/logo.jpg`).
3. Make sure the `cache/` folder is writable (it stores the short-lived Google token).

## Deploy on Hostinger (shared / Premium)
1. In hPanel → File Manager, upload the contents of this folder into `public_html/` (or a subfolder).
2. Create `config.php` there with your real values (it is git-ignored, never committed).
3. Point the domain `majihearing.eu` at the hosting (already the case for a Hostinger-registered domain).
4. Ensure PHP is 8.1+ (hPanel → PHP Configuration). The `curl` and `openssl` extensions are enabled by default on Hostinger.
5. Visit the site — you'll get the login page. Sign in with an existing user from the `Users` sheet.

## Data
Uses the exact same Google Spreadsheets and tabs/columns as the Next.js app:
`Users, Branches, Brands, Tests, Doctors, Config` (master) · `Patients, Appointments, Enquiries, PotentialHA, Approvals, DS-<branch>-<month>` (operations) · `HASales, HAStock, HARepairs, Accessories, Invoices` (sales) · `Expenses, StaffTA, DrPayments, DrVisits, Attendance` (hr).

## Structure
```
index.php            Front controller (routing + auth guard)
config.php           Secrets (git-ignored)
lib/                 sheets, helpers, auth, data, approvals, reports
partials/            top.php (sidebar/header) + bottom.php
views/               one file per page (dashboard, patients, ... , invoice-print)
assets/logo.jpg      Clinic logo (add your own)
```
