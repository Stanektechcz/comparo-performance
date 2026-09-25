/* Comparo Performance — paid-extension engine.

   One resolver for merchant entitlements (plan + add-ons), one for buyer entitlements
   (tier + earned rewards), one for delivery-promise eligibility, one for ad estimates. Every
   gate in the product reads these, so a limit shown on a pricing page, enforced in a console
   and billed on an invoice is the same number.

   window.ComparoAddons(SEED) -> engine. */
(function () {
  window.ComparoAddons = function (S) {
    if (!S || !S.cx) return null;
    const cx = S.cx, gp = S.gp || {}, DAY = S.DAY;
    const r2 = (n) => Math.round(n * 100) / 100;
    const addon = (key) => cx.addons.find((a) => a.key === key) || null;
    const feature = (key) => cx.features.find((f) => f.key === key) || null;
    const planOrder = ['FREE', 'PRO', 'GROWTH', 'ENTERPRISE'];

    /* ---------- merchant entitlements ---------- */
    function planEnt(plan) {
      const out = {};
      cx.entitlements.filter((e) => e.plan === plan).forEach((e) => {
        out[e.feature] = { enabled: e.enabled, limit: e.limit, value: e.value };
      });
      return out;
    }
    /* plan is the floor, add-ons are deltas, and the resolver names which is which so the
       console can show "10 from Pro, +5 from an add-on" rather than an opaque 15 */
    function merchantEnt(plan, active) {
      const base = planEnt(plan), out = {};
      Object.keys(base).forEach((k) => {
        out[k] = { plan: base[k].limit, addon: 0, value: base[k].value, enabled: base[k].enabled, total: base[k].limit, from: [] };
      });
      (active || []).forEach((a) => {
        const ad = addon(a.key);
        if (!ad) return;
        Object.keys(ad.delta).forEach((k) => {
          const d = ad.delta[k], qty = a.qty || 1;
          if (!out[k]) out[k] = { plan: null, addon: 0, value: null, enabled: false, total: null, from: [] };
          if (typeof d === 'number') {
            /* an interval is a floor, not a sum: hourly import replaces six-hourly */
            if (k === 'feed.interval_hours') { out[k].total = Math.min(out[k].plan === null ? 99 : out[k].plan, d); out[k].addon = d; }
            else { out[k].addon += d * qty; out[k].total = (out[k].plan || 0) + out[k].addon; }
            out[k].enabled = true;
          } else if (typeof d === 'boolean') { out[k].enabled = out[k].enabled || d; out[k].addon = d ? 1 : 0; }
          else { out[k].value = d; out[k].enabled = true; }
          out[k].from.push({ key: a.key, name: ad.name, qty: qty });
        });
      });
      return out;
    }
    function entRows(plan, active) {
      const map = merchantEnt(plan, active);
      return Object.keys(map).map((k) => {
        const f = feature(k) || { name: k, group: 'Other', why: '' };
        const e = map[k];
        return {
          key: k, name: f.name, group: f.group, why: f.why, unit: f.unit || '',
          plan: e.plan, addon: e.addon, total: e.total, value: e.value, enabled: e.enabled,
          from: e.from,
          display: e.value ? String(e.value) : e.total !== null && e.total !== undefined ? String(e.total) + (f.unit ? ' ' + f.unit : '') : e.enabled ? 'included' : '—',
          lifted: !!e.from.length,
        };
      }).sort((a, b) => (cx.featureGroups.indexOf(a.group) - cx.featureGroups.indexOf(b.group)) || a.name.localeCompare(b.name));
    }
    function addonCost(active) {
      return (active || []).reduce((sum, a) => { const ad = addon(a.key); return sum + (ad ? ad.price * (a.qty || 1) : 0); }, 0);
    }
    function available(plan) {
      const idx = planOrder.indexOf(plan);
      return cx.addons.map((a) => Object.assign({}, a, {
        eligible: planOrder.indexOf(a.minPlan) <= idx,
        needsPlan: planOrder.indexOf(a.minPlan) > idx ? a.minPlan : null,
      }));
    }
    /* the pricing check that keeps an add-on stack honest */
    function upgradeAdvice(plan, active) {
      const cost = addonCost(active);
      const cur = cx.plans.find((p) => p.key === plan);
      const idx = planOrder.indexOf(plan);
      const next = cx.plans.find((p) => p.key === planOrder[idx + 1]);
      if (!cur || !next || !next.price || next.price.month === null) return null;
      const delta = next.price.month - (cur.price ? cur.price.month : 0);
      if (cost <= delta) return null;
      /* which add-ons the higher plan already contains */
      const higher = planEnt(next.key), included = [];
      (active || []).forEach((a) => {
        const ad = addon(a.key);
        if (!ad) return;
        const covered = Object.keys(ad.delta).every((k) => {
          const h = higher[k];
          if (!h) return false;
          const d = ad.delta[k];
          if (typeof d === 'number') return k === 'feed.interval_hours' ? (h.limit || 99) <= d : (h.limit || 0) >= ((planEnt(plan)[k] || {}).limit || 0) + d * (a.qty || 1);
          return !!h.enabled;
        });
        if (covered) included.push(ad.name);
      });
      return { plan: next.key, planName: next.name, delta: delta, addonCost: cost, save: r2(cost - delta), included: included };
    }

    /* ---------- buyer entitlements ---------- */
    function userEnt(tierKey, earned) {
      const t = cx.userTiers.find((x) => x.key === (tierKey || 'FREE')) || cx.userTiers[0];
      const e = earned || {};
      const out = {};
      Object.keys(t.ent).forEach((k) => { out[k] = { value: t.ent[k], from: t.name, lifted: false }; });
      if (e.alerts && typeof out['alerts.count'].value === 'number') { out['alerts.count'] = { value: out['alerts.count'].value + e.alerts, from: t.name + ' + earned', lifted: true }; }
      if (e.instantDays > 0 && !out['alerts.instant'].value) out['alerts.instant'] = { value: true, from: 'Earned (' + e.instantDays + ' days)', lifted: true };
      if (e.groupSlots) out['community.groups'] = { value: (out['community.groups'].value || 0) + e.groupSlots, from: t.name + ' + earned', lifted: true };
      if (e.earlyDealsDays > 0 && !out['community.early_deals'].value) out['community.early_deals'] = { value: 30, from: 'Earned (' + e.earlyDealsDays + ' days)', lifted: true };
      if (e.exports > 0 && !out['data.export'].value) out['data.export'] = { value: true, from: 'Earned (' + e.exports + ' export)', lifted: true };
      if (e.plusDays > 0 && tierKey === 'FREE') {
        /* earned Plus days are a real tier, not a cosmetic: resolve to Plus and say why */
        const plus = cx.userTiers.find((x) => x.key === 'PLUS');
        Object.keys(plus.ent).forEach((k) => { out[k] = { value: plus.ent[k], from: 'Earned Plus (' + e.plusDays + ' days left)', lifted: true }; });
      }
      return out;
    }
    function userRows(tierKey, earned) {
      const map = userEnt(tierKey, earned);
      return cx.userFeatures.map((f) => {
        const e = map[f.key] || { value: false, from: '', lifted: false };
        const v = e.value;
        return {
          key: f.key, name: f.name, group: f.group, why: f.why, unit: f.unit || '',
          value: v, from: e.from, lifted: e.lifted,
          display: v === true ? 'Included' : v === false ? '—' : v === 1095 ? 'Full history' : String(v) + (f.unit ? ' ' + f.unit : ''),
          on: v !== false && v !== 0,
        };
      });
    }
    function tierMatrix() {
      return cx.userFeatures.map((f) => ({
        name: f.name, group: f.group, why: f.why, unit: f.unit || '',
        cells: cx.userTiers.map((t) => {
          const v = t.ent[f.key];
          return { tier: t.key, display: v === true ? '✓' : v === false || v === 0 ? '—' : v === 1095 ? 'Full' : String(v), on: v !== false && v !== 0 };
        }),
      }));
    }
    function userPrice(tierKey, period) {
      const t = cx.userTiers.find((x) => x.key === tierKey);
      if (!t) return 0;
      return period === 'year' ? t.price.year : t.price.month;
    }

    /* ---------- delivery promise ---------- */
    function measured(merchantId, market) {
      return (gp.measured || []).find((m) => m.merchantId === merchantId && m.market === market) || null;
    }
    function eligibility(merchantId, market, window) {
      const m = measured(merchantId, market);
      const min = (S.orderMeta || {}).minSample || 8;
      const disputes = (S.orders || []).filter((o) => o.merchantId === merchantId && o.market === market);
      const dRate = disputes.length ? (disputes.filter((o) => o.status === 'disputed').length / disputes.length) * 100 : 0;
      const dep = (gp.deposits || []).find((d) => d.merchantId === merchantId);
      const w = window || (m ? Math.ceil(m.p90 || 0) : 0);
      const checks = [
        { label: min + ' delivered orders in this market', ok: !!m && m.n >= min, value: m ? m.n + ' orders' : 'no orders' },
        { label: '92 % on-time over 90 days', ok: !!m && m.onTime >= 92, value: m ? m.onTime + ' %' : '—' },
        { label: 'p90 inside the promised window', ok: !!m && m.p90 <= w, value: m ? 'p90 ' + m.p90 + ' d vs ' + w + ' d' : '—' },
        { label: 'Dispute rate under 2 %', ok: dRate < 2, value: Math.round(dRate * 10) / 10 + ' %' },
        { label: 'Deposit on file', ok: !!dep && dep.held >= dep.required, value: dep ? '€' + dep.held : 'none' },
      ];
      return { ok: checks.every((c) => c.ok), checks: checks, window: w, measured: m };
    }
    function enrolment(merchantId, market) {
      return (gp.enrolments || []).find((e) => e.merchantId === merchantId && (!market || e.market === market)) || null;
    }
    function promiseFor(merchantId, market) {
      const e = (gp.enrolments || []).find((x) => x.merchantId === merchantId && x.market === market);
      if (!e) return null;
      const tier = (gp.tiers || []).find((t) => t.key === e.tier) || {};
      return {
        window: e.window, tier: e.tier, tierName: tier.name, claim: tier.claim,
        onTime: e.measuredOnTime, sample: e.sample, since: e.since,
        label: e.tier === 'promise48' ? 'Promise 48 h' : 'Delivery promise · ' + e.window + ' days',
      };
    }
    function claims(filter) {
      const f = filter || {};
      return (gp.claims || []).filter((c) => (!f.merchantId || c.merchantId === f.merchantId) && (!f.market || c.market === f.market) && (!f.status || c.status === f.status));
    }
    function claimStats(merchantId) {
      const c = claims({ merchantId: merchantId });
      const resolved = c.filter((x) => x.status !== 'open');
      return {
        total: c.length, open: c.filter((x) => x.status === 'open').length,
        paid: c.filter((x) => x.status === 'paid').length,
        rejected: c.filter((x) => x.status === 'rejected').length,
        payout: r2(c.reduce((a, b) => a + b.payout + b.credit, 0)),
        upheldShare: resolved.length ? Math.round((c.filter((x) => x.status === 'paid' || x.status === 'upheld').length / resolved.length) * 1000) / 10 : null,
      };
    }

    /* ---------- advertising ---------- */
    function placement(key) { return cx.placements.find((p) => p.key === key) || null; }
    function booked(key) {
      return (cx.campaigns || []).filter((c) => c.placement === key && (c.status === 'Running' || c.status === 'Scheduled')).length;
    }
    function adEstimate(opts) {
      const o = opts || {};
      const p = placement(o.placementKey);
      if (!p) return null;
      const markets = (o.markets && o.markets.length) ? o.markets : ['DE'];
      const days = Math.max(1, o.days || 30);
      /* market weight is the share of our shop-market footprint the campaign covers. It applies
         to impression- and click-priced inventory only: a newsletter slot or a research
         sponsorship is bought whole, so weighting it by market would under-report the
         subscribers it actually reaches. */
      const cover = markets.reduce((a, iso) => a + (S.merchants || []).filter((m) => (m.shipsTo || []).indexOf(iso) >= 0).length, 0);
      const allCover = (S.merchants || []).reduce((a, m) => a + (m.shipsTo || []).length, 0) || 1;
      const marketShare = Math.min(1, Math.max(0.1, cover / allCover));
      const weight = p.model === 'Fixed placement' ? 1 : marketShare;
      const monthly = p.audienceSessions * weight;
      const impressions = Math.round(monthly * (days / 30));
      const cpm = p.model === 'CPM' ? 14 : p.model === 'CPC' ? null : null;
      const budget = o.budget || p.minSpend;
      const ctr = p.pageType === 'Comparison' ? 0.041 : p.pageType === 'Product' ? 0.032 : p.pageType === 'Forum' ? 0.012 : 0.019;
      const clicks = Math.round(impressions * ctr);
      const capacityLeft = Math.max(0, p.capacity - booked(p.key));
      return {
        placement: p.name, model: p.model, impressions: impressions, clicks: clicks,
        ctr: Math.round(ctr * 1000) / 10, cpm: cpm,
        effectiveCpm: impressions ? r2((budget / impressions) * 1000) : null,
        effectiveCpc: clicks ? r2(budget / clicks) : null,
        budget: budget, minSpend: p.minSpend, days: days, markets: markets,
        capacity: p.capacity, capacityLeft: capacityLeft, soldOut: capacityLeft <= 0,
        audienceSource: p.audienceSource,
        below: budget < p.minSpend ? p.minSpend : null,
        format: (cx.adFormats.find((f) => f.key === p.format) || {}).name || '',
        spec: (cx.adFormats.find((f) => f.key === p.format) || {}).spec || '',
      };
    }
    function inventoryRows() {
      return cx.placements.map((p) => ({
        key: p.key, name: p.name, pageType: p.pageType, model: p.model,
        basePrice: p.basePrice, minSpend: p.minSpend, capacity: p.capacity,
        booked: booked(p.key), left: Math.max(0, p.capacity - booked(p.key)),
        audience: p.audience, audienceSessions: p.audienceSessions, audienceSource: p.audienceSource,
        format: (cx.adFormats.find((f) => f.key === p.format) || {}).name || '',
        fill: Math.round((booked(p.key) / Math.max(1, p.capacity)) * 100),
      }));
    }

    return {
      merchantEnt: merchantEnt, entRows: entRows, addonCost: addonCost, available: available,
      upgradeAdvice: upgradeAdvice, addon: addon, planEnt: planEnt,
      userEnt: userEnt, userRows: userRows, tierMatrix: tierMatrix, userPrice: userPrice,
      eligibility: eligibility, enrolment: enrolment, promiseFor: promiseFor,
      claims: claims, claimStats: claimStats, measured: measured,
      adEstimate: adEstimate, inventoryRows: inventoryRows, placement: placement, booked: booked,
    };
  };
})();
