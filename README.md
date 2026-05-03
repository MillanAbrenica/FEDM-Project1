# DataClean Pro

Data Cleaning and Analytics System for BAT403 - Foundations of Enterprise Data Management.

## Setup (Database-enabled)

1. Install PHP 8.x and Composer.
2. Create MySQL database `dataclean_pro` and import your SQL schema (the provided `dataclean_pro.sql`).
3. In project root, run `composer install`.
4. Start one of these:
   - Built-in PHP server: `php -S localhost:8000`
   - Or XAMPP Apache (recommended for this folder under `htdocs`)
5. Open:
   - Built-in server: `http://localhost:8000/index.php`
   - XAMPP Apache: `http://localhost/FEDM%20Project/index.php`
6. Upload a `.csv` or `.xlsx` file and follow the workflow: Upload -> Profile -> Clean -> Analyze -> Visualize.

## Database Connection Defaults

The app now persists uploads, profiles, cleaning jobs, datasets, and activity logs into MySQL.

Default connection values are:

- Host: `127.0.0.1`
- Port: `3306`
- Database: `dataclean_pro`
- User: `root`
- Password: _(empty)_

You can override them with environment variables:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
