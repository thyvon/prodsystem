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

// User Management
use App\Models\Position;
use App\Policies\PositionPolicy;


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

use App\Models\StockIssue;
use App\Policies\StockIssuePolicy;

use App\Models\StockTransfer;
use App\Policies\StockTransferPolicy;

use App\Models\DigitalDocsApproval;
use App\Policies\DigitalDocsApprovalPolicy;

use App\Models\PurchaseRequest;
use App\Policies\PurchaseRequestPolicy;

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
        Position::class => PositionPolicy::class,
        StockRequest::class => StockRequestPolicy::class,
        StockIssue::class => StockIssuePolicy::class,
        StockTransfer::class => StockTransferPolicy::class,
        DigitalDocsApproval::class => DigitalDocsApprovalPolicy::class,
        PurchaseRequest::class => PurchaseRequestPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
