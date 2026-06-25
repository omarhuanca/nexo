<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxcore_connections', function (Blueprint $table) {
            $table->dropColumn([
                'certificate_encrypted',
                'certificate_password_encrypted',
                'pac_encrypted',
                'vsdc_url',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('taxcore_connections', function (Blueprint $table) {
            $table->longText('certificate_encrypted')->nullable();
            $table->text('certificate_password_encrypted')->nullable();
            $table->text('pac_encrypted')->nullable();
            $table->string('vsdc_url')->nullable();
        });
    }
};
