@extends('layouts.main')

@section('title', 'Unauthorized')

@section('content')
    <div class="container text-center mt-5">
        <!-- Lock Icon -->
        <img src="{{ asset('template/img/errors/403-lock.png') }}" alt="Access Denied" class="img-fluid mb-4" style="max-width: 180px;">

        <h1 class="display-4 text-danger">403 - Access Denied</h1>
        <p class="lead">You are not authorized to access this page.</p>

        <a href="{{ url()->previous() }}" class="btn btn-primary mt-3">
            <i class="fal fa-arrow-left"></i> Go Back
        </a>
    </div>
@endsection
