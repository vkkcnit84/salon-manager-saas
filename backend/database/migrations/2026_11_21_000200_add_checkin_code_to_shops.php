<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', fn (Blueprint $t) => $t->string('code', 8)->nullable()->unique());

        // Slugs are only unique per tenant, so the public QR check-in code must be a separate, globally unique value.
        foreach (DB::table('shops')->whereNull('code')->pluck('id') as $id) {
            DB::table('shops')->where('id', $id)->update(['code' => \App\Shops\Models\Shop::newCode()]);
        }
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $t) {
            $t->dropUnique(['code']);
            $t->dropColumn('code');
        });
    }
};
