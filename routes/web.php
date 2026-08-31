<?php

use App\Http\Controllers\Backup\BackupController;
use App\Http\Controllers\Backup\RestoreController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Mess\AdvanceBalanceController;
use App\Http\Controllers\Mess\AuditController;
use App\Http\Controllers\Mess\BillPreviewController;
use App\Http\Controllers\Mess\DueReminderController;
use App\Http\Controllers\Mess\ExpenseCategoryController;
use App\Http\Controllers\Mess\ExpenseController;
use App\Http\Controllers\Mess\GuestMealController;
use App\Http\Controllers\Mess\ManagerMealOffController;
use App\Http\Controllers\Mess\MealGridController;
use App\Http\Controllers\Mess\MealMonthlyGridController;
use App\Http\Controllers\Mess\MealOffApprovalController;
use App\Http\Controllers\Mess\MemberController;
use App\Http\Controllers\Mess\MemberDisabledDayController;
use App\Http\Controllers\Mess\MemberInviteController;
use App\Http\Controllers\Mess\MemberSearchController;
use App\Http\Controllers\Mess\MessClosedDayController;
use App\Http\Controllers\Mess\MessConfigController;
use App\Http\Controllers\Mess\MessNotificationController;
use App\Http\Controllers\Mess\MonthCloseController;
use App\Http\Controllers\Mess\MonthlyClosingController;
use App\Http\Controllers\Mess\MonthlyCorrectionController;
use App\Http\Controllers\Mess\PaymentController;
use App\Http\Controllers\Mess\PendingSettlementController;
use App\Http\Controllers\Mess\ReportController;
use App\Http\Controllers\Mess\ReportExportController;
use App\Http\Controllers\Mess\WalletController;
use App\Http\Controllers\My\MyBillPreviewController;
use App\Http\Controllers\My\MyPaymentController;
use App\Http\Controllers\My\MyReportController;
use App\Http\Controllers\My\MyReportExportController;
use App\Http\Controllers\My\MyWalletController;
use App\Http\Controllers\MyController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PostLoginRedirectController;
use App\Http\Controllers\RootController;
use App\Http\Controllers\SetPasswordController;
use App\Http\Controllers\SetupController;
use App\Http\Middleware\EnsureMessExists;
use App\Http\Middleware\RedirectIfSetupCompleted;
use Illuminate\Support\Facades\Route;

Route::get('/', RootController::class)->name('welcome');

Route::get('/post-login', PostLoginRedirectController::class)
    ->middleware('auth')
    ->name('post-login');

Route::middleware(RedirectIfSetupCompleted::class)->group(function () {
    Route::get('/setup', [SetupController::class, 'create'])->name('setup.create');
    Route::post('/setup', [SetupController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('setup.store');
});

// Onboarding (super-admin only)
Route::middleware(['auth', 'role:super-admin', EnsureMessExists::class])->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
});

// Phase 6: Backup & Restore (super-admin only — D-03).
// Custom controller + Blade views (research Pattern 3), NOT a Tyro dynamic
// resource. The Backups UI is not CRUD over one model. Every route is
// role:super-admin-gated (T-06-03-01); the destructive restore POST is also
// throttled (T-06-03-04 — brute-force typed-confirm defense-in-depth).
Route::middleware(['auth', 'role:super-admin'])
    ->prefix('dashboard/backups')
    ->name('dashboard.backups.')
    ->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::get('/configure', [BackupController::class, 'edit'])->name('configure');
        Route::put('/configure', [BackupController::class, 'update'])->name('configure.update');
        // Probe a cloud provider's saved credentials (write+read+delete a tiny
        // file). Throttled; returns JSON for a fetch-driven button on the form.
        Route::post('/test/{provider}', [BackupController::class, 'testConnection'])
            ->middleware('throttle:10,1')
            ->name('test');
        Route::post('/run', [BackupController::class, 'runNow'])->name('run');
        // Activity-log delete.
        Route::delete('/logs', [BackupController::class, 'clearLogs'])->name('logs.clear');
        Route::delete('/logs/{log}', [BackupController::class, 'destroyLog'])->name('logs.destroy');
        // Per-backup actions. The backup path is "<APP_NAME>/<file>.zip" and
        // the embedded slash + .zip extension trip nginx static-file matching
        // on shared hosting, so the path is passed via ?path= (GET) or the
        // form body (DELETE) — never as a URL segment.
        Route::get('/download', [BackupController::class, 'download'])->name('download');
        Route::delete('/delete', [BackupController::class, 'destroy'])->name('destroy');
        Route::get('/restore', [RestoreController::class, 'show'])->name('restore.show');
        // Throttle the destructive POST: 5 attempts per minute.
        Route::post('/restore', [RestoreController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('restore.store');
    });

