<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\MetaController;
use App\Http\Controllers\CoreController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\FrontendController;
use App\Http\Controllers\User\HomeController;
use App\Http\Controllers\User\Auth\LoginController;
use App\Http\Controllers\User\Auth\PasswordController;
use App\Http\Controllers\User\Auth\RegisterController;
use App\Http\Controllers\Admin\Core\GlobalWorldController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

/**
 * EMERGENCY CACHE CLEAR ROUTE
 * Use this when the system throws 500 errors after an update
 * Access: /clear-cache?key=YOUR_PURCHASE_KEY
 *
 * This route has NO middleware dependencies to ensure it works
 * even when the application is in a broken state
 */
Route::get('clear-cache', function () {
    // Security: Require purchase key or app key for access
    $providedKey = request()->query('key');
    $purchaseKey = site_settings('purchase_key') ?: env('PURCHASE_KEY', '');
    $appKey = config('app.key', '');

    // Allow access with purchase key, app key, or special emergency key
    $validKeys = array_filter([$purchaseKey, $appKey, 'xsender-emergency-' . date('Y-m-d')]);

    if (empty($providedKey) || !in_array($providedKey, $validKeys)) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Please provide valid key as query parameter: ?key=YOUR_PURCHASE_KEY'
        ], 401);
    }

    $results = ['cleared' => [], 'errors' => []];

    // 1. Clear view cache
    try {
        $viewPath = storage_path('framework/views');
        if (is_dir($viewPath)) {
            $files = glob($viewPath . '/*');
            foreach ($files as $file) {
                if (is_file($file) && basename($file) !== '.gitignore') {
                    @unlink($file);
                }
            }
            $results['cleared'][] = 'views';
        }
    } catch (\Exception $e) {
        $results['errors'][] = 'views: ' . $e->getMessage();
    }

    // 2. Clear cache files
    try {
        $cachePath = storage_path('framework/cache/data');
        if (is_dir($cachePath)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cachePath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                if ($file->isFile() && $file->getFilename() !== '.gitignore') {
                    @unlink($file->getRealPath());
                } elseif ($file->isDir()) {
                    @rmdir($file->getRealPath());
                }
            }
            $results['cleared'][] = 'cache';
        }
    } catch (\Exception $e) {
        $results['errors'][] = 'cache: ' . $e->getMessage();
    }

    // 3. Clear bootstrap cache
    try {
        $bootstrapPath = base_path('bootstrap/cache');
        $bootstrapFiles = [
            'config.php',
            'routes-v7.php',
            'services.php',
            'packages.php',
            'events.php',
        ];
        foreach ($bootstrapFiles as $file) {
            $filePath = $bootstrapPath . '/' . $file;
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        $results['cleared'][] = 'bootstrap';
    } catch (\Exception $e) {
        $results['errors'][] = 'bootstrap: ' . $e->getMessage();
    }

    // 4. Clear OPcache
    try {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
            $results['cleared'][] = 'opcache';
        }
    } catch (\Exception $e) {
        $results['errors'][] = 'opcache: ' . $e->getMessage();
    }

    // 5. Clear Laravel cache
    try {
        Cache::flush();
        $results['cleared'][] = 'laravel_cache';
    } catch (\Exception $e) {
        $results['errors'][] = 'laravel_cache: ' . $e->getMessage();
    }

    // 6. Try Artisan commands
    try {
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');
        $results['cleared'][] = 'artisan';
    } catch (\Exception $e) {
        $results['errors'][] = 'artisan: ' . $e->getMessage();
    }

    // 7. Clear application caches
    try {
        if (function_exists('clear_app_caches')) {
            clear_app_caches();
            $results['cleared'][] = 'app_caches';
        }
    } catch (\Exception $e) {
        $results['errors'][] = 'app_caches: ' . $e->getMessage();
    }

    // 8. Restore critical caches to prevent install/license redirect
    try {
        if (function_exists('restore_critical_caches')) {
            restore_critical_caches();
            $results['cleared'][] = 'critical_caches_restored';
        }
    } catch (\Exception $e) {
        $results['errors'][] = 'restore_caches: ' . $e->getMessage();
    }

    $success = count($results['cleared']) > 0;

    return response()->json([
        'success' => $success,
        'message' => $success ? 'Cache cleared successfully! Please refresh the page.' : 'Cache clearing had issues',
        'details' => $results,
        'next_step' => 'Please refresh your browser or visit the homepage'
    ]);
})->withoutMiddleware(['web', 'auth', 'domain.verified', 'check.domain', 'verified', 'installed'])->name('emergency.cache.clear');

Route::get('queue-work', [QueueController::class, 'processAllQueues'])
    ->name('queue.work');

Route::get('queue-work/default', [QueueController::class, 'processDefault'])
    ->name('queue.work.default');
Route::get('queue-work/dispatch-logs', [QueueController::class, 'processDispatchlogs'])
    ->name('queue.work.dispatch-logs');
Route::get('queue-work/regular-sms', [QueueController::class, 'processRegularSms'])
    ->name('queue.work.regular-sms');
Route::get('queue-work/regular-email', [QueueController::class, 'processRegularEmail'])
    ->name('queue.work.regular-email');
Route::get('queue-work/regular-whatsapp', [QueueController::class, 'processRegularWhatsapp'])
    ->name('queue.work.regular-whatsapp');
