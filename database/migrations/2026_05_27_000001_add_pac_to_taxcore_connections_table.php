<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxcore_connections', function (Blueprint $table) {
            $table->text('pac_encrypted')->after('certificate_password_encrypted');
        });
    }

    public function down(): void
    {
        Schema::table('taxcore_connections', function (Blueprint $table) {
            $table->dropColumn('pac_encrypted');
        });
    }
};
