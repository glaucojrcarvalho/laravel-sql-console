<?php

namespace Glaucojrcarvalho\SqlConsole\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SqlConsoleRevokeUserCommand extends Command
{
    protected $signature = 'sql-console:revoke
                            {user_identifier : Value of configured user identifier (id by default)}
                            {--delete : Delete row instead of only disabling it}';

    protected $description = 'Revoke SQL Console access for a user.';

    public function handle(): int
    {
        $table = (string)config('sql-console.allowlist.table', 'sql_console_allowed_users');
        $idColumn = (string)config('sql-console.allowlist.user_identifier_column', 'user_identifier');
        $activeColumn = (string)config('sql-console.allowlist.active_column', 'is_active');

        if (!Schema::hasTable($table)) {
            $this->error("Table [{$table}] not found. Run package migration first.");
            return self::FAILURE;
        }

        $identifier = (string)$this->argument('user_identifier');

        if ($this->option('delete')) {
            $affected = DB::table($table)->where($idColumn, $identifier)->delete();
            $this->info("Deleted {$affected} row(s) for user [{$identifier}].");
            return self::SUCCESS;
        }

        $affected = DB::table($table)
            ->where($idColumn, $identifier)
            ->update([
                $activeColumn => false,
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            $this->warn("No row found for user [{$identifier}].");
            return self::SUCCESS;
        }

        $this->info("User [{$identifier}] revoked (set inactive).");
        return self::SUCCESS;
    }
}
