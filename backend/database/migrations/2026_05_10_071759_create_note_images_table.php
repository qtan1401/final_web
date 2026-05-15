<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('note_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained()->onDelete('cascade');
            $table->string('path');
            $table->timestamps();
        });

        // Migrate existing images
        $notes = DB::table('notes')->whereNotNull('image')->get();
        foreach ($notes as $note) {
            DB::table('note_images')->insert([
                'note_id'    => $note->id,
                'path'       => $note->image,
                'created_at' => $note->updated_at,
                'updated_at' => $note->updated_at,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('note_images');
    }
};
