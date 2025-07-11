<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $header ?? 'Page' }} | {{ config('app.name', 'Laravel') }}</title>
    <meta name="description" content="Login">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, user-scalable=no, minimal-ui">
    <link rel="stylesheet" href="{{ asset('template/css/vendors.bundle.css') }}">
    <link rel="stylesheet" href="{{ asset('template/css/app.bundle.css') }}">
    <link rel="stylesheet" href="{{ asset('template/css/skins/skin-master.css') }}">
    <link rel="icon" href="{{ asset('template/img/favicon/favicon-32x32.png') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="mod-skin-dark">
    <div class="page-wrapper auth dark">
        <div class="page-inner bg-brand-gradient">
            <div class="page-content-wrapper bg-transparent m-0">
                <div class="height-10 w-100 shadow-lg px-4 bg-brand-gradient">
                    <div class="d-flex align-items-center container p-0">
                        <div class="page-logo width-mobile-auto m-0 align-items-center justify-content-center p-0 bg-transparent bg-img-none shadow-0 height-9 border-0">
                            <a href="javascript:void(0)" class="page-logo-link press-scale-down d-flex align-items-center">
                                <img src="{{ asset('template/img/logo.png') }}" alt="SmartAdmin WebApp">
                                <span class="page-logo-text mr-1">Procurement System</span>
                            </a>
                        </div>
                        <a href="{{url ('/register')}}" class="btn-link text-white ml-auto">
                            Create Account
                        </a>
                    </div>
                </div>
                <div class="flex-1" style="background: url('{{ asset('template/img/svg/pattern-1.svg') }}') no-repeat center bottom fixed; background-size: cover;">
                    <div class="container py-4 py-lg-5 my-lg-5 px-4 px-sm-0">
                        <div class="row">
                            <!-- Left Side Info Panel -->
                            <div class="col col-md-6 col-lg-7 d-none d-md-block">
                                <h2 class="fs-xxl fw-500 mt-4 text-white">
                                    Procurement System
                                    <small class="h3 fw-300 mt-3 mb-5 text-white opacity-60">
                                        Register now if you are new user.
                                    </small>
                                </h2>
                                <div class="d-sm-flex flex-column align-items-center justify-content-center d-md-block">
                                    <div class="px-0 py-1 mt-5 text-white fs-nano opacity-50">
                                        Connect with us
                                    </div>
                                    <div class="d-flex flex-row opacity-70">
                                        <a href="#" class="mr-2 fs-xxl text-white"><i class="fab fa-facebook-square"></i></a>
                                        <a href="#" class="mr-2 fs-xxl text-white"><i class="fab fa-twitter-square"></i></a>
                                        <a href="#" class="mr-2 fs-xxl text-white"><i class="fab fa-google-plus-square"></i></a>
                                        <a href="#" class="mr-2 fs-xxl text-white"><i class="fab fa-linkedin"></i></a>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Side Registration Form -->
                        @yield ('content')
                        </div>

                        <!-- Footer -->
                        <div class="position-absolute pos-bottom pos-left pos-right p-3 text-center text-white">
                            2020 © SmartAdmin by <a href="https://www.gotbootstrap.com" class="text-white opacity-40 fw-500" target="_blank">gotbootstrap.com</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('template/js/vendors.bundle.js') }}"></script>
    <script src="{{ asset('template/js/app.bundle.js') }}"></script>
</body>
</html>
