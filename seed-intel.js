/* Comparo Performance — intelligence-layer seed data.
   Extends window.SEED in place under S.ix (plus a few in-place attributes on merchants/offers/coupons).
   Loaded after seed.js / seed-community.js / seed-seo.js. All data fictional and deterministic. */
(function () {
  const S = window.SEED;
  if (!S) return;
  const mk = (s) => () => { s |= 0; s = s + 0x6D2B79F5 | 0; let t = Math.imul(s ^ s >>> 15, 1 | s); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; };
  const R = mk(20260907);
  const pick = (a) => a[Math.floor(R() * a.length)];
  const int = (a, b) => a + Math.floor(R() * (b - a + 1));
  const flt = (a, b, d = 2) => Math.round((a + R() * (b - a)) * 10 ** d) / 10 ** d;
  const chance = (p) => R() < p;
  const DAY = S.DAY, NOW = S.NOW, HOUR = 3600000;
  const M = S.merchants, P = S.products, O = S.offers;
  const byName = (n) => P.find((p) => p.name === n);
  const offerOf = (name, mid) => { const p = byName(name); return p ? O.find((o) => o.productId === p.id && (mid ? o.merchantId === mid : true)) : null; };
  const ix = {};
  S.ix = ix;

  /* ============ 1. merchant intelligence attributes ============ */
  /* [bizVerified, complaintRate%, resolution%, responseRate%, priceAccuracy%, feedUptime%, shipAccuracy%, brokenLink%, communityReports, deliveryOnTime%, trend] */
  const profiles = {
    1: [1, 0.4, 97, 98, 99.2, 99.7, 97.4, 0.1, 1, 96, 'up'],
    2: [1, 0.8, 93, 95, 98.1, 99.1, 95.2, 0.4, 2, 93, 'flat'],
    3: [1, 1.1, 88, 86, 96.4, 97.2, 92.0, 0.9, 4, 89, 'flat'],
    4: [1, 2.4, 71, 64, 92.1, 88.4, 84.1, 3.2, 9, 78, 'down'],
    5: [1, 0.9, 91, 90, 97.6, 98.4, 94.3, 0.5, 3, 92, 'up'],
    6: [0, 3.6, 52, 41, 86.9, 74.2, 72.4, 7.4, 18, 66, 'down'],
    7: [1, 1.6, 80, 77, 94.2, 93.6, 88.5, 1.8, 6, 85, 'flat'],
    8: [0, 4.8, 38, 29, 79.4, 61.5, 64.2, 11.2, 27, 58, 'down'],
  };
  M.forEach((m) => {
    const q = (m.rating - 3.6) / 1.4; // 0..1 quality proxy for merchants added later
    const pr = profiles[m.id] || [
      m.verified ? 1 : 0, Math.round((3.2 - q * 2.6) * 10) / 10, Math.round(60 + q * 34), Math.round(55 + q * 40),
      Math.round((90 + q * 9) * 10) / 10, Math.round((82 + q * 17) * 10) / 10, Math.round((76 + q * 21) * 10) / 10,
      Math.round((5 - q * 4.6) * 10) / 10, Math.round(14 - q * 12), Math.round(70 + q * 27), q > 0.6 ? 'up' : q > 0.3 ? 'flat' : 'down',
    ];
    const ageDays = m.id <= 8 ? [1420, 980, 2100, 410, 760, 195, 640, 120][m.id - 1] : int(150, 900);
    const series = new Array(365);
    const dir = pr[10] === 'up' ? 1 : pr[10] === 'down' ? -1 : 0;
    let v = -dir * (dir ? 7 : 0);
    for (let i = 0; i < 365; i++) {
      v += (R() - 0.5) * 0.9 + dir * 0.042;
      series[i] = Math.max(-14, Math.min(9, Math.round(v * 10) / 10));
    }
    m.ix = {
      bizVerified: !!pr[0], complaintRate: pr[1], resolution: pr[2], responseRate: pr[3], priceAccuracy: pr[4],
      feedUptime: pr[5], shipAccuracy: pr[6], brokenLinkRate: pr[7], communityReports: pr[8], deliveryOnTime: pr[9],
      trend: pr[10], accountAgeDays: ageDays, verifiedOrderRate: Math.round(48 + (m.rating - 3.6) / 1.4 * 46),
      verifiedReviewRatio: Math.round(40 + (m.rating - 3.6) / 1.4 * 52),
      trustSeries: series,
      sla: {
        feedFreshnessTarget: '6 h', feedFreshnessActual: Math.round((2 + (100 - pr[5]) * 0.55) * 10) / 10 + ' h',
        responseTarget: '24 h', responseActual: Math.round(4 + (100 - pr[3]) * 0.9) + ' h',
        complaintTarget: '72 h', complaintActual: Math.round(18 + (100 - pr[2]) * 1.6) + ' h',
        correctionTarget: '48 h', correctionActual: Math.round(9 + (100 - pr[4]) * 3.4) + ' h',
      },
      cvr: Math.round((1.4 + (m.rating - 3.6) / 1.4 * 4.6) * 100) / 100,
      returnRate: Math.round((1.2 + (5 - m.rating) * 2.4) * 10) / 10,
    };
  });

  /* ============ 2. risk events ============ */
  const riskDefs = [
    [8, 'rating_spike', 'Rating rose 0.9 in 6 days on 31 new reviews', NOW - 2 * DAY, 'HIGH'],
    [8, 'duplicate_reviews', '9 reviews share 88 %+ text similarity', NOW - 2 * DAY, 'HIGH'],
    [8, 'feed_stale', 'Feed has not imported for 4 days 6 h', NOW - 6 * HOUR, 'MEDIUM'],
    [8, 'broken_links', '11.2 % of outbound offer links return 404', NOW - 18 * HOUR, 'HIGH'],
    [8, 'ownership', 'Domain registrant does not match submitted VAT entity', NOW - 9 * DAY, 'CRITICAL'],
    [6, 'review_burst', '22 reviews in 3 h against a 3/day baseline', NOW - 31 * HOUR, 'HIGH'],
    [6, 'price_anomaly', '1 offer priced 91 % below market median', NOW - 5 * HOUR, 'MEDIUM'],
    [6, 'fake_coupon', 'Coupon SUPBAY18 reported invalid by 14 users', NOW - 2 * DAY, 'MEDIUM'],
    [6, 'feed_conflict', 'Feed reports 3 EANs that conflict with canonical records', NOW - 3 * DAY, 'MEDIUM'],
    [4, 'review_bombing', '14 one-star reviews in 9 h, no verified purchases', NOW - 4 * DAY, 'MEDIUM'],
    [4, 'complaint_spike', 'Complaint rate 2.4 % vs 1.1 % category median', NOW - 6 * DAY, 'LOW'],
    [4, 'price_accuracy', '23 offers deviate from landing page price', NOW - 12 * HOUR, 'MEDIUM'],
    [7, 'feed_stale', 'Feed import delayed 14 h beyond SLA', NOW - 14 * HOUR, 'LOW'],
    [3, 'price_anomaly', '1 offer 214 % above market median', NOW - 20 * HOUR, 'LOW'],
  ];
  ix.riskEvents = riskDefs.map((d, i) => ({ id: i + 1, merchantId: d[0], kind: d[1], text: d[2], ts: d[3], severity: d[4] }));

  /* ============ 3. review fraud seed cases ============ */
  const youngUsers = [
    { nick: 'gainz_2026', avatar: 'GA' }, { nick: 'supp_hunter', avatar: 'SU' }, { nick: 'bestdeals_x', avatar: 'BE' },
    { nick: 'fitfast_99', avatar: 'FI' }, { nick: 'proteinpro7', avatar: 'PR' }, { nick: 'value_seeker', avatar: 'VA' },
  ];
  const newUserIds = [];
  youngUsers.forEach((u, i) => {
    const id = S.users.length + 1;
    S.users.push({ id, nick: u.nick, username: S.helpers.slug(u.nick), country: pick(['DE', 'PL', 'IT', 'CZ']), joined: NOW - int(2, 9) * DAY, reviews: int(1, 3), helpful: 0, verified: false, avatar: u.avatar, badges: [], reputation: int(0, 12) });
    newUserIds.push(id);
  });
  let rid = Math.max.apply(null, S.reviews.map((r) => r.id)) + 1;
  const reviewFlags = {};
  const pushReview = (o, flags) => {
    const r = Object.assign({
      id: rid++, type: 'merchant', targetId: 8, userId: newUserIds[0], rating: 5, title: '', text: '',
      pros: [], cons: [], recommend: true, verifiedPurchase: false, merchantId: null,
      date: NOW - 2 * DAY, helpful: 0, notHelpful: 0, status: 'approved', reply: null, reported: false,
    }, o);
    S.reviews.push(r);
    reviewFlags[r.id] = flags;
    return r;
  };
  /* case A — duplicate text cluster, positive manipulation on merchant 8 */
  const dupText = 'Best shop ever, super fast delivery and the prices are unbeatable. I ordered again immediately and everything arrived perfectly.';
  const dupVariants = [
    dupText,
    'Best shop ever, super fast delivery and prices are unbeatable. I ordered again immediately and everything arrived perfectly.',
    'Best shop ever! Super fast delivery and the prices are unbeatable, I ordered again immediately and everything arrived perfect.',
    'Best shop ever, very fast delivery and the prices are unbeatable. Ordered again immediately, everything arrived perfectly.',
    'Best shop, super fast delivery and unbeatable prices. I ordered again immediately and everything arrived perfectly.',
  ];
  const dupIds = [];
  dupVariants.forEach((tx, i) => {
    const r = pushReview({
      targetId: 8, userId: newUserIds[i % newUserIds.length], rating: 5,
      title: 'Amazing shop', text: tx, date: NOW - 2 * DAY + i * 11 * 60000, verifiedPurchase: false,
    }, { device: 'dev-7f21a', session: 'sess-a1' + (i < 3 ? '' : i), accountAgeDays: int(2, 9), duplicateOf: i ? dupVariants[0] : null, cluster: 'A' });
    dupIds.push(r.id);
  });
  /* case B — review burst on merchant 6 */
  const burstIds = [];
  for (let i = 0; i < 22; i++) {
    const r = pushReview({
      targetId: 6, userId: newUserIds[i % newUserIds.length], rating: i % 7 === 0 ? 4 : 5,
      title: pick(['Great', 'Recommended', 'Perfect', 'Very good', 'Top']),
      text: pick([
        'Everything went smoothly, will order again from here for sure.',
        'Fast shipping and good prices, nothing to complain about at all.',
        'Ordered twice already, both times without any problem whatsoever.',
      ]),
      date: NOW - 31 * HOUR + i * 8 * 60000, verifiedPurchase: false,
    }, { device: 'dev-' + (i % 3 === 0 ? 'c11b2' : 'c11b3'), session: 'sess-b' + (i % 4), accountAgeDays: int(2, 11), burst: 'B', cluster: 'B' });
    burstIds.push(r.id);
  }
  /* case C — review bombing on merchant 4 */
  const bombIds = [];
  for (let i = 0; i < 14; i++) {
    const r = pushReview({
      targetId: 4, userId: newUserIds[(i + 2) % newUserIds.length], rating: 1,
      title: pick(['Avoid', 'Terrible', 'Never again', 'Scam']),
      text: pick([
        'Order never arrived and support does not answer any of my messages.',
        'Worst experience, they take the money and then nothing happens at all.',
        'Completely unreliable, do not order here under any circumstances.',
      ]),
      date: NOW - 4 * DAY + i * 38 * 60000, verifiedPurchase: false, recommend: false,
    }, { device: 'dev-e9' + (i % 2), session: 'sess-c' + (i % 3), accountAgeDays: int(1, 7), burst: 'C', cluster: 'C' });
    bombIds.push(r.id);
  }
  /* case D — a genuine-looking but copy-pasted product review pair */
  const pAlpha = byName('Performance Alpha'), pNitric = byName('Nitric Surge');
  const copyText = 'Fully disclosed label, mixes without clumps and the pump is noticeable from the first session. Cost per serving is fair for the dosing.';
  const copyIds = [];
  [pAlpha, pNitric].forEach((p, i) => {
    if (!p) return;
    const r = pushReview({
      type: 'product', targetId: p.id, userId: newUserIds[4], rating: 5, title: 'Excellent formula',
      text: copyText, date: NOW - 6 * DAY + i * 4 * 60000, verifiedPurchase: false, merchantId: 8,
    }, { device: 'dev-7f21a', session: 'sess-d1', accountAgeDays: 5, duplicateOf: copyText, cluster: 'D' });
    copyIds.push(r.id);
  });
  ix.reviewFlags = reviewFlags;
  ix.fraudCases = [
    { id: 1, kind: 'duplicate_text', label: 'Duplicate review cluster', targetType: 'merchant', targetId: 8, reviewIds: dupIds, similarity: 0.91, note: '5 reviews, 3 sharing one device fingerprint, all accounts under 10 days old.' },
    { id: 2, kind: 'burst', label: 'Review burst', targetType: 'merchant', targetId: 6, reviewIds: burstIds.slice(0, 8), all: burstIds, baseline: 3, peak: 22, window: '3 h', note: 'Baseline 3 reviews/day; 22 arrived inside 3 hours from 6 accounts.' },
    { id: 3, kind: 'bombing', label: 'Negative rating campaign', targetType: 'merchant', targetId: 4, reviewIds: bombIds.slice(0, 8), all: bombIds, note: '14 one-star reviews in 9 hours, zero verified purchases, overlapping devices.' },
    { id: 4, kind: 'copy_paste', label: 'Copy-pasted product review', targetType: 'product', targetId: pAlpha ? pAlpha.id : 1, reviewIds: copyIds, similarity: 1, note: 'Identical body posted against two different products by one account.' },
  ];

  /* ============ 4. price anomalies, staleness, link health ============ */
  const anomalyPlan = [
    ['Carb Loader Maltodextrin', 6, 'too_low', 0.09],
    ['Collagen Joint Support', 3, 'too_high', 3.14],
    ['Glutamine Pure', 8, 'too_low', 0.12],
    ['Beta-Alanine Pure', 4, 'too_low', 0.34],
  ];
  ix.anomalySeeds = [];
  anomalyPlan.forEach((a, i) => {
    const p = byName(a[0]);
    if (!p) return;
    let o = O.find((x) => x.productId === p.id && x.merchantId === a[1]);
    if (!o) o = O.find((x) => x.productId === p.id);
    if (!o) return;
    const median = (() => { const v = O.filter((x) => x.productId === p.id).map((x) => x.price).sort((x, y) => x - y); return v[Math.floor(v.length / 2)]; })();
    o.priceBefore = o.price;
    o.price = Math.max(0.9, Math.round(median * a[3] * 100) / 100);
    o.ix = Object.assign({}, o.ix, { anomaly: a[2], marketMedian: median, feedRaw: o.priceBefore });
    ix.anomalySeeds.push({ offerId: o.id, productId: p.id, merchantId: o.merchantId, mode: a[2], median: median });
  });
  /* stale feeds + broken links + fake discount */
  const staleTargets = [['Mass Formula X', 8], ['Endurance Prime', 6], ['ZMA Recovery', 4], ['EAA Complete', 8]];
  staleTargets.forEach((t) => { const o = offerOf(t[0], t[1]); if (o) { o.updated = NOW - int(52, 128) * HOUR; o.ix = Object.assign({}, o.ix, { stale: true }); } });
  const linkDefs = [
    ['Thermo Cut Yohimbine', 8, 404, 'Landing page removed by merchant'],
    ['Whey Isolate 90', 6, 'loop', 'Redirect loop: /p/ → /out/ → /p/'],
    ['Vegan Protein Blend', 4, 'notrack', 'Tracking parameter stripped by merchant redirect'],
    ['Melatonin Sleep 1 mg', 6, 'expired', 'Affiliate programme ended 12 days ago'],
    ['Creatine HCl Caps', 8, 'mismatch', 'Destination resolves to unrelated category page'],
  ];
  ix.linkHealth = linkDefs.map((d, i) => {
    const o = offerOf(d[0], d[1]);
    if (o) o.ix = Object.assign({}, o.ix, { link: d[2] });
    const p = byName(d[0]);
    return {
      id: i + 1, offerId: o ? o.id : null, merchantId: d[1], productId: p ? p.id : null,
      product: d[0], status: d[2], note: d[3], detected: NOW - int(2, 60) * HOUR,
      label: d[2] === 404 ? 'Broken (404)' : d[2] === 'loop' ? 'Redirect loop' : d[2] === 'notrack' ? 'Tracking missing' : d[2] === 'expired' ? 'Programme expired' : 'Unexpected destination',
      state: 'open',
    };
  });
  const fakeDiscountPlan = [['Pre-Burn Extreme', 8, 1.78], ['Nitric Surge', 6, 1.62], ['Recovery Matrix', 4, 1.49]];
  ix.fakeDiscounts = [];
  fakeDiscountPlan.forEach((f) => {
    const o = offerOf(f[0], f[1]);
    const p = byName(f[0]);
    if (!o || !p) return;
    o.oldPrice = Math.round(o.price * f[2] * 100) / 100;
    o.ix = Object.assign({}, o.ix, { refPriceRaisedAt: NOW - int(3, 11) * DAY });
    ix.fakeDiscounts.push({ offerId: o.id, productId: p.id, merchantId: o.merchantId, claimed: o.oldPrice, now: o.price, median90: Math.round(p.hist.min.slice(275, 365).reduce((a, b) => a + b, 0) / 90 * 100) / 100 });
  });

  /* ============ 5. coupon intelligence ============ */
  const couponSource = ['verified', 'community', 'merchant'];
  S.coupons.forEach((c, i) => {
    const bad = i % 9 === 4;
    const worked = bad ? int(3, 14) : int(12, 340);
    const failed = bad ? worked * int(3, 6) : Math.max(0, Math.round(worked * flt(0.02, 0.3, 3)));
    const total = worked + failed, rate = total ? worked / total : 0;
    let st;
    if (c.ends < NOW) st = 'expired';
    else if (rate < 0.35) st = 'invalid';
    else if (c.exclusive) st = 'verified';
    else if (total < 40) st = 'unverified';
    else st = couponSource[i % couponSource.length];
    c.ix = {
      state: st, reports: { worked, failed }, lastReport: NOW - int(1, 70) * HOUR,
      verifiedBy: st === 'verified' ? 'Comparo data team' : st === 'merchant' ? 'Merchant' : st === 'community' ? 'Community' : null,
    };
  });
  /* deliberate demo coupons */
  const cInvalid = S.coupons.find((c) => c.merchantId === 6);
  if (cInvalid) { cInvalid.ix.state = 'invalid'; cInvalid.ix.reports = { worked: 3, failed: 41 }; cInvalid.code = 'SUPBAY18'; }
  const cExp = S.coupons.find((c) => c.merchantId === 8);
  if (cExp) { cExp.ix.state = 'expired'; cExp.ends = NOW - 3 * DAY; cExp.ix.reports = { worked: 88, failed: 12 }; }
  const cTop = S.coupons.find((c) => c.merchantId === 1);
  if (cTop) { cTop.ix.state = 'verified'; cTop.ix.reports = { worked: 421, failed: 27 }; }

  /* ============ 6. Comparo exclusives ============ */
  ix.exclusives = [
    { id: 1, merchantId: 1, type: 'Exclusive coupon', label: 'COMPARO12 — 12 % off everything', starts: NOW - 12 * DAY, ends: NOW + 18 * DAY, views: 41200, clicks: 5140, conversions: 402, revenue: 28940, commission: 2026, uses: 388, status: 'active' },
    { id: 2, merchantId: 3, type: 'Exclusive price', label: 'Whey Isolate 90 at €39.90 (Comparo only)', starts: NOW - 5 * DAY, ends: NOW + 9 * DAY, views: 18600, clicks: 3210, conversions: 288, revenue: 13460, commission: 969, uses: 288, status: 'active' },
    { id: 3, merchantId: 5, type: 'Cashback', label: '4 % cashback on first order', starts: NOW - 30 * DAY, ends: NOW + 30 * DAY, views: 26400, clicks: 2870, conversions: 194, revenue: 11020, commission: 606, uses: 194, status: 'active' },
    { id: 4, merchantId: 2, type: 'Bundle', label: 'Creatine + Whey bundle −€9', starts: NOW - 20 * DAY, ends: NOW + 4 * DAY, views: 9800, clicks: 1240, conversions: 96, revenue: 6240, commission: 393, uses: 96, status: 'ending' },
    { id: 5, merchantId: 7, type: 'Limited-time promotion', label: '48 h flash: free shipping, no minimum', starts: NOW - 40 * DAY, ends: NOW - 38 * DAY, views: 12400, clicks: 2010, conversions: 168, revenue: 8940, commission: 590, uses: 168, status: 'ended' },
  ];

  /* ============ 7. feed runs, versions & diffs ============ */
  ix.feedRuns = [];
  let frid = 1;
  M.forEach((m) => {
    const f = S.feeds[m.id - 1];
    if (!f) return;
    for (let k = 0; k < 6; k++) {
      const items = f.items + int(-60, 60);
      const errs = m.id === 8 ? int(40, 180) : m.id === 6 ? int(12, 60) : int(0, 9);
      ix.feedRuns.push({
        id: frid++, merchantId: m.id, ts: f.lastRun - k * (m.id === 8 ? 26 : 6) * HOUR,
        items, added: int(0, 40), changed: int(20, 260), removed: int(0, 24), errors: errs,
        durationMs: int(2400, 41000), status: errs > 30 ? 'error' : errs > 8 ? 'warning' : 'ok',
      });
    }
  });
  ix.feedDiff = [
    { field: 'price', product: 'Whey Isolate 90', before: '44.90', after: '41.20', kind: 'changed' },
    { field: 'availability', product: 'Creatine Monohydrate Micronized', before: 'in_stock', after: 'low_stock', kind: 'changed' },
    { field: 'ean', product: 'Casein Night Protein', before: '—', after: '8591000234', kind: 'added' },
    { field: 'offer', product: 'Endurance Prime 1.5 kg', before: 'present', after: 'missing', kind: 'removed' },
    { field: 'shipping', product: '(feed level)', before: '5.90', after: '4.90', kind: 'changed' },
    { field: 'title', product: 'Mass Formula X', before: 'MASS FORMULA X 4KG', after: 'Mass Formula X 4000 g chocolate', kind: 'changed' },
    { field: 'price', product: 'Glutamine Pure', before: '16.50', after: '0.00', kind: 'rejected', note: 'Zero price rejected by validation' },
    { field: 'shipping', product: '(feed level)', before: '4.90', after: '−2.00', kind: 'rejected', note: 'Negative shipping cost rejected' },
    { field: 'discount', product: 'Pre-Burn Extreme', before: '−18 %', after: '−94 %', kind: 'rejected', note: 'Impossible discount rejected' },
  ];

  /* ============ 8. matching: extra feed rows, clusters, aliases, conflicts ============ */
  let fiid = Math.max.apply(null, S.feedItems.map((f) => f.id)) + 1;
  const extraFeed = [
    [1, 'IRONFORGE Whey Isolate 90 900g Vanilla', 'Whey Isolate 90', 'IRONFORGE', '900 g', 'Vanilla', true, 43.9],
    [2, 'Strength Core creatine 500g Creapure micronised', 'Strength Core', 'IRONFORGE', '500 g', 'Standard', true, 24.4],
    [3, 'PeakLabs EAA complete 450 g lemon', 'EAA Complete', 'Peak Labs', '450 g', 'Lemon', false, 30.9],
    [4, 'Vegan protein blend 1kg pea rice PureCore', 'Vegan Protein Blend', 'PureCore Nutrition', '1000 g', 'Standard', false, 35.2],
    [5, 'MASS FORMULA X 2000G chocolate', 'Mass Formula X', 'NORDKRAFT', '2000 g', 'Chocolate', false, 31.9],
    [6, 'Beta alanine pure 300 g unflavoured', 'Beta-Alanine Pure', 'PureCore Nutrition', '300 g', 'Unflavoured', false, 18.4],
    [7, 'Collagen joint support 400g type II + vit C', 'Collagen Joint Support', 'Vytal Labs', '400 g', 'Standard', false, 31.9],
    [8, 'Hydration electrolyte sticks 30x', null, 'Kinetiq', '30 sachets', 'Standard', false, 16.9],
    [2, 'Nitric surge 400 g stim-free pump', 'Nitric Surge', 'Vytal Labs', '400 g', 'Standard', false, 34.5],
    [5, 'ZMA recovery 120 caps zinc magnesium B6', 'ZMA Recovery', 'Fortis Nutrition', '120 caps', 'Standard', false, 17.4],
    [7, 'Omega 3 ULTRA 240 caps double pack', 'Omega-3 Ultra', 'BioPeak', '240 caps', 'Standard', false, 38.9],
    [4, 'Creatine hcl capsules 180ct Apex', 'Creatine HCl Caps', 'Apex Fuel', '180 caps', 'Standard', true, 27.4],
  ];
  extraFeed.forEach((r) => {
    const p = r[2] ? byName(r[2]) : null;
    S.feedItems.push({
      id: fiid++, merchantId: r[0], raw: r[1], ean: r[6] && p ? p.ean : '', price: r[7],
      suggested: p ? p.id : null, brandRaw: r[3], packRaw: r[4], variantRaw: r[5],
      confidence: 0, status: 'pending',
    });
  });
  ix.brandAliases = [
    { canonical: 'Peak Labs', aliases: ['PeakLabs', 'Peak-Labs', 'PEAK LABS'], sources: 4, status: 'suggested' },
    { canonical: 'IRONFORGE', aliases: ['Iron Forge', 'Ironforge Nutrition', 'IRON-FORGE'], sources: 6, status: 'approved' },
    { canonical: 'PureCore Nutrition', aliases: ['Pure Core', 'PureCore'], sources: 3, status: 'approved' },
    { canonical: 'NORDKRAFT', aliases: ['Nord Kraft', 'Nordkraft AB'], sources: 2, status: 'suggested' },
    { canonical: 'Vytal Labs', aliases: ['Vytal', 'VytalLabs'], sources: 5, status: 'approved' },
  ];
  ix.newProductCandidates = [
    { id: 1, title: 'Hydration electrolyte sticks 30×', brand: 'Kinetiq', pack: '30 sachets', feeds: 3, merchants: [8, 5, 7], evidence: 'Same normalised title in 3 independent feeds, no canonical match above 65.', ean: '8591000441', status: 'open' },
    { id: 2, title: 'Clear whey refresh 500 g', brand: 'PureCore Nutrition', pack: '500 g', feeds: 2, merchants: [3, 1], evidence: 'Two feeds, matching EAN, no canonical record.', ean: '8591000502', status: 'open' },
    { id: 3, title: 'Creatine gummies 90 ct', brand: 'Apex Fuel', pack: '90 gummies', feeds: 4, merchants: [1, 2, 4, 7], evidence: '142 searches in 30 d with zero results; 4 feeds carry the item.', ean: '8591000618', status: 'open' },
    { id: 4, title: 'Magnesium glycinate powder 300 g', brand: 'BioPeak', pack: '300 g', feeds: 2, merchants: [5, 6], evidence: 'Powder format of an existing capsule product — variant guard blocked auto-merge.', ean: '8591000733', status: 'open' },
  ];
  ix.fieldConflicts = [
    { id: 1, productName: 'Casein Night Protein', field: 'ean', values: [{ v: '8591000709', src: 'PeakSupps feed', n: 3 }, { v: '8591000710', src: 'SupplementBay feed', n: 1 }], resolution: 'pending' },
    { id: 2, productName: 'Mass Formula X', field: 'pack', values: [{ v: '4000 g', src: 'Canonical record', n: 5 }, { v: '2000 g', src: 'PerformanceHub feed', n: 1 }], resolution: 'variant', note: 'Resolved as a separate 2 kg variant, not a conflict.' },
    { id: 3, productName: 'EAA Complete', field: 'brand', values: [{ v: 'Vytal Labs', src: 'Canonical record', n: 4 }, { v: 'Peak Labs', src: 'IronLab feed', n: 1 }], resolution: 'pending' },
    { id: 4, productName: 'Omega-3 Ultra', field: 'packSize', values: [{ v: '120 caps', src: 'Canonical record', n: 6 }, { v: '240 caps', src: 'AthleteSupply feed', n: 2 }], resolution: 'variant' },
  ];
  ix.sourcePriority = [
    { rank: 1, source: 'Admin verified', weight: 100, note: 'Manually reviewed by the catalogue team. Never overwritten by feeds.' },
    { rank: 2, source: 'Official brand data', weight: 88, note: 'Brand-supplied specification sheets and EAN registries.' },
    { rank: 3, source: 'Verified merchant feed', weight: 70, note: 'Merchant with business verification and >95 % price accuracy.' },
    { rank: 4, source: 'Other merchant feed', weight: 48, note: 'Unverified or newly onboarded merchants.' },
    { rank: 5, source: 'Community contribution', weight: 30, note: 'User reports; used as a signal, never as a sole source.' },
  ];
  ix.lineage = [
    { field: 'Product title', value: 'Whey Isolate 90', source: 'Admin verified', at: NOW - 61 * DAY, actor: 'catalogue@comparo' },
    { field: 'Brand', value: 'IRONFORGE', source: 'Official brand data', at: NOW - 61 * DAY, actor: 'brand-import' },
    { field: 'EAN', value: '8591000556', source: 'Verified matching', at: NOW - 44 * DAY, actor: 'MatchingService' },
    { field: 'Pack size', value: '900 g', source: 'Official brand data', at: NOW - 61 * DAY, actor: 'brand-import' },
    { field: 'Price (best)', value: 'from PeakSupps feed', source: 'Verified merchant feed', at: NOW - 2 * 3600000, actor: 'feed-worker-3' },
    { field: 'Image', value: 'merchant asset', source: 'Verified merchant feed', at: NOW - 9 * DAY, actor: 'feed-worker-1' },
    { field: 'Category', value: 'Protein', source: 'Admin verified', at: NOW - 61 * DAY, actor: 'catalogue@comparo' },
    { field: 'Compliance (DE)', value: 'allowed', source: 'Compliance review', at: NOW - 30 * DAY, actor: 'compliance@comparo' },
  ];
  ix.catalogChanges = [
    { id: 1, ts: NOW - 4 * HOUR, entity: 'Whey Isolate 90', field: 'title', from: 'Whey Isolate 90 CFM', to: 'Whey Isolate 90', actor: 'catalogue@comparo', rev: 7 },
    { id: 2, ts: NOW - 2 * DAY, entity: 'Mass Formula X', field: 'variant', from: '—', to: 'split: 2 kg / 4 kg', actor: 'MatchingService', rev: 4 },
    { id: 3, ts: NOW - 3 * DAY, entity: 'Casein Micellar Slow', field: 'merge', from: 'PRD-31', to: 'merged into PRD-9', actor: 'admin@comparo', rev: 2 },
    { id: 4, ts: NOW - 6 * DAY, entity: 'EAA Complete', field: 'ean', from: '—', to: '8591000618', actor: 'MatchingService', rev: 3 },
    { id: 5, ts: NOW - 9 * DAY, entity: 'Peak Labs', field: 'brand alias', from: 'PeakLabs', to: 'Peak Labs', actor: 'catalogue@comparo', rev: 2 },
  ];

  /* ============ 9. demand signals & market intelligence ============ */
  ix.demand = P.map((p) => {
    const offers = O.filter((o) => o.productId === p.id).length;
    const base = Math.round(p.views / 12) + int(20, 260);
    return {
      productId: p.id, searches30: base, views30: Math.round(p.views / 8), saves30: int(4, 90),
      compares30: int(2, 70), clicks30: O.filter((o) => o.productId === p.id).reduce((a, b) => a + b.clicks30, 0),
      offers, trend7: int(-24, 62),
    };
  });
  ix.zeroSupply = [
    { query: 'creatine gummies', searches30: 142, offers: 0, markets: ['DE', 'AT', 'CZ'], note: '4 feeds carry the item but no canonical product exists.', status: 'open' },
    { query: 'clear whey refresh', searches30: 96, offers: 0, markets: ['DE', 'PL'], note: 'Two merchants list it, matching confidence below 65.', status: 'open' },
    { query: 'ashwagandha gummies', searches30: 61, offers: 0, markets: ['FR', 'IT'], note: 'No merchant in catalogue sells this format.', status: 'open' },
    { query: 'electrolyte tablets 60', searches30: 54, offers: 1, markets: ['SE', 'NL'], note: 'Single merchant, no price competition.', status: 'watch' },
  ];
  ix.potentialMerchants = [
    { id: 1, name: 'NutriNord', domain: 'nutrinord.se', market: 'SE', overlap: 68, catalog: 2400, priority: 'HIGH', status: 'Contacted', note: 'Only 2 merchants deliver to Sweden today.', owner: 'sales@comparo', updated: NOW - 2 * DAY },
    { id: 2, name: 'SupleMax', domain: 'suplemax.pl', market: 'PL', overlap: 74, catalog: 3900, priority: 'HIGH', status: 'Interested', note: 'Strong creatine and protein coverage, competitive shipping.', owner: 'sales@comparo', updated: NOW - 5 * DAY },
    { id: 3, name: 'Balkan Sports Nutrition', domain: 'bsn-shop.hr', market: 'HR', overlap: 41, catalog: 1100, priority: 'MEDIUM', status: 'Identified', note: 'New market, no current coverage.', owner: null, updated: NOW - 9 * DAY },
    { id: 4, name: 'FitDirect', domain: 'fitdirect.nl', market: 'NL', overlap: 82, catalog: 5200, priority: 'HIGH', status: 'Onboarding', note: 'Feed sample delivered, 71 % auto-matched.', owner: 'partners@comparo', updated: NOW - 1 * DAY },
    { id: 5, name: 'ProteinPoint', domain: 'proteinpoint.cz', market: 'CZ', overlap: 59, catalog: 1800, priority: 'MEDIUM', status: 'Verified', note: 'Documents complete, awaiting feed URL.', owner: 'partners@comparo', updated: NOW - 3 * DAY },
    { id: 6, name: 'MegaSupps', domain: 'megasupps.eu', market: 'DE', overlap: 88, catalog: 7400, priority: 'LOW', status: 'Rejected', note: 'Multiple consumer complaints and unclear ownership.', owner: 'compliance@comparo', updated: NOW - 14 * DAY },
  ];

  /* ============ 10. support, disputes, announcements, status ============ */
  ix.tickets = [
    { id: 'T-2041', kind: 'Price issue', subject: 'Price on landing page differs from Comparo', from: 'user', who: 'martina_k', merchantId: 8, created: NOW - 4 * HOUR, updated: NOW - 2 * HOUR, state: 'Open', priority: 'Important', assignee: null, messages: 2, body: 'The offer says €22.40 but the shop charges €27.90 at checkout.' },
    { id: 'T-2040', kind: 'Review', subject: 'My review was rejected without reason', from: 'user', who: 'PetrGains', merchantId: null, created: NOW - 11 * HOUR, updated: NOW - 9 * HOUR, state: 'Assigned', priority: 'Info', assignee: 'moderation@comparo', messages: 3, body: 'I wrote an honest review about a delayed order and it disappeared.' },
    { id: 'T-2039', kind: 'Merchant', subject: 'Feed import fails with 502', from: 'merchant', who: 'SupplementBay', merchantId: 8, created: NOW - 26 * HOUR, updated: NOW - 20 * HOUR, state: 'Waiting merchant', priority: 'Action required', assignee: 'feeds@comparo', messages: 5, body: 'Our importer receives 502 from your feed endpoint since Tuesday.' },
    { id: 'T-2038', kind: 'GDPR', subject: 'Data export request', from: 'user', who: 'FitLena', merchantId: null, created: NOW - 2 * DAY, updated: NOW - 2 * DAY, state: 'Open', priority: 'Action required', assignee: 'privacy@comparo', messages: 1, body: 'Please provide a full export of my account data.' },
    { id: 'T-2037', kind: 'Deal', subject: 'Coupon SUPBAY18 does not work', from: 'user', who: 'squatking', merchantId: 6, created: NOW - 3 * DAY, updated: NOW - 30 * HOUR, state: 'Resolved', priority: 'Important', assignee: 'deals@comparo', messages: 4, body: 'Code rejected at checkout, 14 other users reported the same.' },
    { id: 'T-2036', kind: 'Account', subject: 'Cannot enable two-factor authentication', from: 'user', who: 'IronMike', merchantId: null, created: NOW - 5 * DAY, updated: NOW - 4 * DAY, state: 'Closed', priority: 'Info', assignee: 'support@comparo', messages: 6, body: 'QR code never loads on the security page.' },
    { id: 'T-2035', kind: 'Technical', subject: 'Compare table empty on mobile Safari', from: 'user', who: 'volumeguy', merchantId: null, created: NOW - 6 * DAY, updated: NOW - 5 * DAY, state: 'Resolved', priority: 'Important', assignee: 'support@comparo', messages: 3, body: 'Adding a third product clears the table.' },
    { id: 'T-2034', kind: 'Merchant', subject: 'Request business verification review', from: 'merchant', who: 'IronVault', merchantId: 6, created: NOW - 8 * DAY, updated: NOW - 7 * DAY, state: 'Waiting user', priority: 'Info', assignee: 'compliance@comparo', messages: 2, body: 'We uploaded the register extract, please re-check.' },
  ];
  ix.disputes = [
    { id: 'D-118', kind: 'Review', merchantId: 4, subject: 'Review claims non-delivery of a cancelled order', state: 'Under review', opened: NOW - 2 * DAY, reviewId: bombIds[0] || null, merchantResponse: 'Order was cancelled by the customer before dispatch; we hold the cancellation record.', outcome: null },
    { id: 'D-117', kind: 'Offer', merchantId: 8, subject: 'Price mismatch reported by 3 users', state: 'Merchant response', opened: NOW - 3 * DAY, merchantResponse: null, outcome: null },
    { id: 'D-116', kind: 'Review', merchantId: 6, subject: 'Merchant disputes 22 reviews as inauthentic', state: 'Resolved', opened: NOW - 9 * DAY, merchantResponse: 'We did not solicit these reviews.', outcome: 'Burst cluster hidden pending verification; merchant trust unaffected.' },
    { id: 'D-115', kind: 'Coupon', merchantId: 6, subject: 'Coupon reported invalid', state: 'Resolved', opened: NOW - 12 * DAY, merchantResponse: 'Code expired earlier than published.', outcome: 'Coupon marked invalid, merchant asked to publish end dates via feed.' },
    { id: 'D-114', kind: 'Review', merchantId: 3, subject: 'Alleged competitor review', state: 'Rejected', opened: NOW - 20 * DAY, merchantResponse: 'Reviewer never ordered from us.', outcome: 'Verified purchase confirmed in order export. Review kept.' },
  ];
  ix.notices = [
    { id: 1, scope: 'Platform', title: 'Scheduled maintenance on the feed importer', body: 'Feed imports pause for 30 minutes on 9 September at 02:00 UTC. Offers stay visible with their last known price.', ts: NOW - 6 * HOUR, level: 'info' },
    { id: 2, scope: 'Merchant', title: 'Feed SLA tightens to 6 hours on 1 October', body: 'Feeds older than 6 hours will be deprioritised in ComparoRank freshness scoring from 1 October.', ts: NOW - 2 * DAY, level: 'important' },
    { id: 3, scope: 'Market', title: 'Melatonin remains prescription-only in DE, AT, CZ, PL, FR, IT, ES, SK', body: 'Offers for melatonin products are hidden in those markets and excluded from ranking and recommendations.', ts: NOW - 5 * DAY, level: 'important' },
  ];
  ix.status = [
    { service: 'Search', state: 'operational', latency: '84 ms p95', uptime: '99.98 %' },
    { service: 'Feed processing', state: 'degraded', latency: '4.2 min p95', uptime: '99.21 %', note: '2 merchant endpoints failing (SupplementBay, IronVault)' },
    { service: 'Affiliate redirects', state: 'operational', latency: '41 ms p95', uptime: '99.99 %' },
    { service: 'Notifications', state: 'operational', latency: '1.1 s p95', uptime: '99.94 %' },
    { service: 'Price aggregation', state: 'operational', latency: '2.8 min p95', uptime: '99.87 %' },
    { service: 'Matching worker', state: 'operational', latency: '9.4 s p95', uptime: '99.71 %' },
  ];
  ix.errors = [
    { id: 1, module: 'FeedImport', severity: 'error', message: 'HTTP 502 from https://supplementbay.com/feed/comparo.xml', count: 41, last: NOW - 40 * 60000 },
    { id: 2, module: 'AffiliateRedirect', severity: 'warning', message: 'Redirect loop detected for merchant 6 offer 412', count: 12, last: NOW - 3 * HOUR },
    { id: 3, module: 'MatchingWorker', severity: 'warning', message: 'Variant guard blocked auto-merge (pack mismatch)', count: 27, last: NOW - 5 * HOUR },
    { id: 4, module: 'PriceAggregation', severity: 'error', message: 'Zero price rejected for offer 388 (Glutamine Pure)', count: 3, last: NOW - 6 * HOUR },
    { id: 5, module: 'Notifications', severity: 'info', message: 'Digest batch retried after transient timeout', count: 8, last: NOW - 11 * HOUR },
    { id: 6, module: 'SearchIndex', severity: 'warning', message: 'Zero-result query logged: creatine gummies', count: 142, last: NOW - 2 * HOUR },
  ];

  /* ============ 11. automations ============ */
  ix.automationRules = [
    { id: 1, name: 'Stale offer guard', scope: 'admin', trigger: 'offer_stale', param: '48 h', actions: ['deprioritize', 'notify_merchant'], enabled: true, runs: 214, lastRun: NOW - 40 * 60000 },
    { id: 2, name: 'Price anomaly hold', scope: 'admin', trigger: 'price_anomaly', param: '±60 % vs median', actions: ['flag', 'queue_review', 'exclude_best_buy'], enabled: true, runs: 38, lastRun: NOW - 5 * HOUR },
    { id: 3, name: 'Review burst screen', scope: 'admin', trigger: 'review_flagged', param: '>8×/h baseline', actions: ['queue_moderation', 'hold_publish'], enabled: true, runs: 17, lastRun: NOW - 31 * HOUR },
    { id: 4, name: 'Merchant risk escalation', scope: 'admin', trigger: 'merchant_risk_increase', param: 'risk ≥ HIGH', actions: ['create_task', 'notify'], enabled: true, runs: 9, lastRun: NOW - 18 * HOUR },
    { id: 5, name: 'Deal expiry sweep', scope: 'admin', trigger: 'deal_expiry', param: 'ends < 24 h', actions: ['notify', 'mark_ending'], enabled: true, runs: 126, lastRun: NOW - 2 * HOUR },
    { id: 6, name: 'Unmatched feed item digest', scope: 'admin', trigger: 'product_unmatched', param: '>20 rows', actions: ['create_task', 'notify_merchant'], enabled: true, runs: 62, lastRun: NOW - 9 * HOUR },
    { id: 7, name: 'Broken affiliate link', scope: 'admin', trigger: 'broken_affiliate_link', param: 'any 404/loop', actions: ['hide', 'create_task', 'notify_merchant'], enabled: true, runs: 24, lastRun: NOW - 18 * HOUR },
    { id: 8, name: 'Compliance unknown block', scope: 'admin', trigger: 'compliance_unknown', param: 'status = unknown', actions: ['flag', 'suppress_recommendation'], enabled: true, runs: 31, lastRun: NOW - 26 * HOUR },
  ];
  ix.merchantAutomations = [
    { id: 1, name: 'Feed failure alert', trigger: 'Feed import fails or is older than SLA', channel: 'Email + inbox', enabled: true },
    { id: 2, name: 'Price competitiveness alert', trigger: 'An offer falls out of the cheapest 3 for its product', channel: 'Inbox', enabled: true },
    { id: 3, name: 'New review alert', trigger: 'A new shop review is published', channel: 'Email', enabled: true },
    { id: 4, name: 'Coupon expiry reminder', trigger: 'A coupon expires within 48 h', channel: 'Inbox', enabled: false },
    { id: 5, name: 'Stock confidence warning', trigger: 'Stock data older than 24 h on a top-10 offer', channel: 'Inbox', enabled: true },
  ];

  /* ============ 12. roles & permissions ============ */
  const perms = ['merchant.approve', 'merchant.suspend', 'merchant.view', 'review.moderate', 'review.view', 'affiliate.view', 'affiliate.edit', 'compliance.view', 'compliance.edit', 'seo.manage', 'risk.view', 'risk.act', 'ranking.simulate', 'ranking.publish', 'matching.resolve', 'support.handle', 'automation.edit', 'audit.export', 'user.pii'];
  ix.permissions = perms;
  ix.roles = [
    { key: 'super', name: 'Super Admin', perms: perms.slice(), people: 2 },
    { key: 'marketplace', name: 'Marketplace Admin', perms: ['merchant.approve', 'merchant.suspend', 'merchant.view', 'review.moderate', 'review.view', 'risk.view', 'matching.resolve', 'automation.edit', 'ranking.simulate'], people: 3 },
    { key: 'merchantmgr', name: 'Merchant Manager', perms: ['merchant.view', 'merchant.approve', 'support.handle', 'affiliate.view'], people: 4 },
    { key: 'compliance', name: 'Compliance Manager', perms: ['compliance.view', 'compliance.edit', 'merchant.view', 'risk.view', 'audit.export'], people: 2 },
    { key: 'seo', name: 'SEO Manager', perms: ['seo.manage', 'review.view', 'merchant.view'], people: 2 },
    { key: 'moderator', name: 'Community Moderator', perms: ['review.moderate', 'review.view', 'support.handle'], people: 5 },
    { key: 'affiliate', name: 'Affiliate Manager', perms: ['affiliate.view', 'affiliate.edit', 'merchant.view'], people: 2 },
    { key: 'analyst', name: 'Analyst', perms: ['merchant.view', 'review.view', 'affiliate.view', 'risk.view', 'ranking.simulate'], people: 3 },
    { key: 'support', name: 'Support', perms: ['support.handle', 'review.view', 'merchant.view'], people: 6 },
  ];
  ix.staff = [
    { name: 'A. Weber', email: 'admin@comparo', role: 'super', last: NOW - 20 * 60000 },
    { name: 'M. Dvorak', email: 'marketplace@comparo', role: 'marketplace', last: NOW - 3 * HOUR },
    { name: 'L. Novak', email: 'compliance@comparo', role: 'compliance', last: NOW - 8 * HOUR },
    { name: 'S. Keller', email: 'seo@comparo', role: 'seo', last: NOW - 26 * HOUR },
    { name: 'P. Marek', email: 'moderation@comparo', role: 'moderator', last: NOW - 40 * 60000 },
    { name: 'J. Fischer', email: 'affiliate@comparo', role: 'affiliate', last: NOW - 5 * HOUR },
    { name: 'R. Vega', email: 'analyst@comparo', role: 'analyst', last: NOW - 2 * DAY },
  ];

  /* ============ 13. market configuration & feature flags per market ============ */
  const featureDefs = ['forum', 'guides', 'deal_submission', 'cashback', 'merchant_deals', 'basket_compare', 'public_profiles'];
  ix.marketFeatures = featureDefs;
  ix.markets = S.countries.map((c) => {
    const merch = M.filter((m) => m.shipsTo.indexOf(c.iso) >= 0);
    const offs = O.filter((o) => merch.some((m) => m.id === o.merchantId));
    const prods = {}; offs.forEach((o) => { prods[o.productId] = 1; });
    const revs = S.reviews.filter((r) => r.type === 'merchant' && merch.some((m) => m.id === r.targetId)).length;
    const flags = {};
    featureDefs.forEach((f, i) => { flags[f] = !(c.iso === 'FR' && f === 'cashback') && !(c.iso === 'US' && (f === 'deal_submission' || f === 'cashback')) && !(c.iso === 'SE' && f === 'forum'); });
    return {
      iso: c.iso, name: c.name, lang: c.lang, currency: c.currency, vat: c.vat,
      merchants: merch.length, products: Object.keys(prods).length, offers: offs.length,
      brands: new Set(Object.keys(prods).map((id) => (P.find((p) => p.id === +id) || {}).brandId)).size,
      reviews: revs, flags,
      complianceProfile: ['DE', 'FR', 'IT'].indexOf(c.iso) >= 0 ? 'Strict' : ['CZ', 'PL', 'SK', 'ES'].indexOf(c.iso) >= 0 ? 'Standard' : 'Standard',
      affiliates: merch.filter((m) => m.partner).length,
    };
  });

  /* ============ 14. product-surface experiments ============ */
  ix.experiments = [
    { id: 'EXP-11', name: 'Offer ranking display', hypothesis: 'Showing the ComparoRank badge in the offer table raises merchant click-through.', variants: [{ key: 'A', label: 'Total price only', ctr: 5.4, saveRate: 2.1, clickRate: 11.8, n: 8420 }, { key: 'B', label: 'Total price + ComparoRank badge', ctr: 6.2, saveRate: 2.4, clickRate: 13.4, n: 8380 }], status: 'running', started: NOW - 14 * DAY, winner: null },
    { id: 'EXP-12', name: 'Primary CTA wording', hypothesis: '“See total price” outperforms “Go to shop”.', variants: [{ key: 'A', label: 'Go to shop →', ctr: 12.1, saveRate: 1.9, clickRate: 12.1, n: 12400 }, { key: 'B', label: 'See total price →', ctr: 11.4, saveRate: 2.0, clickRate: 11.4, n: 12210 }], status: 'running', started: NOW - 9 * DAY, winner: null },
    { id: 'EXP-13', name: 'Deal card density', hypothesis: 'Compact deal cards increase deal views per session.', variants: [{ key: 'A', label: 'Rich cards', ctr: 4.4, saveRate: 1.4, clickRate: 8.9, n: 6100 }, { key: 'B', label: 'Compact rows', ctr: 5.1, saveRate: 1.6, clickRate: 9.8, n: 6040 }], status: 'concluded', started: NOW - 40 * DAY, winner: 'B' },
    { id: 'EXP-14', name: 'Trust score visualisation', hypothesis: 'A signal breakdown builds more confidence than a single number.', variants: [{ key: 'A', label: 'Score only', ctr: 3.8, saveRate: 1.1, clickRate: 7.4, n: 4400 }, { key: 'B', label: 'Score + 6 signals', ctr: 4.6, saveRate: 1.5, clickRate: 8.1, n: 4380 }], status: 'running', started: NOW - 6 * DAY, winner: null },
    { id: 'EXP-15', name: 'Recommendation strategy', hypothesis: 'Balanced ranking beats price-first and trust-first for save rate.', variants: [{ key: 'A', label: 'Price-first', ctr: 4.1, saveRate: 1.7, clickRate: 9.2, n: 5200 }, { key: 'B', label: 'Trust-first', ctr: 3.9, saveRate: 1.9, clickRate: 8.4, n: 5180 }, { key: 'C', label: 'Balanced (ComparoRank)', ctr: 4.6, saveRate: 2.3, clickRate: 9.9, n: 5240 }], status: 'running', started: NOW - 21 * DAY, winner: null },
  ];

  /* ============ 15. behaviour events (seeded prototype stream) ============ */
  const evTypes = ['search', 'search_click', 'product_view', 'shop_view', 'deal_view', 'compare', 'save', 'follow', 'review', 'merchant_click', 'coupon_copy'];
  ix.eventTypes = evTypes;
  const journeys = [
    ['search', 'search_click', 'product_view', 'compare', 'product_view', 'merchant_click'],
    ['search', 'search_click', 'product_view', 'save', 'product_view', 'merchant_click'],
    ['deal_view', 'product_view', 'coupon_copy', 'merchant_click'],
    ['search', 'search_click', 'product_view'],
    ['shop_view', 'product_view', 'merchant_click'],
    ['search', 'product_view', 'compare', 'shop_view', 'merchant_click'],
    ['search', 'search_click'],
  ];
  ix.events = [];
  let eid = 1;
  for (let s = 0; s < 46; s++) {
    const j = journeys[s % journeys.length];
    const sess = 'anon-' + (10000 + s * 137).toString(36);
    let t = NOW - int(1, 2600) * 60000;
    const p = pick(P), m = pick(M);
    j.forEach((k, i) => {
      t += int(8, 240) * 1000;
      ix.events.push({
        id: eid++, session: sess, type: k, ts: t, country: pick(['DE', 'DE', 'AT', 'CZ', 'PL', 'FR', 'IT']),
        device: i === 0 ? pick(['desktop', 'mobile', 'mobile', 'tablet']) : undefined,
        productId: k.indexOf('product') === 0 || k === 'compare' || k === 'save' ? p.id : null,
        merchantId: k === 'shop_view' || k === 'merchant_click' || k === 'coupon_copy' ? m.id : null,
        query: k === 'search' ? pick(['whey isolate', 'creatine', 'protein cheapest', 'ashwagandha', 'electrolytes', 'creatine gummies']) : null,
        converted: k === 'merchant_click' ? chance(0.07) : false,
      });
    });
  }
  ix.events.sort((a, b) => b.ts - a.ts);

  /* ============ 16. community price verification reports ============ */
  ix.priceReports = [];
  const reportKinds = ['price_correct', 'price_changed', 'out_of_stock', 'coupon_invalid'];
  for (let i = 0; i < 34; i++) {
    const o = pick(O);
    ix.priceReports.push({
      id: i + 1, offerId: o.id, productId: o.productId, merchantId: o.merchantId,
      kind: reportKinds[i % reportKinds.length], userId: pick(S.users).id,
      ts: NOW - int(1, 300) * HOUR, upheld: chance(0.78), note: '',
    });
  }
  ix.offerCorrections = [
    { id: 1, offerId: (offerOf('Whey Isolate 90', 8) || {}).id || null, merchantId: 8, productName: 'Whey Isolate 90', reported: 'Price is €5.50 higher at checkout', reporter: 'martina_k', ts: NOW - 5 * HOUR, state: 'Merchant notified' },
    { id: 2, offerId: (offerOf('Endurance Prime', 6) || {}).id || null, merchantId: 6, productName: 'Endurance Prime', reported: 'Out of stock but listed in stock', reporter: 'PetrGains', ts: NOW - 20 * HOUR, state: 'Admin review' },
    { id: 3, offerId: (offerOf('ZMA Recovery', 4) || {}).id || null, merchantId: 4, productName: 'ZMA Recovery', reported: 'Shipping cost higher than stated', reporter: 'FitLena', ts: NOW - 2 * DAY, state: 'Resolved' },
  ];

  /* ============ 17. default ranking weights ============ */
  ix.rankWeights = { price: 30, trust: 20, delivery: 14, reviews: 12, freshness: 10, availability: 8, shipping: 6 };
  ix.rankLabels = [[90, 'Exceptional'], [80, 'Excellent'], [70, 'Good'], [60, 'Fair'], [0, 'Low confidence']];
})();
