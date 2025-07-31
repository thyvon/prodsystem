@php($header = 'Stock Beginning')
@extends('layouts.main')

@section('content')
<div class="card mb-0 shadow">
    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
        <button class="btn btn-sm btn-outline-success" onclick="window.history.back()">
            <i class="fal fa-backward"></i> Back
        </button>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
            <i class="fal fa-print"></i> Print
        </button>
    </div>
    <div class="card-body">
        <div style="font-family: 'TW Cen MT', 'Khmer OS Content';" class="bg-white p-3">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12 text-center">
                    <h4 class="font-weight-bold text-dark">ស្តុកដើមគ្រា</h4>
                    <h6 class="font-weight-bold text-dark">{{ $mainStockBeginning->warehouse->name ?? 'N/A' }}</h6>
                </div>
            </div>

            <!-- Line Items Table -->
            <div class="row">
                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center">#</th>
                                    <th>Item Code</th>
                                    <th>Product Description</th>
                                    <th>Khmer Name</th>
                                    <th>Unit</th>
                                    <th class="text-right">Unit Price</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-right">Total Value</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($mainStockBeginning->stockBeginnings as $i => $item)
                                    <tr>
                                        <td class="text-center">{{ $i + 1 }}</td>
                                        <td>{{ $item->productVariant->item_code ?? 'N/A' }}</td>
                                        <td>
                                            {{ $item->productVariant->product->name ?? 'N/A' }}
                                            {{ $item->productVariant->description ?? '' }}
                                        </td>
                                        <td>{{ $item->productVariant->product->khmer_name ?? 'N/A' }}</td>
                                        <td>{{ $item->productVariant->product->unit->name ?? 'N/A' }}</td>
                                        <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                                        <td class="text-center">{{ number_format($item->quantity, 2) }}</td>
                                        <td class="text-right">{{ number_format($item->total_value, 2) }}</td>
                                        <td>{{ $item->remarks ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center">No items found.</td>
                                    </tr>
                                @endforelse
                                <!-- Summary Row -->
                                <tr class="table-secondary">
                                    <td colspan="6" class="text-right font-weight-bold">Total</td>
                                    <td class="text-center font-weight-bold">{{ number_format($totalQuantity, 2) }}</td>
                                    <td class="text-right font-weight-bold">{{ number_format($totalValue, 2) }}</td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Approval Information -->
            <div class="row mt-4">
                <div class="col-12">
                    <h5 class="font-weight-bold text-dark mb-3">Approval Information</h5>

                    <!-- Approvals Section -->
                    <h6 class="font-weight-bold text-dark mb-2">Approvals</h6>
                    <div class="row">
                        @forelse ($approvals as $i => $approval)
                            <div class="col-12 col-md-3 mb-3">
                                <div class="border rounded p-3 bg-light h-100">
                                    <p class="mb-1"><strong>#{{ $i + 1 }} - Request Type:</strong> {{ ucfirst($approval['request_type']) }}</p>
                                    <p class="mb-1"><strong>Status:</strong> {{ ucfirst($approval['approval_status']) }}</p>
                                    <p class="mb-1"><strong>Responder:</strong> {{ $approval['responder_name'] }}</p>
                                    <p class="mb-1"><strong>Comment:</strong> {{ $approval['comment'] ?? '-' }}</p>
                                    <p class="mb-1"><strong>Created At:</strong> {{ $approval['created_at'] ?? 'N/A' }}</p>
                                    <p class="mb-1"><strong>Updated At:</strong> {{ $approval['updated_at'] ?? 'N/A' }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="border rounded p-3 bg-light">
                                    <p class="mb-0 text-center">No approvals available.</p>
                                </div>
                            </div>
                        @endforelse
                    </div>

                    <!-- Responders Section -->
                    <h6 class="font-weight-bold text-dark mb-2 mt-4">Assigned Responders</h6>
                    <div class="row">
                        @forelse ($responders as $i => $responder)
                            <div class="col-12 col-md-3 mb-3">
                                <div class="border rounded p-3 bg-light h-100">
                                    <p class="mb-1"><strong>#{{ $i + 1 }} - Request Type:</strong> {{ ucfirst($responder['request_type']) }}</p>
                                    <p class="mb-1"><strong>Responder:</strong> {{ $responder['name'] }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="border rounded p-3 bg-light">
                                    <p class="mb-0 text-center">No assigned responders available.</p>
                                </div>
                            </div>
                        @endforelse
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