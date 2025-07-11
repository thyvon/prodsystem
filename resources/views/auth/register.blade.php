@php($header = 'Register')
@extends('layouts.app')

@section('content')
<!-- Right Side Registration Form -->
<div class="col-sm-12 col-md-6 col-lg-5 col-xl-4 ml-auto">
    <h1 class="text-white fw-300 mb-3 d-sm-block d-md-none">Create an account</h1>
    <div class="card p-4 rounded-plus bg-faded">
        <form method="POST" action="{{ route('register') }}">
            @csrf

            <!-- Name -->
            <div class="form-group">
                <label for="name" class="form-label text-white">Name</label>
                <input type="text" id="name" name="name" class="form-control form-control-lg" value="{{ old('name') }}" required autofocus placeholder="Your full name">
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <!-- Email Address -->
            <div class="form-group">
                <label for="email" class="form-label text-white">Email</label>
                <input type="email" id="email" name="email" class="form-control form-control-lg" value="{{ old('email') }}" required placeholder="Your email address">
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <!-- Password -->
            <div class="form-group">
                <label for="password" class="form-label text-white">Password</label>
                <input type="password" id="password" name="password" class="form-control form-control-lg" required placeholder="Choose a password">
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
                <label for="password_confirmation" class="form-label text-white">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control form-control-lg" required placeholder="Repeat your password">
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <!-- Submit -->
            <div class="row no-gutters mt-4">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-danger btn-block btn-lg">Register</button>
                </div>
            </div>

            <!-- Login Link -->
            <div class="text-center mt-3">
                <a class="text-white opacity-70" href="{{ route('login') }}">
                    Already registered? Log in
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
