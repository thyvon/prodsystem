<header class="page-header" role="banner">
    <div class="page-logo">
        <a href="#" class="page-logo-link press-scale-down d-flex align-items-center position-relative" data-toggle="modal" data-target="#modal-shortcut">
            <img src="{{ asset('template/img/logo.png') }}" alt="PROD System">
            <span class="page-logo-text mr-1">PROD System</span>
            <i class="fal fa-angle-down d-inline-block ml-1 fs-lg color-primary-300"></i>
        </a>
    </div>

    <div class="hidden-md-down dropdown-icon-menu position-relative">
        <a href="#" class="header-btn btn js-waves-off" data-action="toggle" data-class="nav-function-hidden" title="Hide Navigation">
            <i class="ni ni-menu"></i>
        </a>
        <ul>
            <li>
                <a href="#" class="btn js-waves-off" data-action="toggle" data-class="nav-function-minify" title="Minify Navigation">
                    <i class="ni ni-minify-nav"></i>
                </a>
            </li>
            <li>
                <a href="#" class="btn js-waves-off" data-action="toggle" data-class="nav-function-fixed" title="Lock Navigation">
                    <i class="ni ni-lock-nav"></i>
                </a>
            </li>
        </ul>
    </div>

    <div class="hidden-lg-up">
        <a href="#" class="header-btn btn press-scale-down" data-action="toggle" data-class="mobile-nav-on">
            <i class="ni ni-menu"></i>
        </a>
    </div>

    <div class="ml-auto d-flex">
        <div class="hidden-sm-up">
            <a href="#" class="header-icon" data-action="toggle" data-class="mobile-search-on" data-focus="search-field" title="Search">
                <i class="fal fa-search"></i>
            </a>
        </div>

        <div class="hidden-md-down">
            <a href="#" class="header-icon" data-toggle="modal" data-target=".js-modal-settings">
                <i class="fal fa-cog"></i>
            </a>
        </div>

        <div>
            <a href="#" class="header-icon" data-toggle="dropdown" title="Notifications">
                <i class="fal fa-bell"></i>
                <span class="badge badge-icon">11</span>
            </a>
            <div class="dropdown-menu dropdown-menu-animated dropdown-xl">
                <div class="dropdown-header bg-trans-gradient d-flex justify-content-center align-items-center rounded-top mb-2">
                    <h4 class="m-0 text-center text-white">11 New <small class="opacity-80">User Notifications</small></h4>
                </div>
                <div class="py-2 px-3 bg-faded d-block rounded-bottom text-right">
                    <a href="#" class="fs-xs fw-500 ml-auto">View all notifications</a>
                </div>
            </div>
        </div>

        <div>
            <a href="#" data-toggle="dropdown" title="{{ Auth::user()->email }}" class="header-icon d-flex align-items-center justify-content-center ml-2">
                <img src="{{ asset('template/img/demo/avatars/avatar-admin.png') }}" class="profile-image rounded-circle" alt="User Avatar">
            </a>
            <div class="dropdown-menu dropdown-menu-animated dropdown-lg">
                <div class="dropdown-header bg-trans-gradient d-flex flex-row py-4 rounded-top">
                    <div class="d-flex flex-row align-items-center text-white">
                        <img src="{{ asset('template/img/demo/avatars/avatar-admin.png') }}" class="rounded-circle profile-image mr-2" alt="User Avatar">
                        <div>
                            <div class="fs-lg text-truncate">{{ Auth::user()->name }}</div>
                            <span class="opacity-80">{{ Auth::user()->email }}</span>
                        </div>
                    </div>
                </div>

                <div class="dropdown-divider m-0"></div>
                <a href="#" class="dropdown-item" data-action="app-reset">Reset Layout</a>
                <a href="#" class="dropdown-item" data-toggle="modal" data-target=".js-modal-settings">Settings</a>
                <div class="dropdown-divider m-0"></div>
                <a href="#" class="dropdown-item" data-action="app-fullscreen">Fullscreen <i class="float-right text-muted">F11</i></a>
                <a href="#" class="dropdown-item" data-action="app-print">Print <i class="float-right text-muted">Ctrl + P</i></a>

                <div class="dropdown-divider m-0"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item fw-500 pt-3 pb-3">
                        Logout <span class="float-right fw-n">&commat;{{ Auth::user()->name }}</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
