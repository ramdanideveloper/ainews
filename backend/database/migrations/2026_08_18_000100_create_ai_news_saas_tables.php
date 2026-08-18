<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('status')->default('active')->index();
            $t->string('role')->default('user')->index();
            $t->timestamp('welcome_credit_granted_at')->nullable();
        });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('wallets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->decimal('balance_amount', 14, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('guest_trials', function (Blueprint $t) {
            $t->id();
            $t->uuid('install_id')->unique();
            $t->string('site_url');
            $t->string('domain')->index();
            $t->string('plugin_version')->nullable();
            $t->unsignedInteger('free_generate_total')->default(10);
            $t->unsignedInteger('free_generate_used')->default(0);
            $t->unsignedInteger('free_image_total')->default(0);
            $t->unsignedInteger('free_image_used')->default(0);
            $t->timestamp('first_seen_at')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->string('status')->default('active')->index();
            $t->timestamps();
        });
        Schema::create('connected_sites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('site_name');
            $t->string('site_url');
            $t->string('domain')->index();
            $t->uuid('install_id')->unique();
            $t->string('token_hash', 64)->unique();
            $t->string('token_last_four', 4);
            $t->string('status')->default('active')->index();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
        });
        Schema::create('wallet_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type')->index();
            $t->decimal('amount', 14, 2);
            $t->decimal('balance_before', 14, 2);
            $t->decimal('balance_after', 14, 2);
            $t->string('description');
            $t->string('reference_id')->nullable()->unique();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('ai_providers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('provider')->index();
            $t->string('model_id');
            $t->text('api_key');
            $t->string('base_url')->nullable();
            $t->string('status')->default('active')->index();
            $t->unsignedInteger('priority')->default(100);
            $t->unsignedBigInteger('daily_token_limit')->nullable();
            $t->unsignedBigInteger('monthly_token_limit')->nullable();
            $t->boolean('supports_text')->default(true);
            $t->boolean('supports_image')->default(false);
            $t->decimal('price_input_per_1m', 14, 4)->default(0);
            $t->decimal('price_output_per_1m', 14, 4)->default(0);
            $t->decimal('price_image_per_generate', 14, 2)->nullable();
            $t->unsignedInteger('fallback_order')->default(100);
            $t->timestamps();
            $t->unique(['provider', 'model_id']);
        });
        Schema::create('routing_rules', function (Blueprint $t) {
            $t->id();
            $t->string('request_type')->unique();
            $t->foreignId('preferred_provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $t->boolean('prefer_lowest_cost')->default(false);
            $t->boolean('is_active')->default(true);
            $t->json('config')->nullable();
            $t->timestamps();
        });
        Schema::create('usage_logs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('connected_site_id')->nullable()->constrained()->nullOnDelete();
            $t->uuid('install_id')->nullable()->index();
            $t->string('site_url')->nullable();
            $t->string('request_type')->index();
            $t->string('provider')->nullable();
            $t->string('model')->nullable();
            $t->unsignedBigInteger('input_tokens')->default(0);
            $t->unsignedBigInteger('output_tokens')->default(0);
            $t->unsignedBigInteger('total_tokens')->default(0);
            $t->decimal('provider_cost_idr', 14, 4)->default(0);
            $t->decimal('charged_amount', 14, 2)->default(0);
            $t->boolean('free_trial_used')->default(false);
            $t->foreignId('wallet_transaction_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status')->default('success')->index();
            $t->text('error_message')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
        Schema::create('app_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->string('type')->default('string');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['app_settings', 'usage_logs', 'routing_rules', 'ai_providers', 'wallet_transactions', 'connected_sites', 'guest_trials', 'wallets', 'personal_access_tokens'] as $table) {
            Schema::dropIfExists($table);
        }Schema::table('users',fn (Blueprint $t) => $t->dropColumn(['status', 'role', 'welcome_credit_granted_at']));
    }
};
