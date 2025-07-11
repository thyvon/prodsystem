<?php

namespace App\Providers;
use App\Models\Campus;
use App\Policies\CampusPolicy;
use App\Models\Building;
use App\Policies\BuildingPolicy;

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
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
