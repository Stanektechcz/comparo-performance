/* Seed data + domain helpers for the Comparo Performance prototype.
   All brands, shops and products are fictional. Prices are stored in EUR and converted at display time. */
(function () {
  const mk = (s) => () => { s |= 0; s = s + 0x6D2B79F5 | 0; let t = Math.imul(s ^ s >>> 15, 1 | s); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; };
  const R = mk(20260906);
  const pick = (a) => a[Math.floor(R() * a.length)];
  const int = (a, b) => a + Math.floor(R() * (b - a + 1));
  const flt = (a, b, d = 2) => Math.round((a + R() * (b - a)) * 10 ** d) / 10 ** d;
  const chance = (p) => R() < p;
  const shuffle = (a) => { const c = a.slice(); for (let i = c.length - 1; i > 0; i--) { const j = Math.floor(R() * (i + 1));[c[i], c[j]] = [c[j], c[i]]; } return c; };
  const slug = (s) => s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  const DAY = 86400000;
  const NOW = new Date('2026-09-06T09:00:00Z').getTime();
  const isoDate = (t) => new Date(t).toISOString().slice(0, 10);

  /* ---------------- currencies & countries ---------------- */
  const currencies = [
    { code: 'EUR', symbol: '€', rate: 1, locale: 'de-DE' },
    { code: 'USD', symbol: '$', rate: 1.08, locale: 'en-US' },
    { code: 'GBP', symbol: '£', rate: 0.85, locale: 'en-GB' },
    { code: 'CZK', symbol: 'Kč', rate: 25.2, locale: 'cs-CZ' },
    { code: 'PLN', symbol: 'zł', rate: 4.31, locale: 'pl-PL' },
    { code: 'SEK', symbol: 'kr', rate: 11.2, locale: 'sv-SE' },
  ];
  const countries = [
    { iso: 'DE', name: 'Germany', currency: 'EUR', lang: 'de', vat: 19, age: 18 },
    { iso: 'AT', name: 'Austria', currency: 'EUR', lang: 'de', vat: 20, age: 18 },
    { iso: 'CZ', name: 'Czechia', currency: 'CZK', lang: 'cs', vat: 21, age: 18 },
    { iso: 'SK', name: 'Slovakia', currency: 'EUR', lang: 'sk', vat: 20, age: 18 },
    { iso: 'PL', name: 'Poland', currency: 'PLN', lang: 'pl', vat: 23, age: 18 },
    { iso: 'FR', name: 'France', currency: 'EUR', lang: 'fr', vat: 20, age: 18 },
    { iso: 'IT', name: 'Italy', currency: 'EUR', lang: 'it', vat: 22, age: 18 },
    { iso: 'ES', name: 'Spain', currency: 'EUR', lang: 'es', vat: 21, age: 18 },
    { iso: 'NL', name: 'Netherlands', currency: 'EUR', lang: 'nl', vat: 21, age: 18 },
    { iso: 'SE', name: 'Sweden', currency: 'SEK', lang: 'sv', vat: 25, age: 18 },
    { iso: 'GB', name: 'United Kingdom', currency: 'GBP', lang: 'en', vat: 20, age: 18 },
    { iso: 'US', name: 'United States', currency: 'USD', lang: 'en', vat: 0, age: 18 },
  ];

  /* ---------------- brands, categories, ingredients ---------------- */
  const brandDefs = [
    ['IRONFORGE', 'DE', 4.7, 'German manufacturing, third-party lab tested by batch, no proprietary blends.'],
    ['Vytal Labs', 'NL', 4.5, 'Clinically dosed formulas with the full label disclosed on every product.'],
    ['NORDKRAFT', 'SE', 4.6, 'Scandinavian minimalism: short ingredient lists, no artificial sweeteners.'],
    ['Apex Fuel', 'US', 4.3, 'Pre-workout and performance formulas built around strength training.'],
    ['PureCore Nutrition', 'GB', 4.4, 'Vegan-first range, Informed Sport certified.'],
    ['Titan Range', 'PL', 4.0, 'Value bulk range with oversized packs.'],
    ['BioPeak', 'CZ', 4.2, 'Recovery and sleep focused, single-ingredient products.'],
    ['Kinetiq', 'FR', 4.5, 'Endurance nutrition: electrolytes and carbohydrate mixes.'],
    ['Fortis Nutrition', 'IT', 4.1, 'Classic bodybuilding line, in business since 2004.'],
  ];
  const brands = brandDefs.map((b, i) => ({
    id: i + 1, name: b[0], slug: slug(b[0]), country: b[1], rating: b[2], desc: b[3],
    reviews: int(120, 1900), founded: int(1998, 2018),
  }));

  const categories = [
    { id: 1, name: 'Protein', slug: 'protein' },
    { id: 2, name: 'Creatine', slug: 'creatine' },
    { id: 3, name: 'Amino acids', slug: 'amino-acids' },
    { id: 4, name: 'Pre-workout', slug: 'pre-workout' },
    { id: 5, name: 'Recovery & sleep', slug: 'recovery-sleep' },
    { id: 6, name: 'Vitamins & minerals', slug: 'vitamins-minerals' },
    { id: 7, name: 'Gainers & carbs', slug: 'gainers-carbs' },
    { id: 8, name: 'Metabolism', slug: 'metabolism' },
    { id: 9, name: 'Health & immunity', slug: 'health-immunity' },
  ];
  const ingredients = ['Whey isolate', 'Whey concentrate', 'Micellar casein', 'Pea protein', 'Rice protein', 'Creatine monohydrate', 'Creatine HCl', 'Beta-alanine', 'L-citrulline malate', 'Caffeine', 'L-theanine', 'EAA', 'BCAA 4:1:1', 'L-glutamine', 'Magnesium bisglycinate', 'Zinc', 'Vitamin B6', 'Vitamin D3', 'Vitamin K2', 'Omega-3 EPA/DHA', 'Ashwagandha KSM-66', 'Melatonin', 'Electrolytes', 'Maltodextrin', 'Cyclic dextrin', 'Type II collagen', 'Yohimbine HCl', 'Green tea extract'];

  /* name, brandIdx, catId, pack, servings, unit, basePriceEUR, ingredients, short */
  const productDefs = [
    ['Performance Alpha', 3, 4, '360 g', 30, 'g', 39.9, ['L-citrulline malate', 'Beta-alanine', 'Caffeine', 'L-theanine'], 'Fully disclosed pre-workout with 200 mg caffeine per serving.'],
    ['Strength Core', 0, 2, '500 g', 100, 'g', 24.9, ['Creatine monohydrate'], 'Micronised Creapure monohydrate, single ingredient.'],
    ['Recovery Matrix', 1, 5, '600 g', 30, 'g', 34.9, ['EAA', 'L-glutamine', 'Electrolytes'], 'Post-training recovery mix with EAAs and electrolytes.'],
    ['Mass Formula X', 2, 7, '4000 g', 40, 'g', 54.9, ['Whey concentrate', 'Maltodextrin', 'Creatine monohydrate'], '4 kg mass gainer with a 2:1 carbohydrate to protein ratio.'],
    ['Endurance Prime', 7, 7, '1500 g', 30, 'g', 29.9, ['Cyclic dextrin', 'Electrolytes'], 'Endurance carbohydrate mix built on cyclic dextrin.'],
    ['Whey Isolate 90', 0, 1, '900 g', 30, 'g', 44.9, ['Whey isolate'], 'CFM isolate at 90 % protein, effectively lactose free.'],
    ['Native Whey Concentrate', 0, 1, '1000 g', 33, 'g', 32.9, ['Whey concentrate'], 'Native concentrate from fresh milk, low-temperature microfiltration.'],
    ['Vegan Protein Blend', 4, 1, '1000 g', 33, 'g', 36.5, ['Pea protein', 'Rice protein'], 'Pea and rice blend ratioed for a complete amino acid profile.'],
    ['Casein Night Protein', 8, 1, '900 g', 30, 'g', 33.9, ['Micellar casein'], 'Slow-release micellar casein for overnight recovery.'],
    ['Creatine Monohydrate Micronized', 5, 2, '1000 g', 200, 'g', 29.9, ['Creatine monohydrate'], 'Bulk pack of micronised monohydrate, 200 servings.'],
    ['Creatine HCl Caps', 3, 2, '180 caps', 60, 'caps', 27.9, ['Creatine HCl'], 'Creatine HCl in capsules, no loading phase required.'],
    ['EAA Complete', 1, 3, '450 g', 45, 'g', 31.9, ['EAA'], 'All nine essential amino acids in free form.'],
    ['BCAA 4:1:1', 8, 3, '500 g', 50, 'g', 22.9, ['BCAA 4:1:1'], 'Classic BCAA blend in a 4:1:1 ratio.'],
    ['Beta-Alanine Pure', 4, 3, '300 g', 100, 'g', 18.9, ['Beta-alanine'], 'Unflavoured beta-alanine, 3 g per serving.'],
    ['Citrulline Malate 2:1', 4, 3, '400 g', 50, 'g', 24.5, ['L-citrulline malate'], 'Citrulline malate 2:1 for blood flow and pump.'],
    ['Pre-Burn Extreme', 3, 4, '300 g', 25, 'g', 42.9, ['Caffeine', 'Beta-alanine', 'Green tea extract'], 'High-stimulant pre-workout, 350 mg caffeine per serving. Not for stimulant-sensitive users.'],
    ['Nitric Surge', 1, 4, '400 g', 40, 'g', 35.9, ['L-citrulline malate', 'Beta-alanine'], 'Stimulant-free pump formula for evening training.'],
    ['Omega-3 Ultra', 6, 9, '120 caps', 120, 'caps', 21.9, ['Omega-3 EPA/DHA'], 'Fish oil in triglyceride form, 700 mg EPA/DHA per capsule.'],
    ['Vitamin D3 + K2', 6, 6, '90 caps', 90, 'caps', 14.9, ['Vitamin D3', 'Vitamin K2'], 'D3 at 2000 IU paired with K2 as MK-7.'],
    ['ZMA Recovery', 8, 5, '120 caps', 60, 'caps', 17.9, ['Zinc', 'Magnesium bisglycinate', 'Vitamin B6'], 'Zinc, magnesium and B6 for overnight recovery.'],
    ['Magnesium Bisglycinate', 2, 6, '180 caps', 90, 'caps', 19.9, ['Magnesium bisglycinate'], 'Highly bioavailable chelated magnesium.'],
    ['Ashwagandha KSM-66', 6, 5, '90 caps', 90, 'caps', 23.9, ['Ashwagandha KSM-66'], 'Standardised KSM-66 extract, 600 mg daily dose.'],
    ['Melatonin Sleep 1 mg', 6, 5, '120 tabs', 120, 'tabs', 11.9, ['Melatonin'], 'Melatonin 1 mg to shorten time to fall asleep. Regulatory status differs by country.'],
    ['Electrolyte Hydration', 7, 6, '60 sachets', 60, 'sachets', 26.9, ['Electrolytes'], 'Hydration sticks with sodium, potassium and magnesium.'],
    ['Carb Loader Maltodextrin', 5, 7, '3000 g', 60, 'g', 19.9, ['Maltodextrin'], 'Plain maltodextrin for carbohydrate loading.'],
    ['Thermo Cut Yohimbine', 3, 8, '90 caps', 90, 'caps', 28.9, ['Yohimbine HCl', 'Green tea extract'], 'Yohimbine-based formula. Restricted or not permitted in some countries.'],
    ['Collagen Joint Support', 1, 9, '400 g', 40, 'g', 32.9, ['Type II collagen'], 'Type II collagen with vitamin C for joint support.'],
    ['Glutamine Pure', 5, 3, '500 g', 100, 'g', 16.9, ['L-glutamine'], 'Pure L-glutamine, pharmaceutical grade.'],
  ];

  const products = productDefs.map((d, i) => {
    const id = i + 1;
    const grams = parseFloat(d[3]);
    return {
      id, name: d[0], slug: slug(d[0]), brandId: brands[d[1]].id, categoryId: d[2],
      pack: d[3], servings: d[4], unit: d[5], base: d[6], ingredients: d[7], short: d[8],
      ean: '859' + String(1000000 + id * 7919).slice(0, 7) + (id % 10),
      sku: 'CMP-' + String(id).padStart(4, '0'),
      rrp: Math.round(d[6] * 1.22 * 10) / 10,
      variants: (d[5] === 'g' ? ['Vanilla', 'Chocolate', 'Salted caramel', 'Unflavoured'] : ['Standard']).slice(0, d[5] === 'g' ? int(2, 4) : 1),
      packs: d[5] === 'g' ? [d[3], (grams * 2 >= 1000 ? (grams * 2 / 1000) + ' kg' : grams * 2 + ' g')] : [d[3]],
      desc: d[8] + ' Intended for adults as a food supplement; it does not replace a varied diet. Keep out of reach of children.',
      views: int(400, 26000), watchers: int(12, 1450), created: NOW - int(30, 700) * DAY,
    };
  });

  /* ---------------- merchants ---------------- */
  const merchDefs = [
    ['PeakSupps', 'DE', 4.8, 3421, true, true, 'PREMIUM', ['DE', 'AT', 'CZ', 'SK', 'PL', 'NL', 'FR', 'IT', 'ES', 'SE'], 4.9, 'peaksupps.de'],
    ['IronLab Store', 'CZ', 4.6, 2187, true, true, 'PRO', ['CZ', 'SK', 'DE', 'AT', 'PL'], 5.9, 'ironlab.cz'],
    ['PerformanceHub', 'GB', 4.5, 5210, true, false, 'PREMIUM', ['GB', 'DE', 'FR', 'NL', 'SE', 'US'], 4.5, 'performancehub.co.uk'],
    ['BodyCore Market', 'PL', 4.1, 940, true, false, 'FREE', ['PL', 'CZ', 'SK', 'DE'], 3.9, 'bodycore.pl'],
    ['AthleteSupply', 'NL', 4.4, 1630, true, true, 'PRO', ['NL', 'DE', 'FR', 'ES', 'IT', 'AT'], 4.2, 'athletesupply.nl'],
    ['NordicGains', 'SE', 4.3, 610, true, false, 'FREE', ['SE', 'DE', 'NL'], 6.5, 'nordicgains.se'],
    ['IronVault', 'AT', 3.9, 388, false, false, 'FREE', ['AT', 'DE', 'CZ', 'SK'], 5.5, 'ironvault.at'],
    ['SupplementBay', 'US', 4.2, 2760, true, false, 'PRO', ['US', 'GB', 'DE', 'SE'], 9.9, 'supplementbay.com'],
  ];
  const freeOverTable = [85, 48, 60, 36, 70, 56, 40, 60];
  const carriersPool = ['DHL', 'DPD', 'GLS', 'UPS', 'PPL', 'Packeta', 'PostNL', 'FedEx'];
  const paysPool = ['Card', 'PayPal', 'Apple Pay', 'Google Pay', 'Bank transfer', 'Klarna', 'Cash on delivery'];
  const merchants = merchDefs.map((m, i) => {
    const shipsTo = m[7];
    const zones = {};
    shipsTo.forEach((c) => {
      const home = c === m[1];
      zones[c] = { cost: home ? Math.round(m[8] * 0.7 * 10) / 10 : Math.round(m[8] * flt(1, 1.9, 2) * 10) / 10, days: home ? [1, 2] : [int(2, 4), int(4, 8)] };
    });
    return {
      id: i + 1, name: m[0], slug: slug(m[0]), country: m[1], rating: m[2], reviews: m[3],
      verified: m[4], partner: m[5], tier: m[6], shipsTo, zones, freeOverEur: freeOverTable[i],
      web: m[9], status: m[4] ? 'verified' : 'pending',
      currencies: shuffle(currencies.map((c) => c.code)).slice(0, int(2, 4)),
      carriers: shuffle(carriersPool).slice(0, int(2, 4)),
      payments: shuffle(paysPool).slice(0, int(3, 5)),
      sub: { shipping: flt(3.8, 4.9, 1), comms: flt(3.6, 4.9, 1), price: flt(3.5, 4.8, 1), support: flt(3.5, 4.9, 1), claims: flt(3.2, 4.8, 1), trust: flt(3.8, 5, 1) },
      created: NOW - int(20, 1100) * DAY,
      productCount: int(420, 5400),
      desc: m[0] + ' is a specialist sports nutrition retailer holding ' + int(400, 5000) + ' SKUs in stock, with same-day dispatch on weekdays.',
      returnDays: pick([14, 30, 60]),
      affiliate: { network: pick(['Direct', 'Awin', 'Tradedoubler', 'Impact', 'Direct']), commission: flt(4, 12, 1), cookie: pick([7, 14, 30, 45]), sub: 'cmp-' + slug(m[0]) },
      faq: [
        { q: 'How long does delivery take?', a: 'Orders placed before 2 pm ship the same day. Domestic delivery takes 1–2 days, cross-border 2–8 days.' },
        { q: 'How are claims handled?', a: 'Claims can be filed online and are resolved within ' + int(5, 20) + ' days. Damaged parcels are replaced immediately.' },
        { q: 'Do you refund returns?', a: 'Yes, within ' + pick([14, 30]) + ' days of delivery, no reason required.' },
      ],
    };
  });

  const merchantApplications = [
    { id: 1, name: 'FitZone Europe', country: 'ES', web: 'fitzone.eu', contact: 'ops@fitzone.eu', vat: 'ESB12345678', submitted: NOW - 2 * DAY, docs: ['Company register extract', 'Terms & conditions', 'Sample feed'], countriesSold: ['ES', 'PT', 'FR'], status: 'pending', note: 'Sample feed has 2,400 items, 61 % auto-matched.' },
    { id: 2, name: 'MuscleWorks', country: 'IT', web: 'muscleworks.it', contact: 'partner@muscleworks.it', vat: 'IT09876543210', submitted: NOW - 5 * DAY, docs: ['Company register extract', 'Terms & conditions'], countriesSold: ['IT', 'AT', 'DE'], status: 'pending', note: 'Missing food supplement distribution authorisation.' },
    { id: 3, name: 'CoreNutri', country: 'FR', web: 'corenutri.fr', contact: 'hello@corenutri.fr', vat: 'FR44123456789', submitted: NOW - 9 * DAY, docs: ['Company register extract', 'Sample feed', 'HACCP certificate'], countriesSold: ['FR', 'BE', 'CH'], status: 'pending', note: 'Ready to approve, compliance review clean.' },
  ];

  /* ---------------- coupons ---------------- */
  const coupons = [];
  let cid = 1;
  merchants.forEach((m) => {
    const n = m.tier === 'FREE' ? 1 : int(2, 3);
    for (let i = 0; i < n; i++) {
      const type = pick(['percent', 'fixed', 'freeship']);
      coupons.push({
        id: cid++, merchantId: m.id,
        code: (m.name.replace(/[^A-Za-z]/g, '').slice(0, 5) + int(5, 25)).toUpperCase(),
        title: type === 'percent' ? int(5, 20) + ' % off your whole order' : type === 'fixed' ? '€' + int(5, 15) + ' off your order' : 'Free shipping with no minimum',
        type, value: type === 'percent' ? int(5, 20) : type === 'fixed' ? int(5, 15) : 0,
        minOrder: pick([0, 30, 50, 80]), countries: m.shipsTo.slice(0, int(3, m.shipsTo.length)),
        starts: NOW - int(2, 30) * DAY, ends: NOW + int(1, 40) * DAY,
        exclusive: false, uses: int(30, 4200), verifiedAt: NOW - int(0, 6) * DAY,
      });
    }
  });
  [1, 2, 3, 5].forEach((mid) => {
    const m = merchants[mid - 1];
    coupons.push({
      id: cid++, merchantId: m.id, code: 'COMPARO' + int(10, 20),
      title: 'Comparo exclusive: ' + int(10, 18) + ' % off everything',
      type: 'percent', value: int(10, 18), minOrder: pick([0, 40]),
      countries: m.shipsTo, starts: NOW - int(1, 14) * DAY, ends: NOW + int(5, 45) * DAY,
      exclusive: true, uses: int(120, 2800), verifiedAt: NOW - int(0, 2) * DAY,
    });
  });

  /* ---------------- offers (MerchantProduct + Price) ---------------- */
  const offers = [];
  let oid = 1;
  products.forEach((p) => {
    const chosen = shuffle(merchants).slice(0, int(3, 6));
    chosen.forEach((m) => {
      const factor = flt(0.82, 1.18, 3) * (m.tier === 'FREE' ? 0.97 : 1);
      const price = Math.round(p.base * factor * 100) / 100;
      const disc = chance(0.42);
      const old = disc ? Math.round(price * flt(1.08, 1.45, 2) * 100) / 100 : null;
      const mc = coupons.filter((c) => c.merchantId === m.id);
      const av = R();
      offers.push({
        id: oid++, productId: p.id, merchantId: m.id,
        price, oldPrice: old,
        availability: av < 0.68 ? 'in_stock' : av < 0.82 ? 'low_stock' : av < 0.92 ? 'preorder' : 'out_of_stock',
        stock: av < 0.82 ? int(3, 240) : 0,
        warehouse: m.country,
        variant: pick(p.variants), pack: p.pack,
        couponId: chance(0.3) && mc.length ? pick(mc).id : null,
        sponsored: chance(0.08) && m.tier !== 'FREE',
        merchantSku: m.name.slice(0, 3).toUpperCase() + '-' + p.id * 31,
        ean: p.ean,
        url: 'https://' + m.web + '/p/' + p.slug,
        updated: NOW - int(0, 22) * 3600000,
        clicks30: int(20, 2600),
      });
    });
  });

  /* ---------------- price history ---------------- */
  const historyDays = 365;
  products.forEach((p) => {
    const os = offers.filter((o) => o.productId === p.id);
    const curMin = Math.min.apply(null, os.map((o) => o.price));
    const curAvg = os.reduce((s, o) => s + o.price, 0) / os.length;
    const min = new Array(historyDays), avg = new Array(historyDays);
    min[historyDays - 1] = curMin; avg[historyDays - 1] = curAvg;
    for (let i = historyDays - 2; i >= 0; i--) {
      const drift = 1 + (R() - 0.48) * 0.022;
      const spike = chance(0.03) ? flt(1.04, 1.12, 3) : 1;
      min[i] = Math.max(p.base * 0.55, Math.round(min[i + 1] * drift * spike * 100) / 100);
      avg[i] = Math.round(Math.max(min[i] * 1.04, avg[i + 1] * (1 + (R() - 0.49) * 0.018)) * 100) / 100;
    }
    const byMerchant = {};
    os.slice(0, 3).forEach((o) => {
      const arr = new Array(historyDays);
      arr[historyDays - 1] = o.price;
      for (let i = historyDays - 2; i >= 0; i--) arr[i] = Math.round(Math.max(min[i], arr[i + 1] * (1 + (R() - 0.47) * 0.02)) * 100) / 100;
      byMerchant[o.merchantId] = arr;
    });
    p.hist = { min, avg, byMerchant };
    p.lowestEver = Math.round(Math.min.apply(null, min) * 100) / 100;
    p.change30 = Math.round((curMin / min[historyDays - 31] - 1) * 1000) / 10;
    p.change7 = Math.round((curMin / min[historyDays - 8] - 1) * 1000) / 10;
  });

  /* ---------------- compliance ---------------- */
  const complianceRules = [];
  let crid = 1;
  const addRule = (pid, c, status, reason, source) => complianceRules.push({
    id: crid++, productId: pid, country: c, status, reason, source,
    reviewedBy: status === 'unknown' ? null : pick(['compliance@comparo', 'legal@comparo']),
    reviewedAt: status === 'unknown' ? null : NOW - int(3, 200) * DAY,
  });
  const byName = (n) => products.find((p) => p.name === n).id;
  ['DE', 'CZ', 'SK', 'AT', 'PL', 'FR', 'IT', 'ES'].forEach((c) => addRule(byName('Melatonin Sleep 1 mg'), c, 'prescription_only', 'Melatonin above the food supplement threshold is classified as a medicinal product in this market.', 'National medicines agency'));
  ['DE', 'FR'].forEach((c) => addRule(byName('Thermo Cut Yohimbine'), c, 'not_allowed', 'Yohimbine is not permitted in food supplements.', 'National food safety authority decision'));
  ['IT', 'ES', 'PL'].forEach((c) => addRule(byName('Thermo Cut Yohimbine'), c, 'restricted', 'Sale allowed with mandatory warning and a capped daily dose.', 'National decree'));
  ['FR', 'IT'].forEach((c) => addRule(byName('Pre-Burn Extreme'), c, 'restricted', 'Caffeine above 200 mg per serving requires a mandatory on-pack warning.', 'EU 1169/2011, Art. 10'));
  ['SE', 'NL'].forEach((c) => addRule(byName('Pre-Burn Extreme'), c, 'unknown', '', 'Automated feed import'));
  ['DE', 'SE'].forEach((c) => addRule(byName('Ashwagandha KSM-66'), c, 'unknown', '', 'Automated feed import'));
  addRule(byName('Nitric Surge'), 'US', 'unknown', '', 'Automated feed import');
  addRule(byName('Collagen Joint Support'), 'SE', 'unknown', '', 'Automated feed import');
  ['DE', 'CZ', 'GB', 'US'].forEach((c) => addRule(byName('Whey Isolate 90'), c, 'allowed', 'Standard food supplement.', 'Internal review'));

  /* ---------------- users & reviews ---------------- */
  const nickPool = ['LiftHard', 'martina_k', 'PetrGains', 'IronMike', 'Anna F.', 'squatking', 'Tomas_B', 'FitLena', 'volumeguy', 'DavidH', 'natural_paul', 'Klara.S', 'benchpress_jan', 'RunnerEva', 'HeavyMetalGym'];
  const users = nickPool.map((n, i) => ({
    id: i + 1, nick: n, country: pick(countries).iso, joined: NOW - int(40, 1200) * DAY,
    reviews: int(1, 24), helpful: int(0, 340), verified: chance(0.8), avatar: n.slice(0, 2).toUpperCase(),
  }));

  const prPros = ['mixes clean', 'tastes fine in water', 'honest dosing', 'cost per serving', 'no artificial aftertaste', 'noticeable in training', 'large pack', 'lab report published'];
  const prCons = ['premium price', 'sweetener is noticeable', 'foams a little', 'small scoop', 'flavour availability', 'lid seals poorly'];
  const prTitles = ['Best value per serving I found', 'Solid staple in my stack', 'Flavour surprised me', 'Works, but pricey', 'Third tub and counting', 'Nothing comes close at this price', 'Expected more', 'Consistent quality, recommended'];
  const prTexts = [
    'Six months in and the mixability is flawless. The label is fully disclosed, nothing hidden in a proprietary blend, which is why I keep reordering.',
    'Quality matches the price. Flavour is unremarkable but perfectly fine with milk, and one pack lasts exactly a month at two servings a day.',
    'Bought it on a discount and stayed. Dosing matches the label and the batch lab report is public, which matters a lot in this category.',
    'Effect is subjectively good, but full price is steep. Watch the price chart and wait for a drop, it regularly falls by a fifth.',
    'Using it through a bulk, sits fine on my stomach. The scoop is smaller than expected, so check your serving size.',
  ];
  const merTitles = ['No issues, fast delivery', 'Great communication on a claim', 'Arrived a day early', 'Slow shipping, otherwise fine', 'Order mistake fixed immediately'];
  const merTexts = [
    'Shipped the same day and the courier delivered in two days. Packed carefully, nothing damaged.',
    'They resolved a damaged parcel claim within 48 hours with a replacement. Support replies like a human, not a template.',
    'Prices are competitive but cross-border shipping is expensive. Worth pushing the basket over the free shipping threshold.',
    'One item was out of stock, they emailed me and offered a substitute or a refund. Straight dealing.',
  ];
  const reviews = [];
  let rid = 1;
  products.forEach((p) => {
    const n = int(1, 4);
    for (let i = 0; i < n; i++) {
      const rating = pick([5, 5, 5, 4, 4, 4, 3, 2, 5, 4]);
      reviews.push({
        id: rid++, type: 'product', targetId: p.id, userId: pick(users).id,
        rating, title: pick(prTitles), text: pick(prTexts),
        pros: shuffle(prPros).slice(0, int(1, 3)), cons: rating >= 4 ? shuffle(prCons).slice(0, int(0, 2)) : shuffle(prCons).slice(0, int(1, 3)),
        recommend: rating >= 4, verifiedPurchase: chance(0.62),
        merchantId: chance(0.6) ? pick(offers.filter((o) => o.productId === p.id)).merchantId : null,
        date: NOW - int(1, 300) * DAY, helpful: int(0, 84), notHelpful: int(0, 12),
        status: 'approved', reply: null, reported: false,
      });
    }
  });
  merchants.forEach((m) => {
    const n = int(3, 6);
    for (let i = 0; i < n; i++) {
      const rating = pick([5, 5, 4, 4, 4, 3, 5, 2]);
      reviews.push({
        id: rid++, type: 'merchant', targetId: m.id, userId: pick(users).id,
        rating, title: pick(merTitles), text: pick(merTexts),
        sub: { shipping: Math.min(5, rating + int(-1, 1)), comms: Math.min(5, rating + int(-1, 1)), price: Math.min(5, rating + int(-1, 1)), support: Math.min(5, rating + int(-1, 1)), order: Math.min(5, rating + int(-1, 0)) },
        pros: [], cons: [], recommend: rating >= 4, verifiedPurchase: chance(0.7),
        date: NOW - int(1, 240) * DAY, helpful: int(0, 60), notHelpful: int(0, 9),
        status: 'approved',
        reply: chance(0.35) ? { text: 'Thanks for the feedback. We passed the shipping note to our logistics team and are working on the free shipping threshold.', date: NOW - int(1, 60) * DAY, author: m.name } : null,
        reported: false,
      });
    }
  });
  const modSeed = [
    { rating: 1, title: 'WORST SHOP EVER!!! never buy here', text: 'order never arrived, nobody answers the phone, total scam!!! i will post this everywhere', status: 'flagged', reported: true, type: 'merchant', targetId: 7 },
    { rating: 5, title: 'Best prices on the market, buy here', text: 'best eshop ever, recommend to everyone, buy through this link bit.ly/xyz 50% off', status: 'flagged', reported: true, type: 'merchant', targetId: 4 },
    { rating: 5, title: 'Great product', text: 'Great product, works excellently, I recommend it to every athlete.', status: 'pending', reported: false, type: 'product', targetId: 1 },
    { rating: 4, title: 'Good for recovery', text: 'Less soreness the day after training, though the flavour could be better.', status: 'pending', reported: false, type: 'product', targetId: 3 },
    { rating: 2, title: 'Shipping took three weeks', text: 'Product is fine but the cross-border delivery took three weeks with no tracking updates.', status: 'pending', reported: false, type: 'merchant', targetId: 8 },
  ];
  modSeed.forEach((m) => reviews.push(Object.assign({
    id: rid++, userId: pick(users).id, pros: [], cons: [], recommend: m.rating >= 4,
    verifiedPurchase: false, date: NOW - int(0, 4) * DAY, helpful: 0, notHelpful: 0, reply: null,
    sub: m.type === 'merchant' ? { shipping: m.rating, comms: m.rating, price: m.rating, support: m.rating, order: m.rating } : null,
  }, m)));

  /* ---------------- affiliate analytics ---------------- */
  const affiliate = { daily: [], clicks: [] };
  merchants.forEach((m) => {
    const scale = m.tier === 'PREMIUM' ? 3.2 : m.tier === 'PRO' ? 1.8 : 1;
    for (let d = 29; d >= 0; d--) {
      const clicks = Math.round((int(40, 190) + (29 - d) * 1.4) * scale);
      const conv = Math.round(clicks * flt(0.025, 0.075, 4));
      const aov = flt(48, 96, 2);
      const revenue = Math.round(conv * aov * 100) / 100;
      affiliate.daily.push({ merchantId: m.id, day: isoDate(NOW - d * DAY), clicks, unique: Math.round(clicks * flt(0.78, 0.93, 2)), conv, revenue, commission: Math.round(revenue * m.affiliate.commission) / 100 });
    }
  });
  for (let i = 0; i < 40; i++) {
    const o = pick(offers);
    const m = merchants[o.merchantId - 1];
    affiliate.clicks.push({
      id: i + 1, merchantId: m.id, productId: o.productId, country: pick(m.shipsTo),
      placement: pick(['product_offer_table', 'deal_hub', 'homepage_deals', 'search_results', 'compare_table']),
      campaign: pick(['organic', 'newsletter-w36', 'homepage-hero', 'seo-guide']),
      network: m.affiliate.network, subId: m.affiliate.sub + '-' + int(1000, 9999),
      session: 'anon-' + Math.abs(Math.round(R() * 1e9)).toString(36),
      ts: NOW - int(1, 2400) * 60000,
      converted: chance(0.06),
    });
  }

  /* ---------------- merchant feeds & unmatched items ---------------- */
  const feedItems = [
    { id: 1, merchantId: 1, raw: 'WHEY ISOLATE 90 - Vanilla 900g | IRONFORGE', ean: products[5].ean, price: 43.5, suggested: 6, confidence: 0.97, status: 'auto' },
    { id: 2, merchantId: 1, raw: 'Performance ALPHA pre workout 360g', ean: '', price: 38.9, suggested: 1, confidence: 0.88, status: 'suggested' },
    { id: 3, merchantId: 2, raw: 'Creatine monohydrate 500 g Creapure', ean: products[1].ean, price: 23.9, suggested: 2, confidence: 0.94, status: 'auto' },
    { id: 4, merchantId: 2, raw: 'RECOVERY-MATRIX 600G VYTAL', ean: '', price: 33.5, suggested: 3, confidence: 0.72, status: 'suggested' },
    { id: 5, merchantId: 3, raw: 'Mass Formula 4kg chocolate NORDKRAFT', ean: '', price: 52.9, suggested: 4, confidence: 0.69, status: 'suggested' },
    { id: 6, merchantId: 3, raw: 'Hydration sticks 60x electrolyte', ean: '', price: 25.5, suggested: 24, confidence: 0.51, status: 'unmatched' },
    { id: 7, merchantId: 5, raw: 'Vitamin D3 K2 90 caps BioPeak', ean: products[18].ean, price: 13.9, suggested: 19, confidence: 0.96, status: 'auto' },
    { id: 8, merchantId: 5, raw: 'Nigh casein protein 900g choc', ean: '', price: 32.5, suggested: 9, confidence: 0.64, status: 'suggested' },
    { id: 9, merchantId: 8, raw: 'THERMO-CUT YOHIMBINE 90CT', ean: products[25].ean, price: 27.9, suggested: 26, confidence: 0.91, status: 'compliance_hold' },
    { id: 10, merchantId: 8, raw: 'Unknown item 8842-XX bulk', ean: '', price: 18.0, suggested: null, confidence: 0.12, status: 'unmatched' },
  ];
  const feeds = merchants.map((m) => ({
    merchantId: m.id, url: 'https://' + m.web + '/feed/comparo.xml',
    format: pick(['XML', 'CSV', 'JSON', 'API']), interval: pick(['1 h', '4 h', '12 h', '24 h']),
    lastRun: NOW - int(10, 400) * 60000, items: int(380, 5400),
    matched: 0, unmatched: int(4, 90), errors: int(0, 12), status: pick(['ok', 'ok', 'ok', 'warning']),
  }));
  feeds.forEach((f) => { f.matched = f.items - f.unmatched; });

  /* ---------------- content hub ---------------- */
  const articles = [
    { id: 1, title: 'What protein really costs: price per 100 g of protein across 12 countries', cat: 'Research', tags: ['protein', 'pricing', 'analysis'], author: 'Comparo Editorial', date: NOW - 3 * DAY, read: 9, excerpt: 'We normalised 640 offers to a single metric — price per 100 g of protein, shipping included. The gap between the cheapest and most expensive market is 41 %.' },
    { id: 2, title: 'How we read labels: full disclosure versus proprietary blends', cat: 'Guide', tags: ['labels', 'formulation'], author: 'M. Dvorak', date: NOW - 9 * DAY, read: 7, excerpt: 'A proprietary blend never tells you how much active ingredient you actually get. Here is how to compare two labels in under a minute.' },
    { id: 3, title: 'Creatine monohydrate: 14 products compared by cost per serving', cat: 'Comparison', tags: ['creatine', 'comparison'], author: 'Comparo Editorial', date: NOW - 14 * DAY, read: 11, excerpt: 'The most expensive creatine in our set costs 4.1× the cheapest. The difference in raw material is close to nil.' },
    { id: 4, title: 'Shipping is the hidden price: when topping up to the free threshold pays off', cat: 'Guide', tags: ['shipping', 'pricing'], author: 'L. Novak', date: NOW - 21 * DAY, read: 6, excerpt: 'We modelled 8 shops across 6 countries. In half the cases, topping up the basket beats a standalone order.' },
    { id: 5, title: 'EU food supplement regulation: what changes in 2026', cat: 'Industry news', tags: ['regulation', 'compliance'], author: 'Compliance team', date: NOW - 28 * DAY, read: 12, excerpt: 'National deviations on melatonin, caffeine limits and botanical extracts — and why the same product is not purchasable in every market.' },
    { id: 6, title: 'How to spot a fake review: the 7 signals we screen for', cat: 'Guide', tags: ['reviews', 'trust'], author: 'Moderation team', date: NOW - 35 * DAY, read: 8, excerpt: 'Typing speed, rating clusters, repeated phrasing. A walkthrough of the heuristics running in our moderation queue.' },
  ];
  articles.forEach((a) => {
    a.slug = slug(a.title);
    a.body = [
      a.excerpt,
      'The method is deliberately simple. We take offers from verified merchants that genuinely deliver to the selected country on the day of measurement and normalise them to a single metric including shipping. Offers with no stated availability or no shipping cost are excluded from the calculation.',
      'The result shows that the deciding line item is often not the product price itself but the combination of shipping, the free shipping threshold and any active coupon. A shopper comparing product price alone pays roughly 12 % more on average.',
      'The underlying data lives in the comparison engine and refreshes several times a day from merchant product feeds. Every product carries a price history chart, and you can set an alert on a target price.',
    ];
  });

  /* ---------------- sponsored, subscriptions, invoices, audit ---------------- */
  const sponsored = [
    { id: 1, type: 'Homepage placement', merchantId: 1, slot: 'Homepage — Best deals', starts: NOW - 5 * DAY, ends: NOW + 25 * DAY, budget: 2400, spent: 780, cpc: 0.42, status: 'active' },
    { id: 2, type: 'Sponsored product', merchantId: 3, slot: 'Category — Protein', starts: NOW - 12 * DAY, ends: NOW + 18 * DAY, budget: 1500, spent: 1120, cpc: 0.36, status: 'active' },
    { id: 3, type: 'Featured deal', merchantId: 5, slot: 'Deal Hub — top', starts: NOW - 2 * DAY, ends: NOW + 12 * DAY, budget: 900, spent: 145, cpc: 0.5, status: 'active' },
    { id: 4, type: 'Sponsored merchant', merchantId: 2, slot: 'Shops — CZ', starts: NOW - 40 * DAY, ends: NOW - 5 * DAY, budget: 1200, spent: 1200, cpc: 0.31, status: 'ended' },
    { id: 5, type: 'Category placement', merchantId: 8, slot: 'Category — Pre-workout', starts: NOW + 3 * DAY, ends: NOW + 33 * DAY, budget: 1800, spent: 0, cpc: 0.45, status: 'scheduled' },
  ];
  const subscriptions = merchants.map((m) => ({
    merchantId: m.id, plan: m.tier, price: m.tier === 'PREMIUM' ? 399 : m.tier === 'PRO' ? 149 : 0,
    since: NOW - int(60, 900) * DAY, renews: NOW + int(3, 30) * DAY, status: 'active',
  }));
  const invoices = [];
  merchants.filter((m) => m.tier !== 'FREE').forEach((m, i) => {
    for (let k = 0; k < 3; k++) invoices.push({
      id: 'INV-2026-' + String(100 + i * 3 + k), merchantId: m.id,
      amount: (m.tier === 'PREMIUM' ? 399 : 149) + Math.round(flt(0, 320, 2)),
      issued: NOW - (k * 30 + int(1, 8)) * DAY, status: k === 0 ? pick(['paid', 'pending']) : 'paid',
      items: ['Subscription ' + m.tier, 'CPC clicks', 'Sponsored placement'].slice(0, int(1, 3)),
    });
  });
  const auditLog = [
    { id: 1, ts: NOW - 40 * 60000, actor: 'admin@comparo', action: 'merchant.verified', entity: 'Merchant #5 AthleteSupply', ip: '10.4.2.19' },
    { id: 2, ts: NOW - 95 * 60000, actor: 'compliance@comparo', action: 'compliance.rule.updated', entity: 'Product #23 / DE → prescription_only', ip: '10.4.2.7' },
    { id: 3, ts: NOW - 180 * 60000, actor: 'moderator@comparo', action: 'review.rejected', entity: 'Review flagged by spam heuristics', ip: '10.4.2.31' },
    { id: 4, ts: NOW - 320 * 60000, actor: 'affiliate@comparo', action: 'program.commission.changed', entity: 'PerformanceHub 6.5 % → 7.2 %', ip: '10.4.2.11' },
    { id: 5, ts: NOW - 460 * 60000, actor: 'editor@comparo', action: 'article.published', entity: 'What protein really costs', ip: '10.4.2.44' },
    { id: 6, ts: NOW - 700 * 60000, actor: 'admin@comparo', action: 'offer.exclusive.created', entity: 'COMPARO15 / PeakSupps', ip: '10.4.2.19' },
    { id: 7, ts: NOW - 1400 * 60000, actor: 'system', action: 'feed.import.completed', entity: 'IronLab Store — 2,187 items, 41 unmatched', ip: 'worker-3' },
    { id: 8, ts: NOW - 1900 * 60000, actor: 'admin@comparo', action: 'merchant.suspended', entity: 'Merchant #7 IronVault (missing documents)', ip: '10.4.2.19' },
  ];

  /* ---------------- helpers ---------------- */
  const norm = (s) => (s || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  function lev(a, b) {
    if (a === b) return 0;
    const m = a.length, n = b.length;
    if (!m || !n) return m || n;
    let prev = new Array(n + 1), cur = new Array(n + 1);
    for (let j = 0; j <= n; j++) prev[j] = j;
    for (let i = 1; i <= m; i++) {
      cur[0] = i;
      for (let j = 1; j <= n; j++) cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
      const t = prev; prev = cur; cur = t;
    }
    return prev[n];
  }
  const synonyms = {
    protein: ['whey', 'isolate', 'casein', 'protein'],
    creatine: ['creatine', 'monohydrate', 'creapure', 'kreatin'],
    preworkout: ['pre-workout', 'preworkout', 'pump', 'stim'],
    amino: ['eaa', 'bcaa', 'amino', 'glutamine'],
    sleep: ['melatonin', 'sleep', 'zma', 'ashwagandha'],
  };
  function fuzzyScore(query, text) {
    const q = norm(query), t = norm(text);
    if (!q) return 0;
    if (t === q) return 100;
    if (t.startsWith(q)) return 92;
    if (t.includes(q)) return 80;
    const qt = q.split(/\s+/).filter(Boolean), tt = t.split(/\s+/).filter(Boolean);
    let hits = 0, fuzzy = 0;
    qt.forEach((w) => {
      if (tt.some((x) => x.startsWith(w) || x.includes(w))) hits++;
      else if (tt.some((x) => Math.abs(x.length - w.length) <= 3 && lev(x, w) <= (w.length > 5 ? 2 : 1))) fuzzy++;
    });
    if (hits + fuzzy === 0) return 0;
    return Math.round((hits * 60 + fuzzy * 38) / qt.length);
  }

  const fmt = (eur, code) => {
    const c = currencies.find((x) => x.code === code) || currencies[0];
    const v = eur * c.rate;
    const dec = (code === 'CZK' || code === 'SEK' || code === 'PLN') ? 0 : 2;
    try {
      return new Intl.NumberFormat(c.locale, { style: 'currency', currency: code, minimumFractionDigits: dec, maximumFractionDigits: dec }).format(v);
    } catch (e) { return v.toFixed(dec) + ' ' + c.symbol; }
  };
  const num = (n, d = 0) => new Intl.NumberFormat('en-GB', { minimumFractionDigits: d, maximumFractionDigits: d }).format(n);
  const dateLong = (t) => new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(t));
  const dateShort = (t) => new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: '2-digit', year: '2-digit' }).format(new Date(t));
  const ago = (t) => {
    const d = Math.floor((NOW - t) / DAY);
    if (d <= 0) { const h = Math.max(1, Math.floor((NOW - t) / 3600000)); return h + ' h ago'; }
    if (d === 1) return 'yesterday';
    if (d < 31) return d + ' days ago';
    if (d < 365) return Math.floor(d / 30) + ' mo ago';
    return Math.floor(d / 365) + ' y ago';
  };

  window.SEED = {
    NOW, DAY, historyDays,
    currencies, countries, brands, categories, ingredients, products, merchants, merchantApplications,
    offers, coupons, reviews, users, articles, complianceRules, affiliate, feeds, feedItems,
    sponsored, subscriptions, invoices, auditLog,
    helpers: { norm, lev, fuzzyScore, fmt, num, dateLong, dateShort, ago, isoDate, slug, synonyms },
  };
})();
