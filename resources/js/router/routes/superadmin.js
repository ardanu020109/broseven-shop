export default {
    path: '/superadmin',
    component: () => import('@/layouts/DashboardLayout.vue'),
    meta: { requiresAuth: true, roles: ['superadmin'] },
    children: [
        {
            path: 'dashboard',
            name: 'superadmin.dashboard',
            component: () => import('@/pages/superadmin/SuperadminDashboard.vue'),
            meta: { breadcrumb: ['Superadmin', 'Dashboard'] },
        },
        {
            path: 'users',
            name: 'superadmin.users',
            component: () => import('@/pages/superadmin/UsersPage.vue'),
            meta: { breadcrumb: ['Superadmin', 'Manajemen User'] },
        },
        {
            path: 'products',
            name: 'superadmin.products',
            component: () => import('@/pages/products/ProductListPage.vue'),
            meta: { breadcrumb: ['Superadmin', 'Data Produk'] },
        },
        {
            path: 'products/:id(\\d+)',
            name: 'superadmin.products.show',
            component: () => import('@/pages/products/ProductDetailPage.vue'),
            meta: { breadcrumb: ['Superadmin', 'Data Produk', 'Detail'] },
        },
        {
            path: 'stock-history',
            name: 'superadmin.stock-history',
            component: () => import('@/pages/inventory/StockLedgerPage.vue'),
            meta: { breadcrumb: ['Superadmin', 'Riwayat Stok'] },
        },
        {
            path: 'reports/revenue',
            name: 'superadmin.reports.revenue',
            component: () => import('@/pages/superadmin/RevenueReportPage.vue'),
            meta: { breadcrumb: ['Superadmin', 'Laporan', 'Pendapatan'] },
        },
        {
            path: 'reports/inventory',
            name: 'superadmin.reports.inventory',
            component: () => import('@/pages/reports/InventoryReportPage.vue'),
            meta: { breadcrumb: ['Superadmin', 'Laporan', 'Barang'] },
        },
        {
            path: 'reports/cashiers',
            name: 'superadmin.reports.cashiers',
            component: () => import('@/pages/superadmin/CashierReportPage.vue'),
            meta: { breadcrumb: ['Superadmin', 'Laporan', 'Kasir'] },
        },
        {
            path: 'monitor',
            name: 'superadmin.monitor',
            component: () => import('@/pages/superadmin/MonitorPage.vue'),
            meta: { breadcrumb: ['Superadmin', 'Pelayanan Kasir'] },
        },
        {
            path: 'profil',
            name: 'superadmin.profile',
            component: () => import('@/pages/settings/ProfilePage.vue'),
            meta: { breadcrumb: ['Superadmin', 'Pengaturan', 'Profil'] },
        },
        {
            path: 'settings/company',
            name: 'superadmin.settings.company',
            component: () => import('@/pages/settings/CompanySettingsPage.vue'),
            meta: { breadcrumb: ['Superadmin', 'Pengaturan', 'Profil Perusahaan'] },
        },
        {
            path: 'settings/colors',
            name: 'superadmin.settings.colors',
            component: () => import('@/pages/settings/ColorSettingsPage.vue'),
            meta: { breadcrumb: ['Superadmin', 'Pengaturan', 'Warna Website'] },
        },
    ],
};
