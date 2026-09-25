import { ProductCard } from '@/components/catalog/product-card';
import { EmptyState } from '@/components/comparo/empty-state';
import type { ProductSummary } from '@/types/catalog';

type ProductGridProps = {
    products: ProductSummary[];
    emptyTitle?: string;
};

export function ProductGrid({
    products,
    emptyTitle = 'No products listed yet',
}: ProductGridProps) {
    if (products.length === 0) {
        return <EmptyState title={emptyTitle} />;
    }

    return (
        <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {products.map((product) => (
                <li key={product.id}>
                    <ProductCard product={product} />
                </li>
            ))}
        </ul>
    );
}
