<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::where('user_id', auth()->id())
            ->with('products', 'supplier')
            ->get();
        
        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
        ]);
    }

    public function create()
    {
        $products = Product::all();
        $suppliers = Supplier::all();

        return Inertia::render('Invoices/Create', [
            'products' => $products,
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
    
        DB::beginTransaction();
    
        try {
            // Calculate total
            $total = 0;
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $total += $product->price * $item['quantity'];
            }
    
            // Create the invoice
            $invoice = Invoice::create([
                'user_id' => auth()->id(),
                'supplier_id' => $request->supplier_id,
                'total' => $total,
            ]);
    
            // Attach products to the invoice
            foreach ($request->items as $item) {
                $invoice->products()->attach($item['product_id'], ['quantity' => $item['quantity']]);
            }
    
            DB::commit();
    
            return redirect()->route('invoices.index')->with('success', 'Invoice created successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to create invoice: ' . $e->getMessage());
        }
    }

    public function edit(Invoice $invoice)
    {
        $invoice->load('products:id,name,price'); // Load related products
        $products = Product::all();
        $suppliers = Supplier::all();

        return Inertia::render('Invoices/Edit', [
            'invoice' => $invoice,
            'products' => $products,
            'suppliers' => $suppliers,
        ]);
    }

public function update(Request $request, Invoice $invoice)
{
    $request->validate([
        'supplier_id' => 'required|exists:suppliers,id',
        'products' => 'required|array',
        'products.*.id' => 'required|exists:products,id',
        'products.*.quantity' => 'required|integer|min:1',
        'total' => 'required|numeric|min:0', // Validate total
    ]);

    DB::beginTransaction();

    try {
        // Update supplier and total
        $invoice->supplier_id = $request->supplier_id;
        $invoice->total = $request->total;  // Update the total amount
        $invoice->save();

        // Detach existing products
        $invoice->products()->detach();

        // Attach new products
        foreach ($request->products as $productData) {
            $invoice->products()->attach($productData['id'], ['quantity' => $productData['quantity']]);
        }

        DB::commit();
        return redirect()->route('invoices.index')->with('success', 'Invoice updated successfully!');
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Failed to update invoice: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Failed to update invoice: ' . $e->getMessage());
    }
}
    public function show(Invoice $invoice)
        {
            $invoice->load(['products', 'supplier']); // Ensure related data is loaded
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
}
