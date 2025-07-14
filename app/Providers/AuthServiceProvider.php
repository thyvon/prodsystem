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
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
