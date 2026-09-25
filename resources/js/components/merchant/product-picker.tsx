import { useEffect, useId, useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import ProductSearchController from '@/actions/App/Http/Controllers/Merchant/Matching/ProductSearchController';
import {
    FieldError,
    fieldLabel,
    textFieldStyles,
} from '@/components/merchant/form-fields';
import { cn } from '@/lib/utils';
import type { ProductSearchResult } from '@/types/merchant';

const MIN_QUERY = 2;
const DEBOUNCE_MS = 250;

type SearchState = 'idle' | 'loading' | 'done' | 'error';

function isResultList(value: unknown): value is ProductSearchResult[] {
    return Array.isArray(value);
}

/**
 * Search the public catalogue (≤ 20 active products) and pick one; submits
 * `product_id`. Results are buttons (Tab / Enter / Space) and a polite live
 * region announces the result count.
 */
export function MerchantProductPicker({
    initial = null,
    excludeId = null,
    error,
}: {
    initial?: ProductSearchResult | null;
    excludeId?: number | null;
    error?: string;
}) {
    const id = useId();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<ProductSearchResult[]>([]);
    const [state, setState] = useState<SearchState>('idle');
    const [selected, setSelected] = useState<ProductSearchResult | null>(
        initial,
    );
    const timer = useRef<number | null>(null);
    const request = useRef<AbortController | null>(null);

    useEffect(
        () => () => {
            if (timer.current !== null) {
                window.clearTimeout(timer.current);
            }

            request.current?.abort();
        },
        [],
    );

    async function search(term: string): Promise<void> {
        request.current?.abort();
        const controller = new AbortController();
        request.current = controller;

        try {
            const response = await fetch(
                ProductSearchController.index.url({ query: { q: term } }),
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    signal: controller.signal,
                },
            );

            if (!response.ok) {
                throw new Error(`Search failed (${response.status})`);
            }

            const body: unknown = await response.json();
            const data =
                typeof body === 'object' && body !== null && 'data' in body
                    ? body.data
                    : [];

            setResults(isResultList(data) ? data : []);
            setState('done');
        } catch (caught) {
            if (
                caught instanceof DOMException &&
                caught.name === 'AbortError'
            ) {
                return;
            }

            setResults([]);
            setState('error');
        }
    }

    function onChange(event: ChangeEvent<HTMLInputElement>): void {
        const term = event.target.value;
        setQuery(term);

        if (timer.current !== null) {
            window.clearTimeout(timer.current);
        }

        if (term.trim().length < MIN_QUERY) {
            request.current?.abort();
            setResults([]);
            setState('idle');

            return;
        }

        setState('loading');
        timer.current = window.setTimeout(() => {
            void search(term.trim());
        }, DEBOUNCE_MS);
    }

    const visible = results.filter((result) => result.id !== excludeId);
    const statusText = {
        idle: `Type at least ${MIN_QUERY} characters to search.`,
        loading: 'Searching…',
        error: 'Search failed. Try again.',
        done:
            visible.length === 0
                ? 'No catalogue products match.'
                : `${visible.length} ${visible.length === 1 ? 'product' : 'products'} found.`,
    }[state];

    return (
        <div className="flex flex-col gap-2">
            <label htmlFor={`${id}-search`} className={fieldLabel}>
                Search the catalogue (name, brand or EAN)
            </label>
            <input
                id={`${id}-search`}
                type="search"
                autoComplete="off"
                value={query}
                onChange={onChange}
                aria-describedby={`${id}-status${error ? ` ${id}-error` : ''}`}
                className={textFieldStyles}
            />
            <p
                id={`${id}-status`}
                role="status"
                aria-live="polite"
                className="text-xs text-text-3"
            >
                {statusText}
            </p>
            {visible.length > 0 ? (
                <ul
                    aria-label="Catalogue products"
                    className="max-h-56 overflow-y-auto rounded-field border border-line-2"
                >
                    {visible.map((product) => (
                        <li
                            key={product.id}
                            className="border-b border-line-soft last:border-b-0"
                        >
                            <button
                                type="button"
                                aria-pressed={selected?.id === product.id}
                                onClick={() => setSelected(product)}
                                className={cn(
                                    'flex min-h-11 w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-[13px] hover:bg-surface-3',
                                    selected?.id === product.id &&
                                        'bg-acc-tint',
                                )}
                            >
                                <span className="font-bold text-text">
                                    {product.brand} {product.name}
                                </span>
                                <span className="num text-[11.5px] text-text-3">
                                    {product.pack}
                                    {product.ean ? ` · EAN ${product.ean}` : ''}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}
            <input type="hidden" name="product_id" value={selected?.id ?? ''} />
            <p className="text-[13px] text-text-2" aria-live="polite">
                {selected ? (
                    <>
                        Selected:{' '}
                        <strong className="text-text">
                            {selected.brand} {selected.name} · {selected.pack}
                        </strong>
                    </>
                ) : (
                    'No product selected yet.'
                )}
            </p>
            <FieldError id={`${id}-error`} message={error} />
        </div>
    );
}
