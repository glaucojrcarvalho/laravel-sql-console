# SQL Console for Laravel

`glaucojrcarvalho/sql-console` is a browser SQL console package for Laravel with safety guardrails.

## Features

- Single SQL statement per execution
- JSON bindings support (`:named` placeholders)
- Multi-connection support (MySQL, Oracle, and others you configure)
- Connection-aware table browser with query templates
- Read/write statement control
- Confirmed table truncation (can be disabled)
- Optional write confirmation checkbox
- Optional role restriction
- Standalone UI or embedded in your own app layout

## Requirements

- PHP 8.2+
- Laravel 10, 11 or 12

## Installation

### Option 1: Packagist (recommended after publish)

```bash
composer require glaucojrcarvalho/sql-console:^1.1
php artisan vendor:publish --tag=sql-console-config
```

### Option 2: Directly from GitHub

```bash
composer config repositories.glaucojrcarvalho-sql-console vcs https://github.com/glaucojrcarvalho/laravel-sql-console.git
composer require glaucojrcarvalho/sql-console:^1.1
php artisan vendor:publish --tag=sql-console-config
```

### Option 3: Local path (development)

Add to your project `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../packages/sql-console",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "glaucojrcarvalho/sql-console": "*"
  }
}
```

Then:

```bash
composer update glaucojrcarvalho/sql-console --ignore-platform-reqs
php artisan vendor:publish --tag=sql-console-config
```

## Routes

- `GET /admin/sql-console` (`admin.sql.console.index`)
- `GET /admin/sql-console/tables` (`admin.sql.console.tables`)
- `POST /admin/sql-console/run` (`admin.sql.console.run`)

## Configuration

Published file: `config/sql-console.php`

```php
return [
    'route_prefix' => 'admin/sql-console',
    'middleware' => ['web', 'auth'],
    'layout' => null, // ex: 'template.main'
    'authorization_mode' => 'role', // role | allowlist | any_auth
    'manager_role_id' => env('SQL_CONSOLE_MANAGER_ROLE_ID'),
    'role_field' => 'role_id',
    'allowlist' => [
        'table' => 'sql_console_allowed_users',
        'user_identifier_field' => 'id',
        'user_identifier_column' => 'user_identifier',
        'active_column' => 'is_active',
        'can_write_column' => 'can_write',
    ],
    'connections' => [
        'mysql' => 'MySQL',
        'oracle' => 'Oracle',
    ],
    'max_rows' => 200,
    'require_write_confirmation' => true,
    'allow_truncate' => true,
];
```

Notes:

- `authorization_mode=role` uses `manager_role_id`.
- `authorization_mode=allowlist` uses a DB table to decide who can access and write.
- `authorization_mode=any_auth` allows any authenticated user.
- Keep this route protected in production.
- Set `allow_truncate=false` to hide/disable truncation while keeping other writes available.

## Optional Allowlist Table

If you want per-user permissions (recommended for production/shared environments):

```bash
php artisan vendor:publish --tag=sql-console-migrations
php artisan migrate
```

Then insert allowed users in `sql_console_allowed_users`:

- `user_identifier`: matches your auth user field (default `users.id`)
- `is_active`: true/false
- `can_write`: true/false

Example:

```sql
INSERT INTO sql_console_allowed_users (user_identifier, is_active, can_write, created_at, updated_at)
VALUES ('1', 1, 0, NOW(), NOW());
```

### Manage Allowlist via Artisan (No Workbench)

Allow user (read-only):

```bash
php artisan sql-console:allow 1
```

Allow user with write permission:

```bash
php artisan sql-console:allow 1 --write
```

Revoke user (set inactive):

```bash
php artisan sql-console:revoke 1
```

Revoke user and delete row:

```bash
php artisan sql-console:revoke 1 --delete
```

## Security Notes

- This tool is intentionally restricted to one SQL statement at a time.
- Destructive DDL/admin keywords are blocked by default (`drop`, `alter`, etc.). `TRUNCATE` is treated as a write and requires write access and confirmation.
- Use least-privilege database credentials for any connection exposed here.
- Prefer `authorization_mode=allowlist` in production to explicitly control users and write access.

## Release Flow

1. Push package to `main`
2. Create a semantic tag (example: `v1.0.0`)
3. Register/update repository on Packagist
4. For each new release, push a new tag (`v1.0.1`, `v1.1.0`, etc.)

## Release Notes

- Do not define `version` in `composer.json`.
- Package versions are controlled by Git tags (`v1.1.3`, `v1.1.4`, ...).
- After pushing a new tag, trigger update on Packagist.

## Troubleshooting Packagist

If Packagist shows:

`tag (...) does not match version (...) in composer.json`

it means old tags were created when a fixed `version` field existed in `composer.json`.

Fix:

1. Ensure `composer.json` has no `version` field.
2. Commit and push.
3. Create a new tag (do not reuse old tags).
4. Update the package on Packagist.
