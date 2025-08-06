@php($header = 'Register')
@extends('layouts.app')

@section('content')
<!-- Registration Form Container -->
<div class="col-sm-12 col-md-10 col-lg-8 col-xl-7 mx-auto">
    <h1 class="text-white fw-300 mb-3 d-sm-block d-md-none">Create an account</h1>
    <div class="card p-4 rounded-plus bg-faded">
        <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
            @csrf

            <div class="row">
                <!-- Left Column -->
                <div class="col-md-6">
                    <!-- Name -->
                    <div class="form-group">
                        <label for="name" class="form-label text-white">Name</label>
                        <input type="text" id="name" name="name" class="form-control form-control-lg" value="{{ old('name') }}" required autofocus placeholder="Your full name">
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <!-- Email -->
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

                    <!-- Telegram ID -->
                    <div class="form-group">
                        <label for="telegram_id" class="form-label text-white">Telegram ID</label>
                        <input type="text" id="telegram_id" name="telegram_id" class="form-control form-control-lg" value="{{ old('telegram_id') }}" placeholder="Telegram ID">
                        <x-input-error :messages="$errors->get('telegram_id')" class="mt-2" />
                    </div>

                    <!-- Phone -->
                    <div class="form-group">
                        <label for="phone" class="form-label text-white">Phone</label>
                        <input type="text" id="phone" name="phone" class="form-control form-control-lg" value="{{ old('phone') }}" placeholder="Phone Number">
                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-md-6">
                    <!-- Card Number -->
                    <div class="form-group">
                        <label for="card_number" class="form-label text-white">Card Number</label>
                        <input type="text" id="card_number" name="card_number" class="form-control form-control-lg" value="{{ old('card_number') }}" placeholder="Card Number">
                        <x-input-error :messages="$errors->get('card_number')" class="mt-2" />
                    </div>

                    <!-- Profile Image -->
                    <div class="form-group">
                        <label for="profile_url" class="form-label text-white">Profile Image</label>
                        <input type="file" id="profile_url" name="profile_url" class="form-control form-control-lg">
                        <x-input-error :messages="$errors->get('profile_url')" class="mt-2" />
                    </div>

                    <!-- Signature Image -->
                    <div class="form-group">
                        <label for="signature_url" class="form-label text-white">Signature Image</label>
                        <input type="file" id="signature_url" name="signature_url" class="form-control form-control-lg">
                        <x-input-error :messages="$errors->get('signature_url')" class="mt-2" />
                    </div>

                    <!-- Building -->
                    <div class="form-group">
                        <label for="building_id" class="form-label text-white">Building</label>
                        <select name="building_id" id="building_id" class="form-control form-control-lg">
                            <option value="">Select Building</option>
                            @foreach($buildings as $building)
                                <option value="{{ $building->id }}" {{ old('building_id') == $building->id ? 'selected' : '' }}>
                                    {{ $building->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('building_id')" class="mt-2" />
                    </div>

                    <!-- Department -->
                    <div class="form-group">
                        <label for="default_department_id" class="form-label text-white">Default Department</label>
                        <select name="default_department_id" id="default_department_id" class="form-control form-control-lg">
                            <option value="">Select Department</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" {{ old('default_department_id') == $department->id ? 'selected' : '' }}>
                                    {{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('default_department_id')" class="mt-2" />
                    </div>

                    <!-- Campus -->
                    <div class="form-group">
                        <label for="default_campus_id" class="form-label text-white">Default Campus</label>
                        <select name="default_campus_id" id="default_campus_id" class="form-control form-control-lg">
                            <option value="">Select Campus</option>
                            @foreach($campuses as $campus)
                                <option value="{{ $campus->id }}" {{ old('default_campus_id') == $campus->id ? 'selected' : '' }}>
                                    {{ $campus->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('default_campus_id')" class="mt-2" />
                    </div>

                    <!-- Position -->
                    <div class="form-group">
                        <label for="current_position_id" class="form-label text-white">Current Position</label>
                        <select name="current_position_id" id="current_position_id" class="form-control form-control-lg">
                            <option value="">Select Position</option>
                            @foreach($positions as $position)
                                <option value="{{ $position->id }}" {{ old('current_position_id') == $position->id ? 'selected' : '' }}>
                                    {{ $position->title }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('current_position_id')" class="mt-2" />
                    </div>
                </div>
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
