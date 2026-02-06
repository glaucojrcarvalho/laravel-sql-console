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
                <select class="form-select" name="connection" required>
                    @foreach($connections as $key => $label)
                        <option value="{{ $key }}" @selected($selectedConnection === $key)>{{ $label }} ({{ $key }})</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-5">
                <label class="form-label">SQL Template</label>
                <textarea class="form-control font-monospace" rows="10" name="sql" required>{{ $sql }}</textarea>
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
                    I confirm this write query (required for INSERT/UPDATE/DELETE).
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

@if($result)
    <div class="card mt-5">
        <div class="card-header">
            <h3 class="card-title">Result</h3>
        </div>
        <div class="card-body">
            @if($result['type'] === 'write')
                <div class="alert alert-info mb-0">
                    Statement: <strong>{{ strtoupper($result['statementType']) }}</strong><br>
                    Affected rows: <strong>{{ $result['affectedRows'] }}</strong>
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
