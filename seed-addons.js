/* Comparo Performance — paid extensions: merchant add-ons, buyer tiers, delivery guarantee, ad inventory.

   The revenue model has to survive one sentence: we do not sell the products. There is no
   marketplace, no basket, no order of ours — a click leaves for the shop's own checkout. So
   every paid thing in this file is one of exactly three kinds, and anything that is not one of
   them is not for sale:

   1. Merchant capability — analytics, feeds, campaigns, delivery promise, review invitations.
      Work we do for the shop. Never placement, never rank, never a trust badge.
   2. Buyer capability — history depth, alert capacity, exports, no house ads. The comparison
      itself stays free and complete on the free tier, because a paywalled comparison would
      make the ranking unreadable and the ranking is the product.
   3. Declared advertising — bought inventory that is labelled, sits outside organic ordering,
      and is listed publicly with its price.

   Loaded after seed-orders.js (the guarantee is measured from orders) and seed-commercial.js
   (it extends the plan and entitlement tables rather than duplicating them). */
(function () {
  const S = window.SEED;
  if (!S || !S.cx) return;
  const cx = S.cx, gx = S.gx || {}, DAY = S.DAY, NOW = S.NOW;
  let seed = 0x7ab39f21;
  const R = () => { seed |= 0; seed = (seed + 0x6d2b79f5) | 0; let t = Math.imul(seed ^ (seed >>> 15), 1 | seed); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
  const int = (a, b) => Math.floor(R() * (b - a + 1)) + a;
  const pick = (a) => a[Math.floor(R() * a.length)];
  const r2 = (n) => Math.round(n * 100) / 100;
  const median = (a) => { if (!a.length) return null; const s = a.slice().sort((x, y) => x - y); return s.length % 2 ? s[(s.length - 1) / 2] : r2((s[s.length / 2 - 1] + s[s.length / 2]) / 2); };
  const pct = (n, d) => (d ? Math.round((n / d) * 1000) / 10 : null);

  /* ================= 1. new entitlement features the add-ons act on ================= */
  const newFeatures = [
    { key: 'delivery.promise_markets', group: 'Delivery', name: 'Delivery promise markets', why: 'How many markets you can publish an enforceable delivery window in.', unit: 'markets' },
    { key: 'feed.interval_hours', group: 'Feeds', name: 'Feed import interval', why: 'How often we re-read your feed. Lower is fresher, and stale prices are the single largest source of buyer complaints.', unit: 'hours' },
    { key: 'reviews.invites_month', group: 'Reviews', name: 'Review invitations', why: 'Post-purchase invitations sent by us on your behalf, to buyers we can verify.', unit: 'per month' },
    { key: 'alerts.competitors', group: 'Analytics', name: 'Competitor price alerts', why: 'Instant notice when a named competitor undercuts you on a product you both list.', unit: 'competitors' },
    { key: 'qna.sla_hours', group: 'Reviews', name: 'Q&A answering SLA', why: 'We chase your unanswered buyer questions and escalate at this age.', unit: 'hours' },
    { key: 'stock.webhook', group: 'Feeds', name: 'Stock webhook', why: 'Push stock changes to us instead of waiting for the next import.' },
    { key: 'markets.capability', group: 'Profile', name: 'Market packs', why: 'Localised profile, market analytics and campaign booking per region. Never listing or rank — those are free everywhere.', unit: 'regions' },
    { key: 'label.verification', group: 'Profile', name: 'Label verification service', why: 'We photograph and verify amount-per-serving for your catalogue so your listings can show price per gram of active.', unit: 'products / month' },
  ];
  newFeatures.forEach((f) => { if (!cx.features.some((x) => x.key === f.key)) cx.features.push(f); });
  if (cx.featureGroups.indexOf('Delivery') < 0) cx.featureGroups.splice(2, 0, 'Delivery');

  /* plan baselines for the new features — an add-on is a delta on top of these */
  const planBase = {
    FREE: { 'delivery.promise_markets': 0, 'feed.interval_hours': 24, 'reviews.invites_month': 0, 'alerts.competitors': 0, 'qna.sla_hours': 0, 'stock.webhook': false, 'markets.capability': 0, 'label.verification': 0 },
    PRO: { 'delivery.promise_markets': 1, 'feed.interval_hours': 6, 'reviews.invites_month': 500, 'alerts.competitors': 5, 'qna.sla_hours': 72, 'stock.webhook': false, 'markets.capability': 1, 'label.verification': 0 },
    GROWTH: { 'delivery.promise_markets': 3, 'feed.interval_hours': 3, 'reviews.invites_month': 2000, 'alerts.competitors': 15, 'qna.sla_hours': 48, 'stock.webhook': true, 'markets.capability': 2, 'label.verification': 10 },
    ENTERPRISE: { 'delivery.promise_markets': 12, 'feed.interval_hours': 1, 'reviews.invites_month': 10000, 'alerts.competitors': 50, 'qna.sla_hours': 24, 'stock.webhook': true, 'markets.capability': 8, 'label.verification': 50 },
  };
  Object.keys(planBase).forEach((plan) => {
    Object.keys(planBase[plan]).forEach((k) => {
      if (cx.entitlements.some((e) => e.plan === plan && e.feature === k)) return;
      const v = planBase[plan][k];
      cx.entitlements.push({
        feature: k, plan: plan,
        enabled: v !== false && v !== 0,
        limit: typeof v === 'number' ? v : null,
        value: typeof v === 'string' ? v : null,
      });
    });
  });
  /* the feed interval is now a number on a record, so merchant copy can interpolate it
     instead of asserting "imported every six hours" in prose */
  cx.feedIntervalNote = 'Import interval is an entitlement, not a promise in copy: Free reads your feed daily, Pro every 6 hours, Growth every 3, Enterprise hourly, and the hourly add-on lifts any plan to 1.';

  /* ================= 2. merchant add-ons ================= */
  cx.addonGroups = ['Delivery', 'Reach', 'Data', 'Reviews', 'Campaigns', 'Operations'];
  cx.addons = [
    { key: 'delivery_promise', name: 'Delivery promise', group: 'Delivery', price: 39, per: 'market / month', unit: 'markets', max: 12,
      delta: { 'delivery.promise_markets': 1 }, minPlan: 'PRO',
      why: 'Publish an enforceable delivery window in one more market, with the claim process behind it.',
      gate: 'Eligibility is measured, not bought: 8 delivered orders in that market, 92 % on-time, and a p90 inside the window you want to promise.',
      cancel: 'Cancel any time; the badge stays live until the end of the paid month and open claims are still honoured.' },
    { key: 'delivery_promise_48', name: 'Delivery promise 48 h', group: 'Delivery', price: 89, per: 'market / month', unit: 'markets', max: 6,
      delta: { 'delivery.promise_markets': 1 }, minPlan: 'GROWTH',
      why: 'A two-day promise in a market where your measured p90 already clears it.',
      gate: '95 % on-time, p90 ≤ 2.0 days, dispute rate under 2 %, and a deposit that covers 90 days of expected claims.',
      cancel: 'Cancel any time; deposit is returned 60 days after the last promised order is delivered.' },
    { key: 'feed_hourly', name: 'Hourly feed import', group: 'Data', price: 59, per: 'month', unit: null,
      delta: { 'feed.interval_hours': 1 }, minPlan: 'PRO',
      why: 'Your prices go stale the moment you change them. Hourly import is the difference between a correct offer row and a buyer complaint.',
      gate: 'Feed must pass validation on 95 % of imports — we will not poll a broken feed more often.',
      cancel: 'Monthly, no notice.' },
    { key: 'stock_webhook', name: 'Stock webhook', group: 'Data', price: 45, per: 'month', unit: null,
      delta: { 'stock.webhook': true }, minPlan: 'PRO',
      why: 'Push stock changes as they happen. Out-of-stock clicks are the fastest way to lose a buyer who trusted the listing.',
      gate: 'Endpoint must answer our signed test call.',
      cancel: 'Monthly, no notice.' },
    { key: 'history_365', name: 'Analytics history to 365 days', group: 'Data', price: 39, per: 'month', unit: null,
      delta: { 'analytics.history_days': 365 }, minPlan: 'PRO',
      why: 'A year of your own price position, clicks and review trend — enough to see a season.',
      gate: null, cancel: 'Monthly. History is retained regardless; the add-on only opens the window.' },
    { key: 'competitor_alerts', name: 'Competitor price alerts', group: 'Data', price: 39, per: 'month', unit: 'competitors',
      delta: { 'alerts.competitors': 20 }, minPlan: 'PRO',
      why: 'Instant notice when a named competitor undercuts you on a shared product, with the offer record attached.',
      gate: 'Only shops that list the same product. We do not sell watchlists of shops you do not compete with.',
      cancel: 'Monthly, no notice.' },
    { key: 'review_invites', name: 'Review invitations', group: 'Reviews', price: 49, per: 'month', unit: 'per 2,000',
      delta: { 'reviews.invites_month': 2000 }, minPlan: 'PRO',
      why: 'We invite buyers we can verify, after delivery, once. Verified reviews carry full weight in the average — unverified ones do not.',
      gate: 'Invitations are sent by us, to buyers of orders we can match. You never see an address, and you cannot choose who is asked.',
      cancel: 'Monthly. Reviews already collected stay published.' },
    { key: 'qna_concierge', name: 'Q&A concierge', group: 'Reviews', price: 69, per: 'month', unit: null,
      delta: { 'qna.sla_hours': 24 }, minPlan: 'PRO',
      why: 'Unanswered buyer questions on your profile get chased, escalated at 24 hours and summarised weekly.',
      gate: null, cancel: 'Monthly, no notice.' },
    { key: 'label_verification', name: 'Label verification', group: 'Reviews', price: 129, per: 'month', unit: 'per 20 products',
      delta: { 'label.verification': 20 }, minPlan: 'PRO',
      why: 'We photograph and verify amount-per-serving for your catalogue, so your listings can be compared on price per gram of active instead of price per tub.',
      gate: 'We publish what the panel says, including when it disagrees with your feed. That is the point of it.',
      cancel: 'Monthly. Verified dosing records stay published.' },
    { key: 'market_pack', name: 'Market pack', group: 'Reach', price: 49, per: 'region / month', unit: 'regions', max: 8,
      delta: { 'markets.capability': 1 }, minPlan: 'PRO',
      why: 'Localised profile, market analytics, market price position and campaign booking in one region.',
      gate: 'Listing and ComparoRank position are free in all ' + (S.countries || []).length + ' markets and are not part of this. A pack buys work, not placement.',
      cancel: 'Monthly, no notice.' },
    { key: 'deal_slots', name: 'Extra deal slots', group: 'Campaigns', price: 29, per: 'month', unit: 'per 5 slots',
      delta: { 'deal.active_limit': 5 }, minPlan: 'FREE',
      why: 'Run five more coupons or price drops at once.',
      gate: 'Slots do not affect deal ranking; the deal hub orders by confidence and total price.',
      cancel: 'Monthly, no notice.' },
    { key: 'campaign_share', name: 'Campaign inventory share', group: 'Campaigns', price: 149, per: 'month', unit: '+10 %',
      delta: { 'campaign.inventory_share': 10 }, minPlan: 'GROWTH',
      why: 'Reserve more of the sponsored inventory in the markets you sell in.',
      gate: 'Sponsored inventory is capped per surface and always labelled. A larger share never changes organic order.',
      cancel: 'Monthly; booked campaigns run to their end date.' },
    { key: 'team_seats', name: 'Extra team seats', group: 'Operations', price: 19, per: 'month', unit: 'per 3 seats',
      delta: { 'team.member_limit': 3 }, minPlan: 'FREE',
      why: 'Three more people with their own login and audit trail.',
      gate: null, cancel: 'Monthly, no notice.' },
    { key: 'sla_support', name: 'Support SLA 4 h', group: 'Operations', price: 99, per: 'month', unit: null,
      delta: { 'support.sla': '4 h' }, minPlan: 'PRO',
      why: 'Four working hours to first human response, on data disputes as well as billing.',
      gate: null, cancel: 'Monthly, no notice.' },
  ];
  cx.addonNote = 'Add-ons are priced work. None of them can be bought: ComparoRank position, review weight or visibility, a trust badge, a delivery promise you have not measured up to, or a place in an editorial comparison.';
  /* an add-on stack that costs more than the next plan is a pricing failure, so it is checked */
  cx.upgradeAdvice = 'If your active add-ons cost more than the difference to the next plan, the console says so and offers the upgrade instead. We would rather move you up a plan than let an add-on stack quietly overtake it.';

  /* seeded active add-ons per merchant account */
  const addonFor = (key) => cx.addons.find((a) => a.key === key);
  cx.merchantAddons = [];
  let aid = 1;
  (cx.accounts || []).forEach((acc) => {
    if (acc.plan === 'FREE') { if (R() < 0.35) cx.merchantAddons.push({ id: 'ADD-' + (aid++), merchantId: acc.merchantId, key: 'deal_slots', qty: 1, since: NOW - int(20, 300) * DAY, price: 29, status: 'active' }); return; }
    const pool = acc.plan === 'ENTERPRISE' ? ['delivery_promise', 'delivery_promise_48', 'feed_hourly', 'label_verification', 'market_pack', 'competitor_alerts', 'review_invites', 'sla_support']
      : acc.plan === 'GROWTH' ? ['delivery_promise', 'feed_hourly', 'review_invites', 'market_pack', 'competitor_alerts', 'deal_slots']
      : ['delivery_promise', 'history_365', 'deal_slots', 'review_invites'];
    const n = int(1, Math.min(4, pool.length));
    const taken = {};
    for (let i = 0; i < n; i++) {
      const key = pool[int(0, pool.length - 1)];
      if (taken[key]) continue;
      taken[key] = 1;
      const ad = addonFor(key);
      /* a shop that buys the delivery promise buys it for the markets it can win in, so the
         seeded quantity is three rather than one — eligibility then decides how many go live */
      const qty = ad && ad.unit ? (key.indexOf('delivery_promise') === 0 ? 3 : int(1, Math.min(3, ad.max || 3))) : 1;
      cx.merchantAddons.push({ id: 'ADD-' + (aid++), merchantId: acc.merchantId, key: key, qty: qty, since: NOW - int(10, 400) * DAY, price: (ad ? ad.price : 0) * qty, status: R() < 0.06 ? 'cancelling' : 'active' });
    }
  });
  cx.addonMrr = cx.merchantAddons.filter((a) => a.status === 'active').reduce((a, b) => a + b.price, 0);

  /* ================= 3. buyer tiers ================= */
  cx.userFeatureGroups = ['Comparison', 'Alerts', 'Community', 'Data', 'Experience'];
  cx.userFeatures = [
    { key: 'compare.full', group: 'Comparison', name: 'Full comparison, every shop, every market', why: 'The ranking, the totals, the trust scores and the dosing. This is never gated — a paywalled comparison is a rigged comparison.' },
    { key: 'history.days', group: 'Comparison', name: 'Price history depth', unit: 'days', why: 'How far back the chart and the fake-discount check can read.' },
    { key: 'basket.optimiser', group: 'Comparison', name: 'Basket optimiser', why: 'Split a multi-product basket across shops including shipping thresholds and coupons.' },
    { key: 'market.watch', group: 'Comparison', name: 'Cross-market comparison', why: 'Compare the same product across several delivery markets at once, with duties applied.' },
    { key: 'alerts.count', group: 'Alerts', name: 'Price alerts', unit: 'alerts', why: 'How many products you can watch.' },
    { key: 'alerts.instant', group: 'Alerts', name: 'Instant alerts', why: 'Fire on the price change rather than in the next digest.' },
    { key: 'alerts.stock', group: 'Alerts', name: 'Back-in-stock alerts', why: 'Watch an offer, not just a price.' },
    { key: 'community.groups', group: 'Community', name: 'Create groups', unit: 'groups', why: 'Run your own moderated group and its live room.' },
    { key: 'community.plus_rooms', group: 'Community', name: 'Members-only rooms', why: 'Rooms that ask for a paid account to keep the noise and the sellers out.' },
    { key: 'community.early_deals', group: 'Community', name: 'Early community deals', unit: 'minutes', why: 'Community-submitted deals ahead of the public hub. Never applies to shop-submitted deals — those go public immediately.' },
    { key: 'data.export', group: 'Data', name: 'Data export', why: 'Your watchlist, alerts, contributions and the price series behind them, as CSV.' },
    { key: 'data.api', group: 'Data', name: 'Personal API token', unit: 'req / day', why: 'Read your own watchlist and the public price series programmatically.' },
    { key: 'exp.no_house_ads', group: 'Experience', name: 'No house advertising', why: 'Declared sponsored placements still appear, labelled — hiding them would break the disclosure. What goes away is our own promotion.' },
    { key: 'exp.claim_assist', group: 'Experience', name: 'Delivery claim assist', why: 'We file and chase a delivery-promise claim for you, with the order record attached.' },
    { key: 'exp.support', group: 'Experience', name: 'Support response', unit: 'hours', why: 'First human response on a data dispute.' },
  ];
  cx.userTiers = [
    { key: 'FREE', name: 'Free', price: { month: 0, year: 0 }, order: 1, blurb: 'The whole comparison, permanently. Most people never need more.',
      ent: { 'compare.full': true, 'history.days': 90, 'basket.optimiser': true, 'market.watch': false, 'alerts.count': 3, 'alerts.instant': false, 'alerts.stock': true, 'community.groups': 0, 'community.plus_rooms': false, 'community.early_deals': 0, 'data.export': false, 'data.api': 0, 'exp.no_house_ads': false, 'exp.claim_assist': false, 'exp.support': 72 } },
    { key: 'PLUS', name: 'Plus', price: { month: 3.9, year: 39 }, order: 2, blurb: 'For people who watch prices and contribute data.',
      ent: { 'compare.full': true, 'history.days': 365, 'basket.optimiser': true, 'market.watch': true, 'alerts.count': 50, 'alerts.instant': true, 'alerts.stock': true, 'community.groups': 1, 'community.plus_rooms': true, 'community.early_deals': 30, 'data.export': true, 'data.api': 0, 'exp.no_house_ads': true, 'exp.claim_assist': true, 'exp.support': 24 } },
    { key: 'PRO', name: 'Pro', price: { month: 8.9, year: 89 }, order: 3, blurb: 'For coaches, shops\u2019 competitors and anybody who works with this data.',
      ent: { 'compare.full': true, 'history.days': 1095, 'basket.optimiser': true, 'market.watch': true, 'alerts.count': 500, 'alerts.instant': true, 'alerts.stock': true, 'community.groups': 5, 'community.plus_rooms': true, 'community.early_deals': 30, 'data.export': true, 'data.api': 5000, 'exp.no_house_ads': true, 'exp.claim_assist': true, 'exp.support': 8 } },
  ];
  cx.userTierNote = 'Free is not a trial. Ranking, totals, trust scores, dosing, reviews and the forum are complete without paying, and no paid tier can see a different order of offers than a free account sees.';
  cx.userEarned = 'Points earn the same entitlements: 1,200 XP is 30 days of Plus. Roughly a fifth of Plus accounts have never paid money for it.';
  /* consumer subscription base, derived from the member base rather than typed */
  const memberBase = (S.users || []).length * 620;
  cx.userSubs = cx.userTiers.map((t, i) => {
    const share = [0.905, 0.074, 0.021][i];
    const count = Math.round(memberBase * share);
    return {
      tier: t.key, count: count,
      paid: t.key === 'FREE' ? 0 : Math.round(count * 0.79),
      earned: t.key === 'FREE' ? 0 : count - Math.round(count * 0.79),
      mrr: t.key === 'FREE' ? 0 : r2(Math.round(count * 0.79) * t.price.month),
      churn: t.key === 'FREE' ? null : [0, 4.1, 2.6][i],
      yearlyShare: t.key === 'FREE' ? null : [0, 38, 51][i],
    };
  });
  cx.userMrr = r2(cx.userSubs.reduce((a, b) => a + b.mrr, 0));

  /* ================= 4. delivery guarantee ================= */
  const gp = {};
  S.gp = gp;
  gp.what = 'A delivery promise is a shop\u2019s commitment to a delivery window, published by us only when our own order records show the shop already meets it, and backed by a deposit the shop puts up in advance.';
  gp.whatNot = 'We are not the seller. We never hold the price of the goods, we cannot refund a purchase, and we do not insure the parcel. What the guarantee covers is the promise we published: if the window is missed, the buyer gets the shipping paid back from the shop\u2019s deposit and the miss is counted publicly against the shop.';
  gp.tiers = [
    { key: 'measured', name: 'Measured delivery', price: 0, window: 'Your measured p90', deposit: 0, claim: null,
      desc: 'Free for every shop. We publish the median and p90 we measured from orders, or nothing at all below 8 delivered orders in that market.' },
    { key: 'promise', name: 'Delivery promise', price: 39, window: 'p90 rounded up', deposit: 400, claim: 'Shipping refunded',
      desc: 'An enforceable window. Miss it and the buyer gets the shipping cost back from your deposit within 5 working days.' },
    { key: 'promise48', name: 'Promise 48 h', price: 89, window: '2 working days', deposit: 900, claim: 'Shipping refunded + €5 credit',
      desc: 'For shops whose p90 already clears two days. Highest-visibility badge we publish.' },
  ];
  gp.eligibility = [
    { key: 'sample', label: '8 delivered orders in the market', why: 'Below that, a median is noise. This is the same threshold the public shop page uses.' },
    { key: 'ontime', label: '92 % on-time over the last 90 days', why: 'Measured against the window the shop wants to promise, not against its own estimate.' },
    { key: 'p90', label: 'p90 inside the promised window', why: 'A promise that only the median meets is a promise that fails one order in four.' },
    { key: 'disputes', label: 'Dispute rate under 2 %', why: 'A shop with an open dispute pattern cannot buy its way to a trust signal.' },
    { key: 'feed', label: 'Feed fresh within the plan interval', why: 'A stale feed means the offer that carries the badge may not exist.' },
    { key: 'deposit', label: 'Deposit covering 90 days of expected claims', why: 'The claim is paid from the deposit, not from our balance sheet and not from the next buyer.' },
  ];

  /* measured performance per merchant × market, from orders */
  const delivered = (S.orders || []).filter((o) => o.deliveredAt);
  gp.measured = [];
  (S.merchants || []).forEach((m) => {
    (m.shipsTo || []).forEach((iso) => {
      const rows = delivered.filter((o) => o.merchantId === m.id && o.market === iso);
      if (!rows.length) return;
      const days = rows.map((o) => o.actualDays);
      const onTime = rows.filter((o) => o.actualDays <= o.promisedDays).length;
      const p90i = Math.max(0, Math.ceil(days.length * 0.9) - 1);
      gp.measured.push({
        merchantId: m.id, market: iso, n: rows.length,
        median: median(days), p90: days.slice().sort((a, b) => a - b)[p90i],
        onTime: pct(onTime, rows.length),
        enough: rows.length >= (S.orderMeta ? S.orderMeta.minSample : 8),
      });
    });
  });
  /* enrolments are derived from that measurement plus a paid add-on, never from a flag */
  /* Enrolment is the primary record: it is derived from the measurement, and the billed add-on
     line is then generated from it. That ordering is deliberate — if the invoice were the
     source, a shop could be billed for a badge it never earned, or carry a badge nobody is
     billed for. Neither can happen when one is computed from the other. */
  const eligible = gp.measured.filter((x) => x.enough && x.onTime >= 92);
  gp.enrolments = [];
  (S.merchants || []).filter((m) => m.verified && (m.partner || m.tier !== 'FREE')).forEach((m) => {
    const since = NOW - int(40, 300) * DAY;
    const rows = eligible.filter((x) => x.merchantId === m.id)
      .sort((a, b) => (b.onTime - a.onTime) || (b.n - a.n))
      .slice(0, m.partner ? 3 : 2);
    rows.forEach((row, i) => {
      /* the badge follows the measurement, not the invoice: a shop that wanted the 48-hour
         tier but whose p90 does not clear two days is published at the window it earned */
      const wants48 = m.partner && i === 0;
      const qualifies48 = row.p90 <= 2 && row.onTime >= 95;
      const tier = wants48 && qualifies48 ? 'promise48' : 'promise';
      gp.enrolments.push({
        merchantId: m.id, market: row.market, tier: tier, paidTier: wants48 ? 'promise48' : 'promise',
        downgraded: wants48 && !qualifies48,
        window: tier === 'promise48' ? 2 : Math.ceil(row.p90),
        since: since, measuredOnTime: row.onTime, measuredP90: row.p90, sample: row.n,
        price: wants48 ? 89 : 39, status: 'live',
      });
    });
  });
  /* one billed line per shop, quantity equal to the markets actually live */
  cx.merchantAddons = cx.merchantAddons.filter((a) => a.key.indexOf('delivery_promise') !== 0);
  Array.from(new Set(gp.enrolments.map((e) => e.merchantId))).forEach((mid) => {
    const mine = gp.enrolments.filter((e) => e.merchantId === mid);
    const has48 = mine.some((e) => e.paidTier === 'promise48');
    cx.merchantAddons.push({
      id: 'ADD-' + (aid++), merchantId: mid,
      key: has48 ? 'delivery_promise_48' : 'delivery_promise',
      qty: mine.length, since: mine[0].since,
      price: mine.reduce((a, e) => a + e.price, 0), status: 'active',
    });
  });
  cx.addonMrr = cx.merchantAddons.filter((a) => a.status === 'active').reduce((a, b) => a + b.price, 0);
  gp.deposits = Array.from(new Set(gp.enrolments.map((e) => e.merchantId))).map((mid) => {
    const mine = gp.enrolments.filter((e) => e.merchantId === mid);
    const required = mine.reduce((a, e) => a + (e.tier === 'promise48' ? 900 : 400), 0);
    return { merchantId: mid, required: required, held: required + int(0, 3) * 100, markets: mine.length, lastTopUp: NOW - int(2, 120) * DAY };
  });

  /* claims come from orders that actually missed a promised window in an enrolled market */
  const claimReasons = [
    'Arrived two days after the promised window',
    'Tracking stopped for four days, delivered late',
    'Dispatched after the stated cutoff, missed the window',
    'Carrier attempted delivery once and returned the parcel to the depot',
    'Delivered to a pickup point without notice, collected late',
  ];
  gp.claims = [];
  let cid = 1;
  gp.enrolments.forEach((e) => {
    /* a claim can only exist for an order placed while the promise was live */
    const late = delivered.filter((o) => o.merchantId === e.merchantId && o.market === e.market && o.actualDays > e.window && o.deliveredAt > e.since);
    late.slice(0, 4).forEach((o) => {
      const opened = o.deliveredAt + int(2, 40) * 3600000;
      /* the four states a claim can be in are all present in the seed, in rotation, because a
         claims table that only ever shows "paid" is a marketing page rather than a record */
      const status = ['paid', 'open', 'rejected', 'upheld'][cid % 4];
      gp.claims.push({
        id: 'CLM-' + (4000 + cid++), orderId: o.id, merchantId: e.merchantId, market: e.market,
        userId: o.userId, promised: e.window, actual: o.actualDays,
        lateBy: r2(o.actualDays - e.window), reason: pick(claimReasons),
        opened: opened, status: status,
        resolvedAt: status === 'open' ? null : opened + int(6, 96) * 3600000,
        /* shipping is what the guarantee returns. When the buyer paid nothing for shipping
           there is nothing to refund, so the claim pays a flat credit instead of pretending. */
        payout: status === 'paid' ? r2(o.shipping || 0) : 0,
        credit: status === 'paid' ? (o.shipping ? (e.tier === 'promise48' ? 5 : 0) : 5) : 0,
        evidence: o.tracking ? 'Carrier tracking ' + o.tracking : 'Order record',
        rejectReason: status === 'rejected' ? pick(['Delivery attempted inside the window, buyer was not present', 'Address correction requested by the buyer after dispatch', 'Market-wide carrier disruption declared by the carrier']) : null,
      });
    });
  });
  gp.stats = (() => {
    const c = gp.claims, resolved = c.filter((x) => x.status !== 'open');
    const hours = resolved.filter((x) => x.resolvedAt).map((x) => (x.resolvedAt - x.opened) / 3600000);
    const promised = (S.orders || []).filter((o) => o.deliveredAt && gp.enrolments.some((e) => e.merchantId === o.merchantId && e.market === o.market));
    return {
      markets: Array.from(new Set(gp.enrolments.map((e) => e.market))).length,
      shops: Array.from(new Set(gp.enrolments.map((e) => e.merchantId))).length,
      enrolments: gp.enrolments.length,
      promisedOrders: promised.length,
      claims: c.length, open: c.filter((x) => x.status === 'open').length,
      upheldShare: pct(c.filter((x) => x.status === 'paid' || x.status === 'upheld').length, resolved.length),
      medianHours: median(hours) ? Math.round(median(hours)) : null,
      paid: r2(c.reduce((a, b) => a + b.payout + b.credit, 0)),
      claimRate: pct(c.length, promised.length),
      depositHeld: gp.deposits.reduce((a, b) => a + b.held, 0),
    };
  })();
  gp.process = [
    { step: 'You file', detail: 'Open the order on Comparo and press "parcel was late". Nothing to write — we already hold the promise, the dispatch and the delivery date.', sla: 'under a minute' },
    { step: 'We check the record', detail: 'The promised window, the carrier events and the delivery date are compared automatically. Most claims resolve without a human.', sla: '2 hours' },
    { step: 'Shop can contest', detail: 'The shop sees the same record and can contest with carrier evidence. Buyer-caused delays are the usual successful contest.', sla: '48 hours' },
    { step: 'Paid from the deposit', detail: 'Shipping is returned to your card by the shop, or from the deposit if the shop does not act. The miss is counted on the public shop page either way.', sla: '5 working days' },
  ];
  gp.buyerSide = [
    'You do not need a paid account to claim. The guarantee belongs to the promise, not to a subscription.',
    'Plus files and chases it for you, which matters only if the shop contests.',
    'A claim never affects your standing, your points or your ability to review.',
    'We publish the claim rate per shop per market whether the shop likes it or not.',
  ];

  /* ================= 5. advertising inventory ================= */
  /* Audience is computed, and each surface names the record it is computed from. The first
     version of this keyed placement.pageType straight into gx.pageTypeRevenue, which holds six
     page types where the inventory names eleven — so six placements silently fell through to a
     share of the grand total and printed the same figure under a sentence promising a measured
     one. Worse, it overwrote a correct label ('18.4k subscribers') with a wrong number. Every
     surface now resolves explicitly, and a surface we do not measure says so. */
  const entrOf = (t) => ((gx.pageTypeRevenue || []).find((r) => r.type === t) || {}).entrances || 0;
  const sumOf = (arr, k) => (arr || []).reduce((a, b) => a + (b[k] || 0), 0);
  const totalEntr = (gx.pageTypeRevenue || []).reduce((a, b) => a + b.entrances, 0) || 1;
  const biggestSend = Math.max.apply(null, [0].concat((gx.newsletters || []).map((n) => n.sent || 0)));
  cx.surfaceSessions = {
    Home: { sessions: Math.round(totalEntr * 0.22), source: 'Measured entrances across all page types; 22 % of sessions pass the homepage' },
    Product: { sessions: entrOf('Product'), source: 'Measured entrances on product pages' },
    Comparison: { sessions: entrOf('Comparison'), source: 'Measured entrances on comparison pages' },
    Shop: { sessions: entrOf('Shop'), source: 'Measured entrances on shop profiles' },
    Deals: { sessions: entrOf('Deal'), source: 'Measured entrances on the deal hub' },
    Country: { sessions: entrOf('Country'), source: 'Measured entrances on market hubs' },
    Market: { sessions: entrOf('Country'), source: 'Measured entrances on market hubs' },
    Category: { sessions: Math.round(entrOf('Product') * 0.43), source: 'Derived from product-page entrances: 43 % of them arrive through a category page' },
    Guide: { sessions: entrOf('Guide'), source: 'Measured entrances on guides' },
    Forum: { sessions: sumOf(S.forumThreads, 'views'), source: 'Summed views on forum topics' },
    Ingredient: { sessions: Math.round(sumOf(S.products, 'views') * 0.12), source: 'Derived from product views: 12 % of product sessions open an ingredient page' },
    Newsletter: { sessions: biggestSend, source: 'The largest newsletter send — the subscriber base it actually went to' },
    Research: { sessions: 0, source: 'Research sponsorship is bought for citation and backlink reach, not for sessions, and we hold no session record for it — so there is no number here to quote' },
  };
  cx.adFormats = [
    { key: 'featured_shop', name: 'Featured shop card', spec: 'Logo, one line, delivery window. No price claims we cannot verify against the feed.', surfaces: ['Home', 'Category', 'Market hub'] },
    { key: 'featured_deal', name: 'Featured deal', spec: 'Must be a live deal record with a real code and expiry. Expired deals are pulled automatically.', surfaces: ['Home', 'Deal hub', 'Newsletter'] },
    { key: 'native_compare', name: 'Native comparison card', spec: 'Sits after the organic table, never inside it. Same data columns as an organic row.', surfaces: ['Product', 'Comparison'] },
    { key: 'section_sponsor', name: 'Forum section sponsor', spec: 'One line above the topic list. No posts, no messages, no presence in rooms.', surfaces: ['Forum section'] },
    { key: 'ingredient_sponsor', name: 'Ingredient page sponsor', spec: 'Brand-level only, beside the price-per-gram ranking it cannot influence.', surfaces: ['Ingredient'] },
    { key: 'market_banner', name: 'Market hub banner', spec: 'Market-targeted, shipping claims checked against the shop\u2019s lanes.', surfaces: ['Market hub'] },
    { key: 'research_sponsor', name: 'Research report sponsor', spec: 'Named support of an original dataset. No input into the questions or the findings.', surfaces: ['Research'] },
    { key: 'newsletter_slot', name: 'Newsletter slot', spec: 'One slot per send, labelled in the subject line as well as the body.', surfaces: ['Newsletter'] },
  ];
  const extraPlacements = [
    { key: 'forum_section', name: 'Forum section sponsor', pageType: 'Forum', capacity: 1, basePrice: 900, model: 'Fixed placement', minSpend: 450, format: 'section_sponsor', share: 0.34 },
    { key: 'ingredient_page', name: 'Ingredient page sponsor', pageType: 'Ingredient', capacity: 2, basePrice: 1100, model: 'CPM', minSpend: 550, format: 'ingredient_sponsor', share: 0.62 },
    { key: 'market_hub', name: 'Market hub banner', pageType: 'Market', capacity: 2, basePrice: 1400, model: 'CPM', minSpend: 700, format: 'market_banner', share: 0.71 },
    { key: 'compare_native', name: 'Comparison native card', pageType: 'Comparison', capacity: 1, basePrice: 1900, model: 'CPC', minSpend: 900, format: 'native_compare', share: 0.48 },
  ];
  extraPlacements.forEach((p) => { if (!cx.placements.some((x) => x.key === p.key)) cx.placements.push(p); });
  /* the eight original placements carried no format and no reach share; both are declared here
     rather than defaulted, because a default made every one of them a "featured shop card" */
  const PLACEMENT_META = {
    home_merchant: { format: 'featured_shop', share: 0.55 },
    home_deal: { format: 'featured_deal', share: 0.4 },
    category_merchant: { format: 'featured_shop', share: 0.46 },
    country_merchant: { format: 'featured_shop', share: 0.58 },
    product_sponsor: { format: 'native_compare', share: 0.31 },
    deal_hub: { format: 'featured_deal', share: 0.67 },
    newsletter: { format: 'newsletter_slot', share: 1 },
    research: { format: 'research_sponsor', share: 0.9 },
  };
  cx.placements.forEach((p) => {
    const meta = PLACEMENT_META[p.key] || {};
    /* the newsletter slot was priced at €1,800 against an audience nobody had computed. Now that
       the audience is the real send size, the price has to be one a media buyer would accept:
       €520 on 7.2k subscribers is about €72 CPM, which is what a niche list goes for. */
    if (p.key === 'newsletter') { p.basePrice = 520; p.minSpend = 520; }
    p.format = p.format || meta.format || 'featured_shop';
    p.share = p.share !== undefined ? p.share : (meta.share !== undefined ? meta.share : 0.4);
    const surface = cx.surfaceSessions[p.pageType];
    if (!surface || !surface.sessions) {
      p.audienceSessions = 0;
      p.audience = 'not measured yet';
      p.audienceSource = 'We hold no session record for ' + p.pageType + ' pages yet, so this slot is booked on request rather than on a number.';
      return;
    }
    p.audienceSessions = Math.round(surface.sessions * p.share);
    p.audience = (p.audienceSessions >= 1000 ? Math.round(p.audienceSessions / 100) / 10 + 'k' : p.audienceSessions)
      + (p.pageType === 'Newsletter' ? ' subscribers' : ' monthly sessions');
    p.audienceSource = surface.source + ' — ' + Math.round(p.share * 100) + ' % of them reach this slot.';
  });
  cx.adTargeting = [
    { key: 'market', name: 'Delivery market', values: 'Any of the ' + (S.countries || []).length + ' markets', why: 'A shop that cannot ship there should not be advertising there, and we check the lane.' },
    { key: 'category', name: 'Category', values: (S.categories || []).length + ' categories', why: 'Category intent is the strongest signal we hold.' },
    { key: 'ingredient', name: 'Ingredient', values: (S.ingredients || []).length + ' ingredients', why: 'For brands whose argument is a molecule, not a product.' },
    { key: 'price_band', name: 'Price band', values: 'Quartile of the category', why: 'Value shops and premium brands rarely want the same session.' },
    { key: 'device', name: 'Device', values: 'Mobile / desktop', why: 'Checkout completion differs enough to price separately.' },
    { key: 'intent', name: 'Session intent', values: 'Research / compare / buy', why: 'Derived from the pages in the session, never from anything bought from a third party.' },
  ];
  cx.adNotTargeting = ['Individual identity', 'Health status or inferred condition', 'Purchase history of a named person', 'Anything bought from a data broker — we buy no audience data at all'];
  cx.advertiserTypes = [
    { key: 'shop', name: 'Shops', note: 'Already in the comparison. Advertising buys reach, never a better organic position.' },
    { key: 'brand', name: 'Brands', note: 'Do not sell to buyers here either. Brand slots point at the brand page or a comparison, not a checkout.' },
    { key: 'lab', name: 'Testing labs & certifiers', note: 'Relevant to a category that argues about purity. Claims must be verifiable.' },
    { key: 'gym', name: 'Gyms, coaches, events', note: 'Market-targeted only. No health claims, no prescriptions.' },
  ];
  cx.adPolicy = {
    labelled: 'Every bought placement carries the word Sponsored, in the same type size as the surrounding content, and a link to what that means.',
    notForSale: [
      'ComparoRank position and organic order',
      'Review visibility, review weight and the published average',
      'The best-value badge and every trust badge',
      'The delivery promise badge — it is measured, and the measurement is not for sale',
      'Live rooms, forum topics and replies',
      'Ask Comparo answers and editorial comparisons',
      'Research findings, including of a report somebody sponsored',
    ],
    caps: 'One sponsored unit per surface per session, capped at 12 % of the visible area on any page.',
    optOut: 'Comparo Plus removes our own promotion. Declared sponsored units stay visible and labelled, because hiding them from paying users would make the disclosure meaningless.',
    review: 'Creative is reviewed against the shop\u2019s own feed: a delivery claim is checked against its lanes, a price claim against its offers. A claim we cannot verify is rejected, and the rejection reason is on the record.',
  };
  cx.adPackages = [
    { key: 'market_launch', name: 'Market launch', price: 3900, duration: '6 weeks', includes: ['Market hub banner', 'Category featured shop', 'Newsletter slot', 'Market digest mention'], for: 'A shop entering one of the 27 markets' },
    { key: 'brand_authority', name: 'Brand authority', price: 5400, duration: '8 weeks', includes: ['Ingredient page sponsor ×3', 'Research report sponsor', 'Comparison native card'], for: 'A brand whose argument is dosing or testing' },
    { key: 'deal_burst', name: 'Deal burst', price: 1600, duration: '10 days', includes: ['Featured deal', 'Deal hub slot', 'Newsletter slot'], for: 'A live promotion with a real code' },
  ];
  cx.adStats = {
    inventory: cx.placements.length,
    formats: cx.adFormats.length,
    monthlyCapacity: cx.placements.reduce((a, b) => a + b.capacity, 0),
    reachSessions: cx.placements.reduce((a, b) => a + b.audienceSessions, 0),
    packages: cx.adPackages.length,
  };

  /* ================= 6. what the whole thing earns ================= */
  cx.revenueMix = [
    { key: 'plans', label: 'Merchant plans', value: (cx.subscriptions || []).filter((s) => s.status === 'Active' || s.status === 'Trialing').reduce((a, b) => a + (b.billingPeriod === 'year' ? b.price / 12 : b.price), 0) },
    { key: 'addons', label: 'Merchant add-ons', value: cx.addonMrr },
    { key: 'guarantee', label: 'Delivery promise', value: gp.enrolments.reduce((a, b) => a + b.price, 0) },
    { key: 'ads', label: 'Advertising', value: (cx.campaigns || []).reduce((a, b) => a + (b.spent || 0), 0) / 3 },
    { key: 'api', label: 'API & data', value: (cx.apiPlans || []).reduce((a, b) => a + (b.price || 0) * (b.key === 'BIZ' ? 6 : b.key === 'SCALE' ? 2 : 0), 0) },
    { key: 'buyers', label: 'Buyer subscriptions', value: cx.userMrr },
  ].map((r) => Object.assign(r, { value: Math.round(r.value) }));
  cx.revenueMixNote = 'Six lines, and the interesting thing is not their order — it is that none of them can buy a ranking position, a review weight or a trust badge. Buyer subscriptions are large because members outnumber shops by three orders of magnitude, not because the comparison is gated: a free account sees exactly the same offer table in exactly the same order.';
})();
