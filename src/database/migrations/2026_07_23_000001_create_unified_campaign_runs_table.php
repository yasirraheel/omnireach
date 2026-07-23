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
        Schema::create('unified_campaign_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('unified_campaigns')->onDelete('cascade');
            $table->unsignedInteger('run_number')->default(1);
            $table->string('status', 50)->default('completed');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('next_schedule_at')->nullable();
            $table->unsignedInteger('total_contacts')->default(0);
            $table->unsignedInteger('processed_contacts')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->longText('dispatch_history')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unified_campaign_runs');
    }
};
