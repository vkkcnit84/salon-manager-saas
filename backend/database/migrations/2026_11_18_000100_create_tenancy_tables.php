<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('max_shops');
            $table->unsignedInteger('max_staff');
            $table->unsignedInteger('monthly_message_credits');
            $table->unsignedBigInteger('price_paise')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->unsignedBigInteger('owner_user_id')->nullable()->index();
            $table->foreignId('plan_id')->nullable()->constrained('plans');
            $table->timestamp('trial_ends_at')->nullable();
            $table->unsignedBigInteger('message_credits')->default(0);
            $table->string('status')->default('trial');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('name');
            $table->string('slug');
            $table->text('address')->nullable();
            $table->string('phone', 15)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('invoice_prefix', 12)->nullable();
            $table->string('tax_mode')->default('exclusive');
            $table->string('timezone')->default('Asia/Kolkata');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('shop_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('shop_id')->constrained('shops');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
            $table->unique(['shop_id', 'user_id']);
        });

        // Sequential invoice numbers: one row per shop and financial year, locked FOR UPDATE on allocation.
        Schema::create('shop_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('shop_id')->constrained('shops');
            $table->string('financial_year', 9);
            $table->unsignedBigInteger('last_invoice_no')->default(0);
            $table->timestamps();
            $table->unique(['shop_id', 'financial_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_counters');
        Schema::dropIfExists('shop_user');
        Schema::dropIfExists('shops');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('plans');
    }
};
