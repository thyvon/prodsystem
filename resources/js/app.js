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

//Setting Up
 app.component('campus-page', Campus)
 app.component('building-page', BuildingPage)

 app.component('dashboard', Dashboard)
 app.component('datatable', Datatable)

// // User Management
 app.component('user-page', UserPage)
 app.component('role-page', RolePage)
 app.component('permission-page', PermissionPage)

// Mount the Vue app
app.mount('#app')
