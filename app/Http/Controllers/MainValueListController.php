<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Warehouse;
use App\Models\Campus;
use App\Models\Division;
use App\Models\Department;
use App\Models\UnitOfMeasure;
use App\Models\MainCategory;
use App\Models\SubCategory;
use App\Models\User;

class MainValueListController extends Controller
{
    public function getCampuses(Request $request)
    {
        $campuses = Campus::where('is_active', 1)->get();

        return $campuses->map(fn($c) => [
            'id'   => $c->id,
            'text' => $c->short_name, // Select2 needs "text"
        ]);
    }

    public function getUoms(Request $request)
    {
        $uoms = UnitOfMeasure::where('is_active', 1)->get();

        return $uoms->map(fn($u) => [
            'id'   => $u->id,
            'text' => $u->short_name, // Select2 needs "text"
        ]);
    }

    public function getMainCategories(Request $request)
    {
        $categories = MainCategory::where('is_active', 1)->get();

        return $categories->map(fn($c) => [
            'id'   => $c->id,
            'text' => $c->name, // Select2 needs "text"
        ]);
    }

    public function getSubCategories(Request $request)
    {
        $subCategories = SubCategory::where('is_active', 1)->get();

        return $subCategories->map(fn($sc) => [
            'id'   => $sc->id,
            'main_category_id' => $sc->main_category_id,
            'text' => $sc->name, // Select2 needs "text"
        ]);
    }

    public function getWarehouses(Request $request)
    {
        $warehouses = Warehouse::where('is_active', 1)->get();

        return $warehouses->map(fn($w) => [
            'id'   => $w->id,
            'text' => $w->name, // Select2 needs "text"
        ]);
    }

    public function getDepartments(Request $request)
    {
        $departments = Department::where('is_active', 1)->get();

        return $departments->map(fn($d) => [
            'id'   => $d->id,
            'text' => $d->short_name, // Select2 needs "text"
        ]);
    }

    public function getDivisions(Request $request)
    {
        $divisions = Division::where('is_active', 1)->get();

        return $divisions->map(fn($d) => [
            'id'   => $d->id,
            'text' => $d->short_name, // Select2 needs "text"
        ]);
    }

    public function getUsers(Request $request)
    {
        $requesters = User::where('is_active', 1)->get();

        return $requesters->map(fn($r) => [
            'id'   => $r->id,
            'text' => $r->name, // Select2 needs "text"
        ]);
    }
}
