<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integration_events', function (Blueprint $table) {
            $table->id();
            
            $table->uuid('external_event_id')->unique();
            $table->foreignId('connector_id')->constrained("connectors")->onDelete('cascade');
            $table->foreignId('organization_id')->constrained("organizations")->onDelete('cascade');
            $table->string('event_type');
            $table->jsonb('payload');
            $table->string('status')->default('pending');

            $table->integer('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('received_at')->useCurrent();
            
            $table->timestamps();

            $table->index([
                'organization_id',
                'status'
            ]);
            $table->index([
                'connector_id',
                'status'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_events');
    }
};