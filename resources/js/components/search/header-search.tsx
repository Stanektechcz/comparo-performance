import { router } from '@inertiajs/react';
import { Search as SearchIcon } from 'lucide-react';
import type { FormEvent, KeyboardEvent } from 'react';
import { useEffect, useId, useRef, useState } from 'react';
import { textSearchUrl } from '@/components/search/search-url';
import { useMarket } from '@/hooks/use-shared-props';
import { pluralize } from '@/lib/format';
import { cn } from '@/lib/utils';
import { suggest } from '@/routes/api/public/v1/search';
import { search } from '@/routes';
import type { SuggestResponse } from '@/types/search';

const DEBOUNCE_MS = 200;
const MIN_LENGTH = 2;
const MAX_LENGTH = 64;

type SuggestOption = {
    key: string;
    label: string;
    detail: string | null;
    url: string;
};

type SuggestGroup = { key: string; label: string; options: SuggestOption[] };

function toGroups(data: SuggestResponse['data']): SuggestGroup[] {
    const groups: SuggestGroup[] = [
        {
            key: 'products',
            label: 'Products',
            options: data.products.map((item) => ({
                key: `product-${item.slug}`,
                label: item.name,
                detail: `${item.brand} · ${item.packLabel}`,
                url: item.url,
            })),
        },
        {
            key: 'brands',
            label: 'Brands',
            options: data.brands.map((item) => ({
                key: `brand-${item.slug}`,
                label: item.name,
                detail: null,
                url: item.url,
            })),
        },
        {
            key: 'categories',
            label: 'Categories',
            options: data.categories.map((item) => ({
                key: `category-${item.slug}`,
                label: item.name,
                detail: null,
                url: item.url,
            })),
        },
        {
            key: 'ingredients',
            label: 'Ingredients',
            options: data.ingredients.map((item) => ({
                key: `ingredient-${item.slug}`,
                label: item.name,
                detail: null,
                url: item.url,
            })),
        },
        {
            key: 'shops',
            label: 'Shops',
            options: data.merchants.map((item) => ({
                key: `shop-${item.slug}`,
                label: item.name,
                detail: null,
                url: item.url,
            })),
        },
    ];

    return groups.filter((group) => group.options.length > 0);
}

/**
 * Header search: an ARIA 1.2 combobox (input + listbox popup) with grouped,
 * debounced suggestions from the public suggest API. Arrow keys move the
 * active option (aria-activedescendant), Enter opens it — or runs a full
 * search when no option is active — and Escape closes the list (a second
 * Escape clears the text). Without JavaScript it is a plain GET form to
 * /search, so it is SSR-safe.
 */
