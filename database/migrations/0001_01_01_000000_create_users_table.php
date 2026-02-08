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
        Schema::create('users', function (Blueprint $table) {
            // Primary key & basic info
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();

            // Additional profile fields
            $table->string('username', 50)->unique()->nullable();
            $table->string('avatar', 500)->nullable(); // Support full S3/MinIO URLs
            $table->string('phone_country_code', 5)->default('+62'); // Default Indonesia
            $table->text('phone_number')->nullable(); // TEXT for Laravel encrypted values (json format)

            // Authorization & security features
            $table->string('role', 50)->default('user'); // 'user', 'admin', 'moderator', etc.

            // Security tracking - IP addresses
            $table->string('last_login_ip', 45)->nullable();
            $table->string('last_seen_ip', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable(); // Real-time: last activity timestamp
            $table->string('last_failed_login_ip', 45)->nullable();

            // Failed login protection
            $table->unsignedInteger('failed_login_attempts')->default(0);
            $table->timestamp('last_failed_login_at')->nullable();
            $table->timestamp('locked_at')->nullable();

            // Account management
            $table->timestamp('blocked_until')->nullable();
            $table->enum('account_status', ['active', 'blocked', 'suspended', 'terminated'])->default('active');
            $table->text('blocked_reason')->nullable();
            $table->uuid('blocked_by')->nullable(); // UUID to match users.id type

            // Timestamps & soft deletes
            $table->softDeletes();
            $table->timestamps();

            // Performance indexes - Single column
            $table->index('username');
            $table->index('role'); // For authorization queries
            $table->index('account_status');
            $table->index('locked_at');
            $table->index('blocked_until');
            $table->index('failed_login_attempts');
            $table->index('last_seen_at'); // For sorting online/active users
            $table->index('deleted_at'); // For soft delete queries (withTrashed)

            // Composite indexes - For common query patterns
            $table->index(['email', 'account_status']); // Auth login checks
            $table->index(['username', 'account_status']); // Username auth login checks
        });

        // Add self-referencing FK after table creation (PostgreSQL requirement)
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('blocked_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();

            // Index for efficient cleanup of expired tokens
            $table->index('created_at');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();

            // Foreign key - cascade delete sessions when user deleted
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('user_login_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->nullable()->index();
            $table->string('event', 50); // 'login_success', 'login_failure', 'logout', 'account_locked', 'profile_update', 'password_change', 'access_denied_locked', 'access_denied_blocked'
            $table->string('identity')->nullable(); // Email or username used during login
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('session_id')->nullable(); // Laravel session ID for request correlation
            $table->uuid('request_id')->nullable(); // Unique request identifier for distributed tracing
            $table->json('changes')->nullable(); // Audit trail for attribute changes (old/new diffs)
            $table->json('context')->nullable(); // Additional event context (OTP attempts, error details, etc.)
            $table->timestamp('created_at')->useCurrent();

            // Indexes for performance
            $table->index(['event', 'created_at']);
            $table->index('session_id');

            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Simply drop tables - PostgreSQL will handle FK constraints
        // If tables/FKs don't exist, dropIfExists will skip safely
        Schema::dropIfExists('user_login_activities');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
