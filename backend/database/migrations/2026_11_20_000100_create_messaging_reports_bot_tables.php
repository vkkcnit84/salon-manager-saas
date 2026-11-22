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
        Schema::table('customers', function (Blueprint $t) {
            $t->boolean('consent_whatsapp')->default(false);
            $t->boolean('consent_sms')->default(false);
            $t->timestamp('consent_at')->nullable();
            $t->timestamp('opted_out_at')->nullable();
            $t->date('birthday')->nullable();
            $t->date('anniversary')->nullable();
            $t->string('source')->nullable();
            $t->json('tags')->nullable();
        });
        Schema::table('users', fn (Blueprint $t) => $t->json('bot_prefs')->nullable());
        Schema::table('staff', fn (Blueprint $t) => $t->boolean('bot_enabled')->default(true));

        Schema::create('credit_transactions', function (Blueprint $t) {
            $this->base($t);
            $t->string('type'); // topup|debit|refund|grant
            $t->integer('credits');
            $t->bigInteger('balance_after');
            $t->string('reference')->nullable();
            $t->bigInteger('amount_paise')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('user_id')->nullable()->constrained('users');
            $t->string('action');
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('message_templates', function (Blueprint $t) {
            $this->base($t);
            $t->string('channel'); // whatsapp|sms
            $t->string('name');
            $t->string('language', 8)->default('en');
            $t->string('category')->default('marketing'); // marketing|utility
            $t->text('body'); // {{1}} {{2}} placeholders
            $t->json('variables')->nullable(); // ["name","shop","offer","expiry"]
            $t->string('provider_template_id')->nullable();
            $t->string('dlt_template_id')->nullable();
            $t->string('status')->default('approved'); // draft|pending|approved|rejected
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('campaigns', function (Blueprint $t) {
            $this->base($t);
            $t->string('name');
            $t->foreignId('template_id')->constrained('message_templates');
            $t->string('channel');
            $t->json('audience')->nullable();
            $t->json('meta')->nullable(); // offer text, expiry
            $t->timestamp('scheduled_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->string('status')->default('draft'); // draft|scheduled|sending|completed|stopped
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamps();
        });

        Schema::create('campaign_messages', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('campaign_id')->constrained('campaigns');
            $t->foreignId('customer_id')->constrained('customers');
            $t->string('status')->default('queued'); // queued|sent|delivered|read|failed
            $t->string('provider_msg_id')->nullable()->index();
            $t->string('error')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->unique(['campaign_id', 'customer_id']);
        });

        Schema::create('automations', function (Blueprint $t) {
            $this->base($t);
            $t->string('trigger'); // invoice_created|birthday|anniversary|winback|due_reminder|feedback
            $t->unsignedInteger('delay_hours')->default(0);
            $t->foreignId('template_id')->nullable()->constrained('message_templates');
            $t->boolean('active')->default(false);
            $t->timestamps();
            $t->unique(['tenant_id', 'trigger']);
        });

        Schema::create('coupons', function (Blueprint $t) {
            $this->base($t);
            $t->string('code');
            $t->string('type'); // percent|flat
            $t->unsignedBigInteger('value'); // bps for percent, paise for flat
            $t->unsignedInteger('usage_limit')->nullable();
            $t->unsignedInteger('used')->default(0);
            $t->timestamp('expires_at')->nullable();
            $t->foreignId('campaign_id')->nullable()->constrained('campaigns');
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'code']);
        });

        Schema::create('message_log', function (Blueprint $t) {
            $this->base($t);
            $t->string('channel');
            $t->string('direction')->default('out');
            $t->string('to');
            $t->string('template')->nullable();
            $t->text('body');
            $t->json('buttons')->nullable();
            $t->string('status')->default('sent');
            $t->string('provider_msg_id')->nullable();
            $t->json('context')->nullable();
            $t->timestamps();
            $t->index(['to', 'direction', 'created_at']);
        });

        Schema::create('expenses', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('shop_id')->constrained('shops');
            $t->string('category');
            $t->bigInteger('amount_paise');
            $t->string('note')->nullable();
            $t->date('spent_on');
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamps();
            $t->index(['shop_id', 'spent_on']);
        });

        Schema::create('daily_shop_summary', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('shop_id')->constrained('shops');
            $t->date('date');
            $t->bigInteger('gross')->default(0);
            $t->bigInteger('discount')->default(0);
            $t->bigInteger('tax')->default(0);
            $t->bigInteger('net')->default(0);
            $t->unsignedInteger('bills')->default(0);
            $t->unsignedInteger('customers_new')->default(0);
            $t->unsignedInteger('customers_returning')->default(0);
            $t->bigInteger('cash')->default(0);
            $t->bigInteger('upi')->default(0);
            $t->bigInteger('card')->default(0);
            $t->bigInteger('due_collected')->default(0);
            $t->bigInteger('expenses')->default(0);
            $t->bigInteger('commission')->default(0);
            $t->timestamps();
            $t->unique(['shop_id', 'date']);
        });

        Schema::create('daily_staff_summary', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('staff_id')->constrained('staff');
            $t->foreignId('shop_id')->constrained('shops');
            $t->date('date');
            $t->unsignedInteger('services_count')->default(0);
            $t->bigInteger('revenue')->default(0);
            $t->bigInteger('commission')->default(0);
            $t->bigInteger('tips')->default(0);
            $t->timestamps();
            $t->unique(['staff_id', 'shop_id', 'date']);
        });

        Schema::create('bot_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained('tenants');
            $t->string('wa_id')->index();
            $t->string('actor_type'); // owner|manager|staff|customer|unknown
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('state')->nullable();
            $t->json('context')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });

        Schema::create('waiting_queue', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('shop_id')->constrained('shops');
            $t->foreignId('customer_id')->constrained('customers');
            $t->string('status')->default('waiting'); // waiting|served|left
            $t->timestamps();
        });

        Schema::create('ai_usage', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('user_id')->nullable()->constrained('users');
            $t->string('question', 500);
            $t->string('tool')->nullable();
            $t->unsignedInteger('tokens_in')->default(0);
            $t->unsignedInteger('tokens_out')->default(0);
            $t->unsignedBigInteger('cost_micro_inr')->default(0);
            $t->boolean('cached')->default(false);
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('device_tokens', function (Blueprint $t) {
            $this->base($t);
            $t->foreignId('user_id')->constrained('users');
            $t->string('token')->unique();
            $t->string('platform')->default('android');
            $t->timestamps();
        });

        Schema::table('invoices', fn (Blueprint $t) => $t->index(['shop_id', 'created_at']));
        Schema::table('invoice_items', fn (Blueprint $t) => $t->index(['staff_id', 'invoice_id']));
    }

    public function down(): void
    {
        foreach (['device_tokens', 'ai_usage', 'waiting_queue', 'bot_sessions', 'daily_staff_summary', 'daily_shop_summary', 'expenses', 'message_log', 'coupons', 'automations', 'campaign_messages', 'campaigns', 'message_templates', 'audit_logs', 'credit_transactions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
