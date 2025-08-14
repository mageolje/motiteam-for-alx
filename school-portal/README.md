# School Management Portal (PHP + MySQL)

A lightweight school records portal implementing login/logout, assessments, attendance, messaging, result cards, and basic reports. Roles: `record_officer`, `subject_teacher`, and `homeroom_teacher`.

## Features
- Authentication with role-based access control
- Add attendance (subject teachers and homeroom teachers)
- Add assessments (subject teachers)
- Add final results and generate result cards (record officer)
- View/Send messages (between roles and users)
- Basic reports (counts, averages)

## Tech
- PHP 8+
- MySQL 5.7+/MariaDB 10.3+
- No frameworks; PDO for database access; vanilla CSS

## Setup
1. Create database and import schema + seed:
```sql
CREATE DATABASE school_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
Then import both files in order:
- `db/schema.sql`
- `db/seed.sql`

2. Configure database credentials in `app/config.php`.

3. Start a local PHP server from the `public` folder:
```bash
php -S 0.0.0.0:8080 -t public
```
Open `http://localhost:8080`.

## Default login users
- Record officer: `officer1` / `password`
- Subject teacher: `teacher1` / `password`
- Homeroom teacher: `homeroom1` / `password`

## Project structure
```
school-portal/
  app/
    config.php
  db/
    schema.sql
    seed.sql
  includes/
    auth.php
    functions.php
    header.php
    footer.php
  public/
    assets/
      styles.css
    index.php
    dashboard.php
    logout.php
    attendance_add.php
    assessment_add.php
    final_result_add.php
    result_card.php
    report_view.php
    messages.php
    students.php
```

## Notes
- Passwords use `password_hash`/`password_verify` (bcrypt). The seed uses a known bcrypt hash for the string `password`.
- For production, change credentials, create a non-root DB user, and serve behind Apache/Nginx.