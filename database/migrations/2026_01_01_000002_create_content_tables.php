<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 150);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->string('image', 500)->nullable();
            $table->string('seo_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('posts_count')->default(0);
            $table->timestamps();
            $table->index(['status', 'sort_order']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('posts_count')->default(0);
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk', 50)->default('public');
            $table->string('path', 500);
            $table->string('filename', 255);
            $table->string('original_name', 255);
            $table->string('mime', 100);
            $table->string('extension', 20);
            $table->string('type', 20)->default('image'); // image|video|file
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->string('alt', 255)->nullable();
            $table->string('title', 255)->nullable();
            $table->json('variants')->nullable();
            $table->timestamps();
            $table->index(['type', 'created_at']);
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('vlog'); // vlog|article
            $table->string('title', 255);
            $table->string('seo_title', 255)->nullable();
            $table->string('slug', 255)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('featured_image', 500)->nullable();
            $table->string('featured_image_alt', 255)->nullable();
            $table->string('thumbnail', 500)->nullable();
            $table->string('video_type', 20)->default('none'); // none|youtube|vimeo|self_hosted|external
            $table->string('video_url', 1000)->nullable();
            $table->text('video_embed')->nullable();
            $table->foreignId('video_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->unsignedInteger('video_duration')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft|scheduled|published|unpublished
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_trending')->default(false);
            $table->boolean('is_recommended')->default(false);
            $table->boolean('allow_comments')->default(true);
            $table->string('canonical_url', 1000)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('focus_keyword', 150)->nullable();
            $table->string('og_title', 255)->nullable();
            $table->string('og_description', 500)->nullable();
            $table->string('og_image', 500)->nullable();
            $table->string('twitter_card', 30)->default('summary_large_image');
            $table->string('robots', 100)->nullable();
            $table->json('quality_checklist')->nullable();
            $table->unsignedInteger('reading_time')->default(0);
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('unique_views_count')->default(0);
            $table->unsignedBigInteger('video_plays_count')->default(0);
            $table->unsignedBigInteger('shares_count')->default(0);
            $table->unsignedBigInteger('comments_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['type', 'status', 'published_at']);
            $table->index(['category_id', 'status']);
            $table->index(['author_id', 'status']);
            $table->index(['is_featured', 'is_trending', 'is_recommended']);
            $table->index('views_count');
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('slug', 200)->unique();
            $table->longText('content')->nullable();
            $table->string('template', 50)->default('default'); // default|contact
            $table->string('status', 20)->default('published');
            $table->boolean('is_system')->default(false);
            $table->boolean('show_in_footer')->default(true);
            $table->boolean('show_in_header')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('canonical_url', 1000)->nullable();
            $table->string('robots', 100)->nullable();
            $table->string('og_title', 255)->nullable();
            $table->string('og_description', 500)->nullable();
            $table->string('og_image', 500)->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('email', 190)->nullable();
            $table->text('content');
            $table->string('status', 20)->default('pending'); // pending|approved|spam
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
            $table->index(['post_id', 'status']);
        });

        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('title', 150);
            $table->string('subtitle', 255)->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 500);
            $table->string('to_path', 1000);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedBigInteger('hits')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('from_path');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->default('general');
            $table->string('key', 150)->unique();
            $table->longText('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
            $table->index('group');
        });

        Schema::create('ad_slots', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name', 100);
            $table->string('position', 100);
            $table->string('description', 500)->nullable();
            $table->longText('code')->nullable();
            $table->string('ad_slot_id', 50)->nullable();
            $table->string('ad_format', 30)->default('auto');
            $table->boolean('enabled')->default(false);
            $table->boolean('desktop')->default(true);
            $table->boolean('tablet')->default(true);
            $table->boolean('mobile')->default(true);
            $table->boolean('is_safe_zone')->default(true);
            $table->string('safety_note', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('paragraph_offset')->default(3);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('location', 30)->default('header');
            $table->string('label', 100);
            $table->string('url', 500);
            $table->string('target', 10)->default('_self');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('ad_slots');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('home_sections');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('media');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
    }
};
