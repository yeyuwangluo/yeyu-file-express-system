<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ai_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id')->nullable();
            $table->string('filename');
            $table->boolean('is_malicious')->default(false);
            $table->string('threat_type')->nullable();
            $table->string('confidence')->nullable();
            $table->text('reason')->nullable();
            $table->text('suspicious_code')->nullable();
            $table->string('model')->nullable();
            $table->string('scanner')->default('ai');
            $table->boolean('skipped')->default(false);
            $table->timestamps();
            
            $table->index('file_id');
            $table->index('is_malicious');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_scan_logs');
    }
};
