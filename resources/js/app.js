import './bootstrap'
import { createApp } from 'vue'
import axios from 'axios'
// import 'bootstrap/dist/css/bootstrap.min.css';  // Import Bootstrap 5 CSS
// import 'bootstrap';  // Import Bootstrap 5 JS


// Axios configuration
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

// CSRF token from Blade's <meta>
const token = document.querySelector('meta[name="csrf-token"]')
if (token) {
  axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content
} else {
  console.warn('CSRF token not found. Make sure <meta name="csrf-token" content="..."> is present in your layout.')
}

// Expose Axios globally for Blade scripts
window.axios = axios

// Vue Components

//Setting Up
import Campus from './components/Campus/CampusPage.vue'
import BuildingPage from './components/Building/BuildingPage.vue'
import DivisionPage from './components/Division/DivisionPage.vue'
import DepartmentPage from './components/Department/DepartmentPage.vue'
import TocaPage from './components/TocaPolicy/TocaPage.vue'
import TocaAmountPage from './components/TocaPolicy/TocaAmountPage.vue'

//Product Management
import MainCategoryPage from './components/Category/MainCategoryPage.vue'
import SubCategoryPage from './components/Category/SubCategoryPage.vue'
import UnitOfMeasurePage from './components/UnitOfMeasure/UnitOfMeasurePage.vue'
import ProductPage from './components/Product/ProductPage.vue'
import AttributeList from './components/Product/Attribute/AttributeList.vue'
import ProductForm from './components/Product/ProductForm.vue'

//Inventory Management
import WarehousePage from './components/Inventory/Warehouse/WarehouseList.vue'
import InventoryItemList from './components/Inventory/Items/ItemList.vue'
import StockMovement from './components/Inventory/Items/ItemMovement.vue'
import StockBeginningForm from './components/Inventory/StockBeginning/Form.vue'
import StockBeginningList from './components/Inventory/StockBeginning/StockBeginningList.vue'
import StockBeginningShow from './components/Inventory/StockBeginning/Show.vue'

import StockRequestForm from './components/Inventory/StockRequest/Form.vue'
import StockRequestList from './components/Inventory/StockRequest/StockRequestList.vue'
import StockRequestShow from './components/Inventory/StockRequest/Show.vue'

import StockIssueForm from './components/Inventory/StockIssue/Form.vue'
import StockIssueList from './components/Inventory/StockIssue/StockIssueList.vue'

//Approval Management
import ApprovalList from './components/Approval/Index.vue'


//Dashboard
import Dashboard from './components/Dashboard.vue'

// //Reusable Components
import Datatable from './components/Reusable/Datatable.vue'

// //User Management
 import UserPage from './components/UserManagement/User.vue'
 import RolePage from './components/UserManagement/RolePage.vue'
 import PermissionPage from './components/UserManagement/PermissionPage.vue'
 import UserForm from './components/UserManagement/UserForm.vue'
 import PositionList from './components/UserManagement/Position/PositionList.vue'

// Create Vue app instance
 const app = createApp({})

// Register global components

//Setting Up
 app.component('campus-page', Campus)
 app.component('building-page', BuildingPage)
 app.component('division-page', DivisionPage)
 app.component('department-page', DepartmentPage)
 app.component('toca-page', TocaPage)
 app.component('toca-amount-page', TocaAmountPage)

 //Product Management
 app.component('main-category-page', MainCategoryPage)
 app.component('sub-category-page', SubCategoryPage)
 app.component('unit-of-measure-page', UnitOfMeasurePage)
 app.component('product-page', ProductPage)
 app.component('attribute-list', AttributeList)
 app.component('product-form', ProductForm)

 //Inventory Management
 app.component('warehouse-page', WarehousePage)
 app.component('inventory-item-page', InventoryItemList)
 app.component('stock-movement-page', StockMovement)
//========Stock Beginning
 app.component('stock-beginning-form', StockBeginningForm)
 app.component('stock-beginning-list', StockBeginningList)
 app.component('stock-beginning-show', StockBeginningShow)

//========Stock Request
 app.component('stock-request-form', StockRequestForm)
 app.component('stock-request-list', StockRequestList)
 app.component('stock-request-show', StockRequestShow)

 //=======Stock Issue
 app.component('stock-issue-form', StockIssueForm)
 app.component('stock-issue-list', StockIssueList)

 //Approval Management
 app.component('approval-list', ApprovalList)

 app.component('dashboard', Dashboard)
 app.component('datatable', Datatable)

// // User Management
 app.component('user-page', UserPage)
 app.component('role-page', RolePage)
 app.component('permission-page', PermissionPage)
 app.component('user-form', UserForm)
 app.component('position-page', PositionList)

// Mount the Vue app
app.mount('#app')
