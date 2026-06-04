<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    public function up(): void
    {
        DB::table('connectors')->orderBy('id')->chunkById(100, function ($connectors) {
            foreach ($connectors as $connector) {
                DB::table('connectors')
                    ->where('id', $connector->id)
                    ->update(['token' => hash('sha256', $connector->token)]);
            }
        });
    }

    public function down(): void
    {
        // Intentionally empty: SHA-256 is one-way; original tokens cannot be restored.
    }
};
