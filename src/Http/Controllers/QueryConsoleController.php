<?php

namespace Glaucojrcarvalho\SqlConsole\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class QueryConsoleController extends Controller
{
    private function render(array $data)
    {
        $layout = config('sql-console.layout');
        $view = $layout ? 'sql-console::index-app' : 'sql-console::index-standalone';

        return view($view, array_merge($data, ['layout' => $layout]));
    }

    public function index(Request $request)
    {
        $this->authorizeAccess();

        $connections = $this->availableConnections();
        $defaultConnection = array_key_first($connections) ?? config('database.default');
        $requestedConnection = (string)$request->query('connection', '');
        $selectedConnection = array_key_exists($requestedConnection, $connections)
            ? $requestedConnection
            : old('connection', $defaultConnection);
        $tableCatalog = $this->tableCatalog($selectedConnection);

        return $this->render([
            'connections' => $connections,
            'selectedConnection' => $selectedConnection,
            'tables' => $tableCatalog['tables'],
            'tablesError' => $tableCatalog['error'],
            'canTruncate' => config('sql-console.allow_truncate', true) && $this->canWrite(),
            'sql' => old('sql', "SELECT *\nFROM users\nWHERE id = :id"),
            'bindings' => old('bindings', "{\n  \"id\": 1\n}"),
            'result' => null,
            'errorMessage' => null,
        ]);
    }

    public function tables(Request $request)
    {
        $this->authorizeAccess();

        $connections = $this->availableConnections();
        $validated = $request->validate([
            'connection' => 'required|string|in:'.implode(',', array_keys($connections)),
        ]);

        $catalog = $this->tableCatalog($validated['connection']);

        if ($catalog['error'] !== null) {
            return response()->json(['message' => $catalog['error']], 422);
        }

        return response()->json(['tables' => $catalog['tables']]);
    }

