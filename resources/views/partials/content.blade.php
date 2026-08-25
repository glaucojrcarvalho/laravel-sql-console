<div class="card">
    <div class="card-header">
        <h3 class="card-title">SQL Console</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <strong>Restricted tool:</strong> use with caution. Only one SQL statement is allowed per run.
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.sql.console.run') }}">
            @csrf

            <div class="mb-5">
                <label class="form-label">Connection</label>
                <select class="form-select" id="sql-console-connection" name="connection" required>
                    @foreach($connections as $key => $label)
                        <option value="{{ $key }}" @selected($selectedConnection === $key)>{{ $label }} ({{ $key }})</option>
                    @endforeach
                </select>
            </div>

            <div class="card border mb-5">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
                    <div>
                        <h4 class="mb-0">Database Tables</h4>
                        <small class="text-muted">Choose a table to create a safe query template.</small>
                    </div>
                    <input class="form-control form-control-sm" id="sql-console-table-search" type="search" placeholder="Filter tables..." style="max-width: 240px">
                </div>
                <div class="card-body p-0">
                    <div id="sql-console-tables" data-can-truncate="{{ $canTruncate ? '1' : '0' }}" style="max-height: 320px; overflow-y: auto">
                        @if($tablesError)
                            <div class="alert alert-danger m-3 mb-0">Could not load tables: {{ $tablesError }}</div>
                        @elseif(count($tables) === 0)
                            <div class="text-muted p-3">No tables found on this connection.</div>
                        @else
                            <div class="list-group list-group-flush">
                                @foreach($tables as $table)
                                    <div class="list-group-item sql-console-table-row d-flex align-items-center justify-content-between gap-3" data-table-name="{{ strtolower($table['name']) }}">
                                        <code class="text-break">{{ $table['name'] }}</code>
                                        <div class="btn-group btn-group-sm flex-shrink-0">
                                            <button class="btn btn-outline-primary sql-console-template" type="button" data-action="select" data-table="{{ $table['quoted'] }}">Select</button>
                                            @if($canTruncate)
                                                <button class="btn btn-outline-danger sql-console-template" type="button" data-action="truncate" data-table="{{ $table['quoted'] }}">Truncate</button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mb-5">
                <label class="form-label">SQL Template</label>
                <textarea class="form-control font-monospace" id="sql-console-query" rows="10" name="sql" required>{{ $sql }}</textarea>
                <div class="form-text">Use placeholders like <code>:id</code>, <code>:status</code>.</div>
            </div>

            <div class="mb-5">
                <label class="form-label">Bindings (JSON)</label>
                <textarea class="form-control font-monospace" rows="6" name="bindings">{{ $bindings }}</textarea>
                <div class="form-text">Example: <code>{"id": 123, "status": 2}</code></div>
            </div>

            <div class="form-check form-check-custom form-check-solid mb-5">
                <input class="form-check-input" type="checkbox" value="1" id="confirm_write" name="confirm_write" @checked(old('confirm_write') === '1') />
                <label class="form-check-label" for="confirm_write">
                    I confirm this write query (required for INSERT/UPDATE/DELETE/TRUNCATE).
                </label>
            </div>

            <button class="btn btn-primary" type="submit">Run Query</button>
        </form>
    </div>
</div>

@if($errorMessage)
    <div class="card mt-5">
        <div class="card-header">
            <h3 class="card-title text-danger">Execution Error</h3>
        </div>
        <div class="card-body">
            <pre class="mb-0">{{ $errorMessage }}</pre>
        </div>
    </div>
@endif

<script>
(() => {
    const connection = document.getElementById('sql-console-connection');
    const container = document.getElementById('sql-console-tables');
    const search = document.getElementById('sql-console-table-search');
    const query = document.getElementById('sql-console-query');
    const tablesUrl = @json(route('admin.sql.console.tables'));
    const canTruncate = container?.dataset.canTruncate === '1';

    if (!connection || !container || !search || !query) {
        return;
    }

    const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    })[character]);

    const renderTables = (tables) => {
        if (tables.length === 0) {
            container.innerHTML = '<div class="text-muted p-3">No tables found on this connection.</div>';
            return;
        }

        container.innerHTML = '<div class="list-group list-group-flush">' + tables.map(table => {
            const name = escapeHtml(table.name);
            const quoted = escapeHtml(table.quoted);
            const truncateButton = canTruncate
                ? `<button class="btn btn-outline-danger sql-console-template" type="button" data-action="truncate" data-table="${quoted}">Truncate</button>`
                : '';

            return `<div class="list-group-item sql-console-table-row d-flex align-items-center justify-content-between gap-3" data-table-name="${name.toLowerCase()}">
                <code class="text-break">${name}</code>
                <div class="btn-group btn-group-sm flex-shrink-0">
                    <button class="btn btn-outline-primary sql-console-template" type="button" data-action="select" data-table="${quoted}">Select</button>
                    ${truncateButton}
                </div>
            </div>`;
        }).join('') + '</div>';
    };

    connection.addEventListener('change', async () => {
        container.innerHTML = '<div class="text-muted p-3">Loading tables...</div>';
        search.value = '';

        try {
            const response = await fetch(`${tablesUrl}?connection=${encodeURIComponent(connection.value)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Could not load tables.');
            }
            renderTables(data.tables || []);
        } catch (error) {
            container.innerHTML = `<div class="alert alert-danger m-3 mb-0">${escapeHtml(error.message)}</div>`;
        }
    });

    search.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        container.querySelectorAll('.sql-console-table-row').forEach(row => {
            row.style.display = row.dataset.tableName.includes(term) ? '' : 'none';
        });
    });

    container.addEventListener('click', event => {
        const button = event.target.closest('.sql-console-template');
        if (!button) {
            return;
        }

        query.value = button.dataset.action === 'truncate'
            ? `TRUNCATE TABLE ${button.dataset.table}`
            : `SELECT *\nFROM ${button.dataset.table}`;
        query.focus();
    });
})();
</script>

@if($result)
    <div class="card mt-5">
        <div class="card-header">
            <h3 class="card-title">Result</h3>
        </div>
        <div class="card-body">
            @if($result['type'] === 'write')
                <div class="alert alert-info mb-0">
                    Statement: <strong>{{ strtoupper($result['statementType']) }}</strong><br>
                    @if($result['statementType'] === 'truncate')
                        Table truncated successfully.
                    @else
                        Affected rows: <strong>{{ $result['affectedRows'] }}</strong>
                    @endif
                </div>
            @else
                <div class="mb-4">
                    Statement: <strong>{{ strtoupper($result['statementType']) }}</strong><br>
                    Rows returned: <strong>{{ $result['totalRows'] }}</strong>
                    @if($result['truncated'])
                        <br>Showing first <strong>{{ $result['displayedRows'] }}</strong> rows.
                    @endif
                </div>

                @if(count($result['rows']) === 0)
                    <div class="alert alert-secondary mb-0">Query executed successfully, no rows returned.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped table-row-bordered gy-5 gs-7">
                            <thead>
                            <tr class="fw-semibold fs-6 text-gray-800">
                                @foreach($result['columns'] as $column)
                                    <th>{{ $column }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($result['rows'] as $row)
                                <tr>
                                    @foreach($result['columns'] as $column)
                                        <td>{{ is_scalar($row[$column]) || $row[$column] === null ? $row[$column] : json_encode($row[$column]) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    </div>
@endif
