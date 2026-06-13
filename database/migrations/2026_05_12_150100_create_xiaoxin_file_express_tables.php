<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('disk', 64)->default('local');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->string('extension', 32)->nullable();
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64)->nullable()->index();
            $table->string('extract_code_hash')->nullable();
            $table->boolean('has_extract_code')->default(false);
            $table->string('share_theme', 64)->default('default');
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('uploaded_at')->nullable();
            $table->softDeletes();
            $table->unsignedBigInteger('download_count')->default(0);
            $table->unsignedBigInteger('download_bytes')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();
            $table->string('uploader_ip', 45)->nullable()->index();
            $table->text('uploader_user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('file_downloads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->string('code', 16)->index();
            $table->string('ip', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->boolean('success')->default(false);
            $table->string('failure_reason')->nullable();
            $table->unsignedBigInteger('bytes')->default(0);
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('file_uploads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->string('ip', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime_type')->nullable();
            $table->boolean('success')->default(false);
            $table->string('failure_reason')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 64);
            $table->string('key', 128);
            $table->text('value')->nullable();
            $table->string('type', 32)->default('string');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['group', 'key']);
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('type', 32)->default('info');
            $table->integer('priority')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('start_at')->nullable()->index();
            $table->timestamp('end_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('lan_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('session_id', 64)->unique();
            $table->string('short_code', 16)->index();
            $table->string('transfer_kind', 32)->default('file');
            $table->string('status', 32)->default('waiting')->index();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedBigInteger('total_size')->default(0);
            $table->json('files_json')->nullable();
            $table->string('text_title')->nullable();
            $table->longText('text_content')->nullable();
            $table->text('text_preview')->nullable();
            $table->unsignedInteger('text_length')->default(0);
            $table->unsignedInteger('text_line_count')->default(0);
            $table->string('sender_token_hash')->nullable();
            $table->string('receiver_token_hash')->nullable();
            $table->boolean('receiver_joined')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('lan_signals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lan_session_id')->constrained('lan_sessions')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('role', 32)->default('sender');
            $table->unsignedInteger('sequence')->default(1);
            $table->json('payload_json')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['lan_session_id', 'type', 'role', 'sequence']);
        });

        Schema::create('blocked_ips', function (Blueprint $table): void {
            $table->id();
            $table->string('ip', 45)->index();
            $table->string('reason')->nullable();
            $table->string('scope', 32)->default('all')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('target_type')->nullable()->index();
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('health_checks', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 32)->default('healthy')->index();
            $table->unsignedInteger('response_time')->default(0);
            $table->unsignedInteger('db_response_time')->default(0);
            $table->string('storage_status', 32)->default('ok');
            $table->text('error_message')->nullable();
            $table->timestamp('checked_at')->nullable()->index();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('daily_stats', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedBigInteger('upload_count')->default(0);
            $table->unsignedBigInteger('download_count')->default(0);
            $table->unsignedBigInteger('upload_size')->default(0);
            $table->unsignedBigInteger('download_size')->default(0);
            $table->unsignedBigInteger('active_files')->default(0);
            $table->unsignedBigInteger('expired_files')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'daily_stats',
            'health_checks',
            'audit_logs',
            'blocked_ips',
            'lan_signals',
            'lan_sessions',
            'announcements',
            'settings',
            'file_uploads',
            'file_downloads',
            'files',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