    public function run(Request $request)
    {
        $this->authorizeAccess();

        $connections = $this->availableConnections();

        $validated = $request->validate([
            'connection' => 'required|string|in:'.implode(',', array_keys($connections)),
            'sql' => 'required|string|max:50000',
            'bindings' => 'nullable|string|max:50000',
            'confirm_write' => 'nullable|string|in:1',
        ]);

        $sql = trim((string)$validated['sql']);
        $bindingsRaw = (string)($validated['bindings'] ?? '');

        try {
            $normalizedSql = $this->normalizeSql($sql);
            $statementType = $this->detectStatementType($normalizedSql);

            $writeTypes = $this->writeStatementTypes();
            $isWriteStatement = in_array($statementType, $writeTypes, true);

            if ($isWriteStatement && !$this->canWrite()) {
                throw ValidationException::withMessages([
                    'sql' => 'Your user is not allowed to execute write statements in SQL Console.',
                ]);
            }

            if ($isWriteStatement && config('sql-console.require_write_confirmation', true) && ($validated['confirm_write'] ?? null) !== '1') {
                throw ValidationException::withMessages([
                    'confirm_write' => 'To run a write statement you must check "I confirm this write query".',
                ]);
            }

            $bindings = $this->parseBindings($bindingsRaw);
            $connection = DB::connection($validated['connection']);

            $readTypes = config('sql-console.read_statement_types', ['select', 'with', 'show', 'describe', 'desc']);
            if (in_array($statementType, $readTypes, true)) {
                $rowsRaw = $connection->select($normalizedSql, $bindings);
                $rows = array_map(static fn ($row) => (array)$row, $rowsRaw);
                $totalRows = count($rows);
                $maxRows = (int)config('sql-console.max_rows', 200);
                $rows = array_slice($rows, 0, $maxRows);

                $result = [
                    'type' => 'rows',
                    'statementType' => $statementType,
                    'totalRows' => $totalRows,
                    'displayedRows' => count($rows),
                    'truncated' => $totalRows > $maxRows,
                    'rows' => $rows,
                    'columns' => isset($rows[0]) ? array_keys($rows[0]) : [],
                ];
            } else {
                $affectedRows = $connection->affectingStatement($normalizedSql, $bindings);

                $result = [
                    'type' => 'write',
                    'statementType' => $statementType,
                    'affectedRows' => $affectedRows,
                ];
            }

            return $this->render([
                'connections' => $connections,
                'selectedConnection' => $validated['connection'],
                ...$this->tableViewData($validated['connection']),
                'sql' => $sql,
                'bindings' => $bindingsRaw,
                'result' => $result,
                'errorMessage' => null,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            return $this->render([
                'connections' => $connections,
                'selectedConnection' => $validated['connection'],
                ...$this->tableViewData($validated['connection']),
                'sql' => $sql,
                'bindings' => $bindingsRaw,
                'result' => null,
                'errorMessage' => $e->getMessage(),
            ]);
        }
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::check(), 403);

        $mode = (string)config('sql-console.authorization_mode', 'role');
        if ($mode === 'any_auth') {
            return;
        }

        if ($mode === 'allowlist') {
            abort_unless($this->resolveAllowlistEntry() !== null, 403);
            return;
        }

        $requiredRoleId = config('sql-console.manager_role_id');
        if ($requiredRoleId === null) {
            return;
        }

        $roleField = (string)config('sql-console.role_field', 'role_id');
        abort_unless((int)data_get(Auth::user(), $roleField) === (int)$requiredRoleId, 403);
    }

    private function canWrite(): bool
    {
        $mode = (string)config('sql-console.authorization_mode', 'role');
        if ($mode !== 'allowlist') {
            return true;
        }

        $entry = $this->resolveAllowlistEntry();
        return $entry !== null && (bool)($entry->{config('sql-console.allowlist.can_write_column', 'can_write')} ?? false);
    }

    private function resolveAllowlistEntry(): ?object
    {
        $table = (string)config('sql-console.allowlist.table', 'sql_console_allowed_users');
        $userField = (string)config('sql-console.allowlist.user_identifier_field', 'id');
        $userColumn = (string)config('sql-console.allowlist.user_identifier_column', 'user_identifier');
        $activeColumn = (string)config('sql-console.allowlist.active_column', 'is_active');

        if (!Schema::hasTable($table)) {
            abort(500, "SQL Console allowlist table [{$table}] not found. Publish and run sql-console migrations.");
        }

        $identifier = data_get(Auth::user(), $userField);
        if ($identifier === null) {
            return null;
        }

        return DB::table($table)
            ->where($userColumn, (string)$identifier)
            ->where($activeColumn, true)
            ->first();
    }

    private function availableConnections(): array
    {
        $configured = config('sql-console.connections', []);
        $all = config('database.connections', []);

        $connections = [];
        foreach ($configured as $key => $label) {
            if (isset($all[$key])) {
                $connections[$key] = $label;
            }
        }

        return $connections;
    }

    private function tableViewData(string $connectionName): array
    {
        $catalog = $this->tableCatalog($connectionName);

        return [
            'tables' => $catalog['tables'],
            'tablesError' => $catalog['error'],
            'canTruncate' => config('sql-console.allow_truncate', true) && $this->canWrite(),
        ];
    }

    private function tableCatalog(string $connectionName): array
    {
        try {
            $connection = DB::connection($connectionName);
            $grammar = $connection->getQueryGrammar();
            $tableNames = $connection->getSchemaBuilder()->getTableListing();
            natcasesort($tableNames);

            $tables = array_map(static fn (string $table): array => [
                'name' => $table,
                'quoted' => $grammar->wrapTable($table),
            ], array_values($tableNames));

            return ['tables' => $tables, 'error' => null];
        } catch (Throwable $e) {
            return ['tables' => [], 'error' => $e->getMessage()];
        }
    }

    private function parseBindings(string $bindingsRaw): array
    {
        $trimmed = trim($bindingsRaw);
        if ($trimmed === '') {
            return [];
        }

        $bindings = json_decode($trimmed, true);
        if (!is_array($bindings)) {
            throw ValidationException::withMessages([
                'bindings' => 'Bindings must be a valid JSON object or array.',
            ]);
        }

        return $bindings;
    }

    private function normalizeSql(string $sql): string
    {
        $clean = preg_replace('/\/\*.*?\*\//s', ' ', $sql);
        $clean = preg_replace('/--[^\r\n]*/', ' ', $clean);
        $clean = preg_replace('/#[^\r\n]*/', ' ', $clean);
        $clean = trim((string)$clean);

        if ($clean === '') {
            throw ValidationException::withMessages([
                'sql' => 'SQL cannot be empty.',
            ]);
        }

        $clean = rtrim($clean, " \t\n\r\0\x0B;");
        if (str_contains($clean, ';')) {
            throw ValidationException::withMessages([
                'sql' => 'Only one SQL statement is allowed per execution.',
            ]);
        }

        $blockedKeywords = config('sql-console.blocked_keywords', ['drop', 'alter', 'create', 'grant', 'revoke', 'rename']);
        if (config('sql-console.allow_truncate', true)) {
            $blockedKeywords = array_values(array_filter(
                $blockedKeywords,
                static fn (string $keyword): bool => strtolower($keyword) !== 'truncate'
            ));
        }

        if (!empty($blockedKeywords) && preg_match('/\b('.implode('|', array_map('preg_quote', $blockedKeywords)).')\b/i', $clean) === 1) {
            throw ValidationException::withMessages([
                'sql' => 'This console does not allow DDL/admin statements.',
            ]);
        }

        return $clean;
    }

    private function detectStatementType(string $sql): string
    {
        preg_match('/^\s*([a-zA-Z]+)/', $sql, $matches);
        $type = strtolower($matches[1] ?? '');

        $allowed = array_merge(
            config('sql-console.read_statement_types', []),
            $this->writeStatementTypes()
        );

        if (!in_array($type, $allowed, true)) {
            throw ValidationException::withMessages([
                'sql' => 'Allowed statements: '.strtoupper(implode(', ', $allowed)).'.',
            ]);
        }

        return $type;
    }

    private function writeStatementTypes(): array
    {
        $types = config('sql-console.write_statement_types', ['insert', 'update', 'delete']);
        if (!config('sql-console.allow_truncate', true)) {
            return array_values(array_filter(
                $types,
                static fn (string $type): bool => strtolower($type) !== 'truncate'
            ));
        }

        if (!in_array('truncate', $types, true)) {
            $types[] = 'truncate';
        }

        return $types;
    }
}
