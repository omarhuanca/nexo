<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hashes all existing plain-text connector tokens using SHA-256.
 *
 * WARNING: After this migration runs, any connector token that was previously
 * issued in plain text will no longer work. Affected connectors must be
 * regenerated (DELETE + re-create via POST /api/connectors) to obtain a new
 * token.
 *
 * This migration is NOT reversible because the original plain-text values
 * cannot be recovered from their hashes.
 */
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
