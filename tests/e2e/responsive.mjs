import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';

const BASE = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const WIDTHS = [360, 768, 1280];
const OUT = 'storage/e2e';

mkdirSync(OUT, { recursive: true });

const PUBLIC_PAGES = ['/', '/produk', '/keranjang', '/checkout', '/pesanan', '/tentang', '/login'];

const ROLES = [
    {
        name: 'superadmin',
        login: process.env.E2E_SUPERADMIN_USER ?? 'superadmin',
        password: process.env.E2E_SUPERADMIN_PASSWORD ?? '',
        pages: [
            '/superadmin/dashboard', '/superadmin/users', '/superadmin/products', '/superadmin/stock-history',
            '/superadmin/reports/revenue', '/superadmin/reports/inventory', '/superadmin/reports/cashiers',
            '/superadmin/monitor', '/superadmin/profil', '/superadmin/settings/company', '/superadmin/settings/colors',
        ],
    },
    {
        name: 'kasir',
        login: process.env.E2E_KASIR_USER ?? 'kasir1',
        password: process.env.E2E_KASIR_PASSWORD ?? 'password',
        pages: ['/kasir/dashboard', '/kasir/pos', '/kasir/retur', '/kasir/riwayat', '/kasir/laporan', '/kasir/profil'],
    },
    {
        name: 'admin_master',
        login: process.env.E2E_ADMIN_USER ?? 'adminmaster1',
        password: process.env.E2E_ADMIN_PASSWORD ?? 'password',
        pages: [
            '/admin-master/dashboard', '/admin-master/products', '/admin-master/products/create',
            '/admin-master/stock-history', '/admin-master/reports/inventory', '/admin-master/master/categories',
            '/admin-master/master/units', '/admin-master/profil',
        ],
    },
];

const problems = [];
let checked = 0;

async function inspect(page, role, width, path) {
    const errors = [];
    const onConsole = (message) => {
        if (message.type() !== 'error') return;
        if (path === '/login' && message.text().includes('401')) return;
        errors.push(message.text());
    };
    const onPageError = (error) => errors.push(`pageerror: ${error.message}`);

    page.on('console', onConsole);
    page.on('pageerror', onPageError);

    try {
        await page.goto(BASE + path, { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForTimeout(300);

        const overflow = await page.evaluate(() => {
            const width = window.innerWidth;
            const extra = document.documentElement.scrollWidth - width;
            const offenders = [];

            if (extra > 1) {
                for (const el of document.querySelectorAll('body *')) {
                    const rect = el.getBoundingClientRect();
                    if (rect.right > width + 1 && rect.width > 0 && !el.closest('.table-wrap')) {
                        offenders.push(`${el.tagName.toLowerCase()}${el.className ? '.' + String(el.className).split(' ')[0] : ''}`);
                        if (offenders.length >= 3) break;
                    }
                }
            }

            return { extra, offenders };
        });

        const label = `${role}${path === '/' ? '-home' : path.replaceAll('/', '_')}-${width}.png`;
        await page.screenshot({ path: `${OUT}/${label}` });
        checked += 1;

        if (overflow.extra > 1) {
            problems.push(`[${role} ${width}px] ${path}: halaman melebar ${overflow.extra}px ${overflow.offenders.join(', ')}`);
        }

        for (const error of errors) {
            problems.push(`[${role} ${width}px] ${path}: konsol error: ${error.slice(0, 160)}`);
        }
    } catch (error) {
        problems.push(`[${role} ${width}px] ${path}: gagal dibuka: ${error.message.split('\n')[0]}`);
    } finally {
        page.off('console', onConsole);
        page.off('pageerror', onPageError);
    }
}

async function login(page, account) {
    await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
    await page.fill('#login', account.login);
    await page.fill('#password', account.password);

    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 15000 }),
        page.click('button[type=submit]'),
    ]);
}

const browser = await chromium.launch();

for (const width of WIDTHS) {
    const publicContext = await browser.newContext({ viewport: { width, height: 800 } });
    const publicPage = await publicContext.newPage();

    for (const path of PUBLIC_PAGES) {
        await inspect(publicPage, 'publik', width, path);
    }

    await publicContext.close();

    for (const account of ROLES) {
        const context = await browser.newContext({ viewport: { width, height: 800 } });
        const page = await context.newPage();

        try {
            await login(page, account);
        } catch (error) {
            problems.push(`[${account.name} ${width}px] login gagal (${account.login}): ${error.message.split('\n')[0]}`);
            await context.close();
            continue;
        }

        for (const path of account.pages) {
            await inspect(page, account.name, width, path);
        }

        await context.close();
    }
}

await browser.close();

console.log(`\nHalaman diperiksa: ${checked}. Tangkapan layar: ${OUT}/`);

if (problems.length === 0) {
    console.log('HASIL: LULUS (tidak ada luapan horizontal dan tidak ada error konsol)');
    process.exit(0);
}

console.log(`HASIL: ${problems.length} MASALAH`);
problems.forEach((problem) => console.log(' - ' + problem));
process.exit(1);