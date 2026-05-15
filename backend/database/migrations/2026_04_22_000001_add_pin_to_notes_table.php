<?php
// a:\toolweb\xampp\htdocs\note-manage\backend\database\migrations\2026_04_22_000001_add_pin_to_notes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->boolean('is_pinned')->default(false)->after('color');
            $table->timestamp('pinned_at')->nullable()->after('is_pinned');
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn(['is_pinned', 'pinned_at']);
        });
    }
};
