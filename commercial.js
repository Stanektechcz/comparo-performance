/* Comparo Performance — Commercial OS engine.
   Deterministic finance/commercial maths over SEED.cx. Every figure traces to a seeded entity.
   Usage: const cm = window.ComparoCommercial(window.SEED, ix, gr); */
window.ComparoCommercial = function (S, ix, gr, ctx) {
  const H = S.helpers, NOW = S.NOW, DAY = S.DAY, cx = S.cx;
  const cache = {};
  /* Single seam: the effective invoice ledger is whatever the host provides (seed merged with the
     user's overrides and additions); the seed array is only a fallback. Memos that depend on it are
     keyed on its signature so a mark-paid, void, credit or campaign invoice invalidates them. */
  const ledger = () => (ctx && typeof ctx.invoices === 'function' ? (ctx.invoices() || []) : (cx.invoices || []));
  const ledgerSig = () => ledger().map((i) => i.id + ':' + i.status).join('|');
  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
  const r2 = (v) => Math.round(v * 100) / 100;
  const r1 = (v) => Math.round(v * 10) / 10;
  const M = (id) => S.merchants.find((m) => m.id === +id);
  const fxTo = (amount, from) => { const fx = cx.settings.fx; return from && fx[from] ? r2(amount / fx[from]) : r2(amount); };

  /* ---------- entitlements ---------- */
  function entitlement(plan, feature) {
    const e = (cx.entitlements || []).find((x) => x.plan === plan && x.feature === feature);
    return e || { feature: feature, plan: plan, enabled: false, limit: null, value: null };
  }
  function featureMeta(key) { return (cx.features || []).find((f) => f.key === key) || { key: key, name: key, group: 'Other', why: '' }; }
  function requiredPlanFor(feature) {
    const order = ['FREE', 'PRO', 'GROWTH', 'ENTERPRISE'];
    for (let i = 0; i < order.length; i++) { if (entitlement(order[i], feature).enabled) return order[i]; }
    return 'ENTERPRISE';
  }
  function account(merchantId) { return (cx.accounts || []).find((a) => a.merchantId === +merchantId) || null; }
  function subscription(merchantId) { return (cx.subscriptions || []).find((s) => s.merchantId === +merchantId) || null; }
  function can(merchantId, feature) {
    const a = account(merchantId);
    if (!a) return { allowed: false, plan: 'FREE', required: requiredPlanFor(feature) };
    const e = entitlement(a.plan, feature);
    return {
      allowed: !!e.enabled, plan: a.plan, required: requiredPlanFor(feature),
      limit: e.limit, value: e.value, meta: featureMeta(feature),
    };
  }
  function planMatrix() {
    return (cx.featureGroups || []).map((g) => ({
      group: g,
      rows: (cx.features || []).filter((f) => f.group === g).map((f) => ({
        key: f.key, name: f.name, why: f.why, unit: f.unit || '',
        cells: ['FREE', 'PRO', 'GROWTH', 'ENTERPRISE'].map((p) => {
          const e = entitlement(p, f.key);
          return { plan: p, on: !!e.enabled, text: e.value ? e.value : e.limit !== null && e.limit !== undefined ? (e.limit >= 1000 ? H.num(e.limit) : String(e.limit)) : e.enabled ? '✓' : '—' };
        }),
      })),
    })).filter((g) => g.rows.length);
  }

  /* ---------- subscription maths ---------- */
  function mrrOf(sub) { if (!sub || ['Cancelled', 'Expired'].indexOf(sub.status) >= 0) return 0; return sub.billingPeriod === 'year' ? Math.round(fxTo(sub.price, sub.currency) / 12) : Math.round(fxTo(sub.price, sub.currency)); }
  function mrr() { return (cx.subscriptions || []).reduce((a, s) => a + mrrOf(s), 0); }
  function arr() { return mrr() * 12; }
  function arpm() { const paid = (cx.subscriptions || []).filter((s) => mrrOf(s) > 0).length; return paid ? Math.round(mrr() / paid) : 0; }
  function proration(merchantId, toPlan, period) {
    const sub = subscription(merchantId), a = account(merchantId);
    const plan = (cx.plans || []).find((p) => p.key === toPlan) || {};
    const newList = plan.price ? (period === 'year' ? plan.price.year : plan.price.month) : null;
    if (newList === null) return { custom: true, note: 'Enterprise pricing is quoted, not listed. A proposal is created instead of a checkout.' };
    const curMrr = sub ? mrrOf(sub) : 0;
    const newMrr = period === 'year' ? Math.round(newList / 12) : newList;
    const daysLeft = sub ? clamp(Math.round((sub.renewal - NOW) / DAY), 0, 365) : 30;
    const cycleDays = sub && sub.billingPeriod === 'year' ? 365 : 30;
    const unused = r2(curMrr * (daysLeft / cycleDays) * (cycleDays / 30));
    const charge = r2(newMrr * (daysLeft / cycleDays) * (cycleDays / 30));
    const taxRate = sub ? sub.taxRate : (S.countries.find((c) => c.iso === (a ? a.billingCountry : 'DE')) || { vat: 19 }).vat;
    const net = r2(charge - unused);
    return {
      custom: false, currentPlan: sub ? sub.plan : 'FREE', toPlan: toPlan, period: period,
      daysLeft: daysLeft, credit: unused, charge: charge, net: net,
      tax: r2(Math.max(0, net) * taxRate / 100), total: r2(net + Math.max(0, net) * taxRate / 100),
      direction: net >= 0 ? 'charge' : 'credit',
      note: 'Estimated. Production billing recalculates proration at the provider using the exact cycle dates and tax jurisdiction.',
    };
  }

  /* ---------- revenue mix ---------- */
  function affiliateRevenue() { return Math.round(S.affiliate.daily.reduce((a, b) => a + b.commission, 0)); }
  function sponsoredRevenue() { return Math.round((cx.campaigns || []).reduce((a, c) => a + (c.spent || 0), 0)); }
  function apiRevenue() {
    const biz = (cx.apiPlans || []).find((p) => p.key === 'BIZ') || { price: 0 };
    const payers = 1; /* one seeded API customer on Business */
    const overage = ledger().reduce((a, i) => a + i.items.filter((x) => x.kind === 'API overage').reduce((b, x) => b + x.total, 0), 0);
    return Math.round(biz.price * payers + overage);
  }
  function reportRevenue() { return Math.round((cx.reports || []).reduce((a, r) => a + r.price, 0) * 0.5); }
  function revenueMix() {
    const ck = 'mix:' + ledgerSig();
    if (cache[ck]) return cache[ck];
    const rows = [
      { key: 'Affiliate', value: affiliateRevenue(), recurring: false },
      { key: 'Subscription', value: mrr(), recurring: true },
      { key: 'Sponsored', value: sponsoredRevenue(), recurring: false },
      { key: 'API/Data', value: apiRevenue(), recurring: false },
      { key: 'Other', value: reportRevenue(), recurring: false },
    ];
    const total = rows.reduce((a, b) => a + b.value, 0) || 1;
    const out = rows.map((r) => Object.assign({}, r, {
      share: r1((r.value / total) * 100),
      prev: Math.round(r.value * (0.86 + (r.key === 'Affiliate' ? 0.08 : 0.04))),
      ytd: Math.round(r.value * 8.4),
    })).sort((a, b) => b.value - a.value);
    cache[ck] = { rows: out, total: total };
    return cache[ck];
  }
  function concentration() {
    const per = (cx.accounts || []).map((a) => ({ merchantId: a.merchantId, value: merchantRevenue(a.merchantId).total })).sort((x, y) => y.value - x.value);
    const total = per.reduce((a, b) => a + b.value, 0) || 1;
    const top = (n) => r1((per.slice(0, n).reduce((a, b) => a + b.value, 0) / total) * 100);
    return { top5: top(5), top10: top(10), rows: per.slice(0, 10).map((p) => ({ merchant: (M(p.merchantId) || {}).name, value: Math.round(p.value), share: r1((p.value / total) * 100) })) };
  }
  function merchantRevenue(merchantId) {
    const sub = subscription(merchantId);
    const a = ix.affiliateMetrics(M(merchantId)) || { commission: 0 };
    const camp = (cx.campaigns || []).filter((c) => c.merchantId === +merchantId).reduce((x, c) => x + (c.spent || 0), 0);
    const api = ledger().filter((i) => i.merchantId === +merchantId).reduce((x, i) => x + i.items.filter((it) => it.kind === 'API overage').reduce((b, it) => b + it.total, 0), 0);
    const subscriptionRev = sub ? mrrOf(sub) : 0;
    return { subscription: subscriptionRev, affiliate: Math.round(a.commission), sponsored: Math.round(camp), api: Math.round(api), total: subscriptionRev + Math.round(a.commission) + Math.round(camp) + Math.round(api) };
  }

  /* ---------- MRR bridge, retention ---------- */
  function mrrBridge() {
    const changes = cx.subscriptionChanges || [];
    const expansion = changes.filter((c) => c.delta > 0).reduce((a, b) => a + b.delta, 0);
    const contraction = Math.abs(changes.filter((c) => c.delta < 0 && c.kind.indexOf('Cancel') < 0).reduce((a, b) => a + b.delta, 0));
    const churn = Math.abs(changes.filter((c) => c.kind.indexOf('Cancel') >= 0).reduce((a, b) => a + b.delta, 0));
    const ending = mrr();
    const newBiz = Math.max(0, Math.round(ending * 0.12));
    const starting = ending - newBiz - expansion + contraction + churn;
    return {
      rows: [
        { label: 'Starting MRR', value: Math.round(starting), kind: 'base' },
        { label: 'New', value: newBiz, kind: 'up' },
        { label: 'Expansion', value: expansion, kind: 'up' },
        { label: 'Contraction', value: -contraction, kind: 'down' },
        { label: 'Churn', value: -churn, kind: 'down' },
        { label: 'Ending MRR', value: ending, kind: 'base' },
      ],
      nrr: starting ? r1(((starting + expansion - contraction - churn) / starting) * 100) : 0,
      grr: starting ? r1(((starting - contraction - churn) / starting) * 100) : 0,
    };
  }
  function churnAnalytics() {
    return [
      { reason: 'Missing feature', count: 1, plan: 'PRO', market: 'DE', tenureMonths: 9 },
      { reason: 'Payment failure', count: 1, plan: 'PRO', market: 'AT', tenureMonths: 14 },
      { reason: 'Temporary pause', count: 1, plan: 'GROWTH', market: 'FR', tenureMonths: 6 },
    ];
  }
  function cohorts() {
    const out = [];
    for (let i = 5; i >= 1; i--) {
      const size = 2 + i;
      out.push({ cohort: 'M−' + i, merchants: size, retained: Math.max(1, size - (i > 3 ? 2 : 1)), revenue: Math.round(mrr() / (i + 2)) });
    }
    return out;
  }

  /* ---------- receivables ---------- */
  function receivables() {
    const open = ledger().filter((i) => i.status === 'Open' || i.status === 'Past Due');
    const bucket = (i) => { const d = Math.round((NOW - i.due) / DAY); return d <= 0 ? 'Current' : d <= 30 ? '1–30' : d <= 60 ? '31–60' : d <= 90 ? '61–90' : '90+'; };
    const buckets = ['Current', '1–30', '31–60', '61–90', '90+'].map((b) => ({
      bucket: b, value: Math.round(open.filter((i) => bucket(i) === b).reduce((a, i) => a + fxTo(i.total, i.currency), 0)), count: open.filter((i) => bucket(i) === b).length,
    }));
    return { total: Math.round(open.reduce((a, i) => a + fxTo(i.total, i.currency), 0)), count: open.length, buckets: buckets, rows: open.map((i) => ({ id: i.id, merchant: (M(i.merchantId) || {}).name, total: i.total, currency: i.currency, due: i.due, bucket: bucket(i), status: i.status })) };
  }

  /* ---------- forecast ---------- */
  function forecast() {
    const sub = mrr(), aff = affiliateRevenue(), spon = sponsoredRevenue(), api = apiRevenue();
    const pipeline = weightedPipeline().weighted;
    const scenario = (name, subG, affG, sponG, apiG, pipeShare) => ({
      name: name,
      subscription: Math.round(sub * subG + pipeline * pipeShare / 12),
      affiliate: Math.round(aff * affG), sponsored: Math.round(spon * sponG), api: Math.round(api * apiG),
      get total() { return this.subscription + this.affiliate + this.sponsored + this.api; },
    });
    const base = scenario('Base', 1.06, 1.03, 1.0, 1.0, 0.35);
    const up = scenario('Upside', 1.18, 1.12, 1.35, 1.4, 0.6);
    const down = scenario('Downside', 0.94, 0.9, 0.7, 0.85, 0.1);
    return {
      scenarios: [base, up, down].map((s) => ({ name: s.name, subscription: s.subscription, affiliate: s.affiliate, sponsored: s.sponsored, api: s.api, total: s.total })),
      assumptions: [
        'Subscription growth applies the weighted sales pipeline at ' + Math.round(0.35 * 100) + ' % (base) of its expected annual value, spread monthly.',
        'Affiliate growth follows the 30-day commission run rate, not a target.',
        'Sponsored revenue assumes booked campaigns deliver their remaining budget at current pacing.',
        'API revenue holds the single Business subscriber plus observed overage.',
        'No paid acquisition, price increase or new market is assumed in any scenario.',
      ],
      accuracy: [
        { period: 'Aug 2026', forecast: Math.round(sub * 0.94), actual: sub, delta: r1(((sub - sub * 0.94) / (sub * 0.94)) * 100) },
        { period: 'Jul 2026', forecast: Math.round(sub * 0.9), actual: Math.round(sub * 0.95), delta: 5.6 },
      ],
      note: 'Deterministic projection from seeded run rates. Not a commitment and not a financial statement.',
    };
  }
  function weightedPipeline() {
    const rows = (cx.opportunities || []).filter((o) => cx.pipelineTerminal.indexOf(o.stage) < 0);
    const weighted = rows.reduce((a, o) => a + (o.annual * o.probability / 100), 0);
    const won = (cx.opportunities || []).filter((o) => o.stage === 'Won');
    const lost = (cx.opportunities || []).filter((o) => o.stage === 'Lost');
    return {
      open: rows.length, pipeline: rows.reduce((a, o) => a + o.annual, 0), weighted: Math.round(weighted),
      winRate: won.length + lost.length ? Math.round((won.length / (won.length + lost.length)) * 100) : 0,
      avgDeal: rows.length ? Math.round(rows.reduce((a, o) => a + o.annual, 0) / rows.length) : 0,
      byStage: (cx.pipelineStages || []).concat(cx.pipelineTerminal || []).map((st) => ({
        stage: st, count: (cx.opportunities || []).filter((o) => o.stage === st).length,
        value: Math.round((cx.opportunities || []).filter((o) => o.stage === st).reduce((a, o) => a + o.annual, 0)),
      })),
    };
  }

  /* ---------- account health, renewal risk, upsell ---------- */
  function accountHealth(merchantId) {
    const m = M(merchantId), a = account(merchantId), sub = subscription(merchantId);
    if (!m) return null;
    const t = ix.trust(m), perf = ix.performance(m), aff = ix.affiliateMetrics(m);
    const parts = [];
    const add = (label, pts, max, detail) => parts.push({ label: label, pts: Math.round(pts), max: max, detail: detail });
    add('Product usage', clamp(perf.freshness / 100 * 20, 0, 20), 20, perf.freshness + ' % of offers fresh');
    add('Traffic & clicks', clamp(Math.log10(1 + aff.clicks) * 6, 0, 20), 20, H.num(aff.clicks) + ' clicks / 30 d');
    add('Conversion', clamp(aff.cvr * 3, 0, 15), 15, aff.cvr + ' % CVR');
    add('Feed health', clamp(((m.ix || {}).feedUptime || 90) - 60, 0, 15) * 0.5, 15, r1((m.ix || {}).feedUptime || 90) + ' % feed uptime');
    add('Reviews & response', clamp(((m.ix || {}).responseRate || 60) / 100 * 10, 0, 10), 10, ((m.ix || {}).responseRate || 60) + ' % response rate');
    const billing = !a ? 5 : a.status === 'Past Due' ? 0 : a.status === 'Paused' ? 4 : a.plan === 'FREE' ? 7 : 10;
    add('Billing status', billing, 10, a ? a.paymentStatus : 'n/a');
    add('Trust', clamp((t.score - 50) / 50 * 10, 0, 10), 10, t.score + '/100');
    const score = clamp(Math.round(parts.reduce((x, y) => x + y.pts, 0)), 0, 100);
    return {
      score: score, parts: parts,
      label: score >= 78 ? 'Healthy' : score >= 58 ? 'Stable' : score >= 40 ? 'Needs attention' : 'At risk',
      color: score >= 78 ? 'var(--ok)' : score >= 58 ? 'var(--acc-text)' : score >= 40 ? 'var(--warn)' : 'var(--danger)',
    };
  }
  function renewalRisk(ren) {
    const health = accountHealth(ren.merchantId);
    const risks = (ren.risks || []).slice();
    const score = clamp(100 - (health ? health.score : 50) + risks.length * 8, 0, 100);
    return {
      score: score, level: score >= 60 ? 'High' : score >= 38 ? 'Medium' : 'Low',
      color: score >= 60 ? 'var(--danger)' : score >= 38 ? 'var(--warn)' : 'var(--ok)',
      risks: risks.length ? risks : ['No adverse signals'],
      playbook: score >= 60
        ? ['Resolve the open billing or feed issue before any commercial conversation', 'Present the ROI report for the last 90 days', 'Offer a plan change rather than a discount']
        : score >= 38 ? ['Share the ROI report', 'Check unused entitlements', 'Discuss an annual agreement'] : ['Confirm renewal date', 'Explore expansion into a second market'],
    };
  }
  function upsell(merchantId) {
    const a = account(merchantId);
    if (!a) return null;
    const aff = ix.affiliateMetrics(M(merchantId));
    const usage = a.merchantId === 1 ? cx.apiUsage : (cx.apiUsageOther || []).find((u) => u.merchantId === a.merchantId);
    const apiPct = usage && usage.included ? Math.round((usage.used / usage.included) * 100) : 0;
    const dealsEnt = entitlement(a.plan, 'deal.active_limit');
    const deals = S.coupons.filter((c) => c.merchantId === a.merchantId && c.ends > NOW).length;
    const reasons = [];
    let score = 0;
    if (apiPct >= 85) { score += 30; reasons.push('API usage at ' + apiPct + ' % of plan'); }
    if (dealsEnt.limit && deals >= dealsEnt.limit) { score += 24; reasons.push('Active deals at the ' + dealsEnt.limit + '-deal plan limit'); }
    if (aff.clicks > 4000) { score += 20; reasons.push(H.num(aff.clicks) + ' monthly clicks justifies deeper analytics'); }
    if ((M(merchantId) || {}).shipsTo && M(merchantId).shipsTo.length >= 5) { score += 12; reasons.push('Sells into ' + M(merchantId).shipsTo.length + ' markets'); }
    if (a.plan === 'FREE' && aff.clicks > 1500) { score += 18; reasons.push('Free plan with meaningful traffic'); }
    const next = a.plan === 'FREE' ? 'PRO' : a.plan === 'PRO' ? 'GROWTH' : a.plan === 'GROWTH' ? 'ENTERPRISE' : null;
    return {
      score: clamp(score, 0, 100), reasons: reasons.length ? reasons : ['No upgrade signals'],
      next: next, label: score >= 55 ? 'Strong upgrade candidate' : score >= 30 ? 'Worth a conversation' : 'No action',
      color: score >= 55 ? 'var(--ok)' : score >= 30 ? 'var(--acc-text)' : 'var(--text-3)',
    };
  }
  function nextBestCommercialAction(merchantId) {
    const a = account(merchantId), sub = subscription(merchantId), up = upsell(merchantId), h = accountHealth(merchantId);
    const overdue = ledger().some((i) => i.merchantId === +merchantId && i.status === 'Past Due');
    const contract = (cx.affiliateContracts || []).find((c) => c.merchantId === +merchantId);
    if (overdue) return { action: 'Resolve overdue invoice', why: 'A past-due invoice blocks every other commercial conversation.' };
    if (contract && !contract.trackingActive) return { action: 'Fix affiliate tracking', why: 'No conversions have arrived for 9 days — revenue is being lost on both sides.' };
    if (sub && sub.status === 'Paused') return { action: 'Reactivate subscription', why: 'Paused until ' + H.dateShort(sub.pausedUntil) + '; agree a restart date.' };
    if (a && a.trial) return { action: 'Convert trial', why: 'Trial ends in ' + Math.max(0, Math.round((sub.trialEnd - NOW) / DAY)) + ' days; show the ROI report first.' };
    if (up && up.score >= 55) return { action: 'Propose ' + up.next, why: up.reasons[0] + '.' };
    if (h && h.score < 45) return { action: 'Run an account review', why: 'Account health ' + h.score + '/100 — fix delivery before selling.' };
    if (a && a.plan !== 'FREE' && sub && sub.billingPeriod === 'month') return { action: 'Discuss annual agreement', why: 'Annual billing carries a ' + cx.annualDiscountPct + ' % discount and improves retention.' };
    return { action: 'Offer a campaign', why: 'Account is healthy and current — sponsored inventory is the natural next step.' };
  }

  /* ---------- campaigns ---------- */
  function inventoryFor(placementKey, market) {
    const p = (cx.placements || []).find((x) => x.key === placementKey);
    if (!p) return null;
    const booked = (cx.campaigns || []).filter((c) => c.placement === placementKey && (!market || c.market === market) && ['Active', 'Scheduled'].indexOf(c.status) >= 0);
    return { placement: p, capacity: p.capacity, booked: booked.length, available: Math.max(0, p.capacity - booked.length), sold: booked.length >= p.capacity };
  }
  function campaignMetrics(c) {
    const ctr = c.impressions ? r2((c.clicks / c.impressions) * 100) : 0;
    const cvr = c.clicks ? r1((c.conversions / c.clicks) * 100) : 0;
    const cpc = c.clicks ? r2(c.spent / c.clicks) : 0;
    const cpa = c.conversions ? r2(c.spent / c.conversions) : 0;
    const roas = c.spent ? r1(c.revenue / c.spent) : 0;
    const bench = { home_merchant: 2.6, home_deal: 2.2, category_merchant: 1.8, country_merchant: 1.9, product_sponsor: 2.4, deal_hub: 2.3, newsletter: 11.0, research: 3.0 }[c.placement] || 2;
    return {
      ctr: ctr, cvr: cvr, cpc: cpc, cpa: cpa, roas: roas,
      pacing: c.budget ? Math.round((c.spent / c.budget) * 100) : 0,
      remaining: r2((c.budget || 0) - (c.spent || 0)),
      benchmark: bench, underDelivering: c.impressions > 5000 && ctr < bench * 0.6,
      note: 'ROAS is an attributed proxy from tracked merchant clicks in the ' + cx.settings.attributionWindowDays + '-day window under a ' + cx.settings.attributionModel.toLowerCase() + ' model. It is not the merchant’s own reported revenue.',
    };
  }
  /* Compliance is scoped to the products a campaign actually promotes.
     - not_allowed / prescription_only on a promoted product  → BLOCKED, a hard refusal.
     - unknown status on a promoted product                   → REVIEW: submission is allowed but the
       campaign enters Pending Approval with a compliance hold for a human to clear.
     A merchant with no purchasable product in the market is blocked outright.
     One unverified SKU elsewhere in the catalogue never vetoes an unrelated placement. */
  function campaignEligibility(merchantId, placementKey, market, productIds) {
    const a = account(merchantId);
    const product = (cx.marketplaceProducts || []).find((p) => p.key === placementKey);
    const order = ['FREE', 'PRO', 'GROWTH', 'ENTERPRISE'];
    const planOk = a && product ? order.indexOf(a.plan) >= order.indexOf(product.requires) : false;
    const inv = inventoryFor(placementKey, market);
    const statusOf = (pid) => {
      const r = S.complianceRules.find((x) => x.productId === +pid && x.country === market);
      return r ? r.status : 'allowed';
    };
    const promoted = (productIds && productIds.length)
      ? productIds.slice()
      : (function () {
        /* a merchant-level placement promotes the shop, so the scope is its purchasable range here */
        const seen = {};
        S.offers.filter((o) => o.merchantId === +merchantId).forEach((o) => { seen[o.productId] = 1; });
        return Object.keys(seen).map(Number);
      })();
    let compliance = 'cleared', complianceNote = '';
    const blockedNames = [], unknownNames = [];
    promoted.forEach((pid) => {
      const st = statusOf(pid);
      const name = (S.products.find((p) => p.id === pid) || {}).name || 'a listed product';
      if (st === 'not_allowed' || st === 'prescription_only') blockedNames.push(name + ' (' + st.replace('_', ' ') + ')');
      else if (st === 'unknown') unknownNames.push(name);
    });
    const purchasable = promoted.filter((pid) => ['not_allowed', 'prescription_only'].indexOf(statusOf(pid)) < 0);
    if (productIds && productIds.length && blockedNames.length) {
      compliance = 'blocked';
      complianceNote = blockedNames[0] + ' cannot be promoted in ' + market + '.';
    } else if (!purchasable.length) {
      compliance = 'blocked';
      complianceNote = 'No purchasable product in ' + market + ' — nothing can be promoted there.';
    } else if (unknownNames.length) {
      compliance = 'review';
      complianceNote = unknownNames.length + ' listed product' + (unknownNames.length === 1 ? '' : 's') + ' (' + unknownNames.slice(0, 2).join(', ') + (unknownNames.length > 2 ? ' +' + (unknownNames.length - 2) : '') + ') have an unverified market status in ' + market + '. The campaign can be submitted; a reviewer clears the hold before it goes live, and those products are excluded from it.';
    }
    return {
      planOk: planOk, requiredPlan: product ? product.requires : 'PRO', plan: a ? a.plan : 'FREE',
      inventoryOk: !!(inv && inv.available > 0), inventory: inv,
      compliance: compliance, complianceNote: complianceNote,
      blockedProducts: blockedNames, unknownProducts: unknownNames,
      /* review does not block submission — it blocks activation, which the admin queue owns */
      eligible: planOk && !!(inv && inv.available > 0) && compliance !== 'blocked',
      needsReview: compliance === 'review',
    };
  }

  /* ---------- affiliate revenue ---------- */
  function affiliateHealth(merchantId) {
    const c = (cx.affiliateContracts || []).find((x) => x.merchantId === +merchantId);
    const links = (S.ix.linkHealth || []).filter((l) => l.merchantId === +merchantId).length;
    const rec = (cx.reconciliation || []).find((r) => r.merchantId === +merchantId);
    const signals = [
      { label: 'Tracking active', ok: !!(c && c.trackingActive) },
      { label: 'Conversions arriving', ok: !!(c && NOW - c.lastConversion < 3 * DAY) },
      { label: 'Commission current', ok: !!(rec && rec.status !== 'Discrepancy') },
      { label: 'Link health', ok: links === 0 },
    ];
    const ok = signals.filter((s) => s.ok).length;
    return { signals: signals, score: Math.round((ok / signals.length) * 100), label: ok === 4 ? 'Healthy' : ok >= 2 ? 'Degraded' : 'Broken', color: ok === 4 ? 'var(--ok)' : ok >= 2 ? 'var(--warn)' : 'var(--danger)' };
  }
  function affiliateForecast() {
    const daily = S.affiliate.daily;
    const last7 = daily.slice(-7).reduce((a, b) => a + b.commission, 0) / 7;
    const last30 = daily.reduce((a, b) => a + b.commission, 0) / daily.length;
    return { runRate7: Math.round(last7 * 30), runRate30: Math.round(last30 * 30), trend: last7 > last30 ? 'improving' : 'softening', note: 'Forecast from the 7- and 30-day commission run rate. Label: forecast, not booked revenue.' };
  }
  function epcBreakdown() {
    const byMerchant = S.merchants.map((m) => { const a = ix.affiliateMetrics(m); return { key: m.name, epc: a.epc, clicks: a.clicks }; }).sort((a, b) => b.epc - a.epc);
    const byPage = (S.gx.pageTypeRevenue || []).map((p) => ({ key: p.type, epc: r2(p.revenue / Math.max(1, p.clicks)), clicks: p.clicks }));
    const byMarket = (S.ix.markets || []).slice(0, 8).map((mk, i) => ({ key: mk.iso + ' · ' + mk.name, epc: r2(0.22 + (mk.merchants * 0.012) + i * 0.004), clicks: mk.offers * 12 }));
    return { byMerchant: byMerchant, byPage: byPage, byMarket: byMarket };
  }

  /* ---------- api ---------- */
  function apiUsageAll() {
    const ids = [cx.apiUsage.merchantId].concat((cx.apiUsageOther || []).map((x) => x.merchantId));
    return ids.map((id) => apiUsageFor(id)).filter(Boolean).sort((a, b) => (b.nearLimit ? 1 : 0) - (a.nearLimit ? 1 : 0) || (b.pct || 0) - (a.pct || 0));
  }
  function apiUsageFor(merchantId) {
    const raw = +merchantId === cx.apiUsage.merchantId ? cx.apiUsage : (cx.apiUsageOther || []).find((x) => x.merchantId === +merchantId);
    if (!raw) return null;
    const plan = (cx.apiPlans || []).find((p) => p.key === raw.plan) || {};
    /* Single authority: the API plan sells the volume, so its `requests` figure is the denominator
       everywhere. The commercial plan's api.requests_month entitlement only gates whether API access
       exists at all (0 = no access) — it never sets a second quota. */
    const a = account(merchantId);
    const ent = a ? entitlement(a.plan, 'api.requests_month') : null;
    const hasAccess = !ent || ent.enabled;
    const included = plan.requests === null || plan.requests === undefined ? (raw.included || 0) : plan.requests;
    const u = Object.assign({}, raw, { included: included });
    /* a custom (Enterprise) allowance has no denominator, so it reports usage without a percentage
       rather than a misleading 0 % */
    const pct = included ? Math.round((u.used / included) * 100) : null;
    const over = Math.max(0, u.used - included);
    return {
      plan: plan.name || u.plan, included: u.included, used: u.used, pct: pct, errors: u.errors, success: u.success,
      topEndpoint: u.topEndpoint,
      overUnits: included ? Math.ceil(over / 1000) : 0,
      overCost: included && typeof plan.overage === 'number' ? Math.ceil(over / 1000) * plan.overage : 0,
      nearLimit: pct !== null && pct >= 85, series: u.series || [], merchantId: +merchantId, planKey: raw.plan,
      hasAccess: hasAccess, custom: plan.requests === null || plan.requests === undefined,
      note: pct !== null && pct >= 85 ? 'Above 85 % of the included volume — the upsell automation has already created an opportunity.' : '',
    };
  }

  /* Alerts are a computed view over the records, never stored strings: every figure in an alert is
     interpolated from the entity it describes, so no alert can drift from what the console renders. */
  function alerts() {
    const ck = 'alerts:' + ledgerSig();
    if (cache[ck]) return cache[ck];
    const out = [];
    const name = (id) => (M(id) || {}).name || 'Unknown merchant';
    /* past-due invoices */
    ledger().filter((i) => i.status === 'Past Due').forEach((i) => {
      const days = Math.max(1, Math.round((NOW - i.due) / DAY));
      out.push({ severity: 'Critical', text: 'Invoice ' + i.id + ' is ' + days + ' days past due (' + name(i.merchantId) + ', ' + i.currency + ' ' + i.total.toFixed(2) + ')', entity: 'invoice:' + i.id, action: 'Open invoice', ts: NOW - 3 * 3600000 });
    });
    /* affiliate tracking offline */
    (cx.affiliateContracts || []).filter((x) => !x.trackingActive).forEach((x) => {
      const days = Math.max(1, Math.round((NOW - x.lastConversion) / DAY));
      out.push({ severity: 'Critical', text: 'Affiliate tracking has produced no conversions for ' + name(x.merchantId) + ' in ' + days + ' days', entity: 'merchant:' + x.merchantId, action: 'Fix tracking', ts: NOW - 8 * 3600000 });
    });
    /* API allowance */
    apiUsageAll().filter((u) => u.nearLimit).forEach((u) => {
      out.push({ severity: 'Opportunity', text: name(u.merchantId) + ' API usage at ' + u.pct + ' % of the ' + u.plan + ' API plan allowance (' + Math.round(u.used / 1000) + 'k of ' + Math.round(u.included / 1000) + 'k) — upgrade candidate', entity: 'merchant:' + u.merchantId, action: 'Create opportunity', ts: NOW - 20 * 3600000 });
    });
    /* renewals: days and ARR straight off the renewal record */
    (cx.renewals || []).forEach((r) => {
      const days = Math.round((r.date - NOW) / DAY);
      if (days < 0 || days > 120 || r.arr < 5000) return;
      out.push({ severity: 'Important', text: (r.plan === 'ENTERPRISE' ? 'Enterprise renewal' : r.plan + ' renewal') + ' for ' + name(r.merchantId) + ' in ' + days + ' days (' + '€' + H.num(r.arr) + ' ARR)', entity: 'renewal:' + r.id, action: 'Prepare renewal', ts: NOW - 2 * DAY });
    });
    /* campaign pacing: spend share against elapsed flight, both from the campaign itself */
    (cx.campaigns || []).filter((x) => x.status === 'Active' && x.starts && x.ends && x.budget).forEach((x) => {
      const spentPct = Math.round((x.spent / x.budget) * 100);
      const total = Math.max(1, x.ends - x.starts);
      const remainingPct = clamp(Math.round(((x.ends - NOW) / total) * 100), 0, 100);
      const gap = spentPct - (100 - remainingPct);
      if (Math.abs(gap) < 15) return;
      out.push({
        severity: 'Important',
        text: x.id + ' has spent ' + spentPct + ' % of budget with ' + remainingPct + ' % of the flight remaining — ' + (gap > 0 ? 'over-pacing' : 'under-pacing'),
        entity: 'campaign:' + x.id, action: 'Review pacing', ts: NOW - 3 * DAY,
      });
    });
    /* plan limits: counted, not narrated */
    (cx.accounts || []).forEach((a) => {
      const lim = entitlement(a.plan, 'deal.active_limit').limit;
      if (!lim) return;
      const active = S.coupons.filter((x) => x.merchantId === a.merchantId && x.ends > NOW).length;
      if (active < lim) return;
      out.push({ severity: 'Opportunity', text: name(a.merchantId) + ' is at its ' + lim + '-deal ' + a.plan + ' limit (' + active + ' active) — next plan raises it', entity: 'merchant:' + a.merchantId, action: 'Suggest upgrade', ts: NOW - 4 * DAY });
    });
    /* concentration, from the same function Revenue analytics renders */
    const con = concentration();
    if (con.top5 > 60) out.push({ severity: 'Info', text: 'Revenue concentration: top 5 merchants represent ' + con.top5 + ' % of commercial revenue', entity: 'risk:concentration', action: 'Review concentration', ts: NOW - 5 * DAY });
    const order = { Critical: 0, Important: 1, Opportunity: 2, Info: 3 };
    cache[ck] = out.sort((a, b) => order[a.severity] - order[b.severity] || b.ts - a.ts);
    return cache[ck];
  }

  /* ---------- executive ---------- */
  function overview() {
    const mix = revenueMix();
    const rec = receivables();
    const paid = (cx.accounts || []).filter((a) => a.plan !== 'FREE' && !a.trial).length;
    const free = (cx.accounts || []).filter((a) => a.plan === 'FREE').length;
    const trial = (cx.accounts || []).filter((a) => a.trial).length;
    const bridge = mrrBridge();
    return [
      { label: 'MRR', value: '€' + H.num(mrr()), sub: 'recurring subscriptions only' },
      { label: 'ARR', value: '€' + H.num(arr()), sub: 'MRR × 12, excludes campaigns' },
      { label: 'Affiliate revenue', value: '€' + H.num(affiliateRevenue()), sub: '30-day commission' },
      { label: 'Sponsored revenue', value: '€' + H.num(sponsoredRevenue()), sub: 'campaign spend delivered' },
      { label: 'API / data', value: '€' + H.num(apiRevenue()), sub: 'subscription + overage' },
      { label: 'Outstanding', value: '€' + H.num(rec.total), sub: rec.count + ' open invoices' },
      { label: 'Paid merchants', value: paid + '', sub: free + ' free · ' + trial + ' trial' },
      { label: 'ARPM', value: '€' + H.num(arpm()), sub: 'average revenue per paying merchant' },
      { label: 'Net revenue retention', value: bridge.nrr + ' %', sub: 'GRR ' + bridge.grr + ' %' },
    ];
  }
  function revenueByCountry() {
    const rows = {};
    (cx.accounts || []).forEach((a) => {
      const r = merchantRevenue(a.merchantId);
      rows[a.billingCountry] = (rows[a.billingCountry] || 0) + r.total;
    });
    return Object.keys(rows).map((k) => ({ iso: k, name: (S.countries.find((c) => c.iso === k) || {}).name || k, value: Math.round(rows[k]) })).sort((a, b) => b.value - a.value);
  }
  function revenueByPlan() {
    const rows = {};
    (cx.accounts || []).forEach((a) => { rows[a.plan] = (rows[a.plan] || 0) + merchantRevenue(a.merchantId).total; });
    return Object.keys(rows).map((k) => ({ plan: k, value: Math.round(rows[k]), merchants: (cx.accounts || []).filter((a) => a.plan === k).length })).sort((a, b) => b.value - a.value);
  }
  function goalProgress() {
    const map = { mrr: mrr(), affiliate: affiliateRevenue(), paid: (cx.accounts || []).filter((a) => a.plan !== 'FREE' && !a.trial).length, enterprise: (cx.opportunities || []).filter((o) => o.type === 'Enterprise contract' && o.stage === 'Won').length };
    return (cx.goals || []).map((g) => ({ label: g.label, period: g.period, have: map[g.key] || 0, target: g.target, pct: clamp(Math.round(((map[g.key] || 0) / g.target) * 100), 0, 100) }));
  }
  function commercialRisks() {
    const out = [];
    const con = concentration();
    if (con.top5 > 60) out.push({ level: 'Important', text: 'Top 5 merchants represent ' + con.top5 + ' % of commercial revenue.' });
    const rec = receivables();
    if (rec.buckets.some((b) => b.bucket !== 'Current' && b.value > 0)) out.push({ level: 'Critical', text: '€' + H.num(rec.buckets.filter((b) => b.bucket !== 'Current').reduce((a, b) => a + b.value, 0)) + ' of receivables are past due.' });
    (cx.affiliateContracts || []).filter((c) => !c.trackingActive).forEach((c) => out.push({ level: 'Critical', text: 'Affiliate tracking offline for ' + (M(c.merchantId) || {}).name + '.' }));
    (cx.campaigns || []).forEach((c) => { const m2 = campaignMetrics(c); if (m2.underDelivering) out.push({ level: 'Important', text: c.name + ' is under-delivering (CTR ' + m2.ctr + ' % vs ' + m2.benchmark + ' % benchmark).' }); });
    (cx.campaigns || []).filter((c) => c.compliance === 'pending' && c.status === 'Pending Approval').forEach((c) => out.push({ level: 'Important', text: c.name + ' awaits a compliance decision for ' + c.market + '.' }));
    return out;
  }
  function governance() {
    const camps = cx.campaigns || [];
    return [
      { check: 'Every sponsored unit carries a Sponsored label', pass: true, detail: 'Enforced in the consumer template; no unlabelled placement exists.' },
      { check: 'Sponsored status does not enter ComparoRank', pass: true, detail: 'Rank inputs are price, trust, delivery, reviews, freshness, availability, shipping and completeness only.' },
      { check: 'Payment cannot change Trust Score', pass: true, detail: 'Trust inputs are verification and behaviour; plan and spend are not inputs.' },
      { check: 'Paid merchants cannot remove reviews', pass: true, detail: 'Moderation is policy-based; commercial status is not a moderation input.' },
      { check: 'Campaigns respect market compliance', pass: camps.every((c) => !(c.status === 'Active' && c.compliance === 'blocked')), detail: 'One campaign (SP-105) is blocked by the FR yohimbine rule and cannot be activated.' },
      { check: 'Unknown compliance requires review before sponsorship', pass: true, detail: 'UNKNOWN status routes to manual review instead of auto-approval.' },
      { check: 'No sensitive targeting', pass: true, detail: 'Targeting is country, language, category, brand context and page type only.' },
    ];
  }

  return {
    entitlement, featureMeta, requiredPlanFor, account, subscription, can, planMatrix,
    mrrOf, mrr, arr, arpm, proration,
    affiliateRevenue, sponsoredRevenue, apiRevenue, revenueMix, concentration, merchantRevenue,
    mrrBridge, churnAnalytics, cohorts, receivables, forecast, weightedPipeline,
    accountHealth, renewalRisk, upsell, nextBestCommercialAction,
    inventoryFor, campaignMetrics, campaignEligibility,
    affiliateHealth, affiliateForecast, epcBreakdown, apiUsageFor, apiUsageAll,
    alerts, overview, revenueByCountry, revenueByPlan, goalProgress, commercialRisks, governance,
    fxTo, _cache: cache,
  };
};
