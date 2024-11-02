<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function index()
    {
        return Inertia::render('Supplier', [
            'suppliers' => Supplier::all()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required']);
        Supplier::create($request->all());
        return redirect()->route('suppliers.index');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return redirect()->route('suppliers.index');
    }
}
