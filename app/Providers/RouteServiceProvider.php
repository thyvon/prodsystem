<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });

        Route::model('campus', \App\Models\Campus::class);
        Route::model('building', \App\Models\Building::class);
        Route::model('division', \App\Models\Division::class);
        Route::model('department', \App\Models\Department::class);
        Route::model('tocaPolicy', \App\Models\TocaPolicy::class);
        Route::model('tocaAmount', \App\Models\TocaAmount::class);
        Route::model('mainCategory', \App\Models\MainCategory::class);
        Route::model('subCategory', \App\Models\SubCategory::class);
        Route::model('unitOfMeasure', \App\Models\UnitOfMeasure::class);
        Route::model('product', \App\Models\Product::class);
        Route::model('variantAttribute', \App\Models\VariantAttribute::class);
        Route::model('warehouse', \App\Models\Warehouse::class);
        Route::model('mainStockBeginning', \App\Models\MainStockBeginning::class);
        Route::model('stockRequest', \App\Models\StockRequest::class);
        Route::model('stockIssue', \App\Models\StockIssue::class);
        Route::model('position', \App\Models\Position::class);
        Route::model('stockTransfer', \App\Models\StockTransfer::class);
        Route::model('digitalDocsApproval', \App\Models\DigitalDocsApproval::class);
        Route::model('purchaseRequest', \App\Models\PurchaseRequest::class);
        Route::model('stockIn', \App\Models\StockIn::class);
    }
}
