/* Comparo Performance — Commercial OS seed data. Extends window.SEED under S.cx.
   Loaded after seed-growth.js. Deterministic; all values traceable to merchants/subscriptions/campaigns. */
(function () {
  const S = window.SEED;
  if (!S) return;
  const mk = (s) => () => { s |= 0; s = s + 0x6D2B79F5 | 0; let t = Math.imul(s ^ s >>> 15, 1 | s); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; };
  const R = mk(20260909);
  const int = (a, b) => a + Math.floor(R() * (b - a + 1));
  const pick = (a) => a[Math.floor(R() * a.length)];
  const DAY = S.DAY, NOW = S.NOW, M = S.merchants;
  const cx = {};
  S.cx = cx;

  /* ---------- 1. plans, versions, entitlements ---------- */
  cx.featureGroups = ['Profile', 'Feeds', 'Analytics', 'Reviews', 'Deals', 'Campaigns', 'API', 'Team', 'Support'];
  cx.features = [
    { key: 'profile.basic', group: 'Profile', name: 'Merchant profile', why: 'Your shop, shipping rules and reviews on a page that ranks organically.' },
    { key: 'feed.import', group: 'Feeds', name: 'Product feed import', why: 'Keeps prices, stock and coupons accurate every six hours.' },
    { key: 'feed.health', group: 'Feeds', name: 'Feed health insights', why: 'Match rate, rejected rows and the fields costing you visibility.' },
    { key: 'analytics.history_days', group: 'Analytics', name: 'Analytics history', why: 'How far back you can compare clicks, conversions and price position.', unit: 'days' },
    { key: 'analytics.benchmark', group: 'Analytics', name: 'Competitor benchmarking', why: 'Your CTR, freshness and pricing against the anonymised category median.' },
    { key: 'analytics.conversion', group: 'Analytics', name: 'Conversion analytics', why: 'Which offers and pages actually convert, not just which get clicks.' },
    { key: 'reviews.reply', group: 'Reviews', name: 'Review replies', why: 'A public right of reply under every review.' },
    { key: 'reviews.analytics', group: 'Reviews', name: 'Review analytics', why: 'Topics, rating trend, response and resolution rate by market.' },
    { key: 'deal.active_limit', group: 'Deals', name: 'Active deals', why: 'How many coupons and price drops you can run at once.', unit: 'deals' },
    { key: 'campaign.self_service', group: 'Campaigns', name: 'Campaign marketplace', why: 'Book sponsored placements yourself, labelled and rank-independent.' },
    { key: 'campaign.inventory_share', group: 'Campaigns', name: 'Campaign inventory access', why: 'How much sponsored inventory you can reserve per market.', unit: '%' },
    { key: 'api.requests_month', group: 'API', name: 'API access', why: 'Read your own catalogue, prices and reviews programmatically. Request volume comes from your API plan, not from this plan.', unit: 'gate' },
    { key: 'api.webhooks', group: 'API', name: 'Webhooks', why: 'Push notifications on price, offer and rating changes.' },
    { key: 'data_export.enabled', group: 'API', name: 'Data exports', why: 'Price history, benchmarks and coverage as CSV.' },
    { key: 'team.member_limit', group: 'Team', name: 'Team members', why: 'Separate logins with scoped permissions.', unit: 'seats' },
    { key: 'automation.builder', group: 'Campaigns', name: 'Automation builder', why: 'Your own alerts on feed, price position and reviews.' },
    { key: 'support.tier', group: 'Support', name: 'Support tier', why: 'How fast we answer and who answers.' },
    { key: 'reporting.custom', group: 'Analytics', name: 'Custom reporting', why: 'Build a report from the widgets that matter to you.' },
  ];
  const ent = (plan, map) => Object.keys(map).map((k) => ({ feature: k, plan: plan, enabled: map[k] !== false && map[k] !== 0, limit: typeof map[k] === 'number' ? map[k] : null, value: typeof map[k] === 'string' ? map[k] : null, usage_period: 'month' }));
  cx.plans = [
    { key: 'FREE', name: 'Free', price: { month: 0, year: 0 }, order: 1, status: 'active', version: 3, blurb: 'Everything needed to be compared fairly.' },
    { key: 'PRO', name: 'Pro', price: { month: 149, year: 1490 }, order: 2, status: 'active', version: 3, blurb: 'Analytics and benchmarking for shops that price actively.' },
    { key: 'GROWTH', name: 'Growth', price: { month: 399, year: 3990 }, order: 3, status: 'active', version: 3, blurb: 'Intelligence, campaigns, API and team collaboration.' },
    { key: 'ENTERPRISE', name: 'Enterprise', price: { month: null, year: null }, order: 4, status: 'active', version: 2, blurb: 'Custom limits, integrations and commercial agreements.', custom: true },
  ];
  /* the API entitlement is a gate, never a quota — volume is sold by the API plan alone */
  const asGate = (rows) => rows.map((r) => (r.feature === 'api.requests_month' ? Object.assign({}, r, { limit: null, value: r.enabled ? 'included' : null }) : r));
  cx.entitlements = [].concat(
    asGate(ent('FREE', { 'profile.basic': true, 'feed.import': true, 'reviews.reply': true, 'deal.active_limit': 3, 'analytics.history_days': 30, 'api.requests_month': 0, 'team.member_limit': 1, 'support.tier': 'Standard', 'feed.health': false, 'analytics.benchmark': false, 'analytics.conversion': false, 'reviews.analytics': false, 'campaign.self_service': false, 'campaign.inventory_share': 0, 'api.webhooks': false, 'data_export.enabled': false, 'automation.builder': false, 'reporting.custom': false })),
    asGate(ent('PRO', { 'profile.basic': true, 'feed.import': true, 'feed.health': true, 'reviews.reply': true, 'reviews.analytics': true, 'deal.active_limit': 10, 'analytics.history_days': 180, 'analytics.benchmark': true, 'analytics.conversion': false, 'campaign.self_service': true, 'campaign.inventory_share': 10, 'api.requests_month': 10000, 'api.webhooks': false, 'data_export.enabled': false, 'team.member_limit': 3, 'automation.builder': false, 'support.tier': 'Priority', 'reporting.custom': false })),
    asGate(ent('GROWTH', { 'profile.basic': true, 'feed.import': true, 'feed.health': true, 'reviews.reply': true, 'reviews.analytics': true, 'deal.active_limit': 30, 'analytics.history_days': 730, 'analytics.benchmark': true, 'analytics.conversion': true, 'campaign.self_service': true, 'campaign.inventory_share': 30, 'api.requests_month': 100000, 'api.webhooks': true, 'data_export.enabled': true, 'team.member_limit': 10, 'automation.builder': true, 'support.tier': 'Priority', 'reporting.custom': true })),
    asGate(ent('ENTERPRISE', { 'profile.basic': true, 'feed.import': true, 'feed.health': true, 'reviews.reply': true, 'reviews.analytics': true, 'deal.active_limit': 100, 'analytics.history_days': 1825, 'analytics.benchmark': true, 'analytics.conversion': true, 'campaign.self_service': true, 'campaign.inventory_share': 60, 'api.requests_month': 1000000, 'api.webhooks': true, 'data_export.enabled': true, 'team.member_limit': 50, 'automation.builder': true, 'support.tier': 'Dedicated', 'reporting.custom': true }))
  );
  cx.planVersions = [
    { plan: 'PRO', version: 3, from: NOW - 120 * DAY, note: 'Review analytics added; price unchanged.' },
    { plan: 'PRO', version: 2, from: NOW - 400 * DAY, note: 'Benchmarking added.' },
    { plan: 'GROWTH', version: 3, from: NOW - 90 * DAY, note: 'API raised to 100k requests.' },
    { plan: 'ENTERPRISE', version: 2, from: NOW - 260 * DAY, note: 'Custom feed workflows.' },
  ];
  cx.annualDiscountPct = 17;

  /* ---------- 2. commercial accounts + subscriptions ---------- */
  const accountPlan = { 1: 'ENTERPRISE', 2: 'GROWTH', 3: 'GROWTH', 4: 'FREE', 5: 'PRO', 6: 'FREE', 7: 'PRO', 8: 'FREE', 9: 'TRIAL_PRO', 10: 'PRO', 11: 'FREE', 12: 'GROWTH', 13: 'FREE' };
  const statusOf = { 1: 'Enterprise', 2: 'Paid', 3: 'Paid', 4: 'Free', 5: 'Paid', 6: 'Free', 7: 'Past Due', 8: 'Free', 9: 'Trial', 10: 'Paid', 11: 'Free', 12: 'Paused', 13: 'Free' };
  cx.owners = ['sales@comparo', 'accounts@comparo', 'enterprise@comparo'];
  cx.accounts = M.map((m, i) => {
    const raw = accountPlan[m.id] || 'FREE';
    const trial = raw === 'TRIAL_PRO';
    const plan = trial ? 'PRO' : raw;
    const status = statusOf[m.id] || 'Free';
    const paid = plan !== 'FREE';
    return {
      merchantId: m.id, status: status, plan: plan, trial: trial,
      billingCountry: m.country, billingCurrency: m.country === 'GB' ? 'GBP' : m.country === 'CZ' ? 'CZK' : m.country === 'PL' ? 'PLN' : m.country === 'SE' ? 'SEK' : m.country === 'US' ? 'USD' : 'EUR',
      taxProfile: m.country === 'GB' ? 'UK VAT' : m.country === 'US' ? 'US sales tax (out of scope)' : 'EU VAT',
      reverseCharge: m.country !== 'DE' && m.country !== 'US',
      owner: paid ? (m.id === 1 ? 'enterprise@comparo' : m.id % 2 ? 'sales@comparo' : 'accounts@comparo') : null,
      contractType: plan === 'ENTERPRISE' ? 'Custom contract (demo)' : paid ? 'Standard terms' : 'Free listing',
      contractStart: paid ? NOW - int(120, 700) * DAY : null,
      contractEnd: plan === 'ENTERPRISE' ? NOW + 210 * DAY : null,
      renewalType: plan === 'ENTERPRISE' ? 'Manual, 60-day notice' : paid ? 'Auto-renew' : null,
      noticeDays: plan === 'ENTERPRISE' ? 60 : 30,
      paymentStatus: status === 'Past Due' ? 'Past due 21 days' : paid ? 'Current' : 'n/a',
      notes: status === 'Past Due' ? 'Two failed card charges; finance emailed the billing contact twice.' : plan === 'ENTERPRISE' ? 'Anchor account for DE. Quarterly business review each January.' : plan === 'FREE' ? 'Free listing; candidate for Pro if feed quality improves.' : 'Standard account.',
      legalName: m.name + (m.country === 'DE' ? ' GmbH' : m.country === 'CZ' ? ' s.r.o.' : m.country === 'PL' ? ' sp. z o.o.' : ' Ltd'),
      companyId: 'REG-' + (100000 + m.id * 4231),
      vatId: m.country + String(100000000 + m.id * 7919),
      billingEmail: 'billing@' + m.web,
      billingAddress: int(1, 90) + ' Example Street, ' + (m.country === 'DE' ? 'Berlin' : m.country === 'CZ' ? 'Brno' : m.country === 'GB' ? 'Manchester' : 'Warsaw'),
      credits: m.id === 8 ? 120 : m.id === 3 ? 45 : 0,
      firstSeen: NOW - int(200, 900) * DAY,
    };
  });
  const planPrice = (key, period) => { const p = cx.plans.find((x) => x.key === key); return p && p.price ? (period === 'year' ? p.price.year : p.price.month) : null; };
  cx.subscriptions = cx.accounts.filter((a) => a.plan !== 'FREE').map((a, i) => {
    const period = a.plan === 'ENTERPRISE' || i % 3 === 0 ? 'year' : 'month';
    const custom = a.plan === 'ENTERPRISE' ? 14400 : null;
    const list = custom || planPrice(a.plan, period) || 0;
    const discountPct = a.merchantId === 3 ? 15 : a.merchantId === 10 ? 10 : 0;
    const price = Math.round(list * (1 - discountPct / 100));
    const st = a.trial ? 'Trialing' : a.status === 'Past Due' ? 'Past Due' : a.status === 'Paused' ? 'Paused' : a.merchantId === 5 ? 'Cancelled' : 'Active';
    return {
      id: 'SUB-' + (2000 + a.merchantId), merchantId: a.merchantId, plan: a.plan, planVersion: (cx.plans.find((p) => p.key === a.plan) || {}).version,
      status: st, billingPeriod: period, listPrice: list, price: price, currency: 'EUR', billingCurrency: a.billingCurrency,
      discountPct: discountPct, discountReason: discountPct ? (a.merchantId === 3 ? 'Two-year commitment' : 'Launch-market incentive') : null,
      start: NOW - int(60, 620) * DAY,
      renewal: st === 'Cancelled' ? NOW + 12 * DAY : NOW + int(4, 300) * DAY,
      trialStart: a.trial ? NOW - 9 * DAY : null, trialEnd: a.trial ? NOW + 5 * DAY : null,
      cancelledAt: st === 'Cancelled' ? NOW - 4 * DAY : null,
      cancelAtPeriodEnd: st === 'Cancelled',
      cancelReason: st === 'Cancelled' ? 'Missing feature — wanted per-SKU margin reporting' : null,
      pausedUntil: st === 'Paused' ? NOW + 40 * DAY : null,
      taxRate: (S.countries.find((c) => c.iso === a.billingCountry) || { vat: 19 }).vat,
      taxMode: 'exclusive',
      mrr: period === 'year' ? Math.round(price / 12) : price,
    };
  });
  cx.subscriptionChanges = [
    { ts: NOW - 210 * DAY, merchantId: 2, from: 'PRO', to: 'GROWTH', kind: 'Upgrade', actor: 'sales@comparo', delta: 250 },
    { ts: NOW - 96 * DAY, merchantId: 5, from: 'GROWTH', to: 'PRO', kind: 'Downgrade', actor: 'accounts@comparo', delta: -250 },
    { ts: NOW - 60 * DAY, merchantId: 12, from: 'PRO', to: 'GROWTH', kind: 'Upgrade', actor: 'sales@comparo', delta: 250 },
    { ts: NOW - 40 * DAY, merchantId: 3, from: 'PRO', to: 'GROWTH', kind: 'Upgrade', actor: 'sales@comparo', delta: 250 },
    { ts: NOW - 4 * DAY, merchantId: 5, from: 'PRO', to: null, kind: 'Cancellation (period end)', actor: 'merchant', delta: -149 },
  ];

  /* ---------- 3. invoices, credits, payments ---------- */
  cx.invoiceNumberPattern = 'CMP-{YYYY}-{00000}';
  let inv = 1;
  cx.invoices = [];
  cx.subscriptions.forEach((sub) => {
    const months = sub.billingPeriod === 'year' ? [1] : [1, 2, 3];
    months.forEach((k) => {
      const issued = NOW - (k * 30 + int(1, 6)) * DAY;
      const subtotal = sub.price;
      const tax = sub.taxMode === 'exclusive' ? Math.round(subtotal * sub.taxRate) / 100 : 0;
      const state = sub.status === 'Past Due' && k === 1 ? 'Past Due' : k === 1 && sub.merchantId === 3 ? 'Credited' : k === 1 && sub.merchantId === 12 ? 'Open' : 'Paid';
      cx.invoices.push({
        id: 'CMP-2026-' + String(inv++).padStart(5, '0'), merchantId: sub.merchantId, subscriptionId: sub.id,
        issued: issued, due: issued + 14 * DAY, currency: sub.currency, status: state,
        items: [{ kind: 'Subscription', label: sub.plan + ' plan · ' + (sub.billingPeriod === 'year' ? 'annual' : 'monthly'), qty: 1, unit: subtotal, total: subtotal }],
        subtotal: subtotal, tax: tax, total: Math.round((subtotal + tax) * 100) / 100,
        paidAt: state === 'Paid' ? issued + int(1, 10) * DAY : null,
        timeline: [
          { ts: issued, event: 'Invoice issued' },
          state === 'Paid' ? { ts: issued + 3 * DAY, event: 'Payment received' } : state === 'Past Due' ? { ts: issued + 15 * DAY, event: 'Payment failed — retry scheduled' } : { ts: issued + 1 * DAY, event: 'Sent to billing contact' },
        ],
      });
    });
  });
  /* campaign + API invoice items */
  cx.invoices.push({
    id: 'CMP-2026-' + String(inv++).padStart(5, '0'), merchantId: 1, subscriptionId: null,
    issued: NOW - 12 * DAY, due: NOW + 2 * DAY, currency: 'EUR', status: 'Open',
    items: [
      { kind: 'Sponsored campaign', label: 'Homepage featured merchant · DE · 30 days', qty: 1, unit: 2400, total: 2400 },
    ],
    subtotal: 2400, tax: Math.round(2400 * 19) / 100, total: Math.round((2400 + 2400 * 0.19) * 100) / 100, paidAt: null,
    timeline: [{ ts: NOW - 12 * DAY, event: 'Invoice issued' }, { ts: NOW - 11 * DAY, event: 'Sent to billing contact' }],
  });
  /* API overage is metered, never written by hand: an invoice line exists only when a merchant on a
     finite API plan is actually over its allowance. Enterprise allowances are custom, so overage
     there is a negotiated contract term and never a per-1k line. */
  (function () {
    const usage = [cx.apiUsage].concat(cx.apiUsageOther || []);
    usage.forEach((u) => {
      const ap = (cx.apiPlans || []).find((p) => p.key === u.plan) || {};
      if (!ap.requests || typeof ap.overage !== 'number') return;
      const over = u.used - ap.requests;
      if (over <= 0) return;
      const units = Math.ceil(over / 1000);
      const amount = units * ap.overage;
      const m = M.find((x) => x.id === u.merchantId) || { name: 'Unknown merchant' };
      const acct = cx.accounts.find((a) => a.merchantId === u.merchantId) || { billingCurrency: 'EUR' };
      const issued = NOW - 5 * DAY;
      cx.invoices.push({
        id: 'CMP-2026-' + String(inv++).padStart(5, '0'), merchantId: u.merchantId, subscriptionId: null,
        issued: issued, due: issued + 14 * DAY, currency: 'EUR', status: 'Open',
        items: [{ kind: 'API overage', label: units + ',000 requests above the ' + ap.name + ' allowance of ' + (ap.requests / 1000) + 'k', qty: units, unit: ap.overage, total: amount }],
        subtotal: amount, tax: Math.round(amount * 19) / 100, total: Math.round((amount + amount * 0.19) * 100) / 100, paidAt: null,
        timeline: [{ ts: issued, event: 'Invoice issued from metered API overage' }],
      });
    });
  })();
  cx.creditNotes = [
    { id: 'CN-2026-0001', merchantId: 3, invoiceId: cx.invoices.find((i) => i.merchantId === 3) ? cx.invoices.find((i) => i.merchantId === 3).id : null, issued: NOW - 22 * DAY, amount: 45, currency: 'EUR', reason: 'Campaign under-delivery — 18 % of booked impressions not served', status: 'Applied', actor: 'finance@comparo' },
    { id: 'CN-2026-0002', merchantId: 8, invoiceId: null, issued: NOW - 8 * DAY, amount: 120, currency: 'EUR', reason: 'Goodwill credit while affiliate tracking was broken', status: 'Available', actor: 'finance@comparo' },
  ];
  cx.payments = cx.invoices.filter((i) => i.status === 'Paid').map((i, k) => ({
    id: 'PAY-' + (5000 + k), invoiceId: i.id, merchantId: i.merchantId, amount: i.total, currency: i.currency,
    method: k % 3 === 0 ? 'SEPA direct debit' : k % 3 === 1 ? 'Card' : 'Bank transfer', ts: i.paidAt,
  }));
  cx.paymentMethods = [
    { kind: 'Card', detail: 'Visa ···· 4242 (demo, no real card data is collected)', default: true },
    { kind: 'SEPA', detail: 'DE·· ···· 0199 (demo mandate)', default: false },
    { kind: 'Bank transfer', detail: '14-day invoice terms', default: false },
    { kind: 'Invoice terms', detail: 'Enterprise only, 30 days net', default: false },
  ];
  cx.discounts = [
    { code: 'LAUNCH-CZ', type: 'Percent', amount: 15, plan: 'any', merchantId: null, validUntil: NOW + 90 * DAY, maxMonths: 12, note: 'Czech market launch incentive' },
    { code: 'ANNUAL-2Y', type: 'Percent', amount: 15, plan: 'GROWTH', merchantId: 3, validUntil: NOW + 400 * DAY, maxMonths: 24, note: 'Two-year commitment' },
    { code: 'GOODWILL-10', type: 'Percent', amount: 10, plan: 'PRO', merchantId: 10, validUntil: NOW + 60 * DAY, maxMonths: 3, note: 'Applied after feed migration issues', actor: 'sales@comparo', appliedAt: NOW - 30 * DAY },
    { code: 'TRIAL-PLUS14', type: 'Trial extension', amount: 14, plan: 'PRO', merchantId: null, validUntil: NOW + 30 * DAY, maxMonths: null, note: 'Extends a Pro trial by 14 days' },
  ];

  /* ---------- 4. sponsored inventory + campaigns ---------- */
  cx.placements = [
    { key: 'home_merchant', name: 'Homepage featured merchant', pageType: 'Home', capacity: 2, basePrice: 2400, model: 'Fixed placement', minSpend: 1200, audience: '268k monthly sessions' },
    { key: 'home_deal', name: 'Homepage featured deal', pageType: 'Home', capacity: 3, basePrice: 1600, model: 'CPM', minSpend: 800, audience: '268k monthly sessions' },
    { key: 'category_merchant', name: 'Category featured merchant', pageType: 'Category', capacity: 2, basePrice: 900, model: 'CPC', minSpend: 400, audience: '41k category sessions' },
    { key: 'country_merchant', name: 'Country hub featured merchant', pageType: 'Country', capacity: 2, basePrice: 1100, model: 'Fixed placement', minSpend: 600, audience: '18.6k market sessions' },
    { key: 'product_sponsor', name: 'Product related sponsor', pageType: 'Product', capacity: 1, basePrice: 1900, model: 'CPC', minSpend: 900, audience: '96k product sessions' },
    { key: 'deal_hub', name: 'Deal hub placement', pageType: 'Deals', capacity: 4, basePrice: 1200, model: 'CPM', minSpend: 500, audience: '28k deal sessions' },
    { key: 'newsletter', name: 'Newsletter sponsorship', pageType: 'Newsletter', capacity: 1, basePrice: 1800, model: 'Fixed placement', minSpend: 1800, audience: '18.4k subscribers' },
    { key: 'research', name: 'Research sponsorship', pageType: 'Research', capacity: 1, basePrice: 3200, model: 'Fixed placement', minSpend: 3200, audience: 'PR + backlink reach' },
  ];
  cx.campaigns = [
    { id: 'SP-101', merchantId: 1, name: 'homepage takeover', placement: 'home_merchant', market: 'DE', starts: NOW - 12 * DAY, ends: NOW + 18 * DAY, budget: 2400, spent: 1480, model: 'Fixed placement', status: 'Active', impressions: 184000, clicks: 5140, conversions: 402, revenue: 28940, creative: { headline: 'German-made whey, lab tested by batch', desc: 'Free shipping over €85. Verified merchant since 2022.', cta: 'See offers', logo: 'PS', url: 'https://peaksupps.de/comparo', disclosure: 'Sponsored by PeakSupps' }, compliance: 'cleared' },
    { id: 'SP-102', merchantId: 3, name: 'GB deal hub', placement: 'deal_hub', market: 'GB', starts: NOW - 20 * DAY, ends: NOW - 2 * DAY, budget: 1200, spent: 1200, model: 'CPM', status: 'Completed', impressions: 96000, clicks: 2410, conversions: 141, revenue: 6800, creative: { headline: 'Clear whey, 20 % off this week', desc: 'Ships from Manchester in 24 hours.', cta: 'View deal', logo: 'PH', url: 'https://performancehub.co.uk/comparo', disclosure: 'Sponsored by PerformanceHub' }, compliance: 'cleared' },
    { id: 'SP-103', merchantId: 2, name: 'Czech spotlight', placement: 'country_merchant', market: 'CZ', starts: NOW + 6 * DAY, ends: NOW + 36 * DAY, budget: 1100, spent: 0, model: 'Fixed placement', status: 'Scheduled', impressions: 0, clicks: 0, conversions: 0, revenue: 0, creative: { headline: 'Creatine at cost per serving records', desc: 'Czech warehouse, 1–2 day delivery.', cta: 'Compare offers', logo: 'IL', url: 'https://ironlab.cz/comparo', disclosure: 'Sponsored by IronLab Store' }, compliance: 'cleared' },
    { id: 'SP-104', merchantId: 5, name: 'category spotlight', placement: 'category_merchant', market: 'DE', starts: NOW + 2 * DAY, ends: NOW + 32 * DAY, budget: 900, spent: 0, model: 'CPC', status: 'Pending Approval', impressions: 0, clicks: 0, conversions: 0, revenue: 0, creative: { headline: 'Electrolytes for endurance athletes', desc: 'Bundle pricing on hydration sticks.', cta: 'See range', logo: 'AS', url: 'https://athletesupply.de/comparo', disclosure: 'Sponsored by AthleteSupply' }, compliance: 'pending' },
    { id: 'SP-105', merchantId: 8, name: 'thermo push', placement: 'product_sponsor', market: 'FR', starts: null, ends: null, budget: 1900, spent: 0, model: 'CPC', status: 'Rejected', impressions: 0, clicks: 0, conversions: 0, revenue: 0, creative: { headline: 'Thermo Cut, now in France', desc: 'Yohimbine formula, fast shipping.', cta: 'Buy now', logo: 'SB', url: 'https://supplementbay.com/thermo', disclosure: 'Sponsored by SupplementBay' }, compliance: 'blocked', rejectReason: 'Yohimbine is not permitted in food supplements in FR — the compliance gate blocks this placement in that market.' },
    { id: 'SP-106', merchantId: 11, name: 'newsletter feature', placement: 'newsletter', market: 'FR', starts: NOW - 6 * DAY, ends: NOW + 1 * DAY, budget: 1800, spent: 1800, model: 'Fixed placement', status: 'Active', impressions: 18400, clicks: 2140, conversions: 96, revenue: 5400, creative: { headline: 'French-made, Informed Sport certified', desc: 'Featured in this week’s digest.', cta: 'Read more', logo: 'CN', url: 'https://corenutri.fr/comparo', disclosure: 'Sponsored by CoreNutri' }, compliance: 'cleared' },
    { id: 'SP-107', merchantId: 12, name: 'draft', placement: 'home_deal', market: 'AT', starts: null, ends: null, budget: 800, spent: 0, model: 'CPM', status: 'Draft', impressions: 0, clicks: 0, conversions: 0, revenue: 0, creative: { headline: '', desc: '', cta: '', logo: 'AL', url: '', disclosure: '' }, compliance: 'pending' },
    { id: 'SP-108', merchantId: 10, name: 'research sponsorship', placement: 'research', market: 'IT', starts: NOW + 14 * DAY, ends: NOW + 44 * DAY, budget: 3200, spent: 0, model: 'Fixed placement', status: 'Pending Approval', impressions: 0, clicks: 0, conversions: 0, revenue: 0, creative: { headline: 'Supporting the shipping-cost study', desc: 'Sponsor of the September market report.', cta: 'Read the study', logo: 'MW', url: 'https://muscleworks.it/comparo', disclosure: 'Study sponsored by MuscleWorks — data and conclusions are Comparo’s own' }, compliance: 'pending' },
  ];
  /* the merchant is the single source of a campaign's identity: names and creative disclosure are
     derived from merchantId, so a hard-coded label can never contradict the billed account */
  cx.campaigns.forEach((c) => {
    const m = M.find((x) => x.id === c.merchantId) || { name: 'Unknown merchant' };
    c.name = m.name + ' ' + c.name;
    c.creative.disclosure = 'Sponsored by ' + m.name + (c.placement === 'research' ? ' — data and conclusions are Comparo’s own' : '');
    c.creative.logo = m.name.replace(/[^A-Za-z]/g, '').slice(0, 2).toUpperCase();
  });
  cx.frequencyCap = { perSession: 3, perDay: 8, note: 'A visitor sees at most 3 sponsored units per session and 8 per day.' };
  cx.marketplaceProducts = [
    { key: 'home_deal', name: 'Featured Deal', requires: 'PRO', est: '~52k impressions / 30 d' },
    { key: 'country_merchant', name: 'Country Spotlight', requires: 'PRO', est: '~18k market sessions / 30 d' },
    { key: 'category_merchant', name: 'Category Spotlight', requires: 'PRO', est: '~41k category sessions / 30 d' },
    { key: 'newsletter', name: 'Newsletter Feature', requires: 'GROWTH', est: '18.4k subscribers, one send' },
    { key: 'research', name: 'Research Sponsorship', requires: 'GROWTH', est: 'PR reach + backlinks' },
    { key: 'home_merchant', name: 'Featured Merchant', requires: 'GROWTH', est: '~184k impressions / 30 d' },
  ];
  cx.exclusiveAgreements = [
    { id: 'EA-1', merchantId: 1, kind: 'Exclusive coupon', markets: ['DE', 'AT'], start: NOW - 40 * DAY, end: NOW + 50 * DAY, terms: '12 % code, Comparo-only, 60-day exclusivity', deliverables: 'Deal hub placement + newsletter mention', status: 'Active', sponsored: false },
    { id: 'EA-2', merchantId: 3, kind: 'Market exclusive', markets: ['GB'], start: NOW - 90 * DAY, end: NOW + 90 * DAY, terms: 'Sole featured merchant in GB protein category', deliverables: 'Category spotlight, 2 newsletter sends', status: 'Active', sponsored: true },
    { id: 'EA-3', merchantId: 5, kind: 'Newsletter exclusive', markets: ['DE'], start: NOW + 10 * DAY, end: NOW + 40 * DAY, terms: 'Single-sponsor digest', deliverables: 'One send, 18.4k subscribers', status: 'Proposed', sponsored: true },
    { id: 'EA-4', merchantId: 2, kind: 'Sponsored research partnership', markets: ['CZ', 'SK'], start: NOW - 10 * DAY, end: NOW + 80 * DAY, terms: 'Sponsor of the Czech market report; no editorial input', deliverables: 'Logo + methodology credit', status: 'Active', sponsored: true },
  ];

  /* ---------- 5. sales pipeline, renewals, leads ---------- */
  cx.pipelineStages = ['Lead', 'Qualified', 'Discovery', 'Proposal', 'Negotiation', 'Legal review'];
  cx.pipelineTerminal = ['Won', 'Lost'];
  cx.opportunityTypes = ['Subscription', 'Sponsored campaign', 'Affiliate partnership', 'Enterprise contract', 'API/Data product', 'Exclusive partnership'];
  const oppDefs = [
    ['SportNahrung DE', 'Enterprise contract', 'Negotiation', 1200, 70, 18, 'enterprise@comparo', null],
    ['FitDirect', 'Subscription', 'Proposal', 399, 60, 24, 'sales@comparo', null],
    ['PeakSupps', 'Sponsored campaign', 'Legal review', 800, 80, 9, 'accounts@comparo', 1],
    ['SupleMax', 'Subscription', 'Discovery', 149, 40, 40, 'sales@comparo', null],
    ['PerformanceHub', 'API/Data product', 'Qualified', 600, 35, 55, 'sales@comparo', 3],
    ['IronLab Store', 'Exclusive partnership', 'Proposal', 500, 55, 30, 'accounts@comparo', 2],
    ['LiftHouse UK', 'Subscription', 'Lead', 149, 20, 70, 'sales@comparo', null],
    ['NutriNord', 'Sponsored campaign', 'Won', 1100, 100, 0, 'accounts@comparo', null],
    ['MegaSupps', 'Subscription', 'Lost', 399, 0, 0, 'sales@comparo', null],
    ['CoreNutri', 'Subscription', 'Negotiation', 399, 65, 14, 'accounts@comparo', 12],
    ['AthleteSupply', 'Sponsored campaign', 'Discovery', 900, 45, 35, 'accounts@comparo', 5],
    ['ProteinPoint', 'Subscription', 'Qualified', 149, 30, 60, 'sales@comparo', null],
    ['USPerformance', 'API/Data product', 'Lead', 1500, 15, 90, 'enterprise@comparo', null],
    ['BodyCore Market', 'Affiliate partnership', 'Proposal', 0, 50, 21, 'affiliate@comparo', 4],
    ['NordicWhey', 'Subscription', 'Lead', 149, 20, 75, 'sales@comparo', null],
    ['ViennaFit', 'Sponsored campaign', 'Qualified', 600, 35, 45, 'accounts@comparo', null],
    ['ItalFit', 'Exclusive partnership', 'Discovery', 700, 40, 50, 'accounts@comparo', null],
  ];
  cx.opportunities = oppDefs.map((o, i) => ({
    id: 'OPP-' + (4000 + i), name: o[0], type: o[1], stage: o[2], monthly: o[3], probability: o[4],
    closeInDays: o[5], owner: o[6], merchantId: o[7],
    annual: o[3] * 12, created: NOW - int(10, 120) * DAY,
    notes: o[2] === 'Lost' ? 'Lost: unclear ownership, compliance declined onboarding.' : o[2] === 'Won' ? 'Won: 30-day country spotlight signed.' : 'Active conversation with the commercial contact.',
  }));
  cx.renewals = cx.subscriptions.filter((s2) => s2.status !== 'Cancelled').map((s2) => {
    const a = cx.accounts.find((x) => x.merchantId === s2.merchantId);
    const risks = [];
    if (s2.status === 'Past Due') risks.push('Invoice past due 21 days');
    if (s2.status === 'Paused') risks.push('Subscription paused');
    if (s2.merchantId === 8 || s2.merchantId === 6) risks.push('Feed unstable');
    if (s2.merchantId === 4) risks.push('No merchant login in 42 days');
    return {
      id: 'REN-' + s2.merchantId, merchantId: s2.merchantId, subscriptionId: s2.id, plan: s2.plan,
      date: s2.renewal, arr: s2.billingPeriod === 'year' ? s2.price : s2.price * 12,
      owner: a ? a.owner : null, risks: risks, status: risks.length ? 'At risk' : 'On track',
    };
  });
  cx.merchantLeads = [
    { id: 'LD-1', merchantId: 1, type: 'Wholesale enquiry', created: NOW - 2 * DAY, status: 'New', source: 'Shop profile contact form', consent: 'User explicitly submitted and consented to sharing name and message' },
    { id: 'LD-2', merchantId: 3, type: 'Bulk order question', created: NOW - 5 * DAY, status: 'Viewed', source: 'Product page enquiry', consent: 'Consent given at submission' },
    { id: 'LD-3', merchantId: 2, type: 'Partnership enquiry', created: NOW - 9 * DAY, status: 'Contacted', source: 'Merchant Q&A', consent: 'Consent given at submission' },
    { id: 'LD-4', merchantId: 5, type: 'Wholesale enquiry', created: NOW - 14 * DAY, status: 'Qualified', source: 'Shop profile contact form', consent: 'Consent given at submission' },
  ];
  cx.proposals = [
    { id: 'PRP-1', merchantId: null, name: 'SportNahrung DE — Enterprise bundle', items: ['Enterprise plan (annual)', 'Homepage featured merchant × 3 months', 'API Business package'], monthly: 1200, annual: 14400, discountPct: 10, status: 'Negotiation', owner: 'enterprise@comparo', sent: NOW - 12 * DAY },
    { id: 'PRP-2', merchantId: 12, name: 'CoreNutri — Growth + campaign', items: ['Growth plan (annual)', 'Country spotlight FR × 1 month'], monthly: 399, annual: 4788, discountPct: 0, status: 'Sent', owner: 'accounts@comparo', sent: NOW - 4 * DAY },
    { id: 'PRP-3', merchantId: 3, name: 'PerformanceHub — API Business', items: ['API Business package', 'Price History API'], monthly: 600, annual: 7200, discountPct: 5, status: 'Draft', owner: 'sales@comparo', sent: null },
  ];

  /* ---------- 6. API & data products ---------- */
  cx.apiPlans = [
    { key: 'DEV', name: 'Developer', price: 0, requests: 5000, rate: '5 req/s', support: 'Community', overage: null },
    { key: 'BIZ', name: 'Business', price: 490, requests: 250000, rate: '25 req/s', support: 'Priority', overage: 4 },
    { key: 'ENT', name: 'Enterprise', price: null, requests: null, rate: 'Custom', support: 'Dedicated', overage: 'Negotiated' },
  ];
  cx.dataProducts = [
    { key: 'catalog', name: 'Product Catalog API', desc: 'Canonical products, brands, categories, ingredients and identifiers.', plan: 'DEV' },
    { key: 'price', name: 'Price API', desc: 'Current normalised totals per market including shipping and coupon state.', plan: 'BIZ' },
    { key: 'history', name: 'Price History API', desc: 'Daily minimum and average series per product and market.', plan: 'BIZ' },
    { key: 'merchant', name: 'Merchant API', desc: 'Public shop profiles, shipping rules and aggregate trust signals.', plan: 'DEV' },
    { key: 'deal', name: 'Deal API', desc: 'Live coupons and price drops with verification state.', plan: 'BIZ' },
    { key: 'review', name: 'Review Aggregate API', desc: 'Aggregated ratings and distributions. No personal data, ever.', plan: 'DEV' },
    { key: 'market', name: 'Market Intelligence API', desc: 'Coverage, demand and price spread per market and category.', plan: 'ENT' },
  ];
  cx.apiKeys = [
    { id: 'AK-1', merchantId: 3, name: 'Production sync', prefix: 'demo_pk_live_9f2a', created: NOW - 120 * DAY, lastUsed: NOW - 2 * 3600000, status: 'Active' },
    { id: 'AK-2', merchantId: 1, name: 'Warehouse job', prefix: 'demo_pk_live_41bc', created: NOW - 60 * DAY, lastUsed: NOW - 26 * 3600000, status: 'Active' },
    { id: 'AK-3', merchantId: 3, name: 'BI import', prefix: 'demo_pk_test_77de', created: NOW - 30 * DAY, lastUsed: NOW - 5 * DAY, status: 'Active' },
    { id: 'AK-4', merchantId: 1, name: 'Old integration', prefix: 'demo_pk_live_0c19', created: NOW - 300 * DAY, lastUsed: NOW - 200 * DAY, status: 'Revoked' },
  ];
  /* the merchant on the API Business package is one whose commercial plan matches that volume,
     so the percentage is identical wherever it is displayed */
  cx.apiUsage = { merchantId: 3, plan: 'BIZ', included: null, used: 94120, errors: 1240, success: 99.5, topEndpoint: '/v1/prices', overageUnits: 0, series: [] };
  for (let d = 29; d >= 0; d--) cx.apiUsage.series.push(int(2400, 3800));
  cx.apiUsageOther = [
    { merchantId: 1, plan: 'ENT', included: null, used: 612400, errors: 840, success: 99.8, topEndpoint: '/v1/prices' },
    { merchantId: 2, plan: 'DEV', included: null, used: 4820, errors: 61, success: 98.7, topEndpoint: '/v1/products' },
  ];
  cx.webhooks = [
    { id: 'WH-1', merchantId: 1, url: 'https://peaksupps.de/hooks/comparo', events: ['price.updated', 'offer.created'], status: 'Active', lastDelivery: NOW - 40 * 60000, lastCode: 200 },
    { id: 'WH-2', merchantId: 1, url: 'https://peaksupps.de/hooks/reviews', events: ['merchant.rating_changed'], status: 'Failing', lastDelivery: NOW - 6 * 3600000, lastCode: 503 },
    { id: 'WH-3', merchantId: 3, url: 'https://performancehub.co.uk/comparo/hook', events: ['deal.created'], status: 'Active', lastDelivery: NOW - 3 * 3600000, lastCode: 200 },
  ];
  cx.webhookLog = [
    { ts: NOW - 40 * 60000, hook: 'WH-1', event: 'price.updated', code: 200, ms: 142 },
    { ts: NOW - 2 * 3600000, hook: 'WH-1', event: 'offer.created', code: 200, ms: 118 },
    { ts: NOW - 6 * 3600000, hook: 'WH-2', event: 'merchant.rating_changed', code: 503, ms: 3010 },
    { ts: NOW - 9 * 3600000, hook: 'WH-3', event: 'deal.created', code: 200, ms: 96 },
    { ts: NOW - 26 * 3600000, hook: 'WH-2', event: 'merchant.rating_changed', code: 503, ms: 3002 },
  ];
  cx.reports = [
    { key: 'de_market', name: 'Germany market overview', price: 890, sample: '13 merchants · 267 offers · median shipping €4.60', full: 'Coverage, price spread, shipping medians, trust distribution and demand by category.' },
    { key: 'brand_pricing', name: 'Brand pricing report', price: 690, sample: 'IRONFORGE indexed at 104 vs category median', full: 'Per-brand price index, availability growth and merchant spread across 12 markets.' },
    { key: 'competitor', name: 'Competitive merchant report', price: 1200, sample: 'Your platform share of clicks: 18.4 %', full: 'Anonymised competitor set: price position, offer freshness, win rate, trust delta.' },
    { key: 'category_trends', name: 'Category price trends', price: 590, sample: 'Creatine −6.1 % over 90 days', full: '90-day and 12-month price trends per category with volatility and drop frequency.' },
  ];

  /* ---------- 7. affiliate revenue & reconciliation ---------- */
  cx.affiliateContracts = M.map((m) => ({
    merchantId: m.id, model: m.affiliate.network === 'Direct' ? 'CPS' : m.affiliate.network === 'Awin' ? 'CPA' : m.affiliate.network === 'Impact' ? 'Hybrid' : 'CPC',
    rate: m.affiliate.commission, cookieDays: m.affiliate.cookie, network: m.affiliate.network,
    start: NOW - int(120, 700) * DAY, end: null,
    notes: m.partner ? 'Direct agreement, reviewed quarterly.' : 'Network terms, standard.',
    trackingActive: m.id !== 8, lastConversion: m.id === 8 ? NOW - 9 * DAY : NOW - int(1, 20) * 3600000,
  }));
  cx.reconciliation = M.slice(0, 8).map((m, i) => {
    const tracked = 40 + i * 14;
    const rejected = i === 3 ? 18 : int(2, 8);
    const pending = int(3, 12);
    const approved = tracked - rejected - pending;
    return {
      merchantId: m.id, period: '2026-08', tracked: tracked, approved: approved, rejected: rejected, pending: pending,
      reportedByNetwork: i === 5 ? tracked - 22 : tracked - rejected - pending + int(-2, 2),
      commission: Math.round(approved * 8.4 * 100) / 100,
      status: i === 5 ? 'Discrepancy' : pending > 8 ? 'Pending' : 'Approved',
      reversalRate: Math.round((rejected / tracked) * 1000) / 10,
      note: i === 5 ? 'Network reports 22 fewer conversions than we tracked — raised with the affiliate manager.' : null,
    };
  });

  /* ---------- 8. goals, alerts, automations, settings, roles ---------- */
  cx.goals = [
    { key: 'mrr', label: 'MRR', target: 4500, period: 'Q3 2026' },
    { key: 'affiliate', label: 'Affiliate revenue', target: 300000, period: 'Q3 2026' },
    { key: 'paid', label: 'Paid merchants', target: 10, period: 'Q3 2026' },
    { key: 'enterprise', label: 'Enterprise deals', target: 2, period: 'Q3 2026' },
  ];
  /* alerts are derived in commercial.js → alerts(); nothing about them is stored here */
  cx.alerts = [];

  cx.automations = [
    { id: 1, name: 'API limit upsell', trigger: 'merchant API usage > 90 % of plan', action: 'Create upgrade opportunity', enabled: true, runs: 6 },
    { id: 2, name: 'Renewal preparation', trigger: 'renewal < 30 days AND ARR > €5,000', action: 'Create renewal task for the account owner', enabled: true, runs: 11 },
    { id: 3, name: 'Past-due escalation', trigger: 'invoice overdue > 14 days', action: 'Notify billing owner + create finance task', enabled: true, runs: 4 },
    { id: 4, name: 'Campaign under-delivery', trigger: 'campaign CTR < 60 % of placement benchmark', action: 'Flag for optimisation', enabled: true, runs: 3 },
    { id: 5, name: 'Non-monetised traffic', trigger: 'merchant clicks > 500 AND no affiliate contract', action: 'Create sales opportunity', enabled: true, runs: 14 },
    { id: 6, name: 'Plan limit upsell', trigger: 'plan limit reached 3× in a month', action: 'Suggest upgrade to the account owner', enabled: true, runs: 8 },
  ];
  cx.settings = {
    baseCurrency: 'EUR', invoicePattern: 'CMP-{YYYY}-{00000}', taxDisplay: 'Exclusive', trialDays: 14,
    fxDate: '2026-09-01', fx: { EUR: 1, USD: 1.08, GBP: 0.85, CZK: 25.2, PLN: 4.31, SEK: 11.2 },
    revenueCategories: ['Affiliate', 'Subscription', 'Sponsored', 'Campaign', 'API/Data', 'Other'],
    attributionModel: 'Last touch', attributionWindowDays: 30,
    cancellationPolicy: 'Cancel at period end by default; immediate cancellation on request.',
  };
  cx.roles = [
    { key: 'commercial', name: 'Commercial Admin', perms: ['invoice.view', 'invoice.edit', 'invoice.void', 'credit.create', 'subscription.manage', 'campaign.approve', 'commercial.export', 'plan.manage', 'discount.apply', 'opportunity.manage'] },
    { key: 'sales', name: 'Sales Manager', perms: ['opportunity.manage', 'subscription.manage', 'discount.apply', 'invoice.view', 'commercial.export'] },
    { key: 'account', name: 'Account Manager', perms: ['opportunity.manage', 'invoice.view', 'renewal.manage', 'campaign.manage'] },
    { key: 'finance', name: 'Finance', perms: ['invoice.view', 'invoice.edit', 'invoice.void', 'credit.create', 'commercial.export'] },
    { key: 'affiliatemgr', name: 'Affiliate Manager', perms: ['affiliate.view', 'affiliate.edit', 'invoice.view'] },
    { key: 'campaignmgr', name: 'Campaign Manager', perms: ['campaign.manage', 'campaign.approve', 'invoice.view'] },
    { key: 'analyst', name: 'Analyst', perms: ['invoice.view', 'commercial.export', 'affiliate.view'] },
  ];
  cx.permissions = ['invoice.view', 'invoice.edit', 'invoice.void', 'credit.create', 'subscription.manage', 'campaign.manage', 'campaign.approve', 'plan.manage', 'discount.apply', 'opportunity.manage', 'renewal.manage', 'affiliate.view', 'affiliate.edit', 'commercial.export'];
  cx.disputes = [
    { id: 'BD-1', kind: 'Billing', merchantId: 7, subject: 'Charged after downgrade request', state: 'Under review', opened: NOW - 6 * DAY, amount: 149 },
    { id: 'BD-2', kind: 'Campaign', merchantId: 3, subject: 'Impressions 18 % below booked volume', state: 'Credited', opened: NOW - 24 * DAY, amount: 45 },
    { id: 'BD-3', kind: 'Affiliate', merchantId: 6, subject: 'Network reports 22 fewer conversions', state: 'Opened', opened: NOW - 3 * DAY, amount: 185 },
  ];
  cx.successStages = ['Onboarding', 'Activated', 'Growing', 'At risk', 'Expansion', 'Renewal'];
  cx.successTasks = [
    { merchantId: 8, task: 'Improve match rate (61 % → 90 %)', stage: 'At risk' },
    { merchantId: 6, task: 'Add shipping rules for AT and CZ', stage: 'At risk' },
    { merchantId: 4, task: 'Respond to 6 unanswered reviews', stage: 'Onboarding' },
    { merchantId: 11, task: 'Launch first deal', stage: 'Onboarding' },
    { merchantId: 5, task: 'Review Growth plan — deal limit hit 3×', stage: 'Expansion' },
  ];
  cx.knowledgeBase = [
    { title: 'Plan and pricing policy', body: 'List prices are public. Discounts above 15 % need Commercial Admin approval and a stated reason in the audit log.' },
    { title: 'Discount policy', body: 'Percent discounts cap at 24 months. Trial extensions cap at 14 days. Never discount in exchange for ranking, reviews or trust changes.' },
    { title: 'Campaign inventory', body: 'Capacity per placement per market is fixed. Overbooking is refused by the inventory check, not resolved by an opaque auction.' },
    { title: 'Affiliate agreements', body: 'CPS is preferred. Rate changes are recorded with the effective date so historical commission stays reconcilable.' },
    { title: 'Renewal playbook', body: 'Open with the ROI report, resolve open feed or invoice issues first, then discuss plan fit. Never lead with a discount.' },
  ];
})();
