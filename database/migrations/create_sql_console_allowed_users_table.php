<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sql_console_allowed_users', function (Blueprint $table) {
            $table->id();
            $table->string('user_identifier', 191)->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('can_write')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sql_console_allowed_users');
    }
};
