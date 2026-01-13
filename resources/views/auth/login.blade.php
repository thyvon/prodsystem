@php($header = 'Login')
@extends('layouts.app')

@section('content')
    <!-- Right Side (Login Form) -->
    <div class="col-sm-12 col-md-6 col-lg-5 col-xl-4 ml-auto">
        <h1 class="text-white fw-300 mb-3 d-sm-block d-md-none">Secure login</h1>
        <div class="card p-4 rounded-plus bg-faded">
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <!-- Email Address -->
                <div class="form-group">
                    <label for="email" class="form-label text-white">Email</label>
                    <input type="email" id="email" name="email" class="form-control form-control-lg" value="{{ old('email') }}" required autofocus placeholder="Your email address">
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password" class="form-label text-white">Password</label>
                    <input type="password" id="password" name="password" class="form-control form-control-lg" required placeholder="Your password">
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <!-- Remember Me -->
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="remember_me" name="remember">
                        <label class="custom-control-label text-white" for="remember_me"> Remember me for the next 30 days</label>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="row no-gutters">
                    <div class="col-lg-6 pr-lg-1 my-2">
                        <a href="{{ route('microsoft.login') }}" class="btn btn-info btn-block btn-lg">
                            Sign in with <i class="fab fa-microsoft"></i>
                        </a>
                    </div>
                    <div class="col-lg-6 pl-lg-1 my-2">
                        <button type="submit" class="btn btn-danger btn-block btn-lg">
                            Login <i class="fas fa-lock"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
