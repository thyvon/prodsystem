<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <title>{{ $header ?? 'Page' }} | {{ config('app.name', 'Laravel') }}</title>
    <meta name="description" content="Page Title">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Base CSS -->
    <link id="vendorsbundle" rel="stylesheet" href="{{ asset('template/css/vendors.bundle.css') }}">
    <link id="appbundle" rel="stylesheet" href="{{ asset('template/css/app.bundle.css') }}">
    <link id="myskin" rel="stylesheet" href="{{ asset('template/css/skins/skin-master.css') }}">

    <!-- Page-specific styles -->
    @stack('styles')
    @stack('vite')

    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('template/img/favicon/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="https://mjqeducation.edu.kh/FrontEnd/Image/logo/mjq-education-single-logo_1.ico">
    <link rel="mask-icon" href="{{ asset('template/img/favicon/safari-pinned-tab.svg') }}" color="#5bbad5">

    <!-- Auto Khmer / English Font -->
    <style>
        /* Khmer font for Khmer characters only */
        @font-face {
            font-family: 'KhmerOSBattambang';
            src: url("{{ asset('fonts/KhmerOSBattambang-Regular.ttf') }}") format('truetype');
            unicode-range: U+1780-17FF;
        }

        @font-face {
            font-family: 'Roboto';
            src: url("{{ asset('fonts/Roboto-Regular.ttf') }}") format('truetype');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }

        /* Latin / English system font */
        body {
           font-family: 'Roboto', 'Khmer OS Battambang','Helvetica', 'Arial', sans-serif !important;
        }
    </style>
</head>
<body class="mod-nav-link header-function-fixed footer-function-fixed nav-function-fixed nav-mobile-push mod-clean-page-bg mod-hide-info-card mod-nav-dark desktop chrome webkit pace-done blur">

<div id="preloader" style="position: fixed; z-index: 9999; top: 0; left: 0; width: 100%; height: 100%; background: rgba(30, 30, 45, 0.7); display: flex; align-items: center; justify-content: center;">
    <div class="spinner-border text-light" role="status"><span class="visually-hidden"></span></div>
</div>

<script>
    'use strict';
    const classHolder = document.body;
    const themeSettings = JSON.parse(localStorage.getItem('themeSettings') || '{}');
    const themeURL = themeSettings.themeURL || '';
    const themeOptions = themeSettings.themeOptions || '';

    if (themeOptions) {
        classHolder.className = themeOptions;
        console.log("%c✔ Theme settings loaded", "color: #148f32");
    } else {
        console.log("%c✔ Heads up! Theme settings is empty or does not exist, loading default settings...", "color: #ed1c24");
    }

    if (themeURL) {
        let link = document.getElementById('mytheme') || document.createElement('link');
        link.id = 'mytheme';
        link.rel = 'stylesheet';
        link.href = themeURL;
        document.head.appendChild(link);
    }

    const saveSettings = () => {
        themeSettings.themeOptions = Array.from(classHolder.classList)
            .filter(cls => /^(nav|header|footer|mod|display)-/i.test(cls))
            .join(' ');
        if (document.getElementById('mytheme')) {
            themeSettings.themeURL = document.getElementById('mytheme').getAttribute("href");
        }
        localStorage.setItem('themeSettings', JSON.stringify(themeSettings));
    }

    const resetSettings = () => {
        localStorage.removeItem("themeSettings");
    }

    window.addEventListener('load', () => {
        const preloader = document.getElementById('preloader');
        if (preloader) {
            preloader.style.opacity = '0';
            setTimeout(() => preloader.style.display = 'none', 300);
        }
    });
</script>

<div class="page-wrapper">
    <div class="page-inner">
        @include('layouts.partials.sidebar')

        <div class="page-content-wrapper">
            @include('layouts.partials.navbar')

            <main id="js-page-content" role="main" class="page-content">
                <div id="app">
                    @yield('content')
                </div>
            </main>

            <div class="page-content-overlay" data-action="toggle" data-class="mobile-nav-on"></div>

            @include('layouts.partials.footer')
        </div>
    </div>
</div>

@include('layouts.partials.quick')
@include('layouts.partials.setting')

<!-- Base JS -->
<script src="{{ asset('template/js/vendors.bundle.js') }}"></script>
<script src="{{ asset('template/js/app.bundle.js') }}"></script>

<!-- Page-specific scripts -->
@stack('scripts')
</body>
</html>
