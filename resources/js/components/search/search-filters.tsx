import { router } from '@inertiajs/react';
import { SlidersHorizontal } from 'lucide-react';
import type { FormEvent } from 'react';
import { useId, useState } from 'react';
import { buttonStyles, selectStyles } from '@/components/comparo/button-styles';
import { searchUrl } from '@/components/search/search-url';
import { cn } from '@/lib/utils';
import type { FacetOption, SearchCriteria, SearchFacets } from '@/types/search';

const VISIBLE_OPTIONS = 12;

type FacetKey = 'brand' | 'category' | 'ingredient';

const inputStyles =
    'min-h-10 w-full min-w-0 rounded-field border border-line-2 bg-field px-3 text-[13px] text-text';

function visit(url: string) {
    router.get(url, {}, { preserveScroll: true });
}

function toggled(values: string[], value: string, on: boolean): string[] {
    return on
        ? [...values.filter((item) => item !== value), value]
        : values.filter((item) => item !== value);
}

function FacetGroup({
    legend,
    options,
    facet,
    criteria,
}: {
    legend: string;
    options: FacetOption[];
    facet: FacetKey;
    criteria: SearchCriteria;
}) {
    const [showAll, setShowAll] = useState(false);

    if (options.length === 0) {
        return null;
    }

    const shown = showAll
        ? options
        : options.filter(
              (option, index) => option.selected || index < VISIBLE_OPTIONS,
          );

    return (
        <fieldset className="border-t border-line-soft pt-3">
            <legend className="text-xs font-extrabold tracking-[0.06em] text-text-2 uppercase">
                {legend}
            </legend>
            <ul className="mt-2 flex flex-col gap-0.5">
                {shown.map((option) => (
                    <li key={option.value}>
                        <label className="flex min-h-9 cursor-pointer items-center gap-2.5 rounded-btn px-1.5 text-[13px] text-text-2 hover:bg-surface-3">
                            <input
                                type="checkbox"
                                checked={option.selected}
                                onChange={(event) =>
                                    visit(
                                        searchUrl(criteria, {
                                            [facet]: toggled(
                                                criteria[facet],
                                                option.value,
                                                event.target.checked,
                                            ),
                                        }),
                                    )
                                }
                                className="size-4 shrink-0 accent-[var(--acc)]"
                            />
                            <span className="min-w-0 flex-1 break-words">
                                {option.label}
                            </span>
                            <span className="num text-[11px] text-text-4">
                                {option.count}
                            </span>
                        </label>
                    </li>
                ))}
            </ul>
            {options.length > shown.length || showAll ? (
                <button
                    type="button"
                    onClick={() => setShowAll(!showAll)}
                    className={cn(buttonStyles.tertiary, 'mt-1')}
                >
                    {showAll ? 'Show fewer' : `Show all ${options.length}`}
                </button>
            ) : null}
        </fieldset>
    );
}

/**
 * Facet, price, stock and rating filters. Checkboxes apply at once; the
 * price range and rating apply with the button. Below `lg` the panel is a
 * disclosure so results stay first on small screens.
 */
