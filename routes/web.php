<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\TeamController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PublicPayController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

/*
|--------------------------------------------------------------------------
| Guest authentication (throttled)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')->name('login.store');

    // First-run setup only - blocked once an owner exists.
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1')->name('register.store');
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Customer-facing pay page (signed public link, no session required)
|--------------------------------------------------------------------------
*/
Route::get('pay/{invoice:public_id}', [PublicPayController::class, 'show'])
    ->middleware('throttle:30,1')->name('pay.show');

/*
|--------------------------------------------------------------------------
| Authenticated application
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->group(function () {

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile (self-service)
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Customers
    Route::resource('customers', CustomerController::class);

    // Products & services
    Route::resource('products', ProductController::class);

    // Sales
    Route::resource('sales', SaleController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');

    // Invoices (created via sales/quotations - never hand-built money fields)
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
    Route::patch('invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.status');

    // Payments
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/create/{invoice?}', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');

    // Quotations
    Route::resource('quotations', QuotationController::class)->except(['edit', 'update', 'destroy']);
    Route::patch('quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('quotations.status');
    Route::post('quotations/{quotation}/convert', [QuotationController::class, 'convert'])->name('quotations.convert');
    Route::get('quotations/{quotation}/download', [QuotationController::class, 'download'])->name('quotations.download');

    // Debts / receivables
    Route::get('debts', [DebtController::class, 'index'])->name('debts.index');
    Route::get('debts/{debt}', [DebtController::class, 'show'])->name('debts.show');
    Route::post('debts/{debt}/write-off', [DebtController::class, 'writeOff'])->name('debts.write-off');
    Route::post('debts/{debt}/dispute', [DebtController::class, 'markDisputed'])->name('debts.dispute');

    // Expenses
    Route::resource('expenses', ExpenseController::class)->except(['show']);

    // Messages / conversations
    Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('messages/conversation/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::get('messages/compose/{customer}', [MessageController::class, 'compose'])->name('messages.compose');
    Route::post('messages/send', [MessageController::class, 'send'])
        ->middleware('throttle:20,1')->name('messages.send');
    Route::post('messages/templates', [MessageController::class, 'storeTemplate'])->name('messages.templates.store');
    Route::patch('messages/templates/{template}', [MessageController::class, 'updateTemplate'])->name('messages.templates.update');

    // Reminders
    Route::get('reminders', [ReminderController::class, 'index'])->name('reminders.index');
    Route::post('reminders/rules', [ReminderController::class, 'storeRule'])->name('reminders.rules.store');
    Route::patch('reminders/rules/{rule}/toggle', [ReminderController::class, 'toggleRule'])->name('reminders.rules.toggle');
    Route::post('reminders/debts/{debt}/send', [ReminderController::class, 'sendNow'])
        ->middleware('throttle:30,1')->name('reminders.send-now');

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('expenses', [ReportController::class, 'expenses'])->name('expenses');
        Route::get('debtors', [ReportController::class, 'debtors'])->name('debtors');
        Route::get('aging', [ReportController::class, 'aging'])->name('aging');
        Route::get('tax', [ReportController::class, 'tax'])->name('tax');
        Route::get('{type}/export', [ReportController::class, 'exportCsv'])->where('type', 'sales|expenses|invoices')->name('export');
    });

    // Settings
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/categories', [SettingsController::class, 'storeCategory'])->name('settings.categories.store');
    Route::post('settings/expense-categories', [SettingsController::class, 'storeExpenseCategory'])->name('settings.expense-categories.store');

    // Team & permissions (owner only)
    Route::prefix('settings/users')->name('users.')->group(function () {
        Route::get('/', [TeamController::class, 'index'])->name('index');
        Route::post('/', [TeamController::class, 'store'])->name('store');
        Route::patch('{user}', [TeamController::class, 'update'])->name('update');
        Route::put('{user}/password', [TeamController::class, 'resetPassword'])->name('password');
        Route::delete('{user}', [TeamController::class, 'destroy'])->name('destroy');
    });

    // Integrations (owner only)
    Route::get('settings/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::put('settings/integrations/sms', [IntegrationController::class, 'saveSms'])->name('integrations.sms.save');
    Route::post('settings/integrations/sms/test', [IntegrationController::class, 'testSms'])->name('integrations.sms.test');
    Route::put('settings/integrations/whatsapp', [IntegrationController::class, 'saveWhatsapp'])->name('integrations.whatsapp.save');
    Route::post('settings/integrations/whatsapp/test', [IntegrationController::class, 'testWhatsapp'])->name('integrations.whatsapp.test');

    // Audit log (owner)
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
});
