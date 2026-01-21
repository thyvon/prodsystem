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
import WarehouseProductList from './components/Inventory/Warehouse/WarehouseProduct/Index.vue'
//Stock Management
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
import StockIssueItemList from './components/Inventory/StockIssue/StockIssueItem.vue'

// Debit Note
// import DebitNoteForm from './components/Inventory/StockIssue/DebitNote/Form.vue'
import DebitNoteList from './components/Inventory/StockIssue/DebitNote/Index.vue'
// import DebitNoteShow from './components/Inventory/StockIssue/DebitNote/Show.vue'

import StockTransferForm from './components/Inventory/StockTransfer/Form.vue'
import StockTransferList from './components/Inventory/StockTransfer/StockTransferList.vue'
import StockTransferShow from './components/Inventory/StockTransfer/Show.vue'

import StockInForm from './components/Inventory/StockIn/Form.vue'
import StockInList from './components/Inventory/StockIn/StockInList.vue'
import StockInItemList from './components/Inventory/StockIn/StockInItemList.vue'

// Report
import StockReport from './components/Inventory/StockReport/Report.vue'
import StockReportForm from './components/Inventory/StockReport/Form.vue'
import MonthlyStockReportShow from './components/Inventory/StockReport/Show.vue'

import MonthlyStockReport from './components/Inventory/StockReport/MonthlyReport.vue'

import WarehouseProductReportForm from './components/Inventory/StockReport/WarehouseReport/Form.vue'
import WarehouseProductReportShow from './components/Inventory/StockReport/WarehouseReport/Show.vue'
import WarehouseProductReportList from './components/Inventory/StockReport/WarehouseReport/ReportList.vue'
import StockOnhandByWarehouse from './components/Inventory/StockReport/WarehouseReport/StockByWh.vue'

import StockCountForm from './components/Inventory/StockCount/Form.vue'
import StockCountList from './components/Inventory/StockCount/StockCountList.vue'
import StockCountShow from './components/Inventory/StockCount/Show.vue'

//Approval Management
import ApprovalList from './components/Approval/Index.vue'
import MyRequests from './components/Approval/MyRequests.vue'


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

 // Document Transfer
 import DocumentTransferForm from './components/DocumentTransfer/Form.vue'
import DocumentTransferList from './components/DocumentTransfer/DocsList.vue'

// Digital Document Approval
import DigitalDocsApprovalForm from './components/Approval/DigitalApprovalForm.vue'
import DigitalDocsApprovalList from './components/Approval/DigitalApprovalList.vue'
import DigitalDocsApprovalShow from './components/Approval/DigitalApprovalShow.vue'

// Purchase Request Management
import PurchaseRequestForm from './components/PurchaseRequest/Form.vue'
import PurchaseRequestList from './components/PurchaseRequest/List.vue'
import PurchaseRequestShow from './components/PurchaseRequest/Show.vue'

// Debit Note Email List
import DebitNoteEmailList from './components/Inventory/DebitNote/DebitNoteEmailList.vue'

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
 // WH management
 app.component('warehouse-page', WarehousePage)
 app.component('warehouse-product-list', WarehouseProductList)
 // Stock Management
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
 app.component('stock-issue-item-list', StockIssueItemList)

  // Debit Note
//  app.component('debit-note-form', DebitNoteForm)
 app.component('debit-note-list', DebitNoteList)
//  app.component('debit-note-show', DebitNoteShow)

  //=======Stock Transfer
 app.component('stock-transfer-form', StockTransferForm)
 app.component('stock-transfer-list', StockTransferList)
 app.component('stock-transfer-show', StockTransferShow)

 //=======Stock In
 app.component('stock-in-form', StockInForm)
 app.component('stock-in-list', StockInList)
 app.component('stock-in-item-list', StockInItemList)

 //=======Stock Report
 app.component('stock-report', StockReport)
 app.component('stock-report-form', StockReportForm)
 app.component('stock-monthly-report', MonthlyStockReport)
 app.component('monthly-stock-report-show', MonthlyStockReportShow)
 app.component('stock-onhand-by-warehouse', StockOnhandByWarehouse)

 // Stock Report Attach PR
 app.component('wh-product-report-form', WarehouseProductReportForm)
 app.component('wh-product-report-show', WarehouseProductReportShow)
 app.component('wh-product-report-list', WarehouseProductReportList)

 //=======Stock Count
 app.component('stock-count-form', StockCountForm)
 app.component('stock-count-list', StockCountList)
 app.component('stock-count-show', StockCountShow)

 //Approval Management
 app.component('approval-list', ApprovalList)
 app.component('my-requests', MyRequests)

 app.component('dashboard', Dashboard)
 app.component('datatable', Datatable)

// User Management
 app.component('user-page', UserPage)
 app.component('role-page', RolePage)
 app.component('permission-page', PermissionPage)
 app.component('user-form', UserForm)
 app.component('position-page', PositionList)

 // Document Transfer
 app.component('document-transfer-form', DocumentTransferForm)
 app.component('document-transfer-list', DocumentTransferList)

 // Digital Document Approval
app.component('digital-docs-approval-form', DigitalDocsApprovalForm)
app.component('digital-docs-approval-list', DigitalDocsApprovalList)
app.component('digital-docs-approval-show', DigitalDocsApprovalShow)

// Purchase Request Management
app.component('purchase-request-form', PurchaseRequestForm)
app.component('purchase-request-list', PurchaseRequestList)
app.component('purchase-request-show', PurchaseRequestShow)

// Debit Note Email List
app.component('debit-note-email-list', DebitNoteEmailList)

// Mount the Vue app
app.mount('#app')
