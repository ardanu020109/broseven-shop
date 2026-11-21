import { reactive } from 'vue';
import http from '@/lib/http';

const HEX = /^#[0-9a-fA-F]{6}$/;

const DEFAULT_COMPANY = {
    store_name: 'BroSeven! Shop',
    logo_url: null,
    favicon_url: null,
    address: null,
    phone: null,
    email: null,
    description: null,
    footer_text: null,
};

const branding = reactive({
    loaded: false,
    company: { ...DEFAULT_COMPANY },
});

let pending = null;

function applyColors(colors = {}) {
    const style = document.documentElement.style;
    const map = {
        primary: ['--primary', '--success'],
        secondary: ['--secondary', '--warning'],
        danger: ['--danger'],
        background: ['--background'],
        dark: ['--dark', '--text-primary'],
    };

    for (const [key, variables] of Object.entries(map)) {
        const value = colors[key];

        if (typeof value === 'string' && HEX.test(value)) {
            variables.forEach((variable) => style.setProperty(variable, value));
        }
    }
}

function applyIdentity(company) {
    document.title = company.store_name || DEFAULT_COMPANY.store_name;

    if (company.favicon_url) {
        let link = document.querySelector('link[rel~="icon"]');

        if (!link) {
            link = document.createElement('link');
            link.rel = 'icon';
            document.head.appendChild(link);
        }

        link.href = company.favicon_url;
    }
}

export function loadBranding(force = false) {
    if (pending && !force) {
        return pending;
    }

    pending = http
        .get('/store/info', { skipAuthRedirect: true })
        .then(({ data }) => {
            Object.assign(branding.company, DEFAULT_COMPANY, data.company);
            applyColors(data.colors);
            applyIdentity(branding.company);
        })
        .catch(() => {})
        .finally(() => {
            branding.loaded = true;
        });

    return pending;
}

export function useBranding() {
    return branding;
}
