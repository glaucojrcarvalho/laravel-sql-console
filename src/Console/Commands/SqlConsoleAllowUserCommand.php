<?php

namespace Glaucojrcarvalho\SqlConsole\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SqlConsoleAllowUserCommand extends Command
{
    protected $signature = 'sql-console:allow
                            {user_identifier : Value of configured user identifier (id by default)}
                            {--write : Allow INSERT/UPDATE/DELETE}
                            {--inactive : Insert as inactive (default is active)}';

    protected $description = 'Allow a user to access SQL Console without using a database client.';

    public function handle(): int
    {
        $table = (string)config('sql-console.allowlist.table', 'sql_console_allowed_users');
        $idColumn = (string)config('sql-console.allowlist.user_identifier_column', 'user_identifier');
        $activeColumn = (string)config('sql-console.allowlist.active_column', 'is_active');
        $writeColumn = (string)config('sql-console.allowlist.can_write_column', 'can_write');

        if (!Schema::hasTable($table)) {
            $this->error("Table [{$table}] not found. Run package migration first.");
            return self::FAILURE;
        }

        $identifier = (string)$this->argument('user_identifier');
        $active = !$this->option('inactive');
        $canWrite = (bool)$this->option('write');

        DB::table($table)->updateOrInsert(
            [$idColumn => $identifier],
            [
                $activeColumn => $active,
                $writeColumn => $canWrite,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->info("User [{$identifier}] allowed in SQL Console. active=".($active ? '1' : '0').", can_write=".($canWrite ? '1' : '0'));

        return self::SUCCESS;
    }
}
