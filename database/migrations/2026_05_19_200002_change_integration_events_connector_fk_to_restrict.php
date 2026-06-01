<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_events', function (Blueprint $table) {
            $table->dropForeign(['connector_id']);
            $table->foreign('connector_id')
                ->references('id')
                ->on('connectors')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('integration_events', function (Blueprint $table) {
            $table->dropForeign(['connector_id']);
            $table->foreign('connector_id')
                ->references('id')
                ->on('connectors')
                ->onDelete('cascade');
        });
    }
};
