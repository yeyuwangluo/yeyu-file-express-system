<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('virus_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_hash', 64);
            $table->enum('scan_status', ['pending', 'scanning', 'clean', 'infected', 'error'])->default('pending');
            $table->text('scan_result')->nullable();
            $table->string('engine_version')->nullable();
            $table->integer('scan_time_ms')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->index('file_id');
            $table->index('scan_status');
            $table->index('scanned_at');
            $table->index('file_hash');
        });
    }

    public function down()
    {
        Schema::dropIfExists('virus_scan_logs');
    }
};

