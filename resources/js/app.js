import './bootstrap'
import { createApp } from 'vue'
import axios from 'axios'
import { ZiggyVue } from 'ziggy-js';
import { route } from 'ziggy-js';
import {Ziggy} from './ziggy.js';
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
window.route = route;

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

//Inventory Management
import WarehousePage from './components/Inventory/Warehouse/WarehouseList.vue'
import InventoryItemList from './components/Inventory/Items/ItemList.vue'
import StockBeginningCreate from './components/Inventory/StockBeginning/Create.vue'
import StockBeginningList from './components/Inventory/StockBeginning/StockBeginningList.vue'

//Dashboard
import Dashboard from './components/Dashboard.vue'

// //Reusable Components
import Datatable from './components/Reusable/Datatable.vue'

// //User Management
 import UserPage from './components/UserManagement/User.vue'
 import RolePage from './components/UserManagement/RolePage.vue'
 import PermissionPage from './components/UserManagement/PermissionPage.vue'

// Create Vue app instance
 const app = createApp({})

// Register global components


app.use(ZiggyVue, {
  Ziggy,
  ZiggyRoute: route,
});
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

 //Inventory Management
 app.component('warehouse-page', WarehousePage)
 app.component('inventory-item-page', InventoryItemList)
 app.component('stock-beginning-create', StockBeginningCreate)
 app.component('stock-beginning-list', StockBeginningList)

 app.component('dashboard', Dashboard)
 app.component('datatable', Datatable)

// // User Management
 app.component('user-page', UserPage)
 app.component('role-page', RolePage)
 app.component('permission-page', PermissionPage)

// Mount the Vue app
app.mount('#app')
