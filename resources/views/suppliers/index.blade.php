@extends('layouts.app')

@section('content')
    <h1>Suppliers</h1>
    <a href="{{ route('suppliers.create') }}">Add Supplier</a>
    <ul>
        @foreach ($suppliers as $supplier)
            <li>{{ $supplier->name }} 
                <a href="{{ route('suppliers.edit', $supplier) }}">Edit</a>
                <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit">Delete</button>
                </form>
            </li>
        @endforeach
    </ul>
@endsection
