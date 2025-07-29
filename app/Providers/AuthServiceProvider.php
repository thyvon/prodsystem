<?php

namespace App\Providers;
use App\Models\Campus;
use App\Policies\CampusPolicy;

use App\Models\Building;
use App\Policies\BuildingPolicy;

use App\Models\Division;
use App\Policies\DivisionPolicy;

use App\Models\Department;
use App\Policies\DepartmentPolicy;

use App\Models\TocaPolicy;
use App\Policies\TocaPolicyPolicy;

use App\Models\TocaAmount;
use App\Policies\TocaAmountPolicy;


// Product Management
use App\Models\MainCategory;
use App\Policies\MainCategoryPolicy;
use App\Models\SubCategory;
use App\Policies\SubCategoryPolicy;
use App\Models\UnitOfMeasure;
use App\Policies\UnitOfMeasurePolicy;
use App\Models\Product;
use App\Policies\ProductPolicy;
use App\Models\VariantAttribute;
use App\Policies\ProductVariantAttributePolicy;

//Inventory Management
use App\Models\Warehouse;
use App\Policies\WarehousePolicy;

use App\Models\MainStockBeginning;
use App\Policies\MainStockBeginningPolicy;

use App\Models\StockRequest;
use App\Policies\StockRequestPolicy;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Campus::class => CampusPolicy::class,
        Building::class => BuildingPolicy::class,
        Division::class => DivisionPolicy::class,
        Department::class => DepartmentPolicy::class,
        TocaPolicy::class => TocaPolicyPolicy::class,
        TocaAmount::class => TocaAmountPolicy::class,
        MainCategory::class => MainCategoryPolicy::class,
        SubCategory::class => SubCategoryPolicy::class,
        UnitOfMeasure::class => UnitOfMeasurePolicy::class,
        Product::class => ProductPolicy::class,
        VariantAttribute::class => ProductVariantAttributePolicy::class,
        Warehouse::class => WarehousePolicy::class,
        MainStockBeginning::class => MainStockBeginningPolicy::class,
        StockRequest::class => StockRequestPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
