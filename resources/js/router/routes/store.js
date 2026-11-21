export default {
    path: '/',
    component: () => import('@/layouts/StoreLayout.vue'),
    children: [
        { path: '', name: 'store.home', component: () => import('@/pages/storefront/HomePage.vue') },
        { path: 'produk', name: 'store.products', component: () => import('@/pages/storefront/CatalogPage.vue') },
        { path: 'produk/:id(\\d+)', name: 'store.product', component: () => import('@/pages/storefront/ProductShowPage.vue') },
        { path: 'keranjang', name: 'store.cart', component: () => import('@/pages/storefront/CartPage.vue') },
        { path: 'checkout', name: 'store.checkout', component: () => import('@/pages/storefront/CheckoutPage.vue') },
        { path: 'pesanan', name: 'store.orders', component: () => import('@/pages/storefront/OrdersPage.vue') },
        { path: 'pesanan/:orderNumber', name: 'store.order', component: () => import('@/pages/storefront/OrderPage.vue') },
        { path: 'tentang', name: 'store.about', component: () => import('@/pages/storefront/AboutPage.vue') },
    ],
};
