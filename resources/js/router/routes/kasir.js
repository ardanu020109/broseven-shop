export default {
    path: '/kasir',
    component: () => import('@/layouts/DashboardLayout.vue'),
    meta: { requiresAuth: true, roles: ['kasir'] },
    children: [
        {
            path: 'dashboard',
            name: 'kasir.dashboard',
            component: () => import('@/pages/cashier/CashierDashboard.vue'),
            meta: { breadcrumb: ['Kasir', 'Dashboard'] },
        },
        {
            path: 'pos',
            name: 'kasir.pos',
            component: () => import('@/pages/cashier/PosPage.vue'),
            meta: { breadcrumb: ['Kasir', 'Pelayanan / POS'] },
        },
        {
            path: 'retur',
            name: 'kasir.returns',
            component: () => import('@/pages/cashier/ReturnPage.vue'),
            meta: { breadcrumb: ['Kasir', 'Retur Barang'] },
        },
        {
            path: 'riwayat',
            name: 'kasir.history',
            component: () => import('@/pages/cashier/HistoryPage.vue'),
            meta: { breadcrumb: ['Kasir', 'Riwayat Transaksi'] },
        },
        {
            path: 'laporan',
            name: 'kasir.report',
            component: () => import('@/pages/cashier/CashierSelfReportPage.vue'),
            meta: { breadcrumb: ['Kasir', 'Laporan Saya'] },
        },
        {
            path: 'profil',
            name: 'kasir.profile',
            component: () => import('@/pages/settings/ProfilePage.vue'),
            meta: { breadcrumb: ['Kasir', 'Pengaturan', 'Profil'] },
        },
    ],
};