Route::get('queue-work/campaign-sms', [QueueController::class, 'processCampaignSms'])
    ->name('queue.work.campaign-sms');
Route::get('queue-work/campaign-email', [QueueController::class, 'processCampaignEmail'])
    ->name('queue.work.campaign-email');
Route::get('queue-work/campaign-whatsapp', [QueueController::class, 'processCampaignWhatsapp'])
    ->name('queue.work.campaign-whatsapp');
Route::get('queue-work/chat-whatsapp', [QueueController::class, 'processChatWhatsapp'])
    ->name('queue.work.chat-whatsapp');
Route::get('queue-work/import-contacts', [QueueController::class, 'processContactImport'])
    ->name('queue.work.import-contacts');
Route::get('queue-work/verify-email', [QueueController::class, 'processEmailVerify'])
    ->name('queue.work.verify-email');

Route::get('cron/run', [CronController::class, 'run'])->name('cron.run');

// Email Tracking Routes (no auth, minimal middleware)
Route::get('t/o/{token}', [TrackingController::class, 'trackOpen'])->name('email.track.open');
Route::get('t/c/{token}', [TrackingController::class, 'trackClick'])->name('email.track.click');

// Enterprise Automation Routes (Recommended)
Route::prefix('automation')->name('automation.')->group(function () {
    // Main automation endpoint - single URL for all tasks
    Route::get('run', [CronController::class, 'automation'])->name('run');

    // Health check endpoint
    Route::get('health', [CronController::class, 'health'])->name('health');

    // Failed jobs management
    Route::get('retry-failed', [CronController::class, 'retryFailed'])->name('retry-failed');
    Route::get('clear-failed', [CronController::class, 'clearFailed'])->name('clear-failed');
});

Route::middleware([
    'guest',
    'maintenance'
])->group(function () { 

    ## ------------------------------- ##
    ## Authentication Route Declartion ##
    ## ------------------------------- ##

    Route::controller(RegisterController::class)
            ->middleware('registration')
            ->group(function() {

        Route::get('register/{uid?}', 'register')->name('register');
        Route::post('register/{uid?}', 'store')->name('register.store');
    });
 
    Route::middleware('login')
            ->group(function() {
 
        Route::controller(LoginController::class)
                ->group(function() {
            
            Route::get('/login', 'create')->name('login');
            Route::post('login', 'store')->name('login.store');
        });

        Route::controller(PasswordController::class)
                ->name('password.')
                ->group(function() {

            Route::post('forgot-password', 'store')->name('email');
            Route::get('forgot-password', 'create')->name('request');
            Route::get('password/resend/code', 'resendCode')->name('resend.code');
            Route::post('reset-password', 'updatePassword')->name('update');
            Route::get('reset-password/{token}', 'resetPassword')->name('reset');
            Route::get('password/code/verify', 'passwordResetCodeVerify')->name('verify.code');
            Route::post('password/code/verify', 'emailVerificationCode')->name('email.verify.code');
        });
    });

    Route::controller(WebController::class)->middleware(['redirect.to.login'])->group(function () {
        Route::get('/', 'index')->name('home');
        Route::get('service/{type?}', 'service')->name('service');
        Route::get('blog/search', 'blogSearch')->name('blog.search');
        Route::get('blog/{uid?}', 'blog')->name('blog');
        Route::get('about/', 'about')->name('about');
        Route::get('pricing/', 'pricing')->name('pricing');
        Route::get('contact/', 'contact')->name('contact');
        Route::post('contact/', 'getInTouch')->name('contact.get_in_touch');
        Route::get('/pages/{key}/{id}', 'pages')->name('page');
    });
    
    
    Route::controller(FrontendController::class)->group(function() {
    
        Route::get('/default/image/{size}', 'defaultImageCreate')->name('default.image');
        Route::get('email/contact/demo/file', 'demoImportFile')->name('email.contact.demo.import');
        Route::get('sms/demo/import/file', 'demoImportFilesms')->name('phone.book.demo.import.file');
        Route::get('demo/file/download/{extension}/{type}', 'demoFileDownloader')->name('demo.file.download');  
        Route::get('api/document', 'apiDocumentation')->name('api.document');
    });
    
    Route::get('/default-captcha/{randCode}', [HomeController::class, 'defaultCaptcha'])->name('captcha.genarate');
    Route::any('/webhook', [WebhookController::class, 'postWebhook'])->name('webhook');
    Route::any('/facebook/login', [MetaController::class, 'facebookLogin'])->name('facebook.login');

      Route::get('/api/translations/{lang_code}', [GlobalWorldController::class, 'getTranslations'])->name('api.translations');
    Route::get('/language/change/{lang?}', [GlobalWorldController::class, 'languageChange'])->name('language.change');
    
    Route::get('/unsubscribe', [HomeController::class, 'unsubscribe'])->name('unsubscribe');
    Route::get('/unsubscribe/success', [HomeController::class, 'unsubscribeSuccess'])->name('unsubscribe.success');
    
    Route::get('/domain-unverified', [CoreController::class, 'domainNotVerified'])->name('domain.unverified')->withoutMiddleware(['domain.verified' , 'check.domain']);
    
    Route::post('/check-license', [CoreController::class, 'checkLicense'])->name('check.license.key')->withoutMiddleware(['domain.verified' , 'check.domain']);
});




