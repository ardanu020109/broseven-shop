export const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export function pad(value) {
    return String(value).padStart(2, '0');
}

export function todayString(date = new Date()) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function defaultPeriod(type = 'month') {
    const date = new Date();
    const month = `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;

    return {
        type,
        date: todayString(date),
        month,
        year: date.getFullYear(),
        from: `${month}-01`,
        to: todayString(date),
    };
}

export function isValidPeriod(period) {
    switch (period.type) {
        case 'day':
            return /^\d{4}-\d{2}-\d{2}$/.test(period.date);
        case 'month':
            return /^\d{4}-\d{2}$/.test(period.month);
        case 'year':
            return Number.isInteger(period.year) && period.year >= 2000 && period.year <= 2100;
        case 'range':
            return /^\d{4}-\d{2}-\d{2}$/.test(period.from)
                && /^\d{4}-\d{2}-\d{2}$/.test(period.to)
                && period.from <= period.to;
        default:
            return false;
    }
}

export function periodParams(period) {
    switch (period.type) {
        case 'day':
            return { period: 'day', date: period.date };
        case 'month':
            return { period: 'month', month: period.month };
        case 'year':
            return { period: 'year', year: period.year };
        default:
            return { period: 'range', from: period.from, to: period.to };
    }
}

export function bucketLabel(key, granularity) {
    if (granularity === 'hour') {
        return pad(key);
    }

    if (granularity === 'month') {
        return MONTHS_SHORT[Number(key.slice(5, 7)) - 1];
    }

    return key.slice(8, 10);
}

export function bucketTitle(key, granularity) {
    if (granularity === 'hour') {
        return `Jam ${pad(key)}:00`;
    }

    const month = MONTHS_SHORT[Number(key.slice(5, 7)) - 1];

    if (granularity === 'month') {
        return `${month} ${key.slice(0, 4)}`;
    }

    return `${Number(key.slice(8, 10))} ${month} ${key.slice(0, 4)}`;
}

function formatDate(value) {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(`${value}T00:00:00`));
}

export function describePeriod(period) {
    if (!period) {
        return '';
    }

    if (period.type === 'month') {
        return `${MONTHS_SHORT[Number(period.from.slice(5, 7)) - 1]} ${period.from.slice(0, 4)}`;
    }

    if (period.type === 'year') {
        return `Tahun ${period.from.slice(0, 4)}`;
    }

    return period.from === period.to ? formatDate(period.from) : `${formatDate(period.from)} - ${formatDate(period.to)}`;
}
