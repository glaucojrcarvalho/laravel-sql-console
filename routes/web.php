<?php

use Glaucojrcarvalho\SqlConsole\Http\Controllers\QueryConsoleController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => config('sql-console.middleware', ['auth']),
    'prefix' => config('sql-console.route_prefix', 'admin/sql-console'),
    'as' => 'admin.sql.console.',
], static function () {
    Route::get('', [QueryConsoleController::class, 'index'])->name('index');
    Route::get('tables', [QueryConsoleController::class, 'tables'])->name('tables');
    Route::post('run', [QueryConsoleController::class, 'run'])->name('run');
});
