<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->index(['tenant_id', 'created_at'], 'invoices_tenant_created_idx');
            $t->index(['tenant_id', 'customer_id'], 'invoices_tenant_customer_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropIndex('invoices_tenant_created_idx');
            $t->dropIndex('invoices_tenant_customer_idx');
        });
    }
};
