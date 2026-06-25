<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxcore_connections', function (Blueprint $table) {
            $table->longText('certificate_encrypted')->nullable()->change();
            $table->text('certificate_password_encrypted')->nullable()->change();
            $table->text('pac_encrypted')->nullable()->change();

            $table->timestamp('agent_last_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('taxcore_connections', function (Blueprint $table) {
            $table->dropColumn('agent_last_seen_at');
        });
    }
};
