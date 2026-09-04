<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Anonymous visitor (cookie-based random id, never raw IP)
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_key', 64)->unique();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('sessions_count')->default(0);
            $table->string('country', 2)->nullable();
            $table->string('device_type', 20)->nullable();
            $table->string('browser', 50)->nullable();
            $table->string('os', 50)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->timestamps();
            $table->index('last_seen_at');
        });

        Schema::create('analytics_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 64)->unique();
            $table->foreignId('visitor_id')->nullable()->constrained('visitors')->nullOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('last_activity_at');
            $table->unsignedInteger('duration')->default(0); // seconds
            $table->unsignedInteger('engagement_time')->default(0); // seconds actively engaged
            $table->unsignedInteger('page_views')->default(0);
            $table->string('landing_page', 500)->nullable();
            $table->string('exit_page', 500)->nullable();
            $table->string('referrer', 1000)->nullable();
            $table->string('referrer_host', 190)->nullable();
            $table->string('source', 100)->nullable();   // direct|google|facebook|...
            $table->string('medium', 50)->nullable();    // organic|referral|social|cpc|email|none
            $table->string('campaign', 150)->nullable();
            $table->string('utm_source', 150)->nullable();
            $table->string('utm_medium', 150)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('device_type', 20)->nullable(); // desktop|tablet|mobile
            $table->string('browser', 50)->nullable();
            $table->string('browser_version', 30)->nullable();
            $table->string('os', 50)->nullable();
            $table->string('os_version', 30)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('city', 100)->nullable();
            $table->boolean('is_returning')->default(false);
            $table->boolean('is_bot')->default(false);
            $table->string('bot_name', 100)->nullable();
            $table->string('ip_hash', 64)->nullable(); // salted daily hash, rotated by retention job
            $table->timestamps();
            $table->index('started_at');
            $table->index(['is_bot', 'started_at']);
            $table->index('last_activity_at');
            $table->index('source');
            $table->index('country');
        });

        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('analytics_sessions')->cascadeOnDelete();
            $table->foreignId('visitor_id')->nullable()->constrained('visitors')->nullOnDelete();
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('page_type', 30)->default('other'); // home|vlog|article|category|tag|author|page|search|listing|other
            $table->string('path', 500);
            $table->string('title', 255)->nullable();
            $table->string('referrer', 1000)->nullable();
            $table->unsignedInteger('engagement_time')->default(0);
            $table->unsignedTinyInteger('scroll_depth')->default(0);
            $table->boolean('is_bot')->default(false);
            $table->dateTime('viewed_at');
            $table->index(['viewed_at', 'is_bot']);
            $table->index(['post_id', 'viewed_at']);
            $table->index('path');
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->nullable()->constrained('analytics_sessions')->cascadeOnDelete();
            $table->foreignId('visitor_id')->nullable()->constrained('visitors')->nullOnDelete();
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('event_type', 40); // share|outbound_click|scroll|search|consent|...
            $table->string('event_value', 500)->nullable();
            $table->json('event_data')->nullable();
            $table->string('path', 500)->nullable();
            $table->dateTime('created_at');
            $table->index(['event_type', 'created_at']);
            $table->index(['post_id', 'event_type']);
        });

        Schema::create('video_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->nullable()->constrained('analytics_sessions')->cascadeOnDelete();
            $table->foreignId('visitor_id')->nullable()->constrained('visitors')->nullOnDelete();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('event', 20); // start|p25|p50|p75|p90|complete|heartbeat
            $table->string('provider', 20)->nullable();
            $table->unsignedInteger('watch_seconds')->default(0); // for heartbeat: seconds watched since last beat
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('duration')->default(0);
            $table->string('play_id', 40)->nullable(); // per-play unique id to dedupe milestones
            $table->dateTime('created_at');
            $table->index(['post_id', 'event', 'created_at']);
            $table->index(['play_id', 'event']);
        });

        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query', 255);
            $table->string('query_normalized', 255);
            $table->unsignedInteger('results_count')->default(0);
            $table->foreignId('session_id')->nullable()->constrained('analytics_sessions')->nullOnDelete();
            $table->foreignId('visitor_id')->nullable()->constrained('visitors')->nullOnDelete();
            $table->boolean('is_bot')->default(false);
            $table->dateTime('created_at');
            $table->index(['query_normalized', 'created_at']);
            $table->index(['results_count', 'created_at']);
        });

        // Realtime widget (short-lived rows, pruned every few minutes)
        Schema::create('realtime_visitors', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 64)->unique();
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('path', 500);
            $table->string('title', 255)->nullable();
            $table->string('page_type', 30)->nullable();
            $table->string('device_type', 20)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('source', 100)->nullable();
            $table->dateTime('last_seen_at');
            $table->index('last_seen_at');
        });

        // Daily aggregates for fast dashboards
        Schema::create('analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedBigInteger('page_views')->default(0);
            $table->unsignedBigInteger('unique_visitors')->default(0);
            $table->unsignedBigInteger('sessions')->default(0);
            $table->unsignedBigInteger('new_visitors')->default(0);
            $table->unsignedBigInteger('returning_visitors')->default(0);
            $table->unsignedBigInteger('total_duration')->default(0);
            $table->unsignedBigInteger('total_engagement')->default(0);
            $table->unsignedBigInteger('bounces')->default(0);
            $table->unsignedBigInteger('engaged_sessions')->default(0);
            $table->unsignedBigInteger('vlog_views')->default(0);
            $table->unsignedBigInteger('article_views')->default(0);
            $table->unsignedBigInteger('video_plays')->default(0);
            $table->unsignedBigInteger('video_unique_viewers')->default(0);
            $table->unsignedBigInteger('video_completes')->default(0);
            $table->unsignedBigInteger('watch_time')->default(0);
            $table->unsignedBigInteger('searches')->default(0);
            $table->unsignedBigInteger('zero_result_searches')->default(0);
            $table->unsignedBigInteger('shares')->default(0);
            $table->unsignedBigInteger('outbound_clicks')->default(0);
            $table->unsignedBigInteger('bot_views')->default(0);
            $table->unsignedBigInteger('bot_sessions')->default(0);
            $table->decimal('avg_scroll_depth', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('analytics_dimension_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('dimension', 30); // country|city|device|browser|os|source|medium|referrer|landing|exit|utm_source|utm_medium|utm_campaign|page_type
            $table->string('value', 190);
            $table->unsignedBigInteger('page_views')->default(0);
            $table->unsignedBigInteger('sessions')->default(0);
            $table->unsignedBigInteger('visitors')->default(0);
            $table->unsignedBigInteger('duration')->default(0);
            $table->unique(['date', 'dimension', 'value']);
            $table->index(['dimension', 'date']);
        });

        Schema::create('content_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('unique_views')->default(0);
            $table->unsignedBigInteger('engagement_time')->default(0);
            $table->decimal('avg_scroll_depth', 5, 2)->default(0);
            $table->unsignedBigInteger('video_starts')->default(0);
            $table->unsignedBigInteger('video_unique_viewers')->default(0);
            $table->unsignedBigInteger('p25')->default(0);
            $table->unsignedBigInteger('p50')->default(0);
            $table->unsignedBigInteger('p75')->default(0);
            $table->unsignedBigInteger('p90')->default(0);
            $table->unsignedBigInteger('completes')->default(0);
            $table->unsignedBigInteger('watch_time')->default(0);
            $table->unsignedBigInteger('shares')->default(0);
            $table->unsignedBigInteger('outbound_clicks')->default(0);
            $table->unique(['date', 'post_id']);
            $table->index('date');
        });

        Schema::create('content_dimension_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('dimension', 30); // country|device|source
            $table->string('value', 190);
            $table->unsignedBigInteger('views')->default(0);
            $table->unique(['date', 'post_id', 'dimension', 'value'], 'cdd_unique');
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->string('consent_key', 64)->unique();
            $table->boolean('necessary')->default(true);
            $table->boolean('analytics')->default(false);
            $table->boolean('advertising')->default(false);
            $table->boolean('personalization')->default(false);
            $table->string('region', 10)->nullable();
            $table->string('method', 30)->nullable(); // banner|preferences|tcf
            $table->text('tc_string')->nullable(); // IAB TCF string if a CMP is used
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
        Schema::dropIfExists('content_dimension_daily');
        Schema::dropIfExists('content_daily');
        Schema::dropIfExists('analytics_dimension_daily');
        Schema::dropIfExists('analytics_daily');
        Schema::dropIfExists('realtime_visitors');
        Schema::dropIfExists('search_logs');
        Schema::dropIfExists('video_events');
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('page_views');
        Schema::dropIfExists('analytics_sessions');
        Schema::dropIfExists('visitors');
    }
};
