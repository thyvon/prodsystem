@php($header = 'Dashboard')
@extends('layouts.main')

@section('content')
    <div id="app">
        <dashboard message="You're logged in!"></dashboard>
    </div>
@endsection

@push('scripts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@endpush