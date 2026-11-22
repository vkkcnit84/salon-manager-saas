<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function base(Blueprint $t): void
    {
        $t->id();
        $t->foreignId('tenant_id')->constrained('tenants');
    }

    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $t) {
            $this->base($t);
            $t->string('name');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('services', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('category_id')->nullable()->constrained('service_categories');
            $t->string('name');
            $t->unsignedBigInteger('price_paise');
            $t->unsignedInteger('duration_min')->default(30);
            $t->unsignedInteger('gst_bps')->default(1800);
            $t->string('commission_type')->nullable(); // percent|fixed
            $t->unsignedBigInteger('commission_value')->nullable(); // bps for percent, paise for fixed
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('shop_service_prices', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('shop_id')->constrained('shops');
            $t->foreignId('service_id')->constrained('services');
            $t->unsignedBigInteger('price_paise');
            $t->timestamps();
            $t->unique(['shop_id', 'service_id']);
        });

        Schema::create('products', function (Blueprint $t) {
            $this->base($t);
            $t->string('name');
            $t->string('sku')->nullable();
            $t->unsignedBigInteger('price_paise');
            $t->unsignedInteger('gst_bps')->default(1800);
            $t->integer('stock')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('staff', function (Blueprint $t) {
            $this->base($t);
            $t->string('name');
            $t->string('mobile', 15)->nullable();
            $t->string('title')->nullable();
            $t->string('commission_type')->default('percent');
            $t->unsignedBigInteger('commission_value')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('shop_staff', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('shop_id')->constrained('shops');
            $t->foreignId('staff_id')->constrained('staff');
            $t->unique(['shop_id', 'staff_id']);
        });

        Schema::create('staff_service_commissions', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('staff_id')->constrained('staff');
            $t->foreignId('service_id')->constrained('services');
            $t->string('commission_type');
            $t->unsignedBigInteger('commission_value');
            $t->unique(['staff_id', 'service_id']);
        });

        Schema::create('customers', function (Blueprint $t) {
            $this->base($t);
            $t->string('name');
            $t->string('mobile', 15);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'mobile']);
        });

        Schema::create('invoices', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('shop_id')->constrained('shops');
            $t->foreignId('customer_id')->nullable()->constrained('customers');
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->string('financial_year', 9);
            $t->unsignedBigInteger('seq');
            $t->string('number');
            $t->string('status')->default('paid'); // paid|due|void
            $t->string('tax_mode');
            $t->bigInteger('subtotal_paise');
            $t->bigInteger('discount_paise')->default(0);
            $t->bigInteger('tax_paise')->default(0);
            $t->bigInteger('tip_paise')->default(0);
            $t->bigInteger('rounding_paise')->default(0);
            $t->bigInteger('total_paise');
            $t->bigInteger('paid_paise')->default(0);
            $t->bigInteger('due_paise')->default(0);
            $t->string('idempotency_key', 64)->nullable();
            $t->timestamps();
            $t->unique(['shop_id', 'financial_year', 'seq']);
            $t->unique(['shop_id', 'idempotency_key']);
        });

        Schema::create('invoice_items', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('invoice_id')->constrained('invoices');
            $t->string('item_type'); // service|product
            $t->unsignedBigInteger('item_id');
            $t->string('name');
            $t->unsignedInteger('qty')->default(1);
            $t->bigInteger('unit_price_paise');
            $t->bigInteger('discount_paise')->default(0);
            $t->unsignedInteger('gst_bps');
            $t->bigInteger('taxable_paise');
            $t->bigInteger('tax_paise');
            $t->bigInteger('total_paise');
            $t->foreignId('staff_id')->nullable()->constrained('staff');
            $t->bigInteger('commission_paise')->default(0);
            $t->json('commission_snapshot')->nullable();
        });

        Schema::create('payments', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('invoice_id')->constrained('invoices');
            $t->string('method'); // cash|upi|card
            $t->bigInteger('amount_paise');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payments', 'invoice_items', 'invoices', 'customers', 'staff_service_commissions', 'shop_staff', 'staff', 'products', 'shop_service_prices', 'services', 'service_categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
