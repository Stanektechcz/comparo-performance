/* Comparo Performance — community & catalogue expansion.
   Extends window.SEED in place. Loaded after seed.js. All data fictional. */
(function () {
  const S = window.SEED;
  if (!S) return;
  const mk = (s) => () => { s |= 0; s = s + 0x6D2B79F5 | 0; let t = Math.imul(s ^ s >>> 15, 1 | s); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; };
  const R = mk(77123456);
  const pick = (a) => a[Math.floor(R() * a.length)];
  const int = (a, b) => a + Math.floor(R() * (b - a + 1));
  const flt = (a, b, d = 2) => Math.round((a + R() * (b - a)) * 10 ** d) / 10 ** d;
  const chance = (p) => R() < p;
  const shuffle = (a) => { const c = a.slice(); for (let i = c.length - 1; i > 0; i--) { const j = Math.floor(R() * (i + 1)); const t = c[i]; c[i] = c[j]; c[j] = t; } return c; };
  const slug = S.helpers.slug;
  const DAY = S.DAY, NOW = S.NOW, H = S.historyDays;
  const isoDate = S.helpers.isoDate;

  /* ---------------- more brands ---------------- */
  [['Helix Nutrition', 'ES', 4.3, 'Mediterranean sourcing, olive-derived antioxidants and simple stacks.'],
   ['Vertex Labs', 'US', 4.2, 'Research-driven pre-workout and nootropic crossovers, fully disclosed.'],
   ['Grindstone', 'GB', 4.0, 'No-frills strength staples in oversized packs.'],
   ['Aurora Vital', 'SE', 4.6, 'Micronutrients and sleep support, third-party tested every batch.']].forEach((b) => {
    const id = S.brands.length + 1;
    S.brands.push({ id, name: b[0], slug: slug(b[0]), country: b[1], rating: b[2], desc: b[3], reviews: int(90, 1400), founded: int(2001, 2020) });
  });
  const B = (n) => S.brands.find((b) => b.name === n).id;

  /* ---------------- more products ---------------- */
  const newProducts = [
    ['Whey Hydro Peptides', 'IRONFORGE', 1, '800 g', 27, 'g', 52.9, ['Whey isolate'], 'Hydrolysed whey peptides for fast post-training absorption.'],
    ['Clear Whey Refresh', 'PureCore Nutrition', 1, '600 g', 24, 'g', 34.9, ['Whey isolate'], 'Juice-style clear isolate, light texture, 22 g protein per serving.'],
    ['Oat Mass Builder', 'Titan Range', 7, '2500 g', 25, 'g', 32.5, ['Maltodextrin', 'Whey concentrate'], 'Oat-based gainer with a lower sugar load than classic maltodextrin blends.'],
    ['Creatine Gummies', 'Vertex Labs', 2, '90 gummies', 30, 'gummies', 26.9, ['Creatine monohydrate'], 'Chewable creatine, 3 g per serving, no water needed.'],
    ['Pump Matrix Caffeine-Free', 'Helix Nutrition', 4, '450 g', 30, 'g', 33.9, ['L-citrulline malate', 'Beta-alanine'], 'Stimulant-free pump matrix for late sessions.'],
    ['Focus Fuel Nootropic', 'Vertex Labs', 4, '270 g', 30, 'g', 39.9, ['Caffeine', 'L-theanine'], 'Caffeine and theanine at a 1:2 ratio with no proprietary blend.'],
    ['Electrolyte Salt Caps', 'Kinetiq', 6, '120 caps', 60, 'caps', 18.9, ['Electrolytes'], 'Sodium-forward salt capsules for long endurance efforts.'],
    ['Iso Whey Zero Lactose', 'Grindstone', 1, '1000 g', 33, 'g', 41.9, ['Whey isolate'], 'Lactose-free isolate in a value pack.'],
    ['Night Recovery Blend', 'Aurora Vital', 5, '300 g', 30, 'g', 29.9, ['Magnesium bisglycinate', 'Ashwagandha KSM-66'], 'Magnesium and adaptogen blend for evening use.'],
    ['Beef Protein Isolate', 'Fortis Nutrition', 1, '900 g', 30, 'g', 38.9, ['Whey isolate'], 'Dairy-free protein alternative from hydrolysed beef.'],
    ['Vitamin B Complex', 'Aurora Vital', 6, '120 caps', 120, 'caps', 15.9, ['Vitamin B6'], 'Full B-complex in active forms.'],
    ['Zinc Picolinate', 'BioPeak', 6, '180 caps', 180, 'caps', 12.9, ['Zinc'], 'Highly bioavailable zinc picolinate, 25 mg per capsule.'],
    ['Joint Flex Complex', 'Helix Nutrition', 9, '180 caps', 60, 'caps', 27.9, ['Type II collagen'], 'Collagen, MSM and boswellia for joint comfort.'],
    ['Greens Daily Mix', 'NORDKRAFT', 9, '300 g', 30, 'g', 31.9, ['Green tea extract'], 'Vegetable and algae concentrate with no added sweetener.'],
    ['Casein Micellar Slow', 'Vytal Labs', 1, '1000 g', 33, 'g', 37.9, ['Micellar casein'], 'Slow-digesting casein in a larger pack.'],
    ['Intra-Workout Carb Amino', 'Kinetiq', 7, '900 g', 30, 'g', 34.5, ['Cyclic dextrin', 'EAA'], 'Carbohydrate and EAA blend for long training sessions.'],
    ['Taurine Pure', 'Grindstone', 3, '300 g', 100, 'g', 13.9, ['Beta-alanine'], 'Plain taurine powder, no additives.'],
    ['HMB Strength Caps', 'Apex Fuel', 3, '150 caps', 50, 'caps', 24.9, ['L-glutamine'], 'HMB in capsules for training blocks with high volume.'],
  ];
  newProducts.forEach((d) => {
    const id = S.products.length + 1;
    const grams = parseFloat(d[3]);
    S.products.push({
      id, name: d[0], slug: slug(d[0]), brandId: B(d[1]), categoryId: d[2],
      pack: d[3], servings: d[4], unit: d[5], base: d[6], ingredients: d[7], short: d[8],
      ean: '859' + String(1000000 + id * 7919).slice(0, 7) + (id % 10),
      sku: 'CMP-' + String(id).padStart(4, '0'),
      rrp: Math.round(d[6] * 1.22 * 10) / 10,
      variants: d[5] === 'g' ? shuffle(['Vanilla', 'Chocolate', 'Berry', 'Unflavoured']).slice(0, int(2, 3)) : ['Standard'],
      packs: d[5] === 'g' ? [d[3], (grams * 2 >= 1000 ? (grams * 2 / 1000) + ' kg' : grams * 2 + ' g')] : [d[3]],
      desc: d[8] + ' Intended for adults as a food supplement; it does not replace a varied diet. Keep out of reach of children.',
      views: int(300, 18000), watchers: int(8, 900), created: NOW - int(10, 500) * DAY,
    });
  });

  /* ---------------- more merchants ---------------- */
  const newMerch = [
    ['FitZone Europe', 'ES', 4.2, 780, true, false, 'PRO', ['ES', 'PT', 'FR', 'IT', 'DE'], 5.2, 'fitzone.eu', 52],
    ['MuscleWorks', 'IT', 4.0, 615, true, false, 'FREE', ['IT', 'AT', 'DE', 'SK'], 5.8, 'muscleworks.it', 45],
    ['CoreNutri', 'FR', 4.4, 1120, true, true, 'PRO', ['FR', 'ES', 'IT', 'DE', 'NL'], 4.8, 'corenutri.fr', 60],
    ['Alpine Supps', 'AT', 4.5, 940, true, false, 'PRO', ['AT', 'DE', 'CZ', 'SK', 'IT'], 4.4, 'alpinesupps.at', 55],
    ['NorthLift', 'GB', 4.1, 1480, true, false, 'FREE', ['GB', 'NL', 'SE', 'DE'], 6.2, 'northlift.co.uk', 48],
  ];
  const carriers = ['DHL', 'DPD', 'GLS', 'UPS', 'PPL', 'Packeta', 'PostNL', 'FedEx', 'Correos', 'BRT'];
  const pays = ['Card', 'PayPal', 'Apple Pay', 'Google Pay', 'Bank transfer', 'Klarna', 'Cash on delivery'];
  newMerch.forEach((m) => {
    const id = S.merchants.length + 1;
    const zones = {};
    m[7].forEach((c) => {
      const home = c === m[1];
      zones[c] = { cost: home ? Math.round(m[8] * 0.7 * 10) / 10 : Math.round(m[8] * flt(1, 1.8, 2) * 10) / 10, days: home ? [1, 2] : [int(2, 4), int(4, 8)] };
    });
    S.merchants.push({
      id, name: m[0], slug: slug(m[0]), country: m[1], rating: m[2], reviews: m[3], verified: m[4], partner: m[5],
      tier: m[6], shipsTo: m[7], zones, freeOverEur: m[10], web: m[9], status: 'verified',
      currencies: shuffle(S.currencies.map((c) => c.code)).slice(0, int(2, 4)),
      carriers: shuffle(carriers).slice(0, int(2, 4)), payments: shuffle(pays).slice(0, int(3, 5)),
      sub: { shipping: flt(3.7, 4.8, 1), comms: flt(3.6, 4.8, 1), price: flt(3.6, 4.9, 1), support: flt(3.5, 4.8, 1), claims: flt(3.3, 4.7, 1), trust: flt(3.8, 4.9, 1) },
      created: NOW - int(40, 900) * DAY, productCount: int(380, 4200),
      desc: m[0] + ' is a specialist sports nutrition retailer holding ' + int(400, 4200) + ' SKUs in stock, with same-day dispatch on weekdays.',
      returnDays: pick([14, 30, 60]),
      affiliate: { network: pick(['Direct', 'Awin', 'Tradedoubler', 'Impact']), commission: flt(4, 11, 1), cookie: pick([7, 14, 30, 45]), sub: 'cmp-' + slug(m[0]) },
      faq: [
        { q: 'How long does delivery take?', a: 'Domestic orders arrive in 1–2 working days, cross-border in 2–8 days depending on the carrier.' },
        { q: 'How are claims handled?', a: 'Claims are filed online and resolved within ' + int(5, 18) + ' days; damaged parcels are replaced immediately.' },
        { q: 'Do you refund returns?', a: 'Yes, within ' + pick([14, 30]) + ' days of delivery, no reason required.' },
      ],
      // community metrics
      responseRate: int(62, 98), responseHours: flt(2, 46, 1), verifiedOrderRate: int(48, 92),
      complaints30: int(0, 14), complaintsResolved: int(60, 100), claimed: chance(0.8),
    });
  });
  // community metrics for the original merchants too
  S.merchants.forEach((m) => {
    if (m.responseRate === undefined) {
      m.responseRate = int(58, 99); m.responseHours = flt(1.5, 52, 1);
      m.verifiedOrderRate = int(45, 94); m.complaints30 = int(0, 18);
      m.complaintsResolved = int(55, 100); m.claimed = m.tier !== 'FREE' || chance(0.6);
    }
  });

  /* ---------------- offers for the expanded catalogue ---------------- */
  let oid = Math.max.apply(null, S.offers.map((o) => o.id)) + 1;
  const addOffer = (p, m) => {
    if (S.offers.some((o) => o.productId === p.id && o.merchantId === m.id)) return;
    const factor = flt(0.82, 1.18, 3) * (m.tier === 'FREE' ? 0.97 : 1);
    const price = Math.round(p.base * factor * 100) / 100;
    const old = chance(0.42) ? Math.round(price * flt(1.08, 1.45, 2) * 100) / 100 : null;
    const mc = S.coupons.filter((c) => c.merchantId === m.id);
    const av = R();
    S.offers.push({
      id: oid++, productId: p.id, merchantId: m.id, price, oldPrice: old,
      availability: av < 0.68 ? 'in_stock' : av < 0.82 ? 'low_stock' : av < 0.92 ? 'preorder' : 'out_of_stock',
      stock: av < 0.82 ? int(3, 240) : 0, warehouse: m.country,
      variant: pick(p.variants), pack: p.pack,
      couponId: chance(0.28) && mc.length ? pick(mc).id : null,
      sponsored: chance(0.07) && m.tier !== 'FREE',
      merchantSku: m.name.slice(0, 3).toUpperCase() + '-' + p.id * 31, ean: p.ean,
      url: 'https://' + m.web + '/p/' + p.slug,
      updated: NOW - int(0, 22) * 3600000, clicks30: int(15, 2400),
    });
  };
  // coupons for the new merchants first (so offers can reference them)
  let cid = Math.max.apply(null, S.coupons.map((c) => c.id)) + 1;
  S.merchants.slice(8).forEach((m) => {
    const n = m.tier === 'FREE' ? 1 : 2;
    for (let i = 0; i < n; i++) {
      const type = pick(['percent', 'fixed', 'freeship']);
      S.coupons.push({
        id: cid++, merchantId: m.id, code: (m.name.replace(/[^A-Za-z]/g, '').slice(0, 5) + int(5, 25)).toUpperCase(),
        title: type === 'percent' ? int(5, 20) + ' % off your whole order' : type === 'fixed' ? '€' + int(5, 15) + ' off your order' : 'Free shipping with no minimum',
        type, value: type === 'percent' ? int(5, 20) : type === 'fixed' ? int(5, 15) : 0,
        minOrder: pick([0, 30, 50]), countries: m.shipsTo, starts: NOW - int(2, 30) * DAY, ends: NOW + int(2, 40) * DAY,
        exclusive: false, uses: int(20, 2600), verifiedAt: NOW - int(0, 6) * DAY,
      });
    }
  });
  S.products.slice(28).forEach((p) => { shuffle(S.merchants).slice(0, int(3, 6)).forEach((m) => addOffer(p, m)); });
  S.merchants.slice(8).forEach((m) => { shuffle(S.products.slice(0, 28)).slice(0, int(8, 14)).forEach((p) => addOffer(p, m)); });

  /* ---------------- price history for the new products ---------------- */
  S.products.forEach((p) => {
    if (p.hist) return;
    const os = S.offers.filter((o) => o.productId === p.id);
    const curMin = Math.min.apply(null, os.map((o) => o.price));
    const curAvg = os.reduce((s, o) => s + o.price, 0) / os.length;
    const min = new Array(H), avg = new Array(H);
    min[H - 1] = curMin; avg[H - 1] = curAvg;
    for (let i = H - 2; i >= 0; i--) {
      const drift = 1 + (R() - 0.48) * 0.022;
      const spike = chance(0.03) ? flt(1.04, 1.12, 3) : 1;
      min[i] = Math.max(p.base * 0.55, Math.round(min[i + 1] * drift * spike * 100) / 100);
      avg[i] = Math.round(Math.max(min[i] * 1.04, avg[i + 1] * (1 + (R() - 0.49) * 0.018)) * 100) / 100;
    }
    const byMerchant = {};
    os.slice(0, 3).forEach((o) => {
      const arr = new Array(H); arr[H - 1] = o.price;
      for (let i = H - 2; i >= 0; i--) arr[i] = Math.round(Math.max(min[i], arr[i + 1] * (1 + (R() - 0.47) * 0.02)) * 100) / 100;
      byMerchant[o.merchantId] = arr;
    });
    p.hist = { min, avg, byMerchant };
    p.lowestEver = Math.round(Math.min.apply(null, min) * 100) / 100;
    p.change30 = Math.round((curMin / min[H - 31] - 1) * 1000) / 10;
    p.change7 = Math.round((curMin / min[H - 8] - 1) * 1000) / 10;
  });

  /* ---------------- reputation, badges, users ---------------- */
  S.badges = [
    { key: 'verified_buyer', label: 'Verified Buyer', desc: 'At least one review confirmed by an affiliate conversion or order reference.', color: 'ok' },
    { key: 'early_member', label: 'Early Member', desc: 'Joined in the platform\u2019s first year.', color: 'info' },
    { key: 'helpful_reviewer', label: 'Helpful Reviewer', desc: '50+ helpful votes received on reviews.', color: 'acc' },
    { key: 'top_reviewer', label: 'Top Reviewer', desc: '25+ published reviews with an above-average helpfulness rate.', color: 'acc' },
    { key: 'community_expert', label: 'Community Expert', desc: '10+ accepted answers in the forum.', color: 'acc' },
    { key: 'deal_hunter', label: 'Deal Hunter', desc: '5+ community deals approved by moderation.', color: 'warn' },
    { key: 'forum_contributor', label: 'Forum Contributor', desc: '25+ forum replies that were not removed.', color: 'info' },
    { key: 'trusted_member', label: 'Trusted Member', desc: 'Account older than a year with a clean moderation history.', color: 'ok' },
    { key: 'guide_author', label: 'Guide Author', desc: 'Published a community guide that passed review.', color: 'acc' },
  ];
  S.levels = [
    { key: 'new', label: 'New Member', min: 0 },
    { key: 'contributor', label: 'Contributor', min: 50 },
    { key: 'trusted', label: 'Trusted Contributor', min: 250 },
    { key: 'expert', label: 'Expert Contributor', min: 700 },
    { key: 'top', label: 'Top Contributor', min: 1500 },
  ];
  S.repRules = [
    { action: 'Verified review published', points: 20 },
    { action: 'Helpful vote received', points: 2 },
    { action: 'Accepted answer', points: 15 },
    { action: 'Community guide published', points: 25 },
    { action: 'Confirmed report', points: 5 },
    { action: 'Community deal approved', points: 10 },
  ];
  const extraNicks = ['DeadliftDana', 'nordic_nils', 'MacroMarek', 'SprintSofia', 'kettlebell_kim', 'ProteinPablo', 'EnduroErik', 'lab_leon', 'ClaraLifts', 'budget_bruno', 'VeganVera', 'GymRatGreg', 'PowerPia', 'shipping_sam', 'CutSeasonCarl', 'MacroMia', 'ironquest', 'HydrationHugo', 'ReviewRita', 'TrackTomas'];
  extraNicks.forEach((n) => {
    const id = S.users.length + 1;
    S.users.push({ id, nick: n, country: pick(S.countries).iso, joined: NOW - int(60, 1500) * DAY, reviews: int(0, 34), helpful: int(0, 420), verified: chance(0.85), avatar: n.slice(0, 2).toUpperCase() });
  });
  const bios = [
    'Powerlifting since 2016. I only review things I have finished a full tub of.',
    'Endurance athlete, two marathons a year. Mostly here for electrolytes and carb mixes.',
    'Nutrition student. I read labels for fun and complain about proprietary blends.',
    'Gym owner in Berlin. I buy in bulk and track every cent of shipping.',
    'Casual lifter. I care about taste and price, in that order.',
    'Vegan athlete, always hunting for plant protein that actually mixes.',
    'I compare cross-border prices for our training group of twelve people.',
    'Coach. I answer beginner questions so people stop wasting money.',
  ];
  S.users.forEach((u, i) => {
    const rep = Math.round(u.reviews * 20 + u.helpful * 2 + int(0, 180));
    u.rep = rep;
    u.level = S.levels.slice().reverse().find((l) => rep >= l.min).label;
    u.bio = i % 3 === 0 ? pick(bios) : (i % 4 === 1 ? pick(bios) : '');
    u.answers = int(0, 22);
    u.threads = int(0, 14);
    u.dealsShared = int(0, 9);
    u.badges = [];
    if (u.verified) u.badges.push('verified_buyer');
    if (u.joined < NOW - 900 * DAY) u.badges.push('early_member');
    if (u.helpful > 120) u.badges.push('helpful_reviewer');
    if (u.reviews > 22) u.badges.push('top_reviewer');
    if (u.answers > 12) u.badges.push('community_expert');
    if (u.dealsShared > 5) u.badges.push('deal_hunter');
    if (u.joined < NOW - 365 * DAY) u.badges.push('trusted_member');
    u.username = slug(u.nick);
    u.publicProfile = chance(0.9);
  });

  /* ---------------- more reviews (product + shop) ---------------- */
  let rid = 1 + S.reviews.reduce((m, r) => (typeof r.id === 'number' && r.id > m ? r.id : m), 0);
  const prTitles = ['Exactly what the label says', 'Good, not magic', 'Mixes better than my last tub', 'Repurchased three times', 'Great value per serving', 'Taste is the weak point', 'Solid but the pack is small', 'Works for my training block', 'Would buy again on discount', 'Better than the premium brand'];
  const prTexts = [
    'Three months in. Mixability is fine with a shaker, no clumps, and the scoop matches the label weight. Nothing dramatic happened, but nothing bad either, which is what I want from a staple.',
    'Bought it because it was the cheapest total including shipping in my country. Quality is on par with brands costing a third more, so I stopped paying the brand premium.',
    'Taste is average and I would not drink it in water twice a day, but with milk it is completely fine. Cost per serving is the reason I keep it in the rotation.',
    'Delivery took four days cross-border and the tub arrived sealed. Product itself does what it should; dosing is transparent, no hidden blend.',
    'I track my training numbers and I did see a slow improvement in volume over eight weeks. Attributing it entirely to this would be silly, but I am not switching.',
    'The pack is smaller than the photos suggest, so check grams rather than the picture. Otherwise honest product at an honest price.',
    'Support answered a question about the batch report within a day and sent the PDF. That transparency is why this brand gets my money.',
  ];
  const prPros = ['clean label', 'mixes instantly', 'cost per serving', 'no bloating', 'batch report published', 'pleasant taste', 'resealable pack', 'fast delivery'];
  const prCons = ['pricey at full price', 'sweetener aftertaste', 'small scoop', 'limited flavours', 'shipping cost', 'pack design'];
  S.products.forEach((p) => {
    const n = int(2, 5);
    for (let i = 0; i < n; i++) {
      const rating = pick([5, 5, 5, 4, 4, 4, 4, 3, 3, 2]);
      const os = S.offers.filter((o) => o.productId === p.id);
      S.reviews.push({
        id: rid++, type: 'product', targetId: p.id, userId: pick(S.users).id, rating,
        title: pick(prTitles), text: pick(prTexts),
        pros: shuffle(prPros).slice(0, int(1, 3)), cons: rating >= 4 ? shuffle(prCons).slice(0, int(0, 2)) : shuffle(prCons).slice(0, int(1, 3)),
        recommend: rating >= 4, verifiedPurchase: chance(0.58),
        verifyMethod: chance(0.5) ? 'affiliate_conversion' : chance(0.5) ? 'order_reference' : 'merchant_confirmed',
        merchantId: os.length && chance(0.6) ? pick(os).merchantId : null,
        date: NOW - int(1, 320) * DAY, helpful: int(0, 96), notHelpful: int(0, 14),
        status: 'approved', reply: null, reported: false,
        sub: { value: Math.min(5, rating + int(-1, 1)), quality: Math.min(5, rating + int(-1, 1)), packaging: Math.min(5, rating + int(-1, 1)), ease: Math.min(5, rating + int(-1, 1)) },
        usedFor: pick(['less than a month', '1–3 months', '3–6 months', 'over a year']),
        photos: chance(0.22) ? int(1, 3) : 0,
        experience: pick(['beginner', 'intermediate', 'advanced', 'competitive']),
      });
    }
  });
  const merTitles = ['Fast and predictable', 'Support fixed a mistake quickly', 'Cheap but slow', 'Great packaging, fair prices', 'Cross-border shipping was smooth', 'Refund took too long', 'My default shop now'];
  const merTexts = [
    'Four orders so far, all dispatched the same day. Tracking updates arrive properly and nothing has been damaged in transit.',
    'They shipped the wrong flavour, replied within hours and sent the right one with a prepaid return label. That is how it should work.',
    'Prices are among the lowest I found, but cross-border delivery took nine days and the tracking stopped updating halfway.',
    'The free shipping threshold is realistic and the checkout shows the total including delivery, so no surprises at the end.',
    'Refund after a return took nineteen days and two emails. Product side is fine, finance side needs work.',
  ];
  S.merchants.forEach((m) => {
    const n = int(7, 12);
    for (let i = 0; i < n; i++) {
      const rating = pick([5, 5, 4, 4, 4, 4, 3, 3, 2, 5]);
      S.reviews.push({
        id: rid++, type: 'merchant', targetId: m.id, userId: pick(S.users).id, rating,
        title: pick(merTitles), text: pick(merTexts),
        sub: { shipping: Math.min(5, rating + int(-1, 1)), shippingCost: Math.min(5, rating + int(-1, 1)), support: Math.min(5, rating + int(-1, 1)), comms: Math.min(5, rating + int(-1, 1)), accuracy: Math.min(5, rating + int(-1, 1)), returns: Math.min(5, rating + int(-1, 0)) },
        pros: [], cons: [], recommend: rating >= 4, verifiedPurchase: chance(0.66),
        verifyMethod: chance(0.6) ? 'affiliate_conversion' : 'order_reference',
        date: NOW - int(1, 260) * DAY, helpful: int(0, 72), notHelpful: int(0, 11),
        status: 'approved',
        reply: chance(0.32) ? { text: 'Thank you for the detailed feedback. We have raised the delivery issue with our carrier and are reviewing our free shipping threshold for cross-border orders.', date: NOW - int(1, 60) * DAY, author: m.name } : null,
        resolved: chance(0.2), reported: false,
        photos: 0, experience: null,
      });
    }
  });

  /* ---------------- compliance for the new products ---------------- */
  let crid = 1 + S.complianceRules.reduce((m, r) => (r.id > m ? r.id : m), 0);
  const focus = S.products.find((p) => p.name === 'Focus Fuel Nootropic');
  const greens = S.products.find((p) => p.name === 'Greens Daily Mix');
  const joint = S.products.find((p) => p.name === 'Joint Flex Complex');
  [['FR', focus, 'restricted', 'Caffeine content requires a mandatory on-pack warning.', 'EU 1169/2011, Art. 10'],
   ['IT', focus, 'restricted', 'Caffeine content requires a mandatory on-pack warning.', 'EU 1169/2011, Art. 10'],
   ['SE', greens, 'unknown', '', 'Automated feed import'],
   ['DE', greens, 'unknown', '', 'Automated feed import'],
   ['NL', joint, 'unknown', '', 'Automated feed import'],
   ['PL', focus, 'unknown', '', 'Automated feed import']].forEach((r) => {
    if (!r[1]) return;
    S.complianceRules.push({ id: crid++, productId: r[1].id, country: r[0], status: r[2], reason: r[3], source: r[4], reviewedBy: r[2] === 'unknown' ? null : 'compliance@comparo', reviewedAt: r[2] === 'unknown' ? null : NOW - int(5, 120) * DAY });
  });

  /* ---------------- affiliate analytics + feeds for new merchants ---------------- */
  S.merchants.slice(8).forEach((m) => {
    const scale = m.tier === 'PREMIUM' ? 3 : m.tier === 'PRO' ? 1.7 : 1;
    for (let d = 29; d >= 0; d--) {
      const clicks = Math.round((int(30, 160) + (29 - d) * 1.2) * scale);
      const conv = Math.round(clicks * flt(0.02, 0.07, 4));
      const aov = flt(44, 92, 2);
      const revenue = Math.round(conv * aov * 100) / 100;
      S.affiliate.daily.push({ merchantId: m.id, day: isoDate(NOW - d * DAY), clicks, unique: Math.round(clicks * flt(0.78, 0.93, 2)), conv, revenue, commission: Math.round(revenue * m.affiliate.commission) / 100 });
    }
    S.feeds.push({ merchantId: m.id, url: 'https://' + m.web + '/feed/comparo.xml', format: pick(['XML', 'CSV', 'JSON', 'API']), interval: pick(['1 h', '4 h', '12 h', '24 h']), lastRun: NOW - int(10, 400) * 60000, items: int(380, 4200), matched: 0, unmatched: int(4, 70), errors: int(0, 9), status: pick(['ok', 'ok', 'warning']) });
  });
  S.feeds.forEach((f) => { if (!f.matched) f.matched = f.items - f.unmatched; });

  /* ---------------- tags ---------------- */
  S.tags = ['shipping', 'germany', 'eu', 'deal', 'comparison', 'beginner', 'shop-review', 'brand', 'protein', 'creatine', 'pre-workout', 'customs', 'returns', 'price-history', 'vegan', 'endurance', 'labels', 'compliance'];

  /* ---------------- forum ---------------- */
  S.forumCategories = [
    { slug: 'general', name: 'General', desc: 'Anything about comparing, buying and using performance products.', icon: '◆' },
    { slug: 'products', name: 'Products', desc: 'Formulas, labels, dosing and what actually works.', icon: '▣' },
    { slug: 'brands', name: 'Brands', desc: 'Brand quality, testing and transparency.', icon: '◈' },
    { slug: 'shops', name: 'Shops', desc: 'Shop experiences, reliability and support.', icon: '▤' },
    { slug: 'deals', name: 'Deals', desc: 'Deal discussion, coupon stacking and price alerts.', icon: '▧' },
    { slug: 'shipping', name: 'Shipping', desc: 'Carriers, customs, thresholds and delivery times.', icon: '▩' },
    { slug: 'germany', name: 'Germany', desc: 'German market: shops, VAT, regulation.', icon: 'DE' },
    { slug: 'europe', name: 'Europe', desc: 'Cross-border buying inside the EU and EEA.', icon: 'EU' },
    { slug: 'north-america', name: 'North America', desc: 'US and Canada shops, duties and shipping.', icon: 'NA' },
    { slug: 'asia-pacific', name: 'Asia-Pacific', desc: 'APAC availability and import rules.', icon: 'AP' },
    { slug: 'reviews-experiences', name: 'Reviews & Experiences', desc: 'Long-form experiences that do not fit a review form.', icon: '★' },
    { slug: 'beginner-questions', name: 'Beginner Questions', desc: 'No question is too basic here.', icon: '?' },
    { slug: 'comparisons', name: 'Comparisons', desc: 'Head-to-head threads on products, shops and brands.', icon: '⇄' },
    { slug: 'site-feedback', name: 'Site Feedback', desc: 'Bugs, data errors and feature requests for Comparo.', icon: '✎' },
  ];
  const threadSeeds = [
    ['shipping', 'question', 'Which shops actually ship to Germany without customs surprises?', 'I keep getting hit with handling fees on orders from UK shops. Which EU-based shops have you used that quote the final total up front, including any duties? Bonus points if they publish the carrier.', ['shipping', 'germany', 'customs'], 'DE'],
    ['deals', 'discussion', 'Coupon stacking: which shops let you combine a code with a sale price?', 'Comparo shows the best applicable coupon, but a few shops silently block codes on discounted items. Post the ones where stacking still works so we can flag the rest.', ['deal', 'comparison'], null],
    ['products', 'question', 'Is hydrolysed whey worth double the price of a normal isolate?', 'I train fasted in the mornings and keep reading that peptides absorb faster. Is there a practical difference for someone who is not a competitive athlete, or is this a marketing story?', ['protein', 'beginner'], null],
    ['shops', 'discussion', 'PeakSupps vs IronLab: two years of orders compared', 'Twelve orders from PeakSupps, nine from IronLab. Delivery, packaging, support responses and one botched return, with dates. Long post, but the summary is at the end.', ['shop-review', 'comparison'], 'DE'],
    ['beginner-questions', 'question', 'How do I read a label when the brand uses a proprietary blend?', 'The tub says 8 g blend but does not break it down. How do you compare that against a fully disclosed label without guessing?', ['labels', 'beginner'], null],
    ['price-history', 'discussion', 'Creatine has been drifting down all year — is this a raw material thing?', 'Comparo price history shows a steady decline on monohydrate across nine shops. Anyone in the industry know whether this is raw material pricing or just competition?', ['creatine', 'price-history'], null],
    ['germany', 'question', 'Melatonin is prescription-only here — what do people use instead?', 'The compliance notice on the melatonin product explains why it is not purchasable in Germany. What legal alternatives have actually worked for you? Nothing medical, just experience.', ['germany', 'compliance'], 'DE'],
    ['europe', 'discussion', 'Free shipping thresholds ranked across 13 shops', 'I built a small table of thresholds and per-country costs. Interesting finding: two shops are cheaper overall despite a higher product price, purely because of the threshold.', ['shipping', 'eu', 'comparison'], null],
    ['comparisons', 'question', 'Clear whey vs classic isolate for summer training', 'Clear whey tastes better to me but has less protein per serving. Has anyone worked out the cost per 100 g of protein rather than per serving?', ['protein', 'comparison'], null],
    ['shops', 'question', 'Has anyone had a return handled by NorthLift?', 'Ordered the wrong pack size and want to know how their returns work in practice before I commit. The policy page says 14 days.', ['returns', 'shop-review'], 'GB'],
    ['products', 'discussion', 'Electrolytes: sodium content is the only number that matters', 'Most hydration products are dosed for a desk job. If you sweat heavily you need far more sodium per litre. Here is what I use in summer and why.', ['endurance'], null],
    ['brands', 'discussion', 'Which brands actually publish batch lab reports?', 'Publishing a certificate of analysis per batch should be table stakes. Four brands in the catalogue do it consistently, three publish sometimes, the rest never.', ['brand', 'labels'], null],
    ['deals', 'discussion', 'The best deal I found this month was not the biggest discount', 'A 12 % coupon on a shop with free delivery beat a 35 % sale that added €9 shipping. Total price is the only number worth looking at.', ['deal', 'shipping'], null],
    ['general', 'question', 'How does Comparo decide which offer is "best value"?', 'I see the badge on offers that are not the cheapest. What goes into that score, and can shops pay to get it?', ['comparison'], null],
    ['beginner-questions', 'question', 'First stack on a student budget — what is actually necessary?', 'Budget is about €40 a month. I keep reading recommendations for eight products. What would you cut?', ['beginner'], null],
    ['reviews-experiences', 'discussion', 'Six months on a vegan protein blend: honest write-up', 'Taste, digestion, mixing, price per 100 g of protein, and how it compares to the whey I used before. Photos of the label included.', ['vegan', 'protein'], null],
    ['north-america', 'question', 'Ordering from SupplementBay to the EU — duties in practice?', 'The prices look good but I want to know what the landed cost really was for people who did it.', ['shipping', 'customs'], 'US'],
    ['asia-pacific', 'question', 'Any shops in the catalogue that ship to Australia?', 'The country filter shows nothing for AU. Is that a data gap or does nobody ship there?', ['shipping'], null],
    ['site-feedback', 'discussion', 'Price on one offer does not match the shop page', 'Found a 4 % difference on one product. Posting so it can be checked — feed might be stale.', ['price-history'], null],
    ['shipping', 'discussion', 'Which carriers actually deliver on time in winter?', 'Anecdata from 40+ orders: two carriers were consistently late in December, one was flawless.', ['shipping'], null],
  ];
  const replyTexts = [
    'I have ordered from three of those and only one quoted duties up front. The other two added a handling fee on delivery.',
    'This matches my experience. The total including shipping is the only comparison that makes sense, everything else is marketing.',
    'Worth adding that the threshold resets per shipment, so splitting an order almost always costs more.',
    'Good write-up. One correction: the shop changed its return window last month, it is 30 days now, not 14.',
    'I ran the numbers on cost per 100 g of protein for the same three products and the ranking flipped completely.',
    'Support answered me within four hours on a weekday, but a weekend query took until Tuesday. Set expectations accordingly.',
    'The compliance notice explains it well: the status is per market, so what you can buy depends on where you ask it to be delivered.',
    'Not my experience unfortunately. Two of my four orders arrived late and tracking stopped mid-transit.',
    'If you are on a budget, protein and creatine cover almost everything. The rest is optional.',
    'Bought during the last price drop and the alert fired the same evening. The history chart is genuinely useful here.',
    'Careful with the pack size: the cheaper listing is a smaller tub, so the per-serving cost is actually higher.',
    'Their batch reports are published with a lot number you can match to the tub, which is more than most brands do.',
    'Thanks, this saved me an order. I will stick with the EU-based shop even though it is €3 more.',
    'I asked the shop directly and they confirmed they now ship to two more countries. Profile might need updating.',
  ];
  S.forumThreads = [];
  S.forumReplies = [];
  let tid = 1, rpid = 1;
  threadSeeds.forEach((t, i) => {
    const author = pick(S.users);
    const created = NOW - int(1, 90) * DAY;
    const nReplies = int(2, 9);
    const thread = {
      id: tid++, slug: slug(t[2]).slice(0, 60), category: t[0], kind: t[1], title: t[2], body: t[3],
      tags: t[4], country: t[5], userId: author.id, created,
      views: int(140, 8600), pinned: i < 2, locked: false, status: 'approved',
      votes: int(0, 74), acceptedReplyId: null, productId: chance(0.4) ? pick(S.products).id : null,
      merchantId: chance(0.3) ? pick(S.merchants).id : null,
      lastActivity: created,
    };
    S.forumThreads.push(thread);
    for (let k = 0; k < nReplies; k++) {
      const at = created + int(1, 40) * DAY * 0.4 + k * int(1, 20) * 3600000;
      const rep = {
        id: rpid++, threadId: thread.id, userId: pick(S.users).id, body: pick(replyTexts),
        created: Math.min(NOW - 3600000, at), votes: int(0, 41), status: 'approved',
        quoteOf: k > 0 && chance(0.25) ? rpid - 1 : null,
      };
      S.forumReplies.push(rep);
      if (rep.created > thread.lastActivity) thread.lastActivity = rep.created;
    }
    if (t[1] === 'question' && chance(0.6)) {
      const mine = S.forumReplies.filter((r) => r.threadId === thread.id);
      if (mine.length) { const best = mine.reduce((a, b) => (b.votes > a.votes ? b : a)); thread.acceptedReplyId = best.id; }
    }
  });

  /* ---------------- community guides ---------------- */
  S.guides = [
    ['How to compare supplement shops in Germany', 'shops', ['germany', 'shop-review', 'comparison'], 'A practical checklist: delivery cost to your postcode, free shipping threshold, return window, support response time and whether the shop publishes its carrier.'],
    ['Understanding international shipping and customs', 'shipping', ['shipping', 'customs', 'eu'], 'What changes when a parcel crosses a border: VAT handling, handling fees, carrier hand-offs and why a cheap product from a distant warehouse often is not cheap.'],
    ['How price history works and how to use it', 'deals', ['price-history', 'deal'], 'Reading a price chart properly: distinguishing a real drop from a restored RRP, spotting seasonal cycles and choosing a sensible alert target.'],
    ['Comparing shop reliability without guessing', 'shops', ['shop-review'], 'Which signals actually predict a good order: verified order rate, response time, resolution rate and how to read a rating breakdown instead of a single number.'],
    ['Reading a supplement label in two minutes', 'products', ['labels', 'beginner'], 'Serving size versus scoop size, proprietary blends, active dose ranges and the three places brands hide a low dose.'],
    ['Cost per 100 g of protein: the only protein metric that matters', 'products', ['protein', 'comparison'], 'A step-by-step normalisation you can do in your head, plus a worked example across four products and three shops.'],
    ['Building a first stack on a small budget', 'products', ['beginner'], 'What to buy first, what to skip for now, and how to use alerts so you buy at the bottom of the price cycle.'],
    ['How coupons, thresholds and shipping interact', 'deals', ['deal', 'shipping'], 'Why a smaller percentage sometimes wins, when topping up to a threshold pays off and how the total is calculated on Comparo.'],
  ].map((g, i) => {
    const author = S.users.filter((u) => u.rep > 300)[i % Math.max(1, S.users.filter((u) => u.rep > 300).length)] || S.users[i];
    return {
      id: i + 1, slug: slug(g[0]), title: g[0], category: g[1], tags: g[2], excerpt: g[3],
      userId: author.id, created: NOW - int(5, 260) * DAY, status: 'approved',
      views: int(400, 12000), votes: int(6, 180), read: int(4, 14),
      body: [
        g[3],
        'The method below is deliberately boring. Every step uses data you can see on the platform: the offer table, the shipping row, the rating breakdown and the price chart. Nothing depends on trusting a single number.',
        'Start by fixing your delivery country, because everything downstream changes with it: which shops are even eligible, what shipping costs, and whether the product is purchasable in your market at all.',
        'Then normalise. Compare totals including shipping, and per unit where the pack sizes differ. A listing that looks 15 % cheaper is regularly more expensive once the pack size and delivery are accounted for.',
        'Finally, weigh reliability. A two-euro saving at a shop with a 40 % complaint resolution rate is not a saving. Response rate and resolution rate are published on every shop profile for exactly this reason.',
      ],
      relatedProducts: shuffle(S.products).slice(0, 3).map((p) => p.id),
      relatedShops: shuffle(S.merchants).slice(0, 2).map((m) => m.id),
    };
  });

  /* ---------------- community deals ---------------- */
  const dealSeeds = [
    ['PeakSupps', 'Whey Isolate 90', 'Whey Isolate 90 at its lowest total this year', 34.9, 44.9, 'PEAK15', 'DE'],
    ['IronLab Store', 'Strength Core', 'Creapure 500 g under €20 with code', 19.4, 24.9, 'IRONL12', 'CZ'],
    ['CoreNutri', 'EAA Complete', 'EAA Complete 450 g, free delivery over €40', 27.9, 31.9, '', 'FR'],
    ['AthleteSupply', 'Recovery Matrix', 'Recovery Matrix bundle: buy two, second at half price', 26.2, 34.9, 'ATHLE20', 'NL'],
    ['PerformanceHub', 'Omega-3 Ultra', 'Omega-3 Ultra 120 caps, three-for-two', 14.6, 21.9, '', 'GB'],
    ['Alpine Supps', 'Magnesium Bisglycinate', 'Magnesium 180 caps at Austrian warehouse price', 15.9, 19.9, 'ALPIN10', 'AT'],
    ['NordicGains', 'Electrolyte Hydration', 'Hydration sticks 60x, lowest in Sweden', 21.5, 26.9, '', 'SE'],
    ['BodyCore Market', 'Carb Loader Maltodextrin', '3 kg maltodextrin under €15', 14.9, 19.9, '', 'PL'],
    ['FitZone Europe', 'Clear Whey Refresh', 'Clear whey summer clearance, 25 % off', 26.2, 34.9, 'FITZ25', 'ES'],
    ['MuscleWorks', 'BCAA 4:1:1', 'BCAA 500 g, Italian stock, free shipping', 18.9, 22.9, '', 'IT'],
    ['PeakSupps', 'Performance Alpha', 'Performance Alpha with a free shaker', 35.9, 39.9, '', 'DE'],
    ['NorthLift', 'Iso Whey Zero Lactose', 'Lactose-free isolate 1 kg at UK warehouse price', 35.9, 41.9, 'NORTH10', 'GB'],
    ['IronLab Store', 'Vitamin D3 + K2', 'D3+K2 90 caps, add-on price under €12', 11.9, 14.9, '', 'CZ'],
    ['CoreNutri', 'Nitric Surge', 'Stimulant-free pump formula, 20 % off', 28.7, 35.9, 'CORE20', 'FR'],
    ['Alpine Supps', 'Night Recovery Blend', 'New product launch price, ends Sunday', 24.9, 29.9, '', 'AT'],
  ];
  S.communityDeals = dealSeeds.map((d, i) => {
    const m = S.merchants.find((x) => x.name === d[0]) || S.merchants[0];
    const p = S.products.find((x) => x.name === d[1]) || S.products[0];
    const up = int(8, 220), down = int(0, 26);
    return {
      id: i + 1, merchantId: m.id, productId: p.id, title: d[2], price: d[3], oldPrice: d[4],
      code: d[5], country: d[6], userId: pick(S.users).id, created: NOW - int(0, 26) * DAY,
      expires: NOW + int(1, 30) * DAY,
      description: 'Found this while checking the price chart — it is below the 90-day average and the shop ships from its home warehouse, so delivery is fast. Total including shipping is the number to compare.',
      status: i < 12 ? 'approved' : 'pending',
      votesGood: up, votesExpired: down, votesWrong: int(0, 9),
      comments: int(0, 18), views: int(120, 5400),
      link: 'https://' + m.web + '/p/' + p.slug,
    };
  });

  /* ---------------- shop Q&A + announcements ---------------- */
  const qaSeeds = [
    ['Do you ship to Germany and what does it cost?', 'Yes — Germany is served from our home warehouse, €4.90 or free above the threshold shown on the profile. Delivery is 1–2 working days.', true],
    ['Can I combine a coupon with a sale price?', 'Percentage codes apply to the already discounted price; fixed-amount codes require the stated minimum order.', true],
    ['How long does a refund take after a return?', 'From my last return: eight days from drop-off to money back on the card.', false],
    ['Do you accept PayPal for cross-border orders?', 'PayPal is available in every country we serve; Klarna is limited to a subset.', true],
    ['Is the flavour selection the same across countries?', 'Mostly, but two flavours are exclusive to the domestic warehouse. Ask before ordering if it matters.', false],
  ];
  S.shopQA = [];
  let qid = 1;
  S.merchants.forEach((m) => {
    shuffle(qaSeeds).slice(0, int(2, 4)).forEach((q) => {
      S.shopQA.push({
        id: qid++, merchantId: m.id, question: q[0], askedBy: pick(S.users).id, asked: NOW - int(2, 120) * DAY,
        answer: q[1], official: q[2], answeredBy: q[2] ? m.name : pick(S.users).nick,
        answered: NOW - int(1, 60) * DAY, votes: int(0, 34),
      });
    });
  });
  S.announcements = [
    { id: 1, merchantId: 1, kind: 'Shipping update', title: 'Sweden now served from the German warehouse', body: 'Orders to Sweden ship from Germany from this week, cutting delivery from 6–8 to 3–5 working days. Shipping cost is unchanged.', date: NOW - 2 * DAY },
    { id: 2, merchantId: 3, kind: 'Return policy update', title: 'Return window extended to 60 days', body: 'All orders placed from today have a 60-day return window, including opened tubs where the seal is intact.', date: NOW - 6 * DAY },
    { id: 3, merchantId: 5, kind: 'New delivery country', title: 'We now ship to Portugal', body: 'Portugal is live with the same carrier and free shipping threshold as Spain.', date: NOW - 11 * DAY },
    { id: 4, merchantId: 2, kind: 'Store maintenance', title: 'Checkout maintenance on Sunday 02:00–04:00 CET', body: 'Orders cannot be placed during the window. Prices and stock in the comparison stay accurate.', date: NOW - 15 * DAY },
    { id: 5, merchantId: 11, kind: 'Shipping update', title: 'Carrier change for France', body: 'French orders move to a new carrier with earlier cut-off; place orders before 15:00 for same-day dispatch.', date: NOW - 19 * DAY },
  ];

  /* ---------------- review insight topics ---------------- */
  S.insightTopics = ['Delivery', 'Price', 'Support', 'Packaging', 'Returns', 'Taste', 'Mixability', 'Label accuracy'];

  /* ---------------- notification templates ---------------- */
  S.notifTemplates = [
    { kind: 'price', text: 'Whey Isolate 90 dropped to its lowest total in 90 days.', href: '#/products/whey-isolate-90' },
    { kind: 'deal', text: 'New Comparo exclusive at PeakSupps: 15 % off everything.', href: '#/deals' },
    { kind: 'stock', text: 'Recovery Matrix is back in stock at IronLab Store.', href: '#/products/recovery-matrix' },
    { kind: 'forum', text: 'nordic_nils replied to your thread about shipping to Germany.', href: '#/forum' },
    { kind: 'review', text: 'PeakSupps responded to your shop review.', href: '#/shops/peaksupps' },
    { kind: 'accepted', text: 'Your answer was accepted in “How do I read a label…”.', href: '#/forum' },
    { kind: 'follower', text: 'MacroMarek started following you.', href: '#/community' },
    { kind: 'mention', text: 'ReviewRita mentioned you in Deals.', href: '#/forum/deals' },
    { kind: 'moderation', text: 'Your community deal was approved and is now public.', href: '#/deals' },
    { kind: 'announcement', text: 'PeakSupps posted a shipping update for Sweden.', href: '#/shops/peaksupps' },
  ];

  /* ---------------- activity feed ---------------- */
  S.activity = [];
  let aid = 1;
  const actionsFor = () => {
    const u = pick(S.users);
    const roll = R();
    if (roll < 0.32) {
      const p = pick(S.products);
      return { kind: 'review', userId: u.id, text: 'reviewed ' + p.name, href: '#/products/' + p.slug, target: p.name, rating: int(3, 5) };
    }
    if (roll < 0.5) {
      const t = pick(S.forumThreads);
      return { kind: 'discussion', userId: u.id, text: (t.kind === 'question' ? 'answered ' : 'replied in ') + '“' + t.title.slice(0, 52) + (t.title.length > 52 ? '…' : '') + '”', href: '#/forum/topic/' + t.slug, target: t.title };
    }
    if (roll < 0.66) {
      const d = pick(S.communityDeals);
      const m = S.merchants.find((x) => x.id === d.merchantId);
      return { kind: 'deal', userId: u.id, text: 'found a deal at ' + m.name, href: '#/deals', target: d.title };
    }
    if (roll < 0.78) {
      const m = pick(S.merchants);
      return { kind: 'review', userId: u.id, text: 'shared a shop experience with ' + m.name, href: '#/shops/' + m.slug, target: m.name, rating: int(2, 5) };
    }
    if (roll < 0.9) {
      const g = pick(S.guides);
      return { kind: 'guide', userId: u.id, text: 'published the guide “' + g.title + '”', href: '#/guides/' + g.slug, target: g.title };
    }
    const t = pick(S.forumThreads.filter((x) => x.kind === 'question'));
    return { kind: 'question', userId: u.id, text: 'asked “' + (t ? t.title.slice(0, 56) : 'a question') + '”', href: t ? '#/forum/topic/' + t.slug : '#/forum', target: t ? t.title : '' };
  };
  for (let i = 0; i < 60; i++) {
    const a = actionsFor();
    a.id = aid++; a.ts = NOW - int(1, 600) * 3600000;
    S.activity.push(a);
  }
  S.activity.sort((a, b) => b.ts - a.ts);

  /* ---------------- helpers used by the community layer ---------------- */
  S.helpers.levelFor = (rep) => S.levels.slice().reverse().find((l) => rep >= l.min) || S.levels[0];
  S.helpers.trending = (o) => {
    const ageH = Math.max(1, (NOW - (o.lastActivity || o.created)) / 3600000);
    const engagement = (o.views || 0) * 0.02 + (o.votes || 0) * 3 + (o.replies || 0) * 6;
    return Math.round((engagement / Math.pow(ageH + 2, 0.62)) * 10) / 10;
  };
})();
