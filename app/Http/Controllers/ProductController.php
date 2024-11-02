<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index()
    {
        return Inertia::render('Product', [
            'products' => Product::with('supplier')->get(),
            'suppliers' => Supplier::all()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required', 'price' => 'required']);
        Product::create($request->all());
        return redirect()->route('products.index');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('products.index');
    }
}
