// frontend/src/router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import InvoicePage from '../pages/InvoicePage.vue'

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        // Default route -> Invoice Maker
        { path: '/', redirect: '/invoice' },

        // Invoice Maker
        { path: '/invoice', name: 'invoice', component: InvoicePage },
    ],
})

export default router
