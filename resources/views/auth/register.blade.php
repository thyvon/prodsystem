@php($header = 'Register')
@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-sm-12 col-md-10 col-lg-8 col-xl-7">
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-header bg-danger text-white text-center">
                    <h3 class="my-2">Create Your Account</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="form-row">
                            <!-- Name -->
                            <div class="form-group col-md-6">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus
                                       class="form-control form-control-lg @error('name') is-invalid @enderror" placeholder="Your full name">
                                <x-input-error :messages="$errors->get('name')" class="mt-1 text-danger" />
                            </div>

                            <!-- Email -->
                            <div class="form-group col-md-6">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="{{ old('email') }}" required
                                       class="form-control form-control-lg @error('email') is-invalid @enderror" placeholder="Your email address">
                                <x-input-error :messages="$errors->get('email')" class="mt-1 text-danger" />
                            </div>

                            <!-- Password -->
                            <div class="form-group col-md-6">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" required
                                       class="form-control form-control-lg @error('password') is-invalid @enderror" placeholder="Choose a password">
                                <x-input-error :messages="$errors->get('password')" class="mt-1 text-danger" />
                            </div>

                            <!-- Confirm Password -->
                            <div class="form-group col-md-6">
                                <label for="password_confirmation">Confirm Password</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" required
                                       class="form-control form-control-lg @error('password_confirmation') is-invalid @enderror" placeholder="Repeat your password">
                                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1 text-danger" />
                            </div>

                            <!-- Telegram ID -->
                            <div class="form-group col-md-6">
                                <label for="telegram_id">Telegram ID</label>
                                <input type="text" id="telegram_id" name="telegram_id" value="{{ old('telegram_id') }}"
                                       class="form-control form-control-lg @error('telegram_id') is-invalid @enderror" placeholder="Telegram ID">
                                <x-input-error :messages="$errors->get('telegram_id')" class="mt-1 text-danger" />
                            </div>

                            <!-- Phone -->
                            <div class="form-group col-md-6">
                                <label for="phone">Phone</label>
                                <input type="text" id="phone" name="phone" value="{{ old('phone') }}"
                                       class="form-control form-control-lg @error('phone') is-invalid @enderror" placeholder="Phone Number">
                                <x-input-error :messages="$errors->get('phone')" class="mt-1 text-danger" />
                            </div>

                            <!-- Card Number -->
                            <div class="form-group col-md-6">
                                <label for="card_number">Card Number</label>
                                <input type="text" id="card_number" name="card_number" value="{{ old('card_number') }}"
                                       class="form-control form-control-lg @error('card_number') is-invalid @enderror" placeholder="Card Number">
                                <x-input-error :messages="$errors->get('card_number')" class="mt-1 text-danger" />
                            </div>

                            <!-- Profile Image -->
                            <div class="form-group col-md-6">
                                <label for="profile_url">Profile Image</label>
                                <input type="file" id="profile_url" name="profile_url"
                                       class="form-control-file @error('profile_url') is-invalid @enderror">
                                <x-input-error :messages="$errors->get('profile_url')" class="mt-1 text-danger" />
                            </div>

                            <!-- Signature Image -->
                            <div class="form-group col-md-6">
                                <label for="signature_url">Signature Image</label>
                                <input type="file" id="signature_url" name="signature_url"
                                       class="form-control-file @error('signature_url') is-invalid @enderror">
                                <x-input-error :messages="$errors->get('signature_url')" class="mt-1 text-danger" />
                            </div>

                            <!-- Building -->
                            <div class="form-group col-md-6">
                                <label for="building_id">Building</label>
                                <select name="building_id" id="building_id" class="form-control form-control-lg">
                                    <option value="">Select Building</option>
                                    @foreach($buildings as $building)
                                        <option value="{{ $building->id }}" {{ old('building_id') == $building->id ? 'selected' : '' }}>
                                            {{ $building->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('building_id')" class="mt-1 text-danger" />
                            </div>

                            <!-- Department -->
                            <div class="form-group col-md-6">
                                <label for="default_department_id">Department</label>
                                <select name="default_department_id" id="default_department_id" class="form-control form-control-lg">
                                    <option value="">Select Department</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}" {{ old('default_department_id') == $department->id ? 'selected' : '' }}>
                                            {{ $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('default_department_id')" class="mt-1 text-danger" />
                            </div>

                            <!-- Campus -->
                            <div class="form-group col-md-6">
                                <label for="default_campus_id">Campus</label>
                                <select name="default_campus_id" id="default_campus_id" class="form-control form-control-lg">
                                    <option value="">Select Campus</option>
                                    @foreach($campuses as $campus)
                                        <option value="{{ $campus->id }}" {{ old('default_campus_id') == $campus->id ? 'selected' : '' }}>
                                            {{ $campus->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('default_campus_id')" class="mt-1 text-danger" />
                            </div>

                            <!-- Position -->
                            <div class="form-group col-md-6">
                                <label for="current_position_id">Position</label>
                                <select name="current_position_id" id="current_position_id" class="form-control form-control-lg">
                                    <option value="">Select Position</option>
                                    @foreach($positions as $position)
                                        <option value="{{ $position->id }}" {{ old('current_position_id') == $position->id ? 'selected' : '' }}>
                                            {{ $position->title }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('current_position_id')" class="mt-1 text-danger" />
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-danger btn-block btn-lg shadow-sm">
                                Register
                            </button>
                        </div>

                        <!-- Login Link -->
                        <div class="text-center mt-3">
                            <small>
                                Already registered? <a href="{{ route('login') }}" class="text-danger font-weight-bold">Log in</a>
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
