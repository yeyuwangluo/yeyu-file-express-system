<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('risk_download_ack_logs')) {
            return;
        }

        Schema::create('risk_download_ack_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('file_id')->nullable()->index();
            $table->string('code', 32)->index();
            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->text('threat_summary')->nullable();
            $table->timestamp('scan_checked_at')->nullable();
            $table->timestamp('signature_expires_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_download_ack_logs');
    }
};
