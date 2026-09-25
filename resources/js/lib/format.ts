import type { Money } from '@/types/catalog';

/** Every currency Comparo trades in has two minor digits. */
const MINOR_PER_MAJOR = 100;

const currencyFormatters = new Map<string, Intl.NumberFormat>();
const numberFormatters = new Map<string, Intl.NumberFormat>();
const dateFormatters = new Map<string, Intl.DateTimeFormat>();

function currencyFormatter(
    locale: string,
    currency: string,
): Intl.NumberFormat {
    const key = `${locale}|${currency}`;
    let formatter = currencyFormatters.get(key);

    if (!formatter) {
        formatter = new Intl.NumberFormat(locale, {
            style: 'currency',
            currency,
        });
        currencyFormatters.set(key, formatter);
    }

    return formatter;
}

/** The one place money becomes text. */
export function formatMoney(money: Money, locale: string): string {
    return currencyFormatter(locale, money.currency).format(
        money.minor / MINOR_PER_MAJOR,
    );
}

export function formatMinor(
    minor: number,
    currency: string,
    locale: string,
): string {
    return formatMoney({ minor, currency }, locale);
}

export function formatNumber(
    value: number,
    locale: string,
    maximumFractionDigits = 0,
): string {
    const key = `${locale}|${maximumFractionDigits}`;
    let formatter = numberFormatters.get(key);

    if (!formatter) {
        formatter = new Intl.NumberFormat(locale, { maximumFractionDigits });
        numberFormatters.set(key, formatter);
    }

    return formatter.format(value);
}

/**
 * Dates are formatted in UTC so the server render and the hydrated client
 * render produce identical text.
 */
export function formatDate(
    iso: string,
    locale: string,
    style: 'medium' | 'short' = 'medium',
): string {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    const key = `${locale}|${style}`;
    let formatter = dateFormatters.get(key);

    if (!formatter) {
        formatter = new Intl.DateTimeFormat(locale, {
            dateStyle: style,
            timeZone: 'UTC',
        });
        dateFormatters.set(key, formatter);
    }

    return formatter.format(date);
}

export function pluralize(
    count: number,
    singular: string,
    plural = `${singular}s`,
): string {
    return count === 1 ? singular : plural;
}
