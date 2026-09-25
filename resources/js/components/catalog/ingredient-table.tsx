import { Badge } from '@/components/comparo/badge';
import { useMarket } from '@/hooks/use-shared-props';
import { formatNumber } from '@/lib/format';
import type { Ingredient } from '@/types/catalog';

const MG_PER_G = 1000;

function amountText(amountMg: number, locale: string): string {
    return amountMg >= MG_PER_G
        ? `${formatNumber(amountMg / MG_PER_G, locale, 2)} g`
        : `${formatNumber(amountMg, locale, 1)} mg`;
}

/** Declared ingredients per serving with NRV share. */
export function IngredientTable({
    ingredients,
}: {
    ingredients: Ingredient[];
}) {
    const market = useMarket();

    if (ingredients.length === 0) {
        return (
            <p className="text-sm text-text-3">
                No ingredient list is on file for this product yet.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto rounded-card border border-line">
            <table className="w-full text-left text-[13px]">
                <caption className="sr-only">
                    Ingredients and amounts per serving
                </caption>
                <thead className="bg-surface-2">
                    <tr>
                        <th scope="col" className="px-3 py-2.5 eyebrow">
                            Ingredient
                        </th>
                        <th
                            scope="col"
                            className="px-3 py-2.5 text-right eyebrow"
                        >
                            Per serving
                        </th>
                        <th
                            scope="col"
                            className="px-3 py-2.5 text-right eyebrow"
                        >
                            NRV
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {ingredients.map((ingredient) => (
                        <tr
                            key={ingredient.slug}
                            className="border-t border-line-soft"
                        >
                            <th
                                scope="row"
                                className="px-3 py-2.5 font-semibold text-text"
                            >
                                <span className="inline-flex flex-wrap items-center gap-1.5">
                                    {ingredient.name}
                                    {ingredient.isCarrier ? (
                                        <Badge
                                            tone="muted"
                                            title="Carrier or excipient, not an active ingredient"
                                        >
                                            Carrier
                                        </Badge>
                                    ) : null}
                                </span>
                            </th>
                            <td className="px-3 py-2.5 text-right num text-text-2">
                                {ingredient.amountMg === null
                                    ? 'Not declared'
                                    : amountText(
                                          ingredient.amountMg,
                                          market.locale,
                                      )}
                            </td>
                            <td className="px-3 py-2.5 text-right num text-text-2">
                                {ingredient.nrvPercent === null ? (
                                    <>
                                        <span aria-hidden="true">—</span>
                                        <span className="sr-only">
                                            No reference value
                                        </span>
                                    </>
                                ) : (
                                    `${formatNumber(ingredient.nrvPercent, market.locale, 1)} %`
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
