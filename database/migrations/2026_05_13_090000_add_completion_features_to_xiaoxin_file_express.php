<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            if (! Schema::hasColumn('files', 'scan_status')) {
                $table->string('scan_status', 32)->default('pending')->after('sha256')->index();
            }
            if (! Schema::hasColumn('files', 'scan_result')) {
                $table->text('scan_result')->nullable()->after('scan_status');
            }
            if (! Schema::hasColumn('files', 'scan_checked_at')) {
                $table->timestamp('scan_checked_at')->nullable()->after('scan_result');
            }
            if (! Schema::hasColumn('files', 'risk_score')) {
                $table->unsignedSmallInteger('risk_score')->default(0)->after('scan_checked_at')->index();
            }
            if (! Schema::hasColumn('files', 'risk_reasons_json')) {
                $table->json('risk_reasons_json')->nullable()->after('risk_score');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 32)->default('admin')->after('is_admin')->index();
            }
            if (! Schema::hasColumn('users', 'permissions_json')) {
                $table->json('permissions_json')->nullable()->after('role');
            }
        });

        Schema::create('chunked_uploads', function (Blueprint $table): void {
            $table->id();
            $table->string('upload_id', 64)->unique();
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->string('extension', 32)->nullable();
            $table->string('disk', 64)->default('local');
            $table->string('directory');
            $table->unsignedBigInteger('total_size');
            $table->unsignedBigInteger('chunk_size')->default(0);
            $table->unsignedInteger('total_chunks');
            $table->unsignedInteger('received_chunks')->default(0);
            $table->unsignedBigInteger('received_bytes')->default(0);
            $table->string('sha256', 64)->nullable();
            $table->string('extract_code_hash')->nullable();
            $table->boolean('has_extract_code')->default(false);
            $table->string('share_theme', 64)->default('default');
            $table->unsignedInteger('expire_days')->default(1);
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedSmallInteger('risk_score')->default(0);
            $table->json('risk_reasons_json')->nullable();
            $table->string('uploader_ip', 45)->nullable()->index();
            $table->text('uploader_user_agent')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('completed_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chunked_uploads');

        Schema::table('users', function (Blueprint $table): void {
            foreach (['permissions_json', 'role'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('files', function (Blueprint $table): void {
            foreach (['risk_reasons_json', 'risk_score', 'scan_checked_at', 'scan_result', 'scan_status'] as $column) {
                if (Schema::hasColumn('files', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
