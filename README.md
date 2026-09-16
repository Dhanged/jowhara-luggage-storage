# Luggage Storage Management System

Small hotel luggage storage system built with plain PHP, MySQL, Bootstrap, and XAMPP.

## Run With XAMPP

1. Put this folder in `C:\xamppd\htdocs\luggage_storage`.
2. Copy `.env.example` to `.env`, set the database connection, and choose a unique `INIT_ADMIN_USER` and `INIT_ADMIN_PASSWORD` of at least 12 characters. Never commit `.env`.
3. Start `Apache` and `MySQL` from XAMPP Control Panel.
4. Open `http://localhost/luggage_storage/` and log in with the administrator credentials from `.env`.
5. Remove `INIT_ADMIN_USER` and `INIT_ADMIN_PASSWORD` from `.env` after the account is created. Create other users from the admin interface.

The app automatically creates and upgrades the `luggage_storage` database when it loads. You can also import `database.sql` manually in phpMyAdmin.

This repository contains database structure only. Runtime backups, sessions, uploaded IDs, signatures, and other customer files must remain private and are excluded from Git.

## Main Features

- Login, logout, and auto logout after inactivity
- Multi user roles: Admin, Manager, Receptionist, Viewer
- Password change page
- Login history and audit log
- Dashboard statistics and stored vs collected charts
- Add, edit, search, archive, and restore guests
- Guest passport/ID details and ID photo upload
- Add, edit, search, archive, and restore luggage
- Luggage photo upload
- Luggage condition checklist
- Booking/reservation number
- Storage zone and shelf/location tracking
- Storage shelf map with capacity and overdue indicators
- Expected pickup date and overdue alerts
- Checkout workflow with pickup person details
- Guest digital signature at checkout
- Fee, payment status, method, and reference tracking
- Receipt printing
- Email receipt button
- WhatsApp/SMS prepared message link
- QR code generator on tags and receipts
- Code 39 barcode tag printing
- Barcode scanner page
- Bulk tag printing
- Bulk checkout, archive, and restore
- Reports page with advanced filters
- Excel export
- PDF export
- Daily shift report
- Manager monthly dashboard
- Dark mode
- Responsive mobile design
- Database backup download button

## Role Notes

- Admin: full access, user management, backup, audit, delete/archive, restore.
- Manager: operations, reports, shelf map, audit, manager dashboard.
- Receptionist: guest/luggage workflow, checkout, reports, scanner, shift report.
- Viewer: read-only dashboard/reports/shift access.

## Notes

- QR images use `api.qrserver.com`, so QR rendering needs internet access.
- Barcode tags are generated locally.
- XAMPP email sending requires mail configuration. If mail is not configured, the system still logs that an email receipt was prepared.