export function SearchFilters({
    criteria,
    facets,
    priceCurrency,
}: {
    criteria: SearchCriteria;
    facets: SearchFacets;
    priceCurrency: string;
}) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const [priceMin, setPriceMin] = useState(
        criteria.price_min?.toString() ?? '',
    );
    const [priceMax, setPriceMax] = useState(
        criteria.price_max?.toString() ?? '',
    );
    const [minRating, setMinRating] = useState(
        criteria.min_rating?.toString() ?? '',
    );
    const activeCount =
        criteria.brand.length +
        criteria.category.length +
        criteria.ingredient.length +
        (criteria.price_min !== null || criteria.price_max !== null ? 1 : 0) +
        (criteria.in_stock ? 1 : 0) +
        (criteria.min_rating !== null ? 1 : 0);

    const toNumber = (value: string): number | null => {
        const parsed = Number.parseInt(value, 10);

        return Number.isFinite(parsed) && parsed >= 0 ? parsed : null;
    };

    const apply = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        visit(
            searchUrl(criteria, {
                price_min: toNumber(priceMin),
                price_max: toNumber(priceMax),
                min_rating: toNumber(minRating),
            }),
        );
    };

    return (
        <div>
            <button
                type="button"
                aria-expanded={open}
                aria-controls={`${id}-panel`}
                onClick={() => setOpen(!open)}
                className={cn(buttonStyles.secondary, 'w-full lg:hidden')}
            >
                <SlidersHorizontal aria-hidden="true" className="size-4" />
                Filters{activeCount > 0 ? ` (${activeCount})` : ''}
            </button>
            <form
                id={`${id}-panel`}
                aria-label="Filter results"
                onSubmit={apply}
                className={cn(
                    'mt-3 flex-col gap-4 rounded-card border border-line bg-surface p-4 lg:mt-0 lg:flex',
                    open ? 'flex' : 'hidden',
                )}
            >
                <FacetGroup
                    legend="Brand"
                    options={facets.brands}
                    facet="brand"
                    criteria={criteria}
                />
                <FacetGroup
                    legend="Category"
                    options={facets.categories}
                    facet="category"
                    criteria={criteria}
                />
                <FacetGroup
                    legend="Ingredient"
                    options={facets.ingredients}
                    facet="ingredient"
                    criteria={criteria}
                />
                <fieldset className="border-t border-line-soft pt-3">
                    <legend className="text-xs font-extrabold tracking-[0.06em] text-text-2 uppercase">
                        Lowest total ({priceCurrency})
                    </legend>
                    <div className="mt-2 grid grid-cols-2 gap-2">
                        <label className="text-xs text-text-3">
                            From
                            <input
                                type="number"
                                inputMode="numeric"
                                min={0}
                                step={1}
                                value={priceMin}
                                onChange={(event) =>
                                    setPriceMin(event.target.value)
                                }
                                className={cn(inputStyles, 'mt-1')}
                            />
                        </label>
                        <label className="text-xs text-text-3">
                            To
                            <input
                                type="number"
                                inputMode="numeric"
                                min={0}
                                step={1}
                                value={priceMax}
                                onChange={(event) =>
                                    setPriceMax(event.target.value)
                                }
                                className={cn(inputStyles, 'mt-1')}
                            />
                        </label>
                    </div>
                </fieldset>
                <fieldset className="border-t border-line-soft pt-3">
                    <legend className="sr-only">Availability and rating</legend>
                    <label className="flex min-h-9 cursor-pointer items-center gap-2.5 text-[13px] text-text-2">
                        <input
                            type="checkbox"
                            checked={criteria.in_stock}
                            onChange={(event) =>
                                visit(
                                    searchUrl(criteria, {
                                        in_stock: event.target.checked,
                                    }),
                                )
                            }
                            className="size-4 accent-[var(--acc)]"
                        />
                        In stock only
                    </label>
                    <label
                        htmlFor={`${id}-rating`}
                        className="mt-2 block text-xs text-text-3"
                    >
                        Minimum rating
                    </label>
                    <select
                        id={`${id}-rating`}
                        value={minRating}
                        onChange={(event) => setMinRating(event.target.value)}
                        className={cn(selectStyles, 'mt-1 w-full')}
                    >
                        <option value="">Any rating</option>
                        {[4, 3, 2, 1].map((stars) => (
                            <option key={stars} value={stars}>
                                {stars} stars and up
                            </option>
                        ))}
                    </select>
                </fieldset>
                <div className="flex flex-wrap items-center gap-3 border-t border-line-soft pt-3">
                    <button type="submit" className={buttonStyles.primary}>
                        Apply
                    </button>
                    {activeCount > 0 ? (
                        <button
                            type="button"
                            onClick={() =>
                                visit(
                                    searchUrl(criteria, {
                                        brand: [],
                                        category: [],
                                        ingredient: [],
                                        price_min: null,
                                        price_max: null,
                                        in_stock: false,
                                        min_rating: null,
                                    }),
                                )
                            }
                            className={buttonStyles.tertiary}
                        >
                            Clear all filters
                        </button>
                    ) : null}
                </div>
            </form>
        </div>
    );
}
