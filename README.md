# Maternal Health Advisor (PHP)

Quick start for role-based dashboards for Mother and Physician using PHP, MySQL and minimal CSS.

Setup

1. Create database `maternal_health_app` in MySQL.
2. Import `schema.sql` into that database.
3. Configure DB credentials via environment variables if needed: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`. Defaults are `127.0.0.1`, `maternal_health_app`, `root`, empty.
4. Serve the project root with PHP built-in server:

```
php -S 0.0.0.0:8080 -t /workspace
```

Login

- Demo users created by `schema.sql`:
  - mother@example.com / Password@123
  - physician@example.com / Password@123

Structure

- `config.php` – PDO connection, session, helpers
- `login.php`, `register.php`, `logout.php`
- `mother/dashboard.php` and `physician/dashboard.php`
- `schema.sql` – tables for users, appointments, messages, health_records, emergencies, alarms
- `assets/styles.css` – minimal styling