// Public set-password (from invite link)
Route::get('/set-password', [SetPasswordController::class, 'show'])->name('password.set.show');
Route::post('/set-password', [SetPasswordController::class, 'update'])->name('password.set.update');

// Mess managers (admin + super-admin + manager roles) — home + mess config + audit + invite.
// `roles:` = EnsureAnyTyroRole (ANY-match); `role:` would be ALL-match.
// super-admin is included so the installation owner can run all daily mess operations
// (Home, Members, Meals, Expenses, Payments, Reports, Close month, etc.) without 403.
Route::middleware(['auth', 'roles:super-admin,manager', EnsureMessExists::class])->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    Route::get('/mess/settings', [MessConfigController::class, 'edit'])->name('mess.settings.edit');
    Route::patch('/mess/settings', [MessConfigController::class, 'update'])->name('mess.settings.update');

    // Multi-channel notification configuration (email / WhatsApp / Telegram / SMS).
    Route::get('/mess/notifications', [MessNotificationController::class, 'edit'])
        ->name('mess.notifications.edit');
    Route::put('/mess/notifications', [MessNotificationController::class, 'update'])
        ->name('mess.notifications.update');

    Route::get('/mess/audit', [AuditController::class, 'index'])->name('mess.audit');

    Route::get('/mess/members/invite', [MemberInviteController::class, 'create'])->name('mess.members.invite.create');
    Route::post('/mess/members/invite', [MemberInviteController::class, 'store'])->name('mess.members.invite.store');

    Route::resource('mess/members', MemberController::class)
        ->except(['destroy'])
        ->names([
            'index' => 'mess.members.index',
            'create' => 'mess.members.create',
            'store' => 'mess.members.store',
            'show' => 'mess.members.show',
            'edit' => 'mess.members.edit',
            'update' => 'mess.members.update',
        ]);

    Route::patch('mess/members/{member}/deactivate', [MemberController::class, 'destroy'])
        ->name('mess.members.deactivate');

    // Soft-delete a member (reversible; retains history).
    Route::delete('mess/members/{member}', [MemberController::class, 'delete'])
        ->name('mess.members.destroy');

    // Permanent removal — super-admin only, guarded by a dependency check.
    Route::delete('mess/members/{member}/force', [MemberController::class, 'forceDelete'])
        ->middleware('role:super-admin')
        ->name('mess.members.force-destroy');

    Route::get('mess/members-search', MemberSearchController::class)
        ->name('mess.members.search');

    Route::post('mess/members/{member}/meal-off', [ManagerMealOffController::class, 'store'])
        ->name('mess.members.meal-off.store')
        ->middleware('month.open');

    Route::get('mess/meals', [MealGridController::class, 'index'])->name('mess.meals.index');
    Route::post('mess/meals', [MealGridController::class, 'save'])->name('mess.meals.save')
        ->middleware('month.open');
    Route::get('mess/meals/monthly', [MealMonthlyGridController::class, 'index'])->name('mess.meals.monthly');
    Route::post('mess/meals/monthly', [MealMonthlyGridController::class, 'save'])->name('mess.meals.monthly.save')
        ->middleware('month.open');

    Route::get('mess/guest-meals', [GuestMealController::class, 'index'])->name('mess.guest-meals.index');
    Route::get('mess/guest-meals/create', [GuestMealController::class, 'create'])->name('mess.guest-meals.create');
    Route::post('mess/guest-meals', [GuestMealController::class, 'store'])->name('mess.guest-meals.store')
        ->middleware('month.open');
    Route::get('mess/guest-meals/{guestMeal}/edit', [GuestMealController::class, 'edit'])->name('mess.guest-meals.edit');
    Route::patch('mess/guest-meals/{guestMeal}', [GuestMealController::class, 'update'])->name('mess.guest-meals.update')
        ->middleware('month.open');

    Route::get('mess/meal-off', [MealOffApprovalController::class, 'index'])->name('mess.meal-off.index');
    Route::patch('mess/meal-off/{mealOffRequest}/approve', [MealOffApprovalController::class, 'approve'])->name('mess.meal-off.approve')
        ->middleware('month.open');
    Route::patch('mess/meal-off/{mealOffRequest}/reject', [MealOffApprovalController::class, 'reject'])->name('mess.meal-off.reject')
        ->middleware('month.open');

    Route::get('mess/expenses', [ExpenseController::class, 'index'])->name('mess.expenses.index');
    Route::get('mess/expenses/create', [ExpenseController::class, 'create'])->name('mess.expenses.create');
    Route::post('mess/expenses', [ExpenseController::class, 'store'])->name('mess.expenses.store')
        ->middleware('month.open');
    Route::get('mess/expenses/{expense}', [ExpenseController::class, 'show'])->name('mess.expenses.show');
    Route::get('mess/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('mess.expenses.edit');
    Route::patch('mess/expenses/{expense}', [ExpenseController::class, 'update'])->name('mess.expenses.update')
        ->middleware('month.open');
    Route::delete('mess/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('mess.expenses.destroy')
        ->middleware('month.open');

    Route::get('mess/categories', [ExpenseCategoryController::class, 'index'])->name('mess.categories.index');
    Route::post('mess/categories', [ExpenseCategoryController::class, 'store'])->name('mess.categories.store');
    Route::get('mess/categories/{category}/edit', [ExpenseCategoryController::class, 'edit'])->name('mess.categories.edit');
    Route::patch('mess/categories/{category}', [ExpenseCategoryController::class, 'update'])->name('mess.categories.update');
    Route::delete('mess/categories/{category}', [ExpenseCategoryController::class, 'destroy'])->name('mess.categories.destroy');

    // Closed days (global day control)
    Route::get('mess/closed-days', [MessClosedDayController::class, 'index'])->name('mess.closed-days.index');
    Route::post('mess/closed-days', [MessClosedDayController::class, 'store'])->name('mess.closed-days.store');
    Route::delete('mess/closed-days/{closedDay}', [MessClosedDayController::class, 'destroy'])->name('mess.closed-days.destroy');

    // Member disabled days (individual day control)
    Route::get('mess/members/{member}/disabled-days', [MemberDisabledDayController::class, 'index'])->name('mess.members.disabled-days.index');
    Route::post('mess/members/{member}/disabled-days', [MemberDisabledDayController::class, 'store'])->name('mess.members.disabled-days.store');
    Route::delete('mess/members/{member}/disabled-days/{disabledDay}', [MemberDisabledDayController::class, 'destroy'])->name('mess.members.disabled-days.destroy');

    Route::resource('mess/payments', PaymentController::class)
        ->only(['index', 'create', 'show', 'edit'])
        ->names([
            'index' => 'mess.payments.index',
            'create' => 'mess.payments.create',
            'show' => 'mess.payments.show',
            'edit' => 'mess.payments.edit',
        ]);

    Route::post('mess/payments', [PaymentController::class, 'store'])->name('mess.payments.store')
        ->middleware('month.open');
    Route::patch('mess/payments/{payment}', [PaymentController::class, 'update'])->name('mess.payments.update')
        ->middleware('month.open');
    Route::delete('mess/payments/{payment}', [PaymentController::class, 'destroy'])->name('mess.payments.destroy')
        ->middleware('month.open');

    Route::get('mess/advance-balances', [AdvanceBalanceController::class, 'index'])->name('mess.advance-balances.index');
    Route::get('mess/advance-balances/{member}/adjust', [AdvanceBalanceController::class, 'adjust'])->name('mess.advance-balances.adjust');
    Route::post('mess/advance-balances/{member}/adjust', [AdvanceBalanceController::class, 'storeAdjust'])->name('mess.advance-balances.storeAdjust');

    Route::get('mess/members/{member}/wallet', [WalletController::class, 'show'])->name('mess.members.wallet');

    // Pending settlements — cross-month, so NOT gated by month.open.
    Route::get('mess/settlements', [PendingSettlementController::class, 'index'])->name('mess.settlements.index');
    Route::post('mess/settlements/{settlement}/mark-settled', [PendingSettlementController::class, 'markSettled'])->name('mess.settlements.markSettled');

    Route::get('mess/bill-preview', [BillPreviewController::class, 'index'])->name('mess.bill-preview.index');

    // Reports — Plan 04-01 (HTML) + Plan 04-03 (PDF + Excel exports)
    Route::prefix('mess/reports')->name('mess.reports.')->group(function () {
        Route::get('monthly', [ReportController::class, 'monthly'])->name('monthly');
        Route::get('monthly.pdf', [ReportExportController::class, 'monthlyPdf'])->name('monthly.pdf');
        Route::get('monthly.xlsx', [ReportExportController::class, 'monthlyExcel'])->name('monthly.xlsx');

        Route::get('member-statement', [ReportController::class, 'memberStatement'])->name('member-statement');
        Route::get('member-statement.pdf', [ReportExportController::class, 'memberStatementPdf'])->name('member-statement.pdf');
        Route::get('member-statement.xlsx', [ReportExportController::class, 'memberStatementExcel'])->name('member-statement.xlsx');

        Route::get('expenses', [ReportController::class, 'expenses'])->name('expenses');
        Route::get('expenses.pdf', [ReportExportController::class, 'expensesPdf'])->name('expenses.pdf');
        Route::get('expenses.xlsx', [ReportExportController::class, 'expensesExcel'])->name('expenses.xlsx');

        Route::get('payments', [ReportController::class, 'payments'])->name('payments');
        Route::get('payments.pdf', [ReportExportController::class, 'paymentsPdf'])->name('payments.pdf');
        Route::get('payments.xlsx', [ReportExportController::class, 'paymentsExcel'])->name('payments.xlsx');
    });

    // Month-close: trigger + closings list/show + corrections + due reminders (Plan 03.4)
    Route::get('mess/close', [MonthCloseController::class, 'index'])->name('mess.close.index');
    Route::post('mess/close', [MonthCloseController::class, 'trigger'])->name('mess.close.trigger');

    Route::get('mess/closings', [MonthlyClosingController::class, 'index'])->name('mess.closings.index');
    Route::get('mess/closings/{closing}', [MonthlyClosingController::class, 'show'])->name('mess.closings.show');

    // Corrections target a closed month, so they must NOT be locked by month.open
    Route::get('mess/closings/{closing}/corrections', [MonthlyCorrectionController::class, 'index'])->name('mess.closings.corrections.index');
    Route::get('mess/closings/{closing}/corrections/create', [MonthlyCorrectionController::class, 'create'])->name('mess.closings.corrections.create');
    Route::post('mess/closings/{closing}/corrections', [MonthlyCorrectionController::class, 'store'])->name('mess.closings.corrections.store');

    Route::get('mess/due-reminder', [DueReminderController::class, 'index'])->name('mess.due-reminder.index');
    Route::post('mess/due-reminder', [DueReminderController::class, 'send'])->name('mess.due-reminder.send');
});

