<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name', 150)->nullable();
            $table->string('action', 60); // login|failed_login|logout|created|updated|deleted|settings_changed|...
            $table->string('module', 60)->nullable(); // vlog|article|settings|adsense|ads_txt|user|seo|page
            $table->string('model_type', 150)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('device', 100)->nullable();
            $table->dateTime('created_at');
            $table->index(['action', 'created_at']);
            $table->index(['module', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40); // failed_login|login|logout|locked|rate_limited|bot_blocked|suspicious|upload_rejected|csrf|unauthorized
            $table->string('severity', 10)->default('info'); // info|warning|critical
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 190)->nullable();
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('path', 500)->nullable();
            $table->json('details')->nullable();
            $table->dateTime('created_at');
            $table->index(['type', 'created_at']);
            $table->index(['severity', 'created_at']);
            $table->index('ip');
        });

        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 10)->default('error'); // info|warning|error|critical
            $table->string('type', 40); // 404|500|exception|api_failure|adsense_sync|gsc_sync|analytics_sync|job|sitemap|broken_link|storage
            $table->string('message', 1000);
            $table->string('url', 1000)->nullable();
            $table->string('referrer', 1000)->nullable();
            $table->json('context')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('last_seen_at')->nullable();
            $table->dateTime('created_at');
            $table->index(['type', 'created_at']);
            $table->index(['level', 'created_at']);
        });

        Schema::create('job_runs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('status', 20)->default('running'); // running|success|failed
            $table->dateTime('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('message')->nullable();
            $table->index(['name', 'started_at']);
        });

        Schema::create('broken_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('source_url', 1000);
            $table->string('target_url', 1000);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('error', 255)->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->index('is_resolved');
        });

        Schema::create('adsense_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->string('dimension_type', 30); // date|country|platform|ad_unit
            $table->string('dimension_value', 190)->default('_');
            $table->unsignedBigInteger('page_views')->default(0);
            $table->unsignedBigInteger('ad_requests')->default(0);
            $table->unsignedBigInteger('matched_ad_requests')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('ctr', 10, 6)->default(0);
            $table->decimal('cpc', 12, 6)->default(0);
            $table->decimal('page_rpm', 12, 6)->default(0);
            $table->decimal('impression_rpm', 12, 6)->default(0);
            $table->decimal('ad_request_rpm', 12, 6)->default(0);
            $table->decimal('viewability', 10, 6)->default(0);
            $table->decimal('earnings', 14, 6)->default(0);
            $table->string('currency', 5)->default('USD');
            $table->timestamp('synced_at')->nullable();
            $table->unique(['report_date', 'dimension_type', 'dimension_value'], 'adsense_reports_unique');
            $table->index(['dimension_type', 'report_date']);
        });

        Schema::create('search_console_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->string('dimension_type', 30); // date|query|page|country|device
            $table->string('dimension_value', 500)->default('_');
            $table->string('dimension_hash', 40); // md5 of value for unique index
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->decimal('ctr', 10, 6)->default(0);
            $table->decimal('position', 10, 4)->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->unique(['report_date', 'dimension_type', 'dimension_hash'], 'gsc_reports_unique');
            $table->index(['dimension_type', 'report_date']);
        });

        Schema::create('google_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('service', 30)->unique(); // adsense|search_console
            $table->text('access_token')->nullable();   // encrypted
            $table->text('refresh_token')->nullable();  // encrypted
            $table->timestamp('expires_at')->nullable();
            $table->text('scopes')->nullable();
            $table->string('account_id', 150)->nullable(); // adsense account name or GSC site url
            $table->string('account_label', 255)->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_status', 20)->nullable(); // ok|failed
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40); // milestone|scheduled_published|api_failure|sitemap|broken_page|adsense_sync|security
            $table->string('severity', 10)->default('info');
            $table->string('title', 255);
            $table->text('message')->nullable();
            $table->json('data')->nullable();
            $table->string('link', 500)->nullable();
            $table->boolean('is_read')->default(false);
            $table->dateTime('created_at');
            $table->index(['is_read', 'created_at']);
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // database|media
            $table->string('filename', 255);
            $table->string('disk', 30)->default('local');
            $table->string('path', 500);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status', 20)->default('completed');
            $table->string('trigger', 20)->default('manual'); // manual|scheduled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('rate_limit_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('ip_hash', 64);
            $table->string('reason', 100);
            $table->dateTime('blocked_until');
            $table->timestamps();
            $table->index(['ip_hash', 'blocked_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_limit_blocks');
        Schema::dropIfExists('backups');
        Schema::dropIfExists('admin_notifications');
        Schema::dropIfExists('google_tokens');
        Schema::dropIfExists('search_console_reports');
        Schema::dropIfExists('adsense_reports');
        Schema::dropIfExists('broken_links');
        Schema::dropIfExists('job_runs');
        Schema::dropIfExists('system_logs');
        Schema::dropIfExists('security_logs');
        Schema::dropIfExists('admin_logs');
    }
};
