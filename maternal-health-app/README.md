# Maternal Health Advisor Application

A minimal full-stack PHP/MySQL app implementing the use cases from the provided diagram: login, account management, appointments, chat, alarms, emergency, uploads, and admin user management.

## Quick start

1. Create the database and tables:
   - Import `database/schema.sql` into your MySQL server.

2. Configure environment:
   - Copy `config/config.sample.php` to `config/config.php` and update credentials.

3. Start a PHP dev server:
   ```bash
   php -S 0.0.0.0:8080 -t public
   ```

4. Access the app at http://localhost:8080.

## Default roles
- mother
- physician
- admin

Create users via the registration page or insert an admin manually in the DB.

## Folder structure
- `public/` public web root and pages
- `controllers/` AJAX/HTTP endpoints (JSON)
- `lib/` shared PHP libraries (DB, Auth)
- `config/` configuration
- `assets/` static assets (CSS/JS)
- `uploads/` uploaded files directory (make writable)
- `database/schema.sql` database schema

## Notes
- Uses PDO with prepared statements.
- Chat is implemented with lightweight polling.
- Alarms trigger browser notifications when the dashboard is open.
- Emergency alerts are stored in DB and visible to physicians/admin.