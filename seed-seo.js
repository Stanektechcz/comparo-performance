/* Comparo Performance — SEO / GEO / discovery seed layer.
   Extends window.SEED. Prototype data is deterministic, never fabricated external statistics. */
(function () {
  const S = window.SEED;
  if (!S) return;
  const mk = (s) => () => { s |= 0; s = s + 0x6D2B79F5 | 0; let t = Math.imul(s ^ s >>> 15, 1 | s); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; };
  const R = mk(31415926);
  const int = (a, b) => a + Math.floor(R() * (b - a + 1));
  const pick = (a) => a[Math.floor(R() * a.length)];
  const slug = S.helpers.slug;
  const DAY = S.DAY, NOW = S.NOW;

  /* ---------- stable entity ids ---------- */
  const pad = (n) => String(n).padStart(5, '0');
  S.products.forEach((p) => { p.eid = 'PRD-' + pad(p.id); });
  S.brands.forEach((b) => { b.eid = 'BRD-' + pad(b.id); });
  S.merchants.forEach((m) => { m.eid = 'SHP-' + pad(m.id); });
  S.categories.forEach((c) => { c.eid = 'CAT-' + pad(c.id); });
  S.countries.forEach((c, i) => { c.eid = 'MKT-' + pad(i + 1); });
  S.offers.forEach((o) => { o.eid = 'OFR-' + pad(o.id); });

  /* ---------- ingredient entities ---------- */
  const ingContext = {
    'Whey isolate': 'Filtered milk protein with most lactose and fat removed; typically 88–92 % protein by weight.',
    'Whey concentrate': 'Less filtered whey with 70–80 % protein, retaining more lactose and milk fat.',
    'Micellar casein': 'Slow-digesting milk protein fraction, commonly used in overnight products.',
    'Pea protein': 'Plant protein from yellow split peas, usually blended with rice to complete the amino profile.',
    'Rice protein': 'Plant protein commonly paired with pea protein in vegan blends.',
    'Creatine monohydrate': 'The most studied creatine form; sold as micronised powder or capsules, typically 3–5 g per serving.',
    'Creatine HCl': 'Creatine bound to hydrochloride, marketed for solubility; dosed lower per serving than monohydrate.',
    'Beta-alanine': 'Amino acid used in pre-workout formulas, typically 2–3.2 g per serving.',
    'L-citrulline malate': 'Citrulline bound to malic acid, common in pump formulas at 6–8 g per serving.',
    Caffeine: 'Stimulant dosed per serving; EU labelling requires a warning above 150 mg per portion in drinks.',
    'L-theanine': 'Amino acid frequently paired with caffeine in a 1:1 or 1:2 ratio.',
    EAA: 'All nine essential amino acids in free form, sold as powder or capsules.',
    'BCAA 4:1:1': 'Leucine, isoleucine and valine in a fixed ratio.',
    'L-glutamine': 'Conditionally essential amino acid sold as a single-ingredient powder.',
    'Magnesium bisglycinate': 'Chelated magnesium form used in recovery and sleep products.',
    Zinc: 'Trace mineral, commonly in picolinate or gluconate form.',
    'Vitamin B6': 'Water-soluble vitamin frequently combined with zinc and magnesium.',
    'Vitamin D3': 'Fat-soluble vitamin dosed in IU, often paired with K2.',
    'Vitamin K2': 'Usually supplied as MK-7 alongside vitamin D3.',
    'Omega-3 EPA/DHA': 'Marine fatty acids sold in triglyceride or ethyl-ester form; compare mg of EPA/DHA, not capsule size.',
    'Ashwagandha KSM-66': 'Standardised root extract; products state the extract ratio and daily dose.',
    Melatonin: 'Regulatory status differs by market — a supplement in some countries, a medicinal product in others.',
    Electrolytes: 'Sodium, potassium, magnesium and chloride blends; sodium per litre is the number that differs most.',
    Maltodextrin: 'Fast carbohydrate source used for carbohydrate loading and gainers.',
    'Cyclic dextrin': 'Highly branched carbohydrate marketed for lower osmolality than maltodextrin.',
    'Type II collagen': 'Collagen fraction used in joint products, usually with vitamin C.',
    'Yohimbine HCl': 'Restricted or not permitted in food supplements in several markets.',
    'Green tea extract': 'Standardised for EGCG; several markets cap the daily dose.',
  };
  S.ingredientEntities = S.ingredients.map((name, i) => {
    const products = S.products.filter((p) => p.ingredients.indexOf(name) >= 0);
    return {
      id: i + 1, eid: 'ING-' + pad(i + 1), name: name, slug: slug(name),
      context: ingContext[name] || 'Ingredient used in performance nutrition products in this catalogue.',
      productIds: products.map((p) => p.id),
      categoryIds: Array.from(new Set(products.map((p) => p.categoryId))),
      brandIds: Array.from(new Set(products.map((p) => p.brandId))),
      updated: NOW - int(2, 90) * DAY,
    };
  });

  /* ---------- category editorial context ---------- */
  const catContext = {
    protein: 'Protein powders are compared on price per 100 g of protein, not per tub: pack size, protein percentage and shipping decide the real cost.',
    creatine: 'Creatine monohydrate is chemically the same across brands, so cost per 5 g serving and third-party testing are the differentiators.',
    'amino-acids': 'EAA and BCAA products differ mostly in free-form dose per serving; compare grams of actives, not scoop size.',
    'pre-workout': 'Pre-workouts vary by caffeine load and whether the label is fully disclosed. Several markets require warnings above a caffeine threshold.',
    'recovery-sleep': 'Recovery and sleep products are single-ingredient or small blends; the regulatory status of some actives differs by market.',
    'vitamins-minerals': 'Micronutrients are compared on form (chelated vs oxide), dose per capsule and cost per day.',
    'gainers-carbs': 'Gainers and carbohydrate mixes are bulk goods where shipping is often the deciding cost.',
    metabolism: 'Metabolism products contain the most market-restricted actives in this catalogue; availability varies per country.',
    'health-immunity': 'General health products with long shelf life, frequently bought as add-ons to reach free shipping thresholds.',
  };
  S.categories.forEach((c) => { c.context = catContext[c.slug] || ''; });

  /* ---------- search analytics ---------- */
  const queries = [
    ['whey isolate', 'INFORMATIONAL', 1840, true], ['creatine', 'CATEGORY', 1610, true],
    ['peaksupps', 'NAVIGATIONAL', 1240, true], ['best shops germany', 'SHOP_DISCOVERY', 980, false],
    ['cheapest whey protein germany', 'COMMERCIAL', 870, false], ['performance alpha vs pre-burn extreme', 'COMPARISON', 640, false],
    ['whey isolate 90 price', 'TRANSACTIONAL', 610, true], ['ironlab store reviews', 'REVIEW', 590, true],
    ['creatine monohydrate cheapest', 'COMMERCIAL', 560, true], ['vegan protein blend review', 'REVIEW', 480, true],
    ['free shipping supplements eu', 'COMMERCIAL', 450, false], ['electrolyte hydration', 'INFORMATIONAL', 430, true],
    ['is melatonin legal in germany', 'INFORMATIONAL', 410, false], ['best rated shop czechia', 'SHOP_DISCOVERY', 380, false],
    ['8591047514', 'NAVIGATIONAL', 350, true], ['eaa vs bcaa', 'COMPARISON', 340, false],
    ['peaksupps vs performancehub', 'COMPARISON', 320, false], ['pre workout without caffeine', 'INFORMATIONAL', 300, true],
    ['protein price per 100g protein', 'INFORMATIONAL', 280, false], ['ironforge brand', 'NAVIGATIONAL', 260, true],
    ['cheap creatine poland', 'COMMERCIAL', 240, false], ['shops delivering to sweden', 'SHOP_DISCOVERY', 220, false],
    ['ashwagandha ksm-66', 'INFORMATIONAL', 210, true], ['best gainer 2026', 'COMMERCIAL', 190, false],
    ['casein night protein', 'INFORMATIONAL', 180, true], ['supplement customs eu', 'INFORMATIONAL', 170, false],
    ['thermo cut yohimbine', 'INFORMATIONAL', 160, true], ['collagen joint support price', 'TRANSACTIONAL', 150, true],
    ['zinc picolinate', 'INFORMATIONAL', 140, true], ['best value protein netherlands', 'COMMERCIAL', 130, false],
  ];
  S.searchQueries = queries.map((q, i) => ({
    id: i + 1, query: q[0], intent: q[1], volume: q[2], hasResults: q[3],
    clicks: Math.round(q[2] * (q[3] ? 0.44 : 0.06)),
    merchantClicks: Math.round(q[2] * (q[3] ? 0.11 : 0.01)),
    saves: Math.round(q[2] * (q[3] ? 0.04 : 0)),
    zeroResults: q[3] ? 0 : Math.round(q[2] * 0.82),
    trend: int(-18, 46), lastSeen: NOW - int(0, 6) * DAY,
  }));
  S.synonymSets = [
    { id: 1, canonical: 'shop', terms: ['store', 'merchant', 'retailer', 'eshop'], locale: 'all' },
    { id: 2, canonical: 'protein', terms: ['whey', 'isolate', 'concentrate', 'bilkoviny'], locale: 'all' },
    { id: 3, canonical: 'gainer', terms: ['mass gain', 'bulking', 'weight gainer'], locale: 'all' },
    { id: 4, canonical: 'creatine', terms: ['kreatin', 'creapure', 'monohydrate'], locale: 'all' },
    { id: 5, canonical: 'pre-workout', terms: ['preworkout', 'nakopávač', 'booster'], locale: 'all' },
    { id: 6, canonical: 'shipping', terms: ['delivery', 'versand', 'doprava', 'livraison'], locale: 'all' },
  ];

  /* ---------- locales & hreflang model ---------- */
  S.locales = [
    { code: 'en', name: 'English', xdefault: true, coverage: 100, status: 'approved' },
    { code: 'de', name: 'German', xdefault: false, coverage: 82, status: 'human reviewed' },
    { code: 'cs', name: 'Czech', xdefault: false, coverage: 64, status: 'human reviewed' },
    { code: 'fr', name: 'French', xdefault: false, coverage: 41, status: 'machine draft' },
    { code: 'es', name: 'Spanish', xdefault: false, coverage: 33, status: 'machine draft' },
    { code: 'it', name: 'Italian', xdefault: false, coverage: 29, status: 'machine draft' },
    { code: 'pl', name: 'Polish', xdefault: false, coverage: 22, status: 'missing' },
  ];
  S.localeMarkets = [
    { hreflang: 'en-DE', locale: 'en', market: 'DE' }, { hreflang: 'de-DE', locale: 'de', market: 'DE' },
    { hreflang: 'de-AT', locale: 'de', market: 'AT' }, { hreflang: 'cs-CZ', locale: 'cs', market: 'CZ' },
    { hreflang: 'fr-FR', locale: 'fr', market: 'FR' }, { hreflang: 'es-ES', locale: 'es', market: 'ES' },
    { hreflang: 'it-IT', locale: 'it', market: 'IT' }, { hreflang: 'pl-PL', locale: 'pl', market: 'PL' },
    { hreflang: 'en-GB', locale: 'en', market: 'GB' }, { hreflang: 'en-US', locale: 'en', market: 'US' },
    { hreflang: 'x-default', locale: 'en', market: '—' },
  ];

  /* ---------- metadata templates ---------- */
  S.metaTemplates = [
    { key: 'product', label: 'Product', title: '{product} price comparison, reviews & deals | Comparo', description: 'Compare {offerCount} offers for {product} from {shopCount} verified shops delivering to {country}. Total price incl. shipping from {lowest}, {reviewCount} reviews, full price history.', h1: '{product}' },
    { key: 'shop', label: 'Shop', title: '{shop} reviews, ratings, products & deals | Comparo', description: '{shop} rated {rating}/5 from {reviewCount} reviews. Delivery to {marketCount} markets, {offerCount} live offers, verified status, shipping costs and active coupons.', h1: '{shop}' },
    { key: 'brand', label: 'Brand', title: '{brand} products, prices & reviews | Comparo', description: '{brand}: {productCount} products, average rating {rating}/5, sold by {shopCount} shops. Price ranges, deals and community discussions.', h1: '{brand}' },
    { key: 'category', label: 'Category', title: '{category}: prices, best-rated products & deals | Comparo', description: 'Compare {productCount} {category} products from {shopCount} shops. Price range {low}–{high}, review insights and current deals for {country}.', h1: '{category}' },
    { key: 'ingredient', label: 'Ingredient', title: '{ingredient}: products, prices & brands | Comparo', description: '{productCount} products containing {ingredient} from {brandCount} brands. Typical dosing context, price statistics and reviews mentioning it.', h1: '{ingredient}' },
    { key: 'country', label: 'Country hub', title: 'Best performance nutrition shops in {country} | Comparo', description: '{shopCount} shops deliver to {country}. Compare total prices incl. shipping, ratings, deals and market price trends.', h1: 'Buying performance products in {country}' },
    { key: 'compare', label: 'Comparison', title: '{productA} vs {productB}: prices, reviews & comparison | Comparo', description: 'Side-by-side comparison of {productA} and {productB}: total price incl. shipping, price per serving, ratings, availability and delivery.', h1: '{productA} vs {productB}' },
    { key: 'guide', label: 'Guide', title: '{guide} | Comparo research', description: '{excerpt}', h1: '{guide}' },
    { key: 'forum', label: 'Forum topic', title: '{topic} | Comparo community', description: '{replyCount} replies from the Comparo community about {topic}.', h1: '{topic}' },
    { key: 'deal', label: 'Deal hub', title: 'Supplement deals, coupons & price drops for {country} | Comparo', description: '{dealCount} live deals validated against merchant feeds for {country}: coupons, price drops, free shipping and Comparo exclusives.', h1: 'Deals, coupons and exclusives' },
  ];

  /* ---------- indexation rules ---------- */
  S.indexRules = [
    { template: 'home', rule: 'index', note: 'Always indexable.' },
    { template: 'product', rule: 'conditional', note: 'Index when offers ≥ 1 or reviews ≥ 3. Empty products are noindex.' },
    { template: 'shop', rule: 'conditional', note: 'Index when verified or reviews ≥ 5.' },
    { template: 'brand', rule: 'conditional', note: 'Index when products ≥ 2.' },
    { template: 'category', rule: 'index', note: 'Indexable with unique context copy and aggregates.' },
    { template: 'ingredient', rule: 'conditional', note: 'Index when products ≥ 2 and context copy exists.' },
    { template: 'country', rule: 'conditional', note: 'Index when shops delivering ≥ 3.' },
    { template: 'compare', rule: 'conditional', note: 'Index when both products have ≥ 2 offers and combined reviews ≥ 3.' },
    { template: 'guide', rule: 'conditional', note: 'Index when status approved and body ≥ 3 paragraphs.' },
    { template: 'forum', rule: 'conditional', note: 'Index when replies ≥ 2 and UGC quality ≥ 45.' },
    { template: 'reviews', rule: 'index', note: 'Aggregated hub is indexable; single-review URLs are not created.' },
    { template: 'research', rule: 'index', note: 'Original datasets — always indexable.' },
    { template: 'methodology', rule: 'index', note: 'Trust surface — always indexable.' },
    { template: 'profile', rule: 'conditional', note: 'Index when public and contributions ≥ 3.' },
    { template: 'list', rule: 'conditional', note: 'Index when public, titled and items ≥ 3.' },
    { template: 'deal', rule: 'index', note: 'Hub indexable; expired individual deals redirect to the product.' },
    { template: 'search', rule: 'noindex', note: 'Internal search results are never indexable.' },
    { template: 'account', rule: 'noindex', note: 'Private area.' },
    { template: 'merchant', rule: 'noindex', note: 'Merchant dashboard.' },
    { template: 'admin', rule: 'noindex', note: 'Staff console.' },
    { template: 'go', rule: 'noindex', note: 'Affiliate redirect — noindex, nofollow, no canonical identity.' },
  ];
  S.facetRules = [
    { facet: 'sort', policy: 'canonicalized', note: 'Sorting never changes the indexable identity of a page.' },
    { facet: 'currency', policy: 'canonicalized', note: 'Currency is a presentation preference, not a URL.' },
    { facet: 'country', policy: 'indexable', note: 'Market changes the offer set — market hubs are their own pages.' },
    { facet: 'availability', policy: 'noindex', note: 'Transient state.' },
    { facet: 'free_shipping', policy: 'noindex', note: 'Combines with too many other facets.' },
    { facet: 'brand+category', policy: 'indexable', note: 'Approved combination with sufficient unique data.' },
    { facet: 'rating', policy: 'noindex', note: 'Thin variant of the parent listing.' },
    { facet: 'utm/affiliate params', policy: 'canonicalized', note: 'Stripped before canonical evaluation.' },
  ];
  S.crawlerPolicy = [
    { bot: 'Googlebot', kind: 'Traditional search crawler', allow: true, note: 'Full public catalogue, /go/ and private areas disallowed.' },
    { bot: 'Bingbot', kind: 'Traditional search crawler', allow: true, note: 'Same policy as Googlebot; powers Bing and downstream AI answers.' },
    { bot: 'OAI-SearchBot', kind: 'AI search discovery crawler', allow: true, note: 'Search discovery for ChatGPT search surfaces; not model training.' },
    { bot: 'GPTBot', kind: 'Model-training crawler', allow: false, note: 'Training crawler — disallowed by default; separate decision from search discovery.' },
    { bot: 'PerplexityBot', kind: 'AI search crawler', allow: true, note: 'Answer engine with citations.' },
    { bot: 'CCBot', kind: 'Bulk archive crawler', allow: false, note: 'Common Crawl; disallowed to limit uncontrolled redistribution.' },
  ];
  S.robotsRules = [
    { path: '/', allow: true }, { path: '/products/', allow: true }, { path: '/shops/', allow: true },
    { path: '/brands/', allow: true }, { path: '/categories/', allow: true }, { path: '/ingredients/', allow: true },
    { path: '/countries/', allow: true }, { path: '/compare/', allow: true }, { path: '/guides/', allow: true },
    { path: '/forum/', allow: true }, { path: '/research/', allow: true }, { path: '/methodology', allow: true },
    { path: '/go/', allow: false }, { path: '/search', allow: false }, { path: '/account', allow: false },
    { path: '/merchant', allow: false }, { path: '/admin', allow: false }, { path: '/saved', allow: false },
  ];

  /* ---------- redirects ---------- */
  S.redirects = [
    { id: 1, from: '/products/whey-isolate-90-vanilla', to: '/products/whey-isolate-90', type: 301, reason: 'Variant merged into canonical product', created: NOW - 42 * DAY },
    { id: 2, from: '/shops/ironvault-at', to: '/shops/ironvault', type: 301, reason: 'Slug normalised', created: NOW - 96 * DAY },
    { id: 3, from: '/deals/peaksupps-summer-2025', to: '/deals', type: 301, reason: 'Expired deal archived', created: NOW - 12 * DAY },
    { id: 4, from: '/brands/iron-forge', to: '/brands/ironforge', type: 301, reason: 'Duplicate brand merged', created: NOW - 210 * DAY },
    { id: 5, from: '/countries/de/shops', to: '/countries/germany', type: 302, reason: 'Temporary consolidation while market hub is rebuilt', created: NOW - 5 * DAY },
  ];
  S.brokenLinks = [
    { id: 1, kind: 'internal', from: '/guides/how-price-history-works-and-how-to-use-it', to: '/products/mass-formula-x-4kg', status: 404, note: 'Product slug changed' },
    { id: 2, kind: 'outbound', from: '/shops/ironvault', to: 'https://ironvault.at/p/discontinued', status: 404, note: 'Merchant removed landing page' },
    { id: 3, kind: 'outbound', from: '/products/thermo-cut-yohimbine', to: 'https://supplementbay.com/p/thermo-cut', status: 451, note: 'Blocked for this market by the merchant' },
    { id: 4, kind: 'redirect chain', from: '/brands/iron-forge', to: '/brands/ironforge', status: 301, note: '2 hops: /brands/iron-forge → /brands/ironforge-de → /brands/ironforge' },
  ];
  S.notFoundLog = [
    { path: '/products/creatine-gummies-90', hits: 142, suggestion: '/products/creatine-gummies' },
    { path: '/shops/peak-supps', hits: 96, suggestion: '/shops/peaksupps' },
    { path: '/countries/de', hits: 74, suggestion: '/countries/germany' },
    { path: '/best-protein-2026', hits: 61, suggestion: '/categories/protein' },
    { path: '/ingredients/creatin', hits: 38, suggestion: '/ingredients/creatine-monohydrate' },
  ];

  /* ---------- Core Web Vitals (prototype measurements) ---------- */
  S.cwv = [
    { page: 'Home', lcp: 1.7, inp: 84, cls: 0.02, ttfb: 0.32 },
    { page: 'Product', lcp: 2.1, inp: 112, cls: 0.04, ttfb: 0.38 },
    { page: 'Shop', lcp: 1.9, inp: 96, cls: 0.03, ttfb: 0.35 },
    { page: 'Category', lcp: 2.4, inp: 128, cls: 0.06, ttfb: 0.41 },
    { page: 'Forum thread', lcp: 1.6, inp: 74, cls: 0.01, ttfb: 0.29 },
    { page: 'Guide', lcp: 1.8, inp: 68, cls: 0.02, ttfb: 0.31 },
    { page: 'Country hub', lcp: 2.6, inp: 141, cls: 0.09, ttfb: 0.44 },
    { page: 'Research report', lcp: 2.9, inp: 158, cls: 0.05, ttfb: 0.47 },
  ];

  /* ---------- AI citation tracking (imported / manual prototype data) ---------- */
  S.aiCitations = [
    { id: 1, engine: 'Bing / Copilot', url: '/research/protein-price-index', citations: 42, trend: 18, source: 'imported CSV' },
    { id: 2, engine: 'ChatGPT search', url: '/countries/germany', citations: 31, trend: 9, source: 'manual log' },
    { id: 3, engine: 'Perplexity', url: '/methodology', citations: 27, trend: 24, source: 'manual log' },
    { id: 4, engine: 'Bing / Copilot', url: '/guides/cost-per-100-g-of-protein-the-only-protein-metric-that-matters', citations: 22, trend: -4, source: 'imported CSV' },
    { id: 5, engine: 'ChatGPT search', url: '/products/whey-isolate-90', citations: 19, trend: 12, source: 'manual log' },
    { id: 6, engine: 'Perplexity', url: '/shops/peaksupps', citations: 14, trend: 6, source: 'manual log' },
    { id: 7, engine: 'Google AI Overviews', url: '/research/shipping-cost-index', citations: 11, trend: 31, source: 'manual log' },
  ];

  /* ---------- editorial authors ---------- */
  S.authors = [
    { id: 1, name: 'Comparo Editorial', slug: 'comparo-editorial', role: 'Data & research team', bio: 'The in-house team that maintains the canonical catalogue, the price dataset and the published methodology. Not medical professionals and does not give health advice.', expertise: ['price data', 'methodology', 'market analysis'], articles: 3 },
    { id: 2, name: 'M. Dvorak', slug: 'm-dvorak', role: 'Labels & formulation editor', bio: 'Reads labels for a living: dosing transparency, proprietary blends and how to compare two products honestly.', expertise: ['labels', 'formulation', 'comparisons'], articles: 1 },
    { id: 3, name: 'L. Novak', slug: 'l-novak', role: 'Logistics editor', bio: 'Covers shipping, carriers, thresholds and cross-border cost — the part of the price most comparisons ignore.', expertise: ['shipping', 'cross-border retail'], articles: 1 },
    { id: 4, name: 'Compliance team', slug: 'compliance-team', role: 'Market compliance', bio: 'Maintains per-market product status: allowed, restricted, prescription-only, not allowed or unverified.', expertise: ['EU food supplement rules', 'market restrictions'], articles: 1 },
  ];
  S.articles.forEach((a, i) => {
    const au = S.authors.find((x) => x.name === a.author) || S.authors[0];
    a.authorId = au.id; a.editorId = 1;
    a.reviewedAt = a.date + int(1, 10) * DAY < NOW ? a.date + int(1, 10) * DAY : a.date;
    a.dataUpdatedAt = NOW - int(0, 12) * DAY;
    a.status = i === 5 ? 'needs update' : 'published';
  });

  /* ---------- research reports (built from internal data) ---------- */
  S.research = [
    { slug: 'protein-price-index', title: 'Comparo Protein Price Index', metric: 'price', scope: 'category', target: 'protein', question: 'Is protein getting cheaper?', period: '90 days' },
    { slug: 'shipping-cost-index', title: 'Cross-border shipping cost index', metric: 'shipping', scope: 'market', target: 'all', question: 'What does delivery really add?', period: '30 days' },
    { slug: 'most-competitive-shops', title: 'Most competitive shops by total price', metric: 'competitiveness', scope: 'shops', target: 'all', question: 'Which shop wins on total price most often?', period: '30 days' },
    { slug: 'largest-price-drops', title: 'Largest price drops this month', metric: 'drops', scope: 'products', target: 'all', period: '30 days', question: 'Where did prices fall hardest?' },
    { slug: 'best-rated-shops-by-market', title: 'Best-rated shops by market', metric: 'rating', scope: 'market', target: 'all', period: 'current', question: 'Who do buyers trust in each country?' },
    { slug: 'brand-price-positioning', title: 'Brand price positioning', metric: 'positioning', scope: 'brands', target: 'all', period: 'current', question: 'Which brands charge a premium?' },
  ];

  /* ---------- SEO experiments ---------- */
  S.experiments = [
    { id: 1, name: 'Product title: “price comparison” vs “compare prices”', template: 'product', variantA: '{product} price comparison, reviews & deals', variantB: 'Compare {product} prices across {shopCount} shops', ctrA: 3.9, ctrB: 4.6, clicksA: 1840, clicksB: 2170, status: 'running', started: NOW - 18 * DAY },
    { id: 2, name: 'Category layout: grid vs data-first table', template: 'category', variantA: 'Product grid first', variantB: 'Price table first', ctrA: 2.8, ctrB: 2.6, clicksA: 940, clicksB: 880, status: 'running', started: NOW - 9 * DAY },
    { id: 3, name: 'Country hub CTA placement', template: 'country', variantA: 'CTA after shops', variantB: 'CTA in hero', ctrA: 5.1, ctrB: 5.9, clicksA: 610, clicksB: 700, status: 'concluded', started: NOW - 46 * DAY },
  ];

  /* ---------- organic attribution (prototype) ---------- */
  S.attribution = [
    { source: 'Google organic', visits: 184200, merchantClicks: 21400, conversions: 1480, revenue: 96400 },
    { source: 'Bing organic', visits: 21800, merchantClicks: 2610, conversions: 172, revenue: 11200 },
    { source: 'AI search (cited)', visits: 9400, merchantClicks: 1580, conversions: 121, revenue: 8900 },
    { source: 'Direct', visits: 31600, merchantClicks: 4900, conversions: 402, revenue: 26100 },
    { source: 'Community / social', visits: 14300, merchantClicks: 1240, conversions: 78, revenue: 4700 },
    { source: 'Referral', visits: 6100, merchantClicks: 720, conversions: 44, revenue: 2800 },
  ];
  S.eventTaxonomy = ['search', 'search_result_click', 'search_zero_result', 'product_view', 'shop_view', 'compare_add', 'compare_view', 'deal_view', 'coupon_reveal', 'merchant_click', 'affiliate_redirect', 'review_view', 'review_submit', 'follow', 'save', 'price_alert_create', 'forum_view', 'forum_reply', 'ask_query', 'sitemap_generate'];

  /* ---------- duplicate candidates ---------- */
  const dupPairs = [
    ['Whey Isolate 90', 'Iso Whey Zero Lactose', 0.71, false, 'different brand, same format'],
    ['Creatine Monohydrate Micronized', 'Strength Core', 0.68, false, 'same ingredient, different pack'],
    ['Casein Night Protein', 'Casein Micellar Slow', 0.83, false, 'same ingredient, pack size differs'],
    ['Native Whey Concentrate', 'Clear Whey Refresh', 0.52, false, 'different filtration'],
    ['Pump Matrix Caffeine-Free', 'Nitric Surge', 0.79, false, 'both stimulant-free pump formulas'],
  ];
  S.duplicateCandidates = dupPairs.map((d, i) => {
    const a = S.products.find((p) => p.name === d[0]), b = S.products.find((p) => p.name === d[1]);
    if (!a || !b) return null;
    return {
      id: i + 1, aId: a.id, bId: b.id, similarity: d[2], sameEan: d[3], note: d[4],
      sameBrand: a.brandId === b.brandId, packDiff: a.pack !== b.pack,
      status: 'open',
    };
  }).filter(Boolean);

  /* ---------- market insight snapshots ---------- */
  S.marketNotes = {};
  S.countries.forEach((c) => {
    const shops = S.merchants.filter((m) => m.shipsTo.indexOf(c.iso) >= 0).length;
    S.marketNotes[c.iso] = { shops: shops, share: Math.round(shops / S.merchants.length * 100) };
  });

  S.seoMeta = { generated: NOW, version: 'proto-3' };
})();