export function HeaderSearch({ className }: { className?: string }) {
    const market = useMarket();
    const id = useId();
    const listId = `${id}-listbox`;
    const inputRef = useRef<HTMLInputElement>(null);
    const [text, setText] = useState('');
    const [groups, setGroups] = useState<SuggestGroup[]>([]);
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(-1);
    const [status, setStatus] = useState('');

    const options = groups.flatMap((group) => group.options);
    const trimmed = text.trim();
    const expanded = open && trimmed.length >= MIN_LENGTH;
    const optionId = (index: number) => `${id}-option-${index}`;

    useEffect(() => {
        if (trimmed.length < MIN_LENGTH) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(
                suggest.url({
                    query: {
                        q: trimmed.slice(0, MAX_LENGTH),
                        market: market.code,
                    },
                }),
                {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                },
            )
                .then((response) =>
                    response.ok
                        ? (response.json() as Promise<SuggestResponse>)
                        : Promise.reject(new Error(String(response.status))),
                )
                .then((body) => {
                    const next = toGroups(body.data);
                    const count = next.reduce(
                        (sum, group) => sum + group.options.length,
                        0,
                    );
                    setGroups(next);
                    setActive(-1);
                    setStatus(
                        count === 0
                            ? 'No suggestions. Press Enter to search.'
                            : `${count} ${pluralize(count, 'suggestion')} available. Use the arrow keys to browse.`,
                    );
                })
                .catch((error: unknown) => {
                    if (
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    ) {
                        return;
                    }

                    setGroups([]);
                    setStatus(
                        'Suggestions are unavailable. Press Enter to search.',
                    );
                });
        }, DEBOUNCE_MS);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [trimmed, market.code]);

    const close = () => {
        setOpen(false);
        setActive(-1);
    };

    const go = (url: string) => {
        close();
        inputRef.current?.blur();
        router.visit(url);
    };

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (expanded && active >= 0 && options[active]) {
            go(options[active].url);

            return;
        }

        if (trimmed.length > 0) {
            go(textSearchUrl(trimmed));
        }
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                setOpen(true);
                setActive((current) =>
                    options.length === 0 ? -1 : (current + 1) % options.length,
                );
                break;
            case 'ArrowUp':
                event.preventDefault();
                setOpen(true);
                setActive((current) =>
                    options.length === 0
                        ? -1
                        : current <= 0
                          ? options.length - 1
                          : current - 1,
                );
                break;
            case 'Escape':
                if (expanded) {
                    event.preventDefault();
                    close();
                } else if (text !== '') {
                    event.preventDefault();
                    setText('');
                }
                break;
            default:
                break;
        }
    };

    const offsets = groups.map((_, groupIndex) =>
        groups
            .slice(0, groupIndex)
            .reduce((sum, group) => sum + group.options.length, 0),
    );

    return (
        <form
            role="search"
            action={search.url()}
            method="get"
            onSubmit={onSubmit}
            className={cn('relative', className)}
        >
            <label htmlFor={`${id}-input`} className="sr-only">
                Search products, brands, shops and ingredients
            </label>
            <SearchIcon
                aria-hidden="true"
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-text-4"
            />
            <input
                ref={inputRef}
                id={`${id}-input`}
                name="q"
                type="search"
                role="combobox"
                autoComplete="off"
                spellCheck={false}
                maxLength={200}
                placeholder="Search products, brands, shops…"
                aria-expanded={expanded}
                aria-controls={listId}
                aria-autocomplete="list"
                aria-activedescendant={
                    expanded && active >= 0 ? optionId(active) : undefined
                }
                value={text}
                onChange={(event) => {
                    setText(event.target.value);
                    setOpen(true);
                    setActive(-1);

                    if (event.target.value.trim().length < MIN_LENGTH) {
                        setGroups([]);
                        setStatus('');
                    }
                }}
                onFocus={() => setOpen(true)}
                onBlur={close}
                onKeyDown={onKeyDown}
                className="min-h-10 w-full rounded-field border border-line-2 bg-field pr-3 pl-9 text-[14px] text-text placeholder:text-text-4 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-focus"
            />
            <ul
                id={listId}
                role="listbox"
                aria-label="Search suggestions"
                hidden={!expanded || options.length === 0}
                className="absolute inset-x-0 top-full z-50 mt-1.5 max-h-[min(70vh,480px)] overflow-y-auto rounded-card border border-line bg-surface p-1.5 shadow-lg"
            >
                {groups.map((group, groupIndex) => (
                    <li key={group.key} role="presentation">
                        <ul role="group" aria-labelledby={`${id}-${group.key}`}>
                            <li
                                role="presentation"
                                id={`${id}-${group.key}`}
                                className="px-2.5 pt-2 pb-1 text-[10px] font-extrabold tracking-[0.08em] text-text-3 uppercase"
                            >
                                {group.label}
                            </li>
                            {group.options.map((option, position) => {
                                const optionIndex =
                                    offsets[groupIndex] + position;
                                const selected = optionIndex === active;

                                return (
                                    <li
                                        key={option.key}
                                        id={optionId(optionIndex)}
                                        role="option"
                                        aria-selected={selected}
                                        onMouseDown={(event) =>
                                            event.preventDefault()
                                        }
                                        onMouseEnter={() =>
                                            setActive(optionIndex)
                                        }
                                        onClick={() => go(option.url)}
                                        className={cn(
                                            'flex cursor-pointer flex-col rounded-btn px-2.5 py-2 text-[14px]',
                                            selected
                                                ? 'bg-acc-tint text-text'
                                                : 'text-text-2',
                                        )}
                                    >
                                        <span className="font-bold">
                                            {option.label}
                                        </span>
                                        {option.detail ? (
                                            <span className="text-xs text-text-3">
                                                {option.detail}
                                            </span>
                                        ) : null}
                                    </li>
                                );
                            })}
                        </ul>
                    </li>
                ))}
            </ul>
            <p aria-live="polite" role="status" className="sr-only">
                {expanded ? status : ''}
            </p>
        </form>
    );
}
