import { Head } from '@inertiajs/react';
import type { SeoHead as SeoHeadProps } from '@/types/catalog';

/**
 * Inertia's Head serialises children to HTML without escaping text nodes,
 * so the title text is escaped here (attributes are escaped by Inertia).
 */
function escapeText(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
}

/** JSON safe to embed inside a <script> element. */
function serializeJsonLd(value: Record<string, unknown>): string {
    return JSON.stringify(value)
        .replaceAll('<', '\\u003c')
        .replaceAll('>', '\\u003e')
        .replaceAll('&', '\\u0026');
}

function ogProperty(key: string): string {
    return key.startsWith('og:') ? key : `og:${key}`;
}

/**
 * Renders the server-provided SEO head. Every element carries a stable
 * `head-key` (emitted as `data-inertia`) that matches the Blade fallback,
 * so server-rendered copies are replaced rather than duplicated:
 * description, robots, canonical, alternate-{hreflang}, og:{property},
 * jsonld-{index}.
 */
export function SeoHead({ seo }: { seo: SeoHeadProps }) {
    return (
        <Head>
            <title>{escapeText(seo.title)}</title>
            <meta
                head-key="description"
                name="description"
                content={seo.description}
            />
            <meta head-key="robots" name="robots" content={seo.robots} />
            <link head-key="canonical" rel="canonical" href={seo.canonical} />
            {seo.alternates.map((alternate) => (
                <link
                    key={`alternate-${alternate.hreflang}`}
                    head-key={`alternate-${alternate.hreflang}`}
                    rel="alternate"
                    hrefLang={alternate.hreflang}
                    href={alternate.href}
                />
            ))}
            {Object.entries(seo.openGraph).map(([key, content]) => {
                const property = ogProperty(key);

                return (
                    <meta
                        key={property}
                        head-key={property}
                        property={property}
                        content={content}
                    />
                );
            })}
            {seo.jsonLd.map((schema, index) => (
                <script
                    key={`jsonld-${index}`}
                    head-key={`jsonld-${index}`}
                    type="application/ld+json"
                    dangerouslySetInnerHTML={{
                        __html: serializeJsonLd(schema),
                    }}
                />
            ))}
        </Head>
    );
}
