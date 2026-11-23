<?php

use App\Admin\Http\AdminController;
use App\Admin\Http\EnsureSuperAdmin;
use App\Auth\Http\AuthController;
use App\Auth\Http\UserController;
use App\Shared\Http\Middleware\ResolveShopContext;
use App\Billing\Http\InvoiceController;
use App\Catalog\Http\CatalogController;
use App\Customers\Http\CustomerController;
use App\Reports\Http\ReportController;
use App\Bot\Http\BotController;
use App\Messaging\Http\MarketingController;
use App\Messaging\Http\WebhookController;
use App\Shops\Http\ShopController;
use App\Staff\Http\StaffController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => ['status' => 'ok']);

    // Provider callbacks (signature-verified inside the controller)
    Route::get('/webhooks/whatsapp', [WebhookController::class, 'verify']);
    Route::post('/webhooks/whatsapp', [WebhookController::class, 'whatsapp']);
    Route::post('/webhooks/sms', [WebhookController::class, 'sms']);

    Route::prefix('auth')->group(function () {
        Route::post('/otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:otp');
        Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:login');
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:login');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', fn (Request $request) => $request->user()->load('tenant.plan'));
        Route::put('/me', [AuthController::class, 'updateProfile']);
        Route::post('/onboarding/business', [ShopController::class, 'business']);

        // Platform administration (super admin only)
        Route::prefix('admin')->middleware(EnsureSuperAdmin::class)->group(function () {
            Route::get('/overview', [AdminController::class, 'overview']);
            Route::get('/tenants', [AdminController::class, 'tenants']);
            Route::put('/tenants/{id}', [AdminController::class, 'updateTenant']);
            Route::get('/plans', [AdminController::class, 'plans']);
            Route::get('/roles', [AdminController::class, 'roles']);
            Route::put('/roles/{name}/permissions', [AdminController::class, 'updateRole']);
        });

        // Tenant-scoped (no X-Shop-Id needed)
        Route::middleware(ResolveShopContext::class.':tenant')->group(function () {
            Route::get('/shops', [ShopController::class, 'index']);
            Route::post('/shops', [ShopController::class, 'store']);
            Route::get('/shops/{id}', [ShopController::class, 'show']);
            Route::put('/shops/{id}', [ShopController::class, 'update']);
            Route::put('/shops/{id}/settings', [ShopController::class, 'updateSettings']);
            Route::delete('/shops/{id}', [ShopController::class, 'destroy']);
            Route::post('/onboarding/complete', [ShopController::class, 'completeOnboarding']);

            Route::get('/marketing/templates', [MarketingController::class, 'templates']);
            Route::post('/marketing/templates', [MarketingController::class, 'storeTemplate']);
            Route::get('/marketing/campaigns', [MarketingController::class, 'campaigns']);
            Route::post('/marketing/campaigns', [MarketingController::class, 'storeCampaign']);
            Route::post('/marketing/campaigns/estimate', [MarketingController::class, 'estimate']);
            Route::post('/marketing/campaigns/{id}/send', [MarketingController::class, 'sendCampaign']);
            Route::get('/marketing/campaigns/{id}/report', [MarketingController::class, 'campaignReport']);
            Route::get('/marketing/automations', [MarketingController::class, 'automations']);
            Route::put('/marketing/automations/{trigger}', [MarketingController::class, 'saveAutomation']);
            Route::get('/marketing/coupons', [MarketingController::class, 'coupons']);
            Route::post('/marketing/coupons', [MarketingController::class, 'storeCoupon']);
            Route::put('/marketing/coupons/{id}', [MarketingController::class, 'toggleCoupon']);
            Route::get('/coupons/validate', [MarketingController::class, 'validateCoupon']);
            Route::get('/marketing/messages', [MarketingController::class, 'messageLog']);
            Route::get('/credits', [MarketingController::class, 'credits']);
            Route::post('/credits/topup', [MarketingController::class, 'topup']);
            Route::post('/devices', [\App\Mobile\Http\DeviceController::class, 'store']);
            Route::delete('/devices', [\App\Mobile\Http\DeviceController::class, 'destroy']);
            Route::post('/assistant/ask', [\App\Assistant\Http\AssistantController::class, 'ask']);
            Route::post('/assistant/voice', [\App\Assistant\Http\AssistantController::class, 'voice']);
            Route::post('/marketing/campaigns/{id}/skip', [MarketingController::class, 'skipCampaign']);
            Route::get('/bot/identities', [BotController::class, 'identities']);
            Route::post('/bot/simulate', [BotController::class, 'simulate']);
            Route::put('/bot/prefs', [BotController::class, 'prefs']);
            Route::post('/bot/send-summary', [BotController::class, 'sendSummary']);

            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::put('/users/{id}/roles', [UserController::class, 'updateRoles']);
        });

        // Catalog, staff, customers: tenant-wide data; X-Shop-Id optional (narrows staff list / applies price overrides)
        Route::middleware(ResolveShopContext::class.':optional')->group(function () {
            Route::get('/service-categories', [CatalogController::class, 'categories']);
            Route::post('/service-categories', [CatalogController::class, 'storeCategory']);
            Route::get('/services', [CatalogController::class, 'services']);
            Route::post('/services', [CatalogController::class, 'storeService']);
            Route::put('/services/{id}', [CatalogController::class, 'updateService']);
            Route::delete('/services/{id}', [CatalogController::class, 'destroyService']);
            Route::put('/services/{id}/shop-price', [CatalogController::class, 'setShopPrice']);
            Route::get('/products', [CatalogController::class, 'products']);
            Route::post('/products', [CatalogController::class, 'storeProduct']);
            Route::put('/products/{id}', [CatalogController::class, 'updateProduct']);
            Route::get('/staff', [StaffController::class, 'index']);
            Route::post('/staff', [StaffController::class, 'store']);
            Route::put('/staff/{id}', [StaffController::class, 'update']);
            Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);
            Route::get('/reports/sales', [ReportController::class, 'sales']);
            Route::get('/reports/staff', [ReportController::class, 'staff']);
            Route::get('/reports/dues', [ReportController::class, 'dues']);
            Route::get('/reports/{type}/export', [ReportController::class, 'export']);
            Route::get('/reports/table/{type}', [ReportController::class, 'table']);
            Route::get('/expenses', [\App\Expenses\Http\ExpenseController::class, 'index']);
            Route::post('/expenses', [\App\Expenses\Http\ExpenseController::class, 'store']);
            Route::delete('/expenses/{id}', [\App\Expenses\Http\ExpenseController::class, 'destroy']);
            Route::get('/customers', [CustomerController::class, 'index']);
            Route::post('/customers', [CustomerController::class, 'store']);
            Route::put('/customers/{id}', [CustomerController::class, 'update']);
        });

        // Billing: a shop is mandatory
        Route::middleware(ResolveShopContext::class)->group(function () {
            Route::get('/invoices', [InvoiceController::class, 'index']);
            Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
            Route::post('/invoices', [InvoiceController::class, 'store']);
            Route::get('/waiting', [BotController::class, 'waiting']);
            Route::put('/waiting/{id}', [BotController::class, 'serve']);
        });

        Route::middleware(ResolveShopContext::class)->get('/shop-context', fn (Request $request) => [
            'shop_id' => $request->attributes->get('shop')?->id,
        ]);
    });
});
