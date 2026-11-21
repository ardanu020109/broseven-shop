import '../css/app.css';
import '../css/components.css';
import '../css/data.css';
import '../css/store.css';
import '../css/pos.css';
import '../css/inventory.css';
import '../css/admin.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { setUnauthorizedHandler } from '@/lib/http';
import { useAuthStore } from '@/stores/auth';

const pinia = createPinia();
const app = createApp(App);

app.use(pinia).use(router);

setUnauthorizedHandler(() => {
    useAuthStore(pinia).clear();

    const current = router.currentRoute.value;
    const staffArea = current.matched.some((record) => record.meta.requiresAuth);

    if (staffArea && current.name !== 'login') {
        router.push({ name: 'login', query: { redirect: current.fullPath } });
    }
});

app.mount('#app');
