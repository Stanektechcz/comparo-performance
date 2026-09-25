import type { ReactNode } from 'react';
import { IngredientTable } from '@/components/catalog/ingredient-table';
import { ScoreBar } from '@/components/comparo/score-bar';
import { Section } from '@/components/comparo/section';
import type { ProductDetail } from '@/types/catalog';

function ChipList({ items, empty }: { items: string[]; empty: string }) {
    if (items.length === 0) {
        return <p className="text-[13px] text-text-3">{empty}</p>;
    }

    return (
        <ul className="flex flex-wrap gap-1.5">
            {items.map((item) => (
                <li
                    key={item}
                    className="rounded-pill border border-line-2 px-3 py-1 text-xs font-semibold text-text-2"
                >
                    {item}
                </li>
            ))}
        </ul>
    );
}

function Fact({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="bg-surface-2 px-4 py-3">
            <dt className="eyebrow">{label}</dt>
            <dd className="mt-1.5 text-[13px] text-text">{children}</dd>
        </div>
    );
}

export function ProductAbout({ product }: { product: ProductDetail }) {
    const { completeness } = product;

    return (
        <Section id="about" title="About this product">
            {product.description ? (
                <p className="mb-5 max-w-3xl text-[14.5px] leading-relaxed text-text-2">
                    {product.description}
                </p>
            ) : null}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <div>
                    <h3 className="mb-3 text-base font-extrabold">
                        Ingredients per serving
                    </h3>
                    <IngredientTable ingredients={product.ingredients} />
                </div>
                <div className="space-y-5">
                    <div>
                        <h3 className="mb-2 text-base font-extrabold">
                            Flavours
                        </h3>
                        <ChipList
                            items={product.flavours}
                            empty="No flavours listed."
                        />
                    </div>
                    <div>
                        <h3 className="mb-2 text-base font-extrabold">Packs</h3>
                        <ChipList
                            items={product.packs}
                            empty="No pack sizes listed."
                        />
                    </div>
                    <dl className="grid grid-cols-1 gap-px overflow-hidden rounded-card border border-line bg-line sm:grid-cols-2">
                        <Fact label="EAN / GTIN">
                            <span className="num break-all">
                                {product.ean ?? 'Not on file'}
                            </span>
                        </Fact>
                        <Fact label="Data completeness">
                            <span className="num font-bold">
                                {completeness.percent} %
                            </span>
                            <ScoreBar
                                value={completeness.percent}
                                className="mt-1.5"
                            />
                        </Fact>
                    </dl>
                    {completeness.missing.length > 0 ? (
                        <div>
                            <p className="text-[13px] text-text-3">
                                Missing from our record:
                            </p>
                            <ul className="mt-1 list-disc pl-5 text-[13px] text-text-2">
                                {completeness.missing.map((field) => (
                                    <li key={field}>{field}</li>
                                ))}
                            </ul>
                        </div>
                    ) : null}
                </div>
            </div>
        </Section>
    );
}
