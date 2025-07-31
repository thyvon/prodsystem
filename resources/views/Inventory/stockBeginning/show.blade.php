@php($header = 'Stock Beginning')
@extends('layouts.main')

@section('content')
 

    <div class="card mb-0 shadow">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
            <div class="btn btn-sm btn-outline-success" onclick="window.history.back()"><i class="fal fa-backward"></i></div>
            <div class="btn btn-sm btn-outline-secondary ml-2" onclick="window.print()"><i class="fal fa-print"></i> Print</div>
        </div>
        <div class="card-body">
            <div style="font-family: 'TW Cen MT', 'Khmer OS Content';" class="bg-white p-2">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h4 class="font-weight-bold mb-3 text-dark">ស្តុកដើមគ្រា</h4>
                        <h6 class="font-weight-bold mb-3 text-dark">{{ $mainStockBeginning->warehouse->name ?? 'N/A' }}</h4>
                    </div>
                </div>

                <!-- Line Items Table -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th>Item Code</th>
                                        <th>Product Description</th>
                                        <th>Khmer Name</th>
                                        <th>Unit</th>
                                        <th class="text-right">Unit Price</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-right">Total Value</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($mainStockBeginning->stockBeginnings as $i => $item)
                                        <tr>
                                            <td class="text-center">{{ $i + 1 }}</td>
                                            <td>{{ $item->productVariant->item_code ?? 'N/A' }}</td>
                                            <td>{{ $item->productVariant->product->name ?? 'N/A' }} {{ $item->productVariant->description ?? 'N/A' }}</td>
                                            <td>{{ $item->productVariant->product->khmer_name ?? 'N/A' }}</td>
                                            <td>{{ $item->productVariant->product->unit->name ?? 'N/A' }}</td>
                                            <td class="text-start">{{ $item->unit_price }}</td>
                                            <td class="text-start">{{ $item->quantity }}</td>
                                            <td class="text-start">{{ $item->total_value }}</td>
                                            <td>{{ $item->remarks ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center">No items found.</td>
                                        </tr>
                                    @endforelse

                                    <!-- Summary rows using rowspan + colspan -->
                                    <tr>
                                        <td colspan="6" class="text-right align-middle">Total
                                        </td>
                                        <td class="text-center">{{ $totalQuantity }}</td>
                                        <td class="text-center">{{ $totalValue }}</td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('vite')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
@endpush