// Notifications — both managers (admin) and members (user) read their own
Route::middleware(['auth', EnsureMessExists::class])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');

    // Per-user channel preferences (subset of the mess admin's enabled channels).
    Route::get('notification-preferences', [NotificationPreferenceController::class, 'edit'])
        ->name('notification-preferences.edit');
    Route::put('notification-preferences', [NotificationPreferenceController::class, 'update'])
        ->name('notification-preferences.update');
});

// Member (user / mess-member role) home — password.change forces first-login password set.
Route::middleware(['auth', 'roles:mess-member', 'password.change'])->group(function () {
    Route::get('my/password/change', [MyController::class, 'showChangePassword'])->name('my.password.change');
    Route::post('my/password/change', [MyController::class, 'changePassword'])->name('my.password.change.store');

    Route::get('/my', [MyController::class, 'index'])->name('my');
    Route::patch('my/profile', [MyController::class, 'updateProfile'])->name('my.profile.update');
    Route::post('my/meal-off', [MyController::class, 'storeMealOff'])->name('my.meal-off.store');
    Route::get('my/payments', [MyPaymentController::class, 'index'])->name('my.payments');
    Route::get('my/bill-preview', [MyBillPreviewController::class, 'index'])->name('my.bill-preview');
    Route::get('my/wallet', [MyWalletController::class, 'index'])->name('my.wallet');

    // Member-side reports (RPT-05 own statement, RPT-06 aggregates-only monthly)
    // + Plan 04-03 exports (D-33 own statement, D-34 aggregates-only monthly).
    // SECURITY: NO `{member}` URL param — MyReportController derives the member
    // from $request->user()->getMemberOrNull(); ?member_id= query params are ignored.
    Route::prefix('my/reports')->name('my.reports.')->group(function () {
        Route::get('statement', [MyReportController::class, 'statement'])->name('statement');
        Route::get('statement.pdf', [MyReportExportController::class, 'statementPdf'])->name('statement.pdf');
        Route::get('statement.xlsx', [MyReportExportController::class, 'statementExcel'])->name('statement.xlsx');

        Route::get('monthly', [MyReportController::class, 'monthly'])->name('monthly');
        Route::get('monthly.pdf', [MyReportExportController::class, 'monthlyPdf'])->name('monthly.pdf');
        Route::get('monthly.xlsx', [MyReportExportController::class, 'monthlyExcel'])->name('monthly.xlsx');
    });
});
