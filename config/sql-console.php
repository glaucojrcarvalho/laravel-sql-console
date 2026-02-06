<?php

return [
    'route_prefix' => 'admin/sql-console',
    'middleware' => ['web', 'auth'],

    // Set to a Blade layout name (example: 'template.main') to embed in app UI.
    // Set null to use package standalone page.
    'layout' => null,

    // Access mode: role | allowlist | any_auth
    'authorization_mode' => 'role',

    // Optional role-based protection. Used when authorization_mode=role.
    'manager_role_id' => env('SQL_CONSOLE_MANAGER_ROLE_ID'),
    'role_field' => 'role_id',

    // Allowlist-based protection. Used when authorization_mode=allowlist.
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

    'read_statement_types' => ['select', 'with', 'show', 'describe', 'desc'],
    'write_statement_types' => ['insert', 'update', 'delete'],

    'blocked_keywords' => ['drop', 'truncate', 'alter', 'create', 'grant', 'revoke', 'rename'],
];
