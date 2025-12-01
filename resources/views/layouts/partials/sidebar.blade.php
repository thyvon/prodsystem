<aside class="page-sidebar mod-skin-dark">
    <div class="page-logo">
        <a href="#" class="page-logo-link press-scale-down d-flex align-items-center position-relative" data-toggle="modal" data-target="#modal-shortcut">
            <img src="https://mjqeducation.edu.kh/FrontEnd/Image/logo/mjq-education-single-logo_1.ico" alt="SmartAdmin WebApp" aria-roledescription="logo">
            <span class="page-logo-text mr-1 fw-700">PROD System</span>
            <span class="position-absolute text-white opacity-50 small pos-top pos-right mr-2 mt-n2"></span>
        </a>
    </div>
    <!-- BEGIN PRIMARY NAVIGATION -->
    <nav id="js-primary-nav" class="primary-nav" role="navigation">
        <div class="nav-filter">
            <div class="position-relative">
                <input type="text" id="nav_filter_input" placeholder="Filter menu" class="form-control" tabindex="0">
                <a href="#" onclick="return false;" class="btn-primary btn-search-close js-waves-off" data-action="toggle" data-class="list-filter-active" data-target=".page-sidebar">
                    <i class="fal fa-chevron-up"></i>
                </a>
            </div>
        </div>
        <div class="info-card">
            <img src="{{ asset('storage/'.auth()->user()->profile_url) }}" class="profile-image rounded-circle" alt="{{ auth()->user()->name }}" width="128" height="128">
            <div class="info-card-text">
                <a href="#" class="d-flex align-items-center text-white">
                    <span class="text-truncate text-truncate-sm d-inline-block">
                        {{ auth()->user()->name}}
                    </span>
                </a>
                <span class="d-inline-block text-truncate text-truncate-sm">{{ auth()->user()->defaultPosition()?->short_title ?? 'N/A' }}</span>
            </div>
            <img src="{{asset ('template/img/card-backgrounds/cover-2-lg.png') }}" class="cover" alt="cover">
            <a href="#" onclick="return false;" class="pull-trigger-btn" data-action="toggle" data-class="list-filter-active" data-target=".page-sidebar" data-focus="nav_filter_input">
                <i class="fal fa-angle-down"></i>
            </a>
        </div>
        <!--
        TIP: The menu items are not auto translated. You must have a residing lang file associated with the menu saved inside dist/media/data with reference to each 'data-i18n' attribute.
        -->
        <ul id="js-nav-menu" class="nav-menu">
            @php
                $approvalActive = request()->is('approvals*');
            @endphp
            <li class="{{ $approvalActive ? 'active' : '' }}">
                <a href="{{ url('approvals') }}" title="Notifications" data-filter-tags="notifications">
                    <i class="fal fa-bell"></i>
                    <span class="nav-link-text">Notifications</span>
                    @if(isset($pendingApprovalCount) && $pendingApprovalCount > 0)
                        <span class="badge badge-danger ml-1">{{ $pendingApprovalCount }}</span>
                    @endif
                </a>
            </li>
            @php
                $dashboardActive = request()->is('/');
            @endphp
            <li class="{{ $dashboardActive ? 'active' : '' }}">
                <a href="{{ url('/') }}" title="Dashboard" data-filter-tags="blank page">
                    <i class="fal fa-chart-pie"></i>
                    <span class="nav-link-text">Dashboard</span>
                </a>
            </li>
            @php
                $digitalDocumentActive = request()->is('digital-docs-approvals*');
            @endphp
            <li class="{{ $digitalDocumentActive ? 'active' : '' }}">
                <a href="{{ url('digital-docs-approvals') }}" title="Digital Approvals" data-filter-tags="digital approvals">
                    <i class="fal fa-stamp"></i>
                    <span class="nav-link-text">Digital Approvals</span>
                </a>
            </li>
            @php
                $documentActive = request()->is('document-transfers');
            @endphp
            <li class="{{ $documentActive ? 'active' : '' }}">
                <a href="{{ url('document-transfers') }}" title="Document Transfers" data-filter-tags="document transfers">
                    <i class="fal fa-file-alt"></i>
                    <span class="nav-link-text">Document Transfers</span>
                </a>
            </li>

            @php
                $productActive = request()->is('products*') || 
                request()->is('sub-categories*') || 
                request()->is('main-categories*') || 
                request()->is('unit-of-measures*') ||
                request()->is('product-variant-attributes*');
            @endphp
            <li class="{{ $productActive ? 'active open' : '' }}">
                <a href="#" title="Product" data-filter-tags="product">
                    <i class="fal fa-box"></i>
                    <span class="nav-link-text">Product</span>
                </a>
                <ul>
                    <li class="{{ request()->is('products*') ? 'active' : '' }}">
                        <a href="{{ url('products') }}" title="Products" data-filter-tags="products">
                            <span class="nav-link-text">Products</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('main-categories*') ? 'active' : '' }}">
                        <a href="{{ url('main-categories') }}" title="Categories" data-filter-tags="categories">
                            <span class="nav-link-text">Main Categories</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('sub-categories*') ? 'active' : '' }}">
                        <a href="{{ url('sub-categories') }}" title="Sub Categories" data-filter-tags="sub categories">
                            <span class="nav-link-text">Sub Categories</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('unit-of-measures*') ? 'active' : '' }}">
                        <a href="{{ url('unit-of-measures') }}" title="Unit of Measures" data-filter-tags="unit of measures">
                            <span class="nav-link-text">Unit of Measures</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('product-variant-attributes*') ? 'active' : '' }}">
                        <a href="{{ url('product-variant-attributes') }}" title="Product Variant Attributes" data-filter-tags="product variant attributes">
                            <span class="nav-link-text">Product Attributes</span>
                        </a>    
                    </li>
                </ul>
            </li>

            @php
                $supplierActive = request()->is('suppliers*');
            @endphp
            <li class="{{ $supplierActive ? 'active open' : '' }}">
                <a href="#" title="Supplier" data-filter-tags="supplier">
                    <i class="fal fa-truck"></i>
                    <span class="nav-link-text">Supplier</span>
                </a>
                <ul>
                    <li class="{{ request()->is('suppliers/list') ? 'active' : '' }}">
                        <a href="{{ url('suppliers/list') }}" title="Supplier List" data-filter-tags="supplier list">
                            <span class="nav-link-text">Supplier List</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('suppliers/create') ? 'active' : '' }}">
                        <a href="{{ url('suppliers/create') }}" title="Add Supplier" data-filter-tags="add supplier">
                            <span class="nav-link-text">Add Supplier</span>
                        </a>
                    </li>
                </ul>
            </li>

            @php
                $PurchasingActive = request()->is('purchase-requests*')
                || request()->is('purchase-orders*');
            @endphp
            <!-- <li class="nav-title">Purchasing</li> -->
            <li class="{{ $PurchasingActive ? 'active open' : '' }}">
                <a href="#" title="Purchasing" data-filter-tags="purchasing">
                    <i class="fal fa-shopping-basket"></i>
                    <span class="nav-link-text">Purchasing</span>
                </a>
                <ul>
                    <li class="{{ request()->is('purchase-requests*') ? 'active' : '' }}">
                        <a href="{{ url('purchase-requests') }}" title="Purchase Request" data-filter-tags="purchase request">
                            <span class="nav-link-text">Purchase Request</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('purchase-orders*') ? 'active open' : '' }}">
                        <a href="#" title="Purchase Order" data-filter-tags="purchase order">
                            <span class="nav-link-text">Purchase Order</span>
                        </a>
                        <ul>
                            <li class="{{ request()->is('purchase-orders/list') ? 'active' : '' }}">
                                <a href="{{ url('purchase-orders/list') }}" title="Purchase Orders List" data-filter-tags="purchase orders list">
                                    <span class="nav-link-text">Purchase Orders List</span>
                                </a>
                            </li>
                            <li class="{{ request()->is('purchase-orders/supplier-evaluation') ? 'active' : '' }}">
                                <a href="{{ url('purchase-orders/supplier-evaluation') }}" title="Supplier Evaluation" data-filter-tags="supplier evaluation">
                                    <span class="nav-link-text">Supplier Evaluation</span>
                                </a>
                            </li>
                            <li class="{{ request()->is('purchase-orders/verbal-quotation') ? 'active' : '' }}">
                                <a href="{{ url('purchase-orders/verbal-quotation') }}" title="Verbal Quotation" data-filter-tags="verbal quotation">
                                    <span class="nav-link-text">Verbal Quotation</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="{{ request()->is('cash-requests*') ? 'active' : '' }}">
                        <a href="#" title="Cash Request" data-filter-tags="cash request">
                            <span class="nav-link-text">Cash Request</span>
                        </a>
                    </li>
                </ul>
            </li>

            @php
                $inventoryActive = request()->is('inventory/warehouses*') 
                || request()->is('inventory/items*') 
                || request()->is('inventory/stock-movements*')
                || request()->is('inventory/stock-beginnings*')
                || request()->is('inventory/stock-requests*')
                || request()->is('inventory/stock-issues*')
                || request()->is('inventory/stock-issue/items*')
                || request()->is('inventory/stock-ins*')
                || request()->is('inventory/stock-in/items*')
                || request()->is('inventory/stock-transfers*')
                || request()->is('inventory/stock-counts*');
            @endphp

            @if (auth()->user()->hasAnyRole(['admin', 'stock']) || auth()->user()->hasPermissionTo('product.view'))
                <li class="nav-title">Inventory</li>
                <li class="{{ $inventoryActive ? 'active open' : '' }}">
                    <a href="#" title="Inventory" data-filter-tags="inventory">
                        <i class="fal fa-warehouse"></i>
                        <span class="nav-link-text">Inventory</span>
                    </a>
                    <ul>
                        @can('warehouse.view') <!-- Optional permission check -->
                            <li class="{{ request()->is('inventory/warehouses') ? 'active' : '' }}">
                                <a href="{{ url('inventory/warehouses') }}" title="Warehouse List" data-filter-tags="warehouse list">
                                    <span class="nav-link-text">Warehouse</span>
                                </a>
                            </li>
                        @endcan
                        @can('product.view')
                            <li class="{{ request()->is('inventory/items') ? 'active' : '' }}">
                                <a href="{{ url('inventory/items') }}" title="Inventory List" data-filter-tags="inventory list">
                                    <span class="nav-link-text">Item List</span>
                                </a>
                            </li>
                        @endcan
                        @can('product.view')
                            <li class="{{ request()->is('inventory/stock-movements') ? 'active' : '' }}">
                                <a href="{{ url('inventory/stock-movements') }}" title="Stock Movements" data-filter-tags="stock movements">
                                    <span class="nav-link-text">Stock Movements</span>
                                </a>
                            </li>
                        @endcan
                        @can('mainStockBeginning.view')
                            <li class="{{ request()->is('inventory/stock-beginnings*') ? 'active' : '' }}">
                                <a href="{{ url('inventory/stock-beginnings') }}" title="Stock Beginning" data-filter-tags="stock beginning">
                                    <span class="nav-link-text">Stock Beginning</span>
                                </a>
                            </li>
                        @endcan
                        @can('stockIn.view')
                            <li class="{{ request()->is('inventory/stock-ins*') || request()->is('inventory/stock-in/items*') ? 'active' : '' }}">
                                <a href="{{ url('inventory/stock-ins') }}" title="Stock Receipt" data-filter-tags="stock receipt">
                                    <span class="nav-link-text">Stock Receipt</span>
                                </a>
                            </li>
                        @endcan
                        @can('stockRequest.view')
                            <li class="{{ request()->is('inventory/stock-requests*') ? 'active' : '' }}">
                                <a href="{{ url('inventory/stock-requests') }}" title="Stock Request" data-filter-tags="stock request">
                                    <span class="nav-link-text">Stock Request</span>
                                </a>
                            </li>
                        @endcan
                        @can('stockIssue.view')
                            <li class="{{ request()->is('inventory/stock-issues*') || request()->is('inventory/stock-issue/items*') ? 'active' : '' }}">
                                <a href="{{ url('inventory/stock-issues') }}" title="Stock Issues" data-filter-tags="stock issues">
                                    <span class="nav-link-text">Stock Issues</span>
                                </a>
                            </li>
                        @endcan
                        @can('stockTransfer.view')
                            <li class="{{ request()->is('inventory/stock-transfers*') ? 'active' : '' }}">
                                <a href="{{ url('inventory/stock-transfers') }}" title="Stock Transfers" data-filter-tags="stock transfers">
                                    <span class="nav-link-text">Stock Transfers</span>
                                </a>
                            </li>
                        @endcan
                        @can('stockCount.view')
                            <li class="{{ request()->is('inventory/stock-counts*') ? 'active' : '' }}">
                                <a href="{{ url('inventory/stock-counts') }}" title="Physical Count" data-filter-tags="physical count">
                                    <span class="nav-link-text">Physical Count</span>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </li>
            @endif

            @php
                $reportActive = request()->is('inventory/stock-report*')
                    || request()->is('inventory/stock-reports*');
            @endphp
            <li class="nav-title">Reports</li>
            <li class="{{ $reportActive ? 'active open' : '' }}">
                <a href="#" title="Reports" data-filter-tags="reports">
                    <i class="fal fa-chart-line"></i>
                    <span class="nav-link-text">Stock Report</span>
                </a>
                <ul>
                    <li class="{{ request()->is('inventory/stock-reports') ? 'active' : '' }}">
                        <a href="{{ url('inventory/stock-reports') }}" title="Stock Report" data-filter-tags="stock report" data-filter-tags="stock report">
                            <span class="nav-link-text">Tracking Report</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('inventory/stock-reports/monthly-report') ? 'active' : '' }}">
                        <a href="{{ url('inventory/stock-reports/monthly-report') }}" title="Stock Report" data-filter-tags="stock report" data-filter-tags="stock report">
                            <span class="nav-link-text">Monthly Report</span>
                        </a>
                    </li>
                    <!-- <li class="{{ request()->is('reports/details') ? 'active' : '' }}">
                        <a href="{{ url('reports/details') }}" title="Detailed Report" data-filter-tags="detailed report">
                            <span class="nav-link-text">PR Summary</span>
                        </a>
                    </li> -->
                    <!-- Add more report links as needed -->
                </ul>
            </li>

            @php
                $settingActive = request()->is('settings*') 
                    || request()->is('currency*')
                    || request()->is('campus*')
                    || request()->is('buildings*')
                    || request()->is('divisions*')
                    || request()->is('departments*')
                    || request()->is('positions*')
                    || request()->is('toca-policies*')
                    || request()->is('toca-amounts*')
                    || request()->is('mvl*');
            @endphp
            <li class="nav-title">System Setting</li>
            <li class="{{ $settingActive ? 'active open' : '' }}">
                <a href="#" title="Setting" data-filter-tags="Setting">
                    <i class="fal fa-cog"></i>
                    <span class="nav-link-text">Setting Up</span>
                </a>
                <ul>
                    <li class="{{ request()->is('currency*') ? 'active' : '' }}">
                        <a href="{{ url('currency') }}" title="Currency/Exchange Rate" data-filter-tags="currency exchange rate">
                            <span class="nav-link-text">Currency/Exchange Rate</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('campus*') ? 'active' : '' }}">
                        <a href="{{ url('campuses') }}" title="Campus" data-filter-tags="campus">
                            <span class="nav-link-text">Campus</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('buildings*') ? 'active' : '' }}">
                        <a href="{{ url('buildings') }}" title="Building" data-filter-tags="building">
                            <span class="nav-link-text">Building</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('divisions*') ? 'active' : '' }}">
                        <a href="{{ url('divisions') }}" title="Division" data-filter-tags="division">
                            <span class="nav-link-text">Division</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('departments*') ? 'active' : '' }}">
                        <a href="{{ url('departments') }}" title="Department" data-filter-tags="department">
                            <span class="nav-link-text">Department</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('positions*') ? 'active' : '' }}">
                        <a href="{{ url('positions') }}" title="Position" data-filter-tags="position">
                            <span class="nav-link-text">Position</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('toca-policies*') || request()->is('toca-amounts*') ? 'active open' : '' }}">
                        <a href="#" title="TOCA Policies" data-filter-tags="toca policies">
                            <span class="nav-link-text">TOCA Policies</span>
                        </a>
                        <ul>
                            <li class="{{ request()->is('toca-policies') ? 'active' : '' }}">
                                <a href="{{ url('toca-policies') }}" title="TOCA Policies List" data-filter-tags="toca policies list">
                                    <span class="nav-link-text">TOCA Policies List</span>
                                </a>
                            </li>
                            <li class="{{ request()->is('toca-amounts') ? 'active' : '' }}">
                                <a href="{{ url('toca-amounts') }}" title="TOCA Amounts" data-filter-tags="toca amounts">
                                    <span class="nav-link-text">TOCA Amount</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="{{ request()->is('mvl*') ? 'active' : '' }}">
                        <a href="{{ url('mvl') }}" title="MVL" data-filter-tags="mvl">
                            <span class="nav-link-text">MVL</span>
                        </a>
                    </li>
                </ul>
            </li>
            @php
                $userManagementActive = request()->is('roles*') || request()->is('permissions*') || request()->is('users*');
            @endphp
            <!-- <li class="nav-title">User Management</li> -->
            <li class="{{ $userManagementActive ? 'active open' : '' }}">
                <a href="#" title="User Management" data-filter-tags="user management">
                    <i class="fal fa-users-cog"></i>
                    <span class="nav-link-text">User Management</span>
                </a>
                <ul>
                    <li class="{{ request()->is('roles*') ? 'active' : '' }}">
                        <a href="{{ route('roles.index') }}" title="Roles" data-filter-tags="roles">
                            <span class="nav-link-text">Roles</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('permissions*') ? 'active' : '' }}">
                        <a href="{{ route('permissions.index') }}" title="Permissions" data-filter-tags="permissions">
                            <span class="nav-link-text">Permissions</span>
                        </a>
                    </li>
                    <li class="{{ request()->is('users*') ? 'active' : '' }}">
                        <a href="{{ route('users.index') }}" title="Users" data-filter-tags="users">
                            <span class="nav-link-text">Users</span>
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
        <div class="filter-message js-filter-message bg-success-600"></div>
    </nav>
    <!-- END PRIMARY NAVIGATION -->
</aside>
