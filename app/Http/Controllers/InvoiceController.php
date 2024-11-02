<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceController extends Controller
{

    public function index()
    {
    $invoices = Invoice::where('user_id', auth()->id())->get();
    // dd($invoices); 

    return Inertia::render('Invoices/Index', [
        'invoices' => $invoices,
    ]);
    }

    public function create()
    {
        $products = Product::all(); // Get all products for the dropdown
        return Inertia::render('Invoices/Create', [
            'products' => $products,
        ]);
    }

    public function show(Invoice $invoice)
    {
    // Optionally, add additional logic for viewing a single invoice
    return Inertia::render('Invoices/Show', [
        'invoice' => $invoice,
    ]);
    }

    public function destroy(Invoice $invoice)
    {
    $this->authorize('delete', $invoice);
    $invoice->delete();

    return redirect()->route('invoices.index')->with('success', 'Invoice deleted successfully!');
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        // Calculate total
        $total = 0;
        foreach ($request->items as $item) {
            $product = Product::find($item['product_id']);
            $total += $product->price * $item['quantity'];
        }

        $invoice = Invoice::create([
            'user_id' => auth()->id(),
            'total' => $total,
        ]);

        return redirect()->route('invoices.create')->with('success', 'Invoice created successfully!');
    }
}
