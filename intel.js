/* Comparo Performance — intelligence engine.
   Pure deterministic scoring built on window.SEED. No network, no randomness at call time.
   Maps 1:1 to the future backend services documented in COMPARORANK.md / TRUST-SCORING.md etc.
   Usage:  const ix = window.ComparoIntel(window.SEED);  */
window.ComparoIntel = function (S) {
  const H = S.helpers, NOW = S.NOW, DAY = S.DAY, HOUR = 3600000;
  const cache = {};
  const memo = (k, fn) => (cache[k] !== undefined ? cache[k] : (cache[k] = fn()));
  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
  const r1 = (v) => Math.round(v * 10) / 10;
  const r2 = (v) => Math.round(v * 100) / 100;
  const med = (arr) => { if (!arr.length) return 0; const s = arr.slice().sort((a, b) => a - b); return s[Math.floor(s.length / 2)]; };
  const avg = (arr) => (arr.length ? arr.reduce((a, b) => a + b, 0) / arr.length : 0);
  const P = (id) => S.products.find((p) => p.id === +id);
  const M = (id) => S.merchants.find((m) => m.id === +id);
  const ixOf = (m) => (m && m.ix) || {};

  /* ---------------- text similarity (matching + duplicate detection) ---------------- */
  const norm = (s) => H.norm(s || '').replace(/[^a-z0-9 ]+/g, ' ').replace(/\s+/g, ' ').trim();
  const tokens = (s) => norm(s).split(' ').filter((t) => t.length > 2);
  function jaccard(a, b) {
    const A = tokens(a), B = tokens(b);
    if (!A.length || !B.length) return 0;
    const sa = {}, sb = {};
    A.forEach((t) => { sa[t] = 1; }); B.forEach((t) => { sb[t] = 1; });
    let inter = 0;
    Object.keys(sa).forEach((t) => { if (sb[t]) inter++; });
    return inter / (Object.keys(sa).length + Object.keys(sb).length - inter);
  }
  function trigram(a, b) {
    const g = (s) => { const o = {}, n = norm(s); for (let i = 0; i < n.length - 2; i++) o[n.slice(i, i + 3)] = 1; return o; };
    const ga = g(a), gb = g(b), ka = Object.keys(ga), kb = Object.keys(gb);
    if (!ka.length || !kb.length) return 0;
    let inter = 0; ka.forEach((k) => { if (gb[k]) inter++; });
    return (2 * inter) / (ka.length + kb.length);
  }
  const similarity = (a, b) => r2(0.5 * jaccard(a, b) + 0.5 * trigram(a, b));

  /* ================= MERCHANT TRUST SCORE 2.0 ================= */
  const trustDefs = [
    ['biz', 'Business verified', 14],
    ['age', 'Account maturity', 6],
    ['rating', 'Review score', 14],
    ['verifiedRatio', 'Verified review ratio', 8],
    ['complaint', 'Complaint rate', 10],
    ['resolution', 'Complaint resolution', 10],
    ['response', 'Merchant response rate', 6],
    ['orders', 'Order verification', 6],
    ['priceAcc', 'Pricing accuracy', 8],
    ['feed', 'Feed uptime', 8],
    ['ship', 'Shipping accuracy', 6],
    ['links', 'Link health', 4],
  ];
  function trust(m) {
    if (!m) return { score: 0, label: '—', color: 'var(--text-3)', signals: [], public: [] };
    return memo('trust' + m.id + (m.rating || ''), () => {
      const a = ixOf(m);
      const sub = {
        biz: a.bizVerified ? 1 : m.verified ? 0.55 : 0.1,
        age: clamp((a.accountAgeDays || 200) / 900, 0, 1),
        rating: clamp((m.rating - 3) / 2, 0, 1),
        verifiedRatio: clamp((a.verifiedReviewRatio || 50) / 100, 0, 1),
        complaint: clamp(1 - (a.complaintRate || 2) / 5, 0, 1),
        resolution: clamp((a.resolution || 70) / 100, 0, 1),
        response: clamp((a.responseRate || 70) / 100, 0, 1),
        orders: clamp((a.verifiedOrderRate || 60) / 100, 0, 1),
        priceAcc: clamp(((a.priceAccuracy || 92) - 70) / 30, 0, 1),
        feed: clamp(((a.feedUptime || 90) - 55) / 45, 0, 1),
        ship: clamp(((a.shipAccuracy || 88) - 55) / 45, 0, 1),
        links: clamp(1 - (a.brokenLinkRate || 1) / 8, 0, 1),
      };
      let score = 0;
      const signals = trustDefs.map((d) => {
        const pts = sub[d[0]] * d[2];
        score += pts;
        return { key: d[0], label: d[1], weight: d[2], pts: r1(pts), pct: Math.round(sub[d[0]] * 100) };
      });
      const reports = a.communityReports || 0;
      const penalty = clamp(reports * 0.22, 0, 8);
      score = clamp(Math.round(score - penalty), 0, 100);
      const label = score >= 90 ? 'Highly trusted' : score >= 78 ? 'Trusted' : score >= 65 ? 'Generally reliable' : score >= 50 ? 'Mixed signals' : 'Low trust';
      const color = score >= 78 ? 'var(--ok)' : score >= 65 ? 'var(--acc-text)' : score >= 50 ? 'var(--warn)' : 'var(--danger)';
      /* public-facing breakdown — six plain-language signals, no internal weights */
      const pub = [
        { label: 'Business verified', value: a.bizVerified ? 'Verified company' : 'Not verified', pct: Math.round(sub.biz * 100), good: !!a.bizVerified },
        { label: 'Pricing accuracy', value: r1(a.priceAccuracy || 92) + ' %', pct: Math.round(sub.priceAcc * 100), good: (a.priceAccuracy || 92) >= 95 },
        { label: 'Customer satisfaction', value: r1(m.rating) + ' / 5 from ' + H.num(m.reviews) + ' reviews', pct: Math.round(sub.rating * 100), good: m.rating >= 4.3 },
        { label: 'Shipping reliability', value: r1(a.deliveryOnTime || 85) + ' % on time', pct: Math.round(clamp(((a.deliveryOnTime || 85) - 50) / 50, 0, 1) * 100), good: (a.deliveryOnTime || 85) >= 90 },
        { label: 'Complaint resolution', value: (a.resolution || 70) + ' % resolved', pct: Math.round(sub.resolution * 100), good: (a.resolution || 70) >= 85 },
        { label: 'Data freshness', value: r1(a.feedUptime || 90) + ' % feed uptime', pct: Math.round(sub.feed * 100), good: (a.feedUptime || 90) >= 97 },
      ];
      return { score, label, color, signals, public: pub, penalty: r1(penalty), reports };
    });
  }
  function trustHistory(m, days) {
    const t = trust(m), a = ixOf(m), series = a.trustSeries || [];
    const n = Math.min(days || 30, series.length || 1);
    const slice = series.slice(series.length - n);
    const pts = slice.map((off) => clamp(Math.round(t.score + off - (slice[slice.length - 1] || 0)), 0, 100));
    if (pts.length) pts[pts.length - 1] = t.score;
    const first = pts[0] || t.score;
    return { pts, min: Math.min.apply(null, pts), max: Math.max.apply(null, pts), first, last: t.score, delta: t.score - first };
  }

  /* ================= MERCHANT RISK ENGINE (internal) ================= */
  function risk(m) {
    if (!m) return { score: 0, level: 'LOW', color: 'var(--ok)', signals: [], events: [] };
    return memo('risk' + m.id, () => {
      const a = ixOf(m);
      const events = (S.ix.riskEvents || []).filter((e) => e.merchantId === m.id).sort((x, y) => y.ts - x.ts);
      const sev = { LOW: 4, MEDIUM: 10, HIGH: 18, CRITICAL: 30 };
      const signals = [];
      const add = (label, pts, detail) => { if (pts > 0) signals.push({ label, pts: Math.round(pts), detail }); };
      add('Unverified ownership', a.bizVerified ? 0 : 16, a.bizVerified ? '' : 'No matching business register entry on file');
      add('Complaint rate', clamp((a.complaintRate - 1.2) * 6, 0, 18), r1(a.complaintRate) + ' % vs 1.2 % platform median');
      add('Feed instability', clamp((97 - a.feedUptime) * 0.55, 0, 18), r1(a.feedUptime) + ' % uptime');
      add('Broken outbound links', clamp(a.brokenLinkRate * 1.7, 0, 16), r1(a.brokenLinkRate) + ' % of links failing');
      add('Price accuracy drift', clamp((97 - a.priceAccuracy) * 0.9, 0, 14), r1(a.priceAccuracy) + ' % of offers match landing page');
      add('Community reports', clamp(a.communityReports * 0.5, 0, 12), a.communityReports + ' reports in 90 days');
      add('Low response rate', clamp((80 - a.responseRate) * 0.22, 0, 10), a.responseRate + ' % of messages answered');
      events.forEach((e) => add('Event: ' + e.kind.replace(/_/g, ' '), sev[e.severity] * 0.55, e.text));
      let score = clamp(Math.round(signals.reduce((s, x) => s + x.pts, 0)), 0, 100);
      const level = score >= 62 ? 'CRITICAL' : score >= 42 ? 'HIGH' : score >= 22 ? 'MEDIUM' : 'LOW';
      const color = level === 'CRITICAL' ? 'var(--danger)' : level === 'HIGH' ? 'var(--danger-2)' : level === 'MEDIUM' ? 'var(--warn)' : 'var(--ok)';
      const rules = signals.filter((s) => s.pts >= 6).map((s) => s.label);
      return { score, level, color, signals: signals.sort((x, y) => y.pts - x.pts), events, rules };
    });
  }

  /* ================= REVIEW FRAUD DETECTION (internal) ================= */
  function reviewTrust(r) {
    if (!r) return { score: 100, level: 'High confidence', color: 'var(--ok)', signals: [] };
    return memo('rt' + r.id, () => {
      const flags = (S.ix.reviewFlags || {})[r.id] || {};
      const all = S.reviews;
      const user = S.users.find((u) => u.id === r.userId);
      const ageDays = flags.accountAgeDays !== undefined ? flags.accountAgeDays : user ? Math.round((NOW - user.joined) / DAY) : 400;
      const signals = [];
      let penalty = 0;
      const pen = (label, pts, detail) => { penalty += pts; signals.push({ label, pts, detail }); };
      if (flags.duplicateOf) pen('Duplicate text', 34, 'Body matches another published review');
      if (flags.burst) pen('Arrived inside a review burst', 24, 'Cluster ' + flags.burst);
      if (ageDays < 14) pen('Very young account', 14, 'Account is ' + ageDays + ' days old');
      if (!r.verifiedPurchase) pen('No verified purchase', 10, 'No order match on file');
      /* same device / session across reviews */
      if (flags.device) {
        const same = Object.keys(S.ix.reviewFlags || {}).filter((k) => (S.ix.reviewFlags[k] || {}).device === flags.device).length;
        if (same > 2) pen('Shared device fingerprint', 16, same + ' reviews from one device');
      }
      const sameTargetSameUser = all.filter((x) => x.userId === r.userId && x.type === r.type && x.targetId === r.targetId).length;
      if (sameTargetSameUser > 1) pen('Repeated target', 8, sameTargetSameUser + ' reviews on the same entity');
      const txtLen = (r.text || '').length;
      if (txtLen < 60) pen('Very short body', 6, txtLen + ' characters');
      const score = clamp(100 - penalty, 0, 100);
      const level = score >= 85 ? 'High confidence' : score >= 65 ? 'Normal' : score >= 45 ? 'Needs review' : 'Suspicious';
      const color = score >= 85 ? 'var(--ok)' : score >= 65 ? 'var(--text-2)' : score >= 45 ? 'var(--warn)' : 'var(--danger)';
      return { score, level, color, signals, ageDays };
    });
  }
  function dupClusters() {
    return memo('dupc', () => {
      const flags = S.ix.reviewFlags || {};
      const groups = {};
      Object.keys(flags).forEach((id) => {
        const c = flags[id].cluster;
        if (!c) return;
        (groups[c] = groups[c] || []).push(+id);
      });
      return Object.keys(groups).map((c) => {
        const ids = groups[c];
        const rows = ids.map((id) => S.reviews.find((r) => r.id === id)).filter(Boolean);
        const base = rows[0];
        const pairs = rows.slice(1).map((r) => ({
          id: r.id, pct: Math.round(similarity(base.text, r.text) * 100),
          user: (S.users.find((u) => u.id === r.userId) || {}).nick || '—',
          rating: r.rating, date: r.date,
        })).sort((a, b) => b.pct - a.pct);
        const strong = pairs.filter((p) => p.pct >= 55);
        const target = base.type === 'merchant' ? (M(base.targetId) || {}).name : (P(base.targetId) || {}).name;
        return {
          cluster: c, targetType: base.type, targetId: base.targetId, target: target || '—',
          count: strong.length + 1, baseId: base.id, baseText: base.text, baseUser: (S.users.find((u) => u.id === base.userId) || {}).nick || '—',
          pairs: strong.slice(0, 6), avgPct: Math.round(avg(strong.map((p) => p.pct)) || 0),
          ids: [base.id].concat(strong.map((p) => p.id)),
        };
      }).filter((c) => c.avgPct >= 55 && c.count > 1).sort((a, b) => b.count - a.count);
    });
  }
  function burst(merchantId) {
    return memo('burst' + merchantId, () => {
      const rows = S.reviews.filter((r) => r.type === 'merchant' && r.targetId === merchantId);
      const buckets = {};
      rows.forEach((r) => { const k = Math.floor((NOW - r.date) / HOUR); buckets[k] = (buckets[k] || 0) + 1; });
      const hours = [];
      for (let i = 71; i >= 0; i--) hours.push(buckets[i] || 0);
      const daily = rows.filter((r) => r.date > NOW - 30 * DAY).length / 30;
      const peak = Math.max.apply(null, hours.concat([0]));
      const ratio = daily > 0 ? r1(peak / (daily / 24)) : peak;
      return { hours, baseline: r1(daily), peak, ratio, flagged: peak >= 6 && ratio >= 8, window: '72 h' };
    });
  }
  function manipulation(merchantId) {
    return memo('manip' + merchantId, () => {
      const rows = S.reviews.filter((r) => r.type === 'merchant' && r.targetId === merchantId).sort((a, b) => a.date - b.date);
      const days = 90, series = [];
      let running = [];
      for (let d = days; d >= 0; d--) {
        const upto = rows.filter((r) => r.date <= NOW - d * DAY);
        series.push(upto.length ? r2(avg(upto.map((r) => r.rating))) : 0);
      }
      const spikes = [];
      for (let i = 7; i < series.length; i++) {
        const delta = series[i] - series[i - 7];
        if (Math.abs(delta) >= 0.45 && series[i - 7] > 0) spikes.push({ dayAgo: series.length - 1 - i, delta: r2(delta), dir: delta > 0 ? 'up' : 'down' });
      }
      const verdict = spikes.length ? (spikes[spikes.length - 1].dir === 'up' ? 'Positive manipulation pattern' : 'Negative campaign pattern') : 'No manipulation pattern detected';
      return { series, spikes, verdict, flagged: !!spikes.length };
    });
  }
  function userTrust(u, extra) {
    const ageDays = u ? Math.round((NOW - u.joined) / DAY) : 0;
    const rev = (u && u.reviews) || 0, help = (u && u.helpful) || 0;
    const reports = (extra && extra.reportsUpheld) || 0, spam = (extra && extra.spam) || 0;
    let score = 40 + clamp(ageDays / 12, 0, 20) + clamp(rev * 1.6, 0, 14) + clamp(help / 12, 0, 14) + (u && u.verified ? 8 : 0) + clamp(reports * 2, 0, 8) - spam * 12;
    score = clamp(Math.round(score), 0, 100);
    return {
      score, level: score >= 80 ? 'Trusted contributor' : score >= 60 ? 'Established' : score >= 40 ? 'New' : 'Restricted',
      signals: [
        { label: 'Account age', value: ageDays + ' days' },
        { label: 'Verified purchases', value: (u && u.verified) ? 'yes' : 'no' },
        { label: 'Helpful votes received', value: H.num(help) },
        { label: 'Reviews published', value: rev + '' },
        { label: 'Reports upheld', value: reports + '' },
        { label: 'Spam strikes', value: spam + '' },
      ],
    };
  }
  /* community abuse prototype limits */
  const abuseLimits = [
    { key: 'post_rate', label: 'Posting rate', value: '5 posts / 10 min', applies: 'All accounts' },
    { key: 'review_rate', label: 'Review rate', value: '3 reviews / day', applies: 'All accounts' },
    { key: 'dup_post', label: 'Duplicate post detection', value: '≥ 85 % similarity blocked', applies: 'All accounts' },
    { key: 'links', label: 'Link frequency', value: 'max 2 links / post', applies: 'Accounts < 30 days' },
    { key: 'new_account', label: 'New-account restriction', value: 'no deal submission in first 48 h', applies: 'Accounts < 2 days' },
    { key: 'reports', label: 'Report threshold', value: '3 upheld reports → review queue', applies: 'All accounts' },
  ];

  /* ================= PRODUCT MATCHING ENGINE 2.0 ================= */
  function match(item) {
    return memo('match' + item.id, () => {
      const cands = S.products;
      const brandOf = (p) => (S.brands.find((b) => b.id === p.brandId) || {}).name || '';
      let best = null;
      cands.forEach((p) => {
        const parts = [];
        let sc = 0;
        if (item.ean && p.ean && item.ean === p.ean) { sc += 50; parts.push({ label: 'EAN exact match', pts: 50 }); }
        const bAlias = (S.ix.brandAliases || []).find((a) => a.canonical === brandOf(p));
        const brandRaw = item.brandRaw || '';
        const brandHit = brandRaw && (H.norm(brandRaw) === H.norm(brandOf(p)) || (bAlias && bAlias.aliases.some((x) => H.norm(x) === H.norm(brandRaw))));
        if (brandHit) { sc += 15; parts.push({ label: 'Brand exact' + (H.norm(brandRaw) !== H.norm(brandOf(p)) ? ' (alias)' : ''), pts: 15 }); }
        else if (brandRaw && H.norm(item.raw).indexOf(H.norm(brandOf(p))) >= 0) { sc += 9; parts.push({ label: 'Brand found in title', pts: 9 }); }
        const sim = similarity(item.raw, p.name + ' ' + p.pack + ' ' + brandOf(p));
        const simPts = Math.round(sim * 22);
        if (simPts > 0) { sc += simPts; parts.push({ label: 'Title similarity ' + Math.round(sim * 100) + ' %', pts: simPts }); }
        const packHit = item.packRaw && H.norm(item.packRaw) === H.norm(p.pack);
        if (packHit) { sc += 10; parts.push({ label: 'Package size match', pts: 10 }); }
        else if (item.packRaw && (p.packs || []).some((k) => H.norm(k) === H.norm(item.packRaw))) { sc += 6; parts.push({ label: 'Known alternate pack', pts: 6 }); }
        else if (item.packRaw) parts.push({ label: 'Package size differs (' + item.packRaw + ' vs ' + p.pack + ')', pts: -12 });
        const varHit = item.variantRaw && (p.variants || []).some((v) => H.norm(v) === H.norm(item.variantRaw));
        if (varHit) { sc += 7; parts.push({ label: 'Variant match', pts: 7 }); }
        const ingHit = (p.ingredients || []).some((g) => H.norm(item.raw).indexOf(H.norm(g).split(' ')[0]) >= 0);
        if (ingHit) { sc += 5; parts.push({ label: 'Ingredient set overlap', pts: 5 }); }
        if (item.packRaw && !packHit && !(p.packs || []).some((k) => H.norm(k) === H.norm(item.packRaw))) sc -= 12;
        const score = clamp(Math.round(sc), 0, 100);
        if (!best || score > best.score) best = { product: p, score, parts };
      });
      const score = best ? best.score : 0;
      const level = score >= 100 ? 'Exact' : score >= 90 ? 'Very high' : score >= 80 ? 'High' : score >= 65 ? 'Possible' : 'Manual review';
      const color = score >= 90 ? 'var(--ok)' : score >= 80 ? 'var(--acc-text)' : score >= 65 ? 'var(--warn)' : 'var(--danger)';
      const bucket = score >= 90 ? 'auto' : score >= 65 ? 'confirm' : 'unmatched';
      return { score, level, color, bucket, product: best ? best.product : null, parts: best ? best.parts.filter((x) => x.pts !== 0) : [] };
    });
  }
  function variantGuard(a, b) {
    const reasons = [];
    if (!a || !b) return { safe: false, reasons: ['missing entity'] };
    if (a.pack !== b.pack) reasons.push('Different pack size (' + a.pack + ' vs ' + b.pack + ')');
    if (a.servings !== b.servings) reasons.push('Different serving count (' + a.servings + ' vs ' + b.servings + ')');
    if (a.brandId !== b.brandId) reasons.push('Different brand');
    if (H.norm((a.ingredients || []).join()) !== H.norm((b.ingredients || []).join())) reasons.push('Different formulation');
    if ((a.variants || []).join() !== (b.variants || []).join()) reasons.push('Different flavour set');
    return { safe: reasons.length === 0, reasons };
  }
  function clusters() {
    return memo('clusters', () => {
      const groups = {};
      S.feedItems.forEach((it) => {
        const m = match(it);
        if (!m.product || m.score < 65) return;
        (groups[m.product.id] = groups[m.product.id] || []).push({ item: it, m });
      });
      return Object.keys(groups).map((pid) => {
        const p = P(pid), listings = groups[pid];
        return {
          productId: p.id, product: p.name, slug: p.slug, count: listings.length,
          listings: listings.map((l) => ({
            id: l.item.id, merchant: (M(l.item.merchantId) || {}).name || '—', merchantId: l.item.merchantId,
            raw: l.item.raw, price: l.item.price, score: l.m.score, level: l.m.level, color: l.m.color,
          })),
        };
      }).filter((c) => c.count > 1).sort((a, b) => b.count - a.count);
    });
  }
  function matchBuckets() {
    return memo('mbuckets', () => {
      const out = { auto: [], confirm: [], unmatched: [], conflicts: [], candidates: S.ix.newProductCandidates || [] };
      S.feedItems.forEach((it) => {
        const m = match(it);
        const row = { item: it, m, merchant: (M(it.merchantId) || {}).name || '—' };
        if (it.status === 'compliance_hold') out.conflicts.push(row);
        else out[m.bucket].push(row);
      });
      return out;
    });
  }
  function completion(productId) {
    const ck = 'cmpl' + productId;
    if (cache[ck]) return cache[ck];
    const p = P(productId);
    if (!p) return { pct: 0, missing: [] };
    const fields = [['EAN', !!p.ean], ['Brand', !!p.brandId], ['Pack size', !!p.pack], ['Category', !!p.categoryId], ['Ingredients', (p.ingredients || []).length > 0], ['Servings', !!p.servings], ['Description', (p.desc || '').length > 80], ['Variants', (p.variants || []).length > 0]];
    const have = fields.filter((f) => f[1]).length;
    cache[ck] = { pct: Math.round((have / fields.length) * 100), missing: fields.filter((f) => !f[1]).map((f) => f[0]), fields };
    return cache[ck];
  }

  /* ================= PRICE INTELLIGENCE ================= */
  function histStats(p) {
    return memo('hs' + p.id, () => {
      const h = p.hist.min, n = h.length;
      const tail = (d) => h.slice(Math.max(0, n - d));
      return {
        avg7: r2(avg(tail(7))), avg30: r2(avg(tail(30))), avg90: r2(avg(tail(90))), avg365: r2(avg(h)),
        low: r2(Math.min.apply(null, h)), high: r2(Math.max.apply(null, h)), median: r2(med(h)),
        low30: r2(Math.min.apply(null, tail(30))), low90: r2(Math.min.apply(null, tail(90))),
        volatility: r1((Math.max.apply(null, tail(90)) - Math.min.apply(null, tail(90))) / (avg(tail(90)) || 1) * 100),
        cur: r2(h[n - 1]),
      };
    });
  }
  function priceBadge(cur, st) {
    const ratio = cur / (st.avg90 || cur || 1);
    if (cur <= st.low90 * 1.02) return { label: 'Exceptional price', color: 'var(--ok)', tone: 'ok', explain: 'Within 2 % of the 90-day low.' };
    if (ratio <= 0.94) return { label: 'Good price', color: 'var(--acc-text)', tone: 'acc', explain: r1((1 - ratio) * 100) + ' % below the 90-day average.' };
    if (ratio <= 1.06) return { label: 'Typical price', color: 'var(--text-2)', tone: 'neutral', explain: 'In line with the 90-day average.' };
    return { label: 'Above average', color: 'var(--warn)', tone: 'warn', explain: r1((ratio - 1) * 100) + ' % above the 90-day average.' };
  }
  function timing(cur, st) {
    const gap = st.low90 ? (cur / st.low90 - 1) * 100 : 99;
    if (gap <= 3) return { label: 'Strong time to buy', color: 'var(--ok)', text: 'Current price is within ' + r1(gap) + ' % of the 90-day low.' };
    if (gap <= 10) return { label: 'Reasonable time to buy', color: 'var(--acc-text)', text: r1(gap) + ' % above the 90-day low.' };
    if (gap <= 22) return { label: 'Worth waiting', color: 'var(--warn)', text: r1(gap) + ' % above the 90-day low; drops of this size happened before.' };
    return { label: 'Poor timing', color: 'var(--danger)', text: r1(gap) + ' % above the 90-day low.' };
  }
  function forecast(p) {
    return memo('fc' + p.id, () => {
      const st = histStats(p), h = p.hist.min, n = h.length;
      const ma7 = avg(h.slice(n - 7)), ma30 = avg(h.slice(n - 30)), ma90 = avg(h.slice(n - 90));
      const spread = st.volatility;
      let label = 'Likely stable', color = 'var(--text-2)';
      if (spread > 26) { label = 'Highly volatile'; color = 'var(--warn)'; }
      else if (ma7 < ma30 * 0.975 && ma30 <= ma90) { label = 'Downward trend'; color = 'var(--ok)'; }
      else if (ma7 > ma30 * 1.025 && ma30 >= ma90) { label = 'Upward trend'; color = 'var(--danger)'; }
      return {
        label, color, ma7: r2(ma7), ma30: r2(ma30), ma90: r2(ma90),
        note: 'Trend indicator derived from 7/30/90-day moving averages of the cheapest total. Not a guarantee.',
      };
    });
  }
  function priceConfidence(o) {
    const ck = 'pc' + o.id + o.price + o.updated;
    if (cache[ck]) return cache[ck];
    const m = M(o.merchantId), a = ixOf(m);
    const ageH = (NOW - o.updated) / HOUR;
    const signals = [];
    let sc = 100;
    const hit = (label, pts, ok) => { if (!ok) { sc -= pts; signals.push({ label, ok: false, pts: -pts }); } else signals.push({ label, ok: true, pts: 0 }); };
    hit('Fresh feed (< 24 h)', 24, ageH <= 24);
    hit('Merchant verified', 12, !!(m && m.verified));
    hit('Historically consistent', 22, !(o.ix && o.ix.anomaly));
    hit('Valid currency & price', 18, o.price > 0);
    hit('Stock state present', 10, !!o.availability);
    hit('Shipping known', 8, !!(m && m.zones));
    hit('Link healthy', 6, !(o.ix && o.ix.link));
    const score = clamp(Math.round(sc), 0, 100);
    cache[ck] = {
      score, signals, ageHours: r1(ageH),
      level: score >= 88 ? 'High' : score >= 70 ? 'Moderate' : score >= 50 ? 'Low' : 'Unreliable',
      color: score >= 88 ? 'var(--ok)' : score >= 70 ? 'var(--acc-text)' : score >= 50 ? 'var(--warn)' : 'var(--danger)',
    };
    return cache[ck];
  }
  function anomalies() {
    return memo('anom', () => {
      const out = [];
      S.products.forEach((p) => {
        const os = S.offers.filter((o) => o.productId === p.id);
        const prices = os.map((o) => o.price).filter((v) => v > 0);
        const m = med(prices);
        if (!m) return;
        os.forEach((o) => {
          let kind = null, diff = o.price ? (o.price / m - 1) * 100 : -100;
          if (o.price === 0) kind = 'Zero price';
          else if (o.price < m * 0.45) kind = 'Far below market';
          else if (o.price > m * 2.2) kind = 'Far above market';
          if (!kind) return;
          out.push({
            offerId: o.id, productId: p.id, product: p.name, slug: p.slug,
            merchantId: o.merchantId, merchant: (M(o.merchantId) || {}).name || '—',
            price: o.price, median: r2(m), diff: r1(diff), kind,
            confidence: priceConfidence(o).score,
            state: (o.ix && o.ix.reviewState) || 'open',
          });
        });
      });
      (S.ix.feedDiff || []).filter((d) => d.kind === 'rejected').forEach((d, i) => {
        out.push({ offerId: null, productId: null, product: d.product, merchant: '(import validation)', merchantId: null, price: d.after, median: d.before, diff: null, kind: d.note || 'Rejected at import', confidence: 0, state: 'rejected' });
      });
      return out.sort((a, b) => Math.abs(b.diff || 999) - Math.abs(a.diff || 999));
    });
  }
  function fakeDiscount(o) {
    if (!o || !o.oldPrice) return null;
    const p = P(o.productId);
    if (!p) return null;
    const st = histStats(p);
    const claimedDisc = Math.round((1 - o.price / o.oldPrice) * 100);
    const refVsMedian = o.oldPrice / (st.median || o.oldPrice);
    if (refVsMedian < 1.25) return null;
    return {
      claimed: o.oldPrice, now: o.price, disc: claimedDisc, median90: st.avg90,
      note: 'Reference price is ' + r1((refVsMedian - 1) * 100) + ' % above the 12-month median. Discount percentage withheld until verified.',
      raisedAt: o.ix && o.ix.refPriceRaisedAt,
    };
  }
  function unitEconomics(o, p) {
    if (!p) return {};
    const grams = parseFloat(p.pack) || 0;
    const isG = /g$/.test(p.pack) || /kg/.test(p.pack);
    const kg = /kg/.test(p.pack) ? grams * 1000 : grams;
    return {
      perServing: p.servings ? r2(o.price / p.servings) : null,
      perGram: isG && kg ? r2(o.price / kg * 100) : null,
      perUnitLabel: isG ? 'per 100 g' : 'per unit',
    };
  }

  /* ================= COUPONS ================= */
  const couponStateMeta = {
    verified: ['Verified', 'var(--ok)', 'Checked by the Comparo data team'],
    merchant: ['Merchant verified', 'var(--ok)', 'Confirmed by the merchant'],
    community: ['Community verified', 'var(--acc-text)', 'Confirmed by user reports'],
    unverified: ['Unverified', 'var(--text-3)', 'Not enough reports yet'],
    expired: ['Expired', 'var(--text-4)', 'End date has passed'],
    invalid: ['Invalid', 'var(--danger)', 'Reported as not working'],
  };
  function couponMeta(c) {
    if (!c) return null;
    const a = c.ix || {};
    let state = a.state || 'unverified';
    if (c.ends < NOW) state = 'expired';
    const rep = a.reports || { worked: 0, failed: 0 };
    const total = rep.worked + rep.failed;
    const rate = total >= 12 ? Math.round((rep.worked / total) * 100) : null;
    const meta = couponStateMeta[state] || couponStateMeta.unverified;
    return {
      state, label: meta[0], color: meta[1], why: meta[2], reports: rep, total,
      successRate: rate, successText: rate !== null ? 'Worked for ' + rate + ' % of ' + total + ' users' : 'Needs ' + (12 - total) + ' more reports',
      usable: state !== 'expired' && state !== 'invalid',
      verifiedBy: a.verifiedBy,
    };
  }

  /* ================= DEAL SCORE ================= */
  function dealScore(d, ctx) {
    const p = d.productId ? P(d.productId) : null;
    const m = d.merchantId ? M(d.merchantId) : null;
    const st = p ? histStats(p) : null;
    const price = ctx && ctx.total !== undefined ? ctx.total : d.price || (p ? p.base : 0);
    const parts = [];
    let sc = 0;
    const add = (label, pts) => { sc += pts; parts.push({ label, pts: Math.round(pts) }); };
    if (st && price) {
      const vs = 1 - price / (st.avg90 || price);
      add('Discount vs 90-day average', clamp(vs * 120, -10, 34));
      add('Distance to historical low', clamp((1 - (price / (st.low || price) - 1) * 4) * 18, 0, 18));
    } else add('No price history', 0);
    if (m) {
      const t = trust(m);
      add('Shop trust', clamp((t.score - 50) / 50 * 18, -6, 18));
    }
    const cm = d.couponId ? couponMeta(S.coupons.find((c) => c.id === d.couponId)) : null;
    if (cm) add('Coupon validity', cm.state === 'invalid' ? -10 : cm.usable ? 10 : 0);
    if (d.availability) add('Stock', d.availability === 'in_stock' ? 8 : d.availability === 'low_stock' ? 4 : 0);
    const ageH = d.ts ? (NOW - d.ts) / HOUR : 12;
    add('Deal freshness', clamp(10 - ageH / 12, -4, 10));
    const score = clamp(Math.round(50 + sc), 0, 100);
    return {
      score, parts,
      label: score >= 85 ? 'Outstanding deal' : score >= 72 ? 'Strong deal' : score >= 58 ? 'Decent deal' : score >= 44 ? 'Weak deal' : 'Not a deal',
      color: score >= 72 ? 'var(--ok)' : score >= 58 ? 'var(--acc-text)' : score >= 44 ? 'var(--warn)' : 'var(--danger)',
    };
  }

  /* ================= OFFER STATE ================= */
  function stockConfidence(o) {
    const ageH = (NOW - o.updated) / HOUR;
    const conf = ageH <= 6 ? 'High' : ageH <= 24 ? 'Good' : ageH <= 48 ? 'Moderate' : 'Unknown';
    const label = o.availability === 'out_of_stock' ? 'Out of stock' : o.availability === 'low_stock' ? 'Low stock' : o.availability === 'preorder' ? 'Pre-order' : ageH > 48 ? 'Unknown' : 'In stock';
    return {
      label, confidence: conf, ageHours: r1(ageH),
      color: label === 'In stock' ? 'var(--ok)' : label === 'Low stock' ? 'var(--warn)' : label === 'Pre-order' ? 'var(--info)' : label === 'Unknown' ? 'var(--text-3)' : 'var(--danger)',
      note: 'Stock state from feed ' + H.ago(o.updated) + '; confidence ' + conf.toLowerCase() + '.',
    };
  }
  function offerLifecycle(endsTs) {
    if (!endsTs) return { state: 'Active', color: 'var(--ok)' };
    const h = (endsTs - NOW) / HOUR;
    if (h < 0) return { state: 'Expired', color: 'var(--text-4)' };
    if (h < 48) return { state: 'Ending soon', color: 'var(--warn)' };
    return { state: 'Active', color: 'var(--ok)' };
  }
  function deliveryReliability(m) {
    if (!m) return null;
    const a = ixOf(m);
    const revs = S.reviews.filter((r) => r.type === 'merchant' && r.targetId === m.id);
    const shipMentions = revs.filter((r) => /deliver|shipping|arrived|courier|parcel/i.test(r.text || '')).length;
    return {
      onTime: r1(a.deliveryOnTime || 85), sample: Math.max(shipMentions, 12),
      text: r1(a.deliveryOnTime || 85) + ' % reported delivery inside the expected window',
      color: (a.deliveryOnTime || 85) >= 92 ? 'var(--ok)' : (a.deliveryOnTime || 85) >= 80 ? 'var(--acc-text)' : 'var(--warn)',
      accuracy: r1(a.shipAccuracy || 88),
    };
  }
  function shippingIntel(m, iso) {
    if (!m) return null;
    const all = S.merchants.filter((x) => x.zones && x.zones[iso]).map((x) => x.zones[iso].cost);
    const mine = m.zones && m.zones[iso] ? m.zones[iso].cost : null;
    const median = r2(med(all));
    if (mine === null) return { ships: false };
    const delta = r2(mine - median);
    return {
      ships: true, cost: mine, median, delta,
      label: delta <= -0.5 ? 'Below market' : delta >= 0.5 ? 'Above market' : 'At market',
      color: delta <= -0.5 ? 'var(--ok)' : delta >= 0.5 ? 'var(--warn)' : 'var(--text-2)',
      freeOver: m.freeOverEur,
    };
  }

  /* ================= COMPARORANK ================= */
  function rank(ctx) {
    const w = Object.assign({}, S.ix.rankWeights, ctx.weights || {});
    const wsum = Object.keys(w).reduce((a, k) => a + w[k], 0) || 100;
    const sub = {
      price: ctx.anomaly ? 0 : clamp(1 - ((ctx.total / (ctx.marketMin || ctx.total)) - 1) / 0.35, 0, 1),
      trust: clamp((ctx.trust || 60) / 100, 0, 1),
      delivery: clamp(1 - ((ctx.deliveryDays || 6) - 2) / 8, 0, 1),
      reviews: clamp(((ctx.rating || 4) - 3) / 2, 0, 1) * clamp(0.55 + Math.log10(1 + (ctx.reviewCount || 50)) / 6, 0, 1),
      freshness: clamp(1 - (ctx.freshnessHours || 12) / 72, 0, 1),
      availability: ctx.availability === 'in_stock' ? 1 : ctx.availability === 'low_stock' ? 0.7 : ctx.availability === 'preorder' ? 0.35 : 0,
      shipping: clamp(1 - (ctx.ship || 0) / ((ctx.shipMedian || 5) * 2 || 10), 0, 1),
    };
    const labelMap = {
      price: 'Price competitiveness', trust: 'Shop trust', delivery: 'Fast delivery', reviews: 'Customer rating',
      freshness: 'Fresh data', availability: 'Verified availability', shipping: 'Shipping cost',
    };
    const parts = [];
    let score = 0;
    Object.keys(w).forEach((k) => {
      const pts = (sub[k] || 0) * w[k] * (100 / wsum);
      score += pts;
      parts.push({ key: k, label: labelMap[k] || k, pts: Math.round(pts), max: Math.round(w[k] * (100 / wsum)) });
    });
    /* offer quality: completeness + valid coupon */
    const quality = (ctx.completeness || 0.8) * 5 + (ctx.hasValidCoupon ? 2 : 0);
    score += quality;
    parts.push({ key: 'quality', label: 'Offer quality', pts: Math.round(quality), max: 7 });
    const penalties = [];
    const pen = (label, pts, hidden) => { score -= pts; penalties.push({ label, pts: -pts, hidden: !!hidden }); };
    if ((ctx.freshnessHours || 0) > 48) pen('Stale data', 10);
    if (ctx.anomaly) pen('Price under review', 14);
    if (ctx.fakeDiscount) pen('Unverified reference price', 8);
    if (ctx.linkFlag) pen('Outbound link problem', 10, true);
    if (ctx.complianceUnknown) pen('Market status not verified', 6);
    if (ctx.riskLevel === 'HIGH') pen('Integrity signals', 6, true);
    if (ctx.riskLevel === 'CRITICAL') pen('Integrity signals', 14, true);
    score = clamp(Math.round(score), 0, 100);
    const label = score >= 90 ? 'Exceptional' : score >= 80 ? 'Excellent' : score >= 70 ? 'Good' : score >= 60 ? 'Fair' : 'Low confidence';
    const color = score >= 90 ? 'var(--ok)' : score >= 80 ? 'var(--acc-text)' : score >= 70 ? 'var(--text-2)' : score >= 60 ? 'var(--warn)' : 'var(--danger)';
    return {
      score, label, color,
      parts: parts.filter((p) => p.pts !== 0).sort((a, b) => b.pts - a.pts),
      penalties: penalties.filter((p) => !p.hidden),
      hiddenPenalties: penalties.filter((p) => p.hidden).length,
      eligibleBestBuy: !ctx.anomaly && !ctx.complianceBlocked && !ctx.complianceUnknown && score >= 60,
      weights: w,
    };
  }

  /* ================= RECOMMENDATIONS & RELATED GRAPH ================= */
  function related(p) {
    if (!p) return [];
    return memo('rel' + p.id, () => {
      const st = histStats(p);
      return S.products.filter((x) => x.id !== p.id).map((x) => {
        const reasons = [];
        let sc = 0;
        if (x.categoryId === p.categoryId) { sc += 30; reasons.push('Same category'); }
        if (x.brandId === p.brandId) { sc += 22; reasons.push('Same brand'); }
        const ing = (p.ingredients || []).filter((g) => (x.ingredients || []).indexOf(g) >= 0).length;
        if (ing) { sc += ing * 14; reasons.push(ing + ' shared ingredient' + (ing > 1 ? 's' : '')); }
        const pr = Math.abs(x.base - p.base) / (p.base || 1);
        if (pr < 0.2) { sc += 14; reasons.push('Similar price band'); }
        const compares = (S.ix.demand || []).find((d) => d.productId === x.id);
        if (compares && compares.compares30 > 40) { sc += 8; reasons.push('Frequently compared'); }
        if (x.unit === p.unit) sc += 4;
        return { product: x, score: sc, reasons };
      }).filter((r) => r.score >= 30).sort((a, b) => b.score - a.score);
    });
  }
  function similarShops(m) {
    if (!m) return [];
    return memo('sim' + m.id, () => {
      const mine = {};
      S.offers.filter((o) => o.merchantId === m.id).forEach((o) => { const p = P(o.productId); if (p) mine[p.categoryId] = 1; });
      const t0 = trust(m);
      return S.merchants.filter((x) => x.id !== m.id).map((x) => {
        const cats = {};
        S.offers.filter((o) => o.merchantId === x.id).forEach((o) => { const p = P(o.productId); if (p) cats[p.categoryId] = 1; });
        const overlapMarkets = (m.shipsTo || []).filter((c) => (x.shipsTo || []).indexOf(c) >= 0).length;
        const overlapCats = Object.keys(mine).filter((c) => cats[c]).length;
        const t = trust(x);
        const reasons = [];
        let sc = overlapMarkets * 4 + overlapCats * 7;
        if (overlapMarkets) reasons.push(overlapMarkets + ' shared markets');
        if (overlapCats) reasons.push(overlapCats + ' shared categories');
        if (Math.abs(t.score - t0.score) < 10) { sc += 8; reasons.push('Similar trust level'); }
        if (Math.abs(x.rating - m.rating) < 0.3) { sc += 6; reasons.push('Similar rating'); }
        return { merchant: x, score: sc, reasons, trust: t };
      }).sort((a, b) => b.score - a.score).slice(0, 6);
    });
  }

  /* ================= TRENDS ================= */
  function trends() {
    return memo('trends', () => {
      const demand = S.ix.demand || [];
      const decay = (v, d) => v * Math.pow(0.94, d);
      const products = demand.map((d) => {
        const p = P(d.productId);
        const score = Math.round(decay(d.searches30, 0) * 0.4 + d.views30 * 0.2 + d.saves30 * 2.4 + d.compares30 * 2.1 + d.clicks30 * 0.02 + d.trend7 * 6);
        return { id: d.productId, name: p ? p.name : '—', slug: p ? p.slug : '', score, trend7: d.trend7, offers: d.offers, searches: d.searches30 };
      }).sort((a, b) => b.score - a.score);
      const shops = S.merchants.map((m) => {
        const clicks = S.offers.filter((o) => o.merchantId === m.id).reduce((a, b) => a + b.clicks30, 0);
        const revs = S.reviews.filter((r) => r.type === 'merchant' && r.targetId === m.id && r.date > NOW - 30 * DAY).length;
        const t = trust(m);
        return { id: m.id, name: m.name, slug: m.slug, score: Math.round(clicks * 0.05 + revs * 12 + t.score * 0.6), clicks, reviews: revs, trust: t.score, trend: ixOf(m).trend };
      }).sort((a, b) => b.score - a.score);
      const brands = S.brands.map((b) => {
        const ps = S.products.filter((p) => p.brandId === b.id);
        const d = ps.reduce((a, p) => { const x = demand.find((y) => y.productId === p.id); return a + (x ? x.searches30 + x.saves30 * 3 : 0); }, 0);
        return { id: b.id, name: b.name, slug: b.slug, score: Math.round(d), products: ps.length };
      }).sort((a, b) => b.score - a.score);
      const cats = S.categories.map((c) => {
        const ps = S.products.filter((p) => p.categoryId === c.id);
        const d = ps.reduce((a, p) => { const x = demand.find((y) => y.productId === p.id); return a + (x ? x.searches30 : 0); }, 0);
        return { id: c.id, name: c.name, slug: c.slug, score: d, products: ps.length };
      }).sort((a, b) => b.score - a.score);
      const searches = (S.searchQueries || []).slice().sort((a, b) => b.trend - a.trend).slice(0, 10);
      return { products, shops, brands, categories: cats, searches };
    });
  }

  /* ================= MARKET INTELLIGENCE ================= */
  function coverage() {
    return memo('cov', () => {
      const raw = (S.ix.markets || []).map((mk) => mk.merchants * 6 + mk.products * 0.9 + mk.reviews * 0.35 + mk.offers * 0.05);
      const top = Math.max.apply(null, raw.concat([1]));
      return (S.ix.markets || []).map((mk, i) => {
      const score = clamp(Math.round((raw[i] / top) * 100), 0, 100);
      return Object.assign({}, mk, {
        score,
        label: score >= 80 ? 'Strong coverage' : score >= 60 ? 'Adequate' : score >= 40 ? 'Thin' : 'Weak coverage',
        color: score >= 80 ? 'var(--ok)' : score >= 60 ? 'var(--acc-text)' : score >= 40 ? 'var(--warn)' : 'var(--danger)',
        gap: score >= 80 ? null : mk.merchants < 4 ? 'Few merchants (' + mk.merchants + ')' : mk.reviews < 60 ? 'Weak review coverage' : 'Limited catalogue depth',
      });
      }).sort((a, b) => b.score - a.score);
    });
  }
  function productCoverage(p) {
    const offers = S.offers.filter((o) => o.productId === p.id);
    const merch = new Set(offers.map((o) => o.merchantId)).size;
    const markets = new Set(); offers.forEach((o) => { const m = M(o.merchantId); (m ? m.shipsTo : []).forEach((c) => markets.add(c)); });
    const revs = S.reviews.filter((r) => r.type === 'product' && r.targetId === p.id).length;
    const comp = completion(p.id);
    const score = clamp(Math.round(merch * 11 + markets.size * 2.6 + revs * 3 + comp.pct * 0.25), 0, 100);
    return { merchants: merch, markets: markets.size, offers: offers.length, reviews: revs, completeness: comp.pct, score, label: score >= 75 ? 'Well covered' : score >= 50 ? 'Partial' : 'Thin' };
  }
  function categoryCoverage() {
    return memo('catcov', () => S.categories.map((c) => {
      const ps = S.products.filter((p) => p.categoryId === c.id);
      const offers = S.offers.filter((o) => ps.some((p) => p.id === o.productId));
      const merch = new Set(offers.map((o) => o.merchantId)).size;
      const brands = new Set(ps.map((p) => p.brandId)).size;
      const demand = (S.ix.demand || []).filter((d) => ps.some((p) => p.id === d.productId)).reduce((a, b) => a + b.searches30, 0);
      const missing = ps.reduce((a, p) => a + completion(p.id).missing.length, 0);
      return { id: c.id, name: c.name, slug: c.slug, products: ps.length, offers: offers.length, brands, merchants: merch, demand, missing };
    }).sort((a, b) => b.demand - a.demand));
  }
  function searchSupply() {
    return memo('ssg', () => {
      const rows = (S.ix.demand || []).map((d) => {
        const p = P(d.productId);
        const ratio = d.offers ? d.searches30 / d.offers : d.searches30;
        return {
          productId: d.productId, name: p ? p.name : '—', slug: p ? p.slug : '', searches: d.searches30,
          offers: d.offers, ratio: Math.round(ratio),
          gap: d.offers === 0 ? 'No supply' : d.offers <= 2 && d.searches30 > 120 ? 'High demand / thin supply' : ratio > 90 ? 'Under-supplied' : null,
        };
      }).filter((r) => r.gap).sort((a, b) => b.ratio - a.ratio);
      return { rows, zero: S.ix.zeroSupply || [] };
    });
  }

  /* ================= AFFILIATE & COMMERCIAL ================= */
  function affiliateMetrics(m) {
    if (!m) return null;
    return memo('aff' + m.id, () => {
      const rows = S.affiliate.daily.filter((d) => d.merchantId === m.id);
      const clicks = rows.reduce((a, b) => a + b.clicks, 0);
      const conv = rows.reduce((a, b) => a + b.conv, 0);
      const rev = rows.reduce((a, b) => a + b.revenue, 0);
      const comm = rows.reduce((a, b) => a + b.commission, 0);
      const views = clicks * 14;
      const model = m.affiliate.network === 'Direct' ? 'CPS' : m.affiliate.network === 'Awin' ? 'CPA' : m.affiliate.network === 'Impact' ? 'Hybrid' : 'CPC';
      return {
        clicks, conv, revenue: r2(rev), commission: r2(comm),
        ctr: r1((clicks / views) * 100), cvr: r1((conv / (clicks || 1)) * 100),
        epc: r2(comm / (clicks || 1)), aov: r2(rev / (conv || 1)),
        model, network: m.affiliate.network, commissionRate: m.affiliate.commission, cookie: m.affiliate.cookie,
        series: rows.map((r) => r.clicks), revSeries: rows.map((r) => r.revenue),
        normalized: r2(comm / (clicks || 1)) + ' € per click (' + model + ' normalised to EPC)',
      };
    });
  }
  function commercial(m) {
    if (!m) return null;
    const a = affiliateMetrics(m), t = trust(m);
    const inventory = S.offers.filter((o) => o.merchantId === m.id).length;
    const rel = m.partner ? 22 : m.tier === 'PRO' ? 14 : 6;
    const score = clamp(Math.round(a.cvr * 6 + a.epc * 40 + Math.log10(1 + a.revenue) * 9 + inventory * 0.35 + rel), 0, 100);
    return {
      score, label: score >= 78 ? 'Priority account' : score >= 58 ? 'Growth account' : score >= 38 ? 'Maintain' : 'Low priority',
      color: score >= 78 ? 'var(--ok)' : score >= 58 ? 'var(--acc-text)' : score >= 38 ? 'var(--text-2)' : 'var(--text-3)',
      parts: [
        { label: 'Conversion rate', value: a.cvr + ' %' }, { label: 'EPC', value: '€' + a.epc },
        { label: '30-day revenue', value: '€' + H.num(Math.round(a.revenue)) }, { label: 'Traffic (clicks)', value: H.num(a.clicks) },
        { label: 'Inventory', value: inventory + ' offers' }, { label: 'Relationship', value: m.partner ? 'Partner' : m.tier },
      ],
      trust: t.score,
    };
  }
  function performance(m) {
    if (!m) return null;
    const a = affiliateMetrics(m), t = trust(m), ia = ixOf(m);
    const offers = S.offers.filter((o) => o.merchantId === m.id);
    const fresh = offers.length ? offers.filter((o) => NOW - o.updated < 24 * HOUR).length / offers.length : 0;
    const score = clamp(Math.round(t.score * 0.32 + a.cvr * 5 + fresh * 22 + (ia.feedUptime || 90) * 0.16 + clamp((m.rating - 3) / 2, 0, 1) * 12), 0, 100);
    return {
      score, label: score >= 80 ? 'Excellent' : score >= 66 ? 'Good' : score >= 50 ? 'Fair' : 'Needs work',
      color: score >= 80 ? 'var(--ok)' : score >= 66 ? 'var(--acc-text)' : score >= 50 ? 'var(--warn)' : 'var(--danger)',
      freshness: Math.round(fresh * 100),
      parts: [
        { label: 'Offer quality', value: Math.round(fresh * 100) + ' % fresh' },
        { label: 'Conversion', value: a.cvr + ' %' },
        { label: 'Feed quality', value: r1(ia.feedUptime || 90) + ' % uptime' },
        { label: 'Reviews', value: r1(m.rating) + ' / 5' },
        { label: 'Trust', value: t.score + '/100' },
        { label: 'Data freshness', value: Math.round(fresh * 100) + ' %' },
      ],
    };
  }
  function benchmarks(m) {
    if (!m) return [];
    return memo('bench' + m.id, () => {
      const all = S.merchants;
      const mine = affiliateMetrics(m), ia = ixOf(m);
      const allCtr = all.map((x) => affiliateMetrics(x).ctr), allCvr = all.map((x) => affiliateMetrics(x).cvr);
      const offersFresh = (x) => { const os = S.offers.filter((o) => o.merchantId === x.id); return os.length ? Math.round(os.filter((o) => NOW - o.updated < 24 * HOUR).length / os.length * 100) : 0; };
      const rows = [
        ['Click-through rate', mine.ctr, med(allCtr), '%'],
        ['Conversion rate', mine.cvr, med(allCvr), '%'],
        ['Offer freshness', offersFresh(m), med(all.map(offersFresh)), '%'],
        ['Feed uptime', ia.feedUptime, med(all.map((x) => ixOf(x).feedUptime || 90)), '%'],
        ['Price accuracy', ia.priceAccuracy, med(all.map((x) => ixOf(x).priceAccuracy || 92)), '%'],
        ['Rating', m.rating, med(all.map((x) => x.rating)), '/5'],
        ['On-time delivery', ia.deliveryOnTime, med(all.map((x) => ixOf(x).deliveryOnTime || 85)), '%'],
        ['Complaint rate', ia.complaintRate, med(all.map((x) => ixOf(x).complaintRate || 2)), '%', true],
      ];
      return rows.map((r) => {
        const diff = r[4] ? r[2] - r[1] : r[1] - r[2];
        const equal = Math.abs(r[1] - r[2]) < 0.25;
        const better = !equal && diff > 0;
        return {
          label: r[0], mine: r1(r[1]) + r[3], market: r1(r[2]) + r[3],
          delta: r1(r[1] - r[2]), better,
          color: better ? 'var(--ok)' : equal ? 'var(--text-2)' : 'var(--warn)',
          verdict: equal ? 'at market' : better ? 'above market' : 'below market',
          pct: clamp(Math.round((r[1] / (Math.max(r[1], r[2]) || 1)) * 100), 4, 100),
        };
      });
    });
  }
  function competitiveness(m, iso) {
    if (!m) return null;
    const offers = S.offers.filter((o) => o.merchantId === m.id);
    let cheaper = 0, dearer = 0, best = 0;
    const worst = [];
    offers.forEach((o) => {
      const others = S.offers.filter((x) => x.productId === o.productId && x.price > 0);
      const m2 = med(others.map((x) => x.price));
      if (!m2 || !o.price) return;
      const d = (o.price / m2 - 1) * 100;
      if (d < -1) cheaper++; else if (d > 1) dearer++;
      if (o.price === Math.min.apply(null, others.map((x) => x.price || 9e9))) best++;
      if (d > 10) worst.push({ productId: o.productId, name: (P(o.productId) || {}).name, slug: (P(o.productId) || {}).slug, price: o.price, median: r2(m2), delta: r1(d) });
    });
    const ship = shippingIntel(m, iso || m.country);
    const t = trust(m);
    return {
      offers: offers.length, cheaper, dearer, best,
      pricePct: Math.round((cheaper / (offers.length || 1)) * 100),
      shipping: ship, rating: m.rating, ratingMarket: r1(med(S.merchants.map((x) => x.rating))),
      availability: Math.round(offers.filter((o) => o.availability === 'in_stock').length / (offers.length || 1) * 100),
      trust: t, worst: worst.sort((a, b) => b.delta - a.delta).slice(0, 12),
    };
  }
  function merchantRecs(m) {
    if (!m) return [];
    const c = competitiveness(m), ia = ixOf(m), out = [];
    if (c.shipping && c.shipping.ships && c.shipping.delta > 0.4) out.push({ level: 'warn', text: 'Your shipping to ' + m.country + ' is €' + r2(c.shipping.delta) + ' above the category median (€' + c.shipping.median + ').', action: 'Review shipping' });
    if (c.worst.length) out.push({ level: 'warn', text: c.worst.length + ' products are priced more than 10 % above the market median.', action: 'Open price list' });
    const stale = S.offers.filter((o) => o.merchantId === m.id && NOW - o.updated > 48 * HOUR).length;
    if (stale) out.push({ level: 'warn', text: stale + ' listings have stock data older than 48 hours.', action: 'Refresh feed' });
    if ((ia.feedUptime || 100) < 97) out.push({ level: 'warn', text: 'Feed uptime is ' + r1(ia.feedUptime) + ' %, below the 97 % SLA target.', action: 'Check endpoint' });
    if ((ia.responseRate || 100) < 80) out.push({ level: 'info', text: 'You answer ' + ia.responseRate + ' % of review replies. Merchants above 90 % gain ~3 trust points.', action: 'Open reviews' });
    const unmatched = (S.feedItems || []).filter((f) => f.merchantId === m.id && match(f).bucket !== 'auto').length;
    if (unmatched) out.push({ level: 'info', text: unmatched + ' feed rows need matching confirmation.', action: 'Open match center' });
    if (c.best > 0) out.push({ level: 'ok', text: 'You hold the cheapest total on ' + c.best + ' products — those offers rank first organically.', action: null });
    return out;
  }
  function opportunities(m) {
    if (!m) return [];
    const mine = {};
    S.offers.filter((o) => o.merchantId === m.id).forEach((o) => { mine[o.productId] = 1; });
    return (S.ix.demand || []).filter((d) => !mine[d.productId] && d.offers <= 3 && d.searches30 > 90).map((d) => {
      const p = P(d.productId);
      return {
        productId: d.productId, name: p ? p.name : '—', slug: p ? p.slug : '', searches: d.searches30,
        offers: d.offers, median: r2(med(S.offers.filter((o) => o.productId === d.productId && o.price > 0).map((o) => o.price))),
        note: d.searches30 + ' searches in 30 d, only ' + d.offers + ' merchant' + (d.offers === 1 ? '' : 's') + ' listing it',
      };
    }).sort((a, b) => b.searches - a.searches).slice(0, 8);
  }

  /* ================= BASKET OPTIMIZER ================= */
  function basket(items, ctx) {
    /* items: [{productId, qty}]; ctx.rowsFor(productId) -> [{merchantId, effNum, availNum, ships}] ; ctx.shipFor(merchantId, subtotal) -> number|null */
    const perProduct = items.map((it) => ({ it, rows: (ctx.rowsFor(it.productId) || []).filter((r) => r.ships && r.effNum > 0) }));
    const merchants = {};
    perProduct.forEach((pp) => pp.rows.forEach((r) => { merchants[r.merchantId] = 1; }));
    const mids = Object.keys(merchants).map(Number);
    /* single-shop options: only shops that carry everything */
    const single = mids.map((mid) => {
      const picks = perProduct.map((pp) => { const r = pp.rows.find((x) => x.merchantId === mid); return r ? { r, qty: pp.it.qty } : null; });
      if (picks.some((p) => !p)) return null;
      const sub = r2(picks.reduce((a, p) => a + p.r.effNum * p.qty, 0));
      const ship = ctx.shipFor(mid, sub);
      if (ship === null) return null;
      const m = M(mid), t = trust(m);
      return { merchantId: mid, merchant: m.name, slug: m.slug, sub, ship: r2(ship), total: r2(sub + ship), lines: picks.map((p) => ({ productId: p.r.productId, price: p.r.effNum, qty: p.qty, merchantId: mid })), trust: t.score, trustLabel: t.label, items: picks.length };
    }).filter(Boolean).sort((a, b) => a.total - b.total);
    /* split: cheapest per line, then group by shop and add shipping once */
    const buildSplit = (keyFn) => {
      const chosen = perProduct.map((pp) => {
        const best = pp.rows.slice().sort(keyFn)[0];
        return best ? { r: best, qty: pp.it.qty } : null;
      }).filter(Boolean);
      if (chosen.length !== perProduct.length) return null;
      const groups = {};
      chosen.forEach((c) => { (groups[c.r.merchantId] = groups[c.r.merchantId] || []).push(c); });
      let total = 0;
      const shops = Object.keys(groups).map((mid) => {
        const sub = r2(groups[mid].reduce((a, c) => a + c.r.effNum * c.qty, 0));
        const ship = ctx.shipFor(+mid, sub);
        const s = ship === null ? 0 : ship;
        total += sub + s;
        const m = M(+mid);
        return { merchantId: +mid, merchant: m.name, slug: m.slug, sub, ship: r2(s), lines: groups[mid].map((c) => ({ productId: c.r.productId, price: c.r.effNum, qty: c.qty })), trust: trust(m).score };
      });
      return { shops, total: r2(total), count: shops.length };
    };
    const split = buildSplit((a, b) => a.effNum - b.effNum);
    const lowShip = mids.map((mid) => {
      const picks = perProduct.map((pp) => { const r = pp.rows.find((x) => x.merchantId === mid); return r ? { r, qty: pp.it.qty } : null; });
      if (picks.some((p) => !p)) return null;
      const sub = r2(picks.reduce((a, p) => a + p.r.effNum * p.qty, 0));
      const ship = ctx.shipFor(mid, sub);
      if (ship === null) return null;
      return { merchantId: mid, merchant: M(mid).name, sub, ship: r2(ship), total: r2(sub + ship) };
    }).filter(Boolean).sort((a, b) => a.ship - b.ship || a.total - b.total);
    const trustFirst = single.slice().sort((a, b) => b.trust - a.trust || a.total - b.total);
    /* free shipping nudges */
    const nudges = single.slice(0, 4).map((s) => {
      const m = M(s.merchantId);
      if (!m || !m.freeOverEur || s.sub >= m.freeOverEur || s.ship === 0) return null;
      return { merchantId: s.merchantId, merchant: s.merchant, need: r2(m.freeOverEur - s.sub), threshold: m.freeOverEur, save: s.ship };
    }).filter(Boolean);
    const best = single.length && split ? (split.total < single[0].total - 0.5 ? 'split' : 'single') : single.length ? 'single' : split ? 'split' : null;
    return { single, split, lowShip, trustFirst, nudges, best, perProduct };
  }

  /* ================= PERSONALIZATION ================= */
  function personal(sig) {
    /* sig: {country, saved:[pid], follows:{'brand:1':true,...}, searches:[q], compares:[pid], dealClicks:[mid]} */
    const out = [];
    const savedIds = sig.saved || [];
    const followBrands = Object.keys(sig.follows || {}).filter((k) => k.indexOf('brand:') === 0).map((k) => +k.split(':')[1]);
    const followShops = Object.keys(sig.follows || {}).filter((k) => k.indexOf('merchant:') === 0).map((k) => +k.split(':')[1]);
    const drops = S.products.map((p) => {
      const st = histStats(p);
      const drop = st.avg30 ? r1((1 - st.cur / st.avg30) * 100) : 0;
      const relevant = savedIds.indexOf(p.id) >= 0 ? 'saved' : followBrands.indexOf(p.brandId) >= 0 ? 'brand' : null;
      return { p, drop, relevant, st };
    }).filter((x) => x.drop > 1.5).sort((a, b) => (b.relevant ? 1 : 0) - (a.relevant ? 1 : 0) || b.drop - a.drop);
    out.push({
      key: 'drops', title: 'Price drops you may care about',
      rows: drops.slice(0, 4).map((x) => ({
        productId: x.p.id, name: x.p.name, slug: x.p.slug, drop: x.drop, price: x.st.cur,
        why: x.relevant === 'saved' ? 'Because you saved ' + x.p.name : x.relevant === 'brand' ? 'Because you follow ' + (S.brands.find((b) => b.id === x.p.brandId) || {}).name : 'Popular in ' + (S.countries.find((c) => c.iso === sig.country) || {}).name,
      })),
    });
    const shopDeals = followShops.length ? followShops : S.merchants.filter((m) => m.partner).map((m) => m.id).slice(0, 2);
    out.push({
      key: 'shops', title: followShops.length ? 'Deals from shops you follow' : 'Deals from top-trust shops',
      rows: S.coupons.filter((c) => shopDeals.indexOf(c.merchantId) >= 0 && couponMeta(c).usable).slice(0, 4).map((c) => ({
        code: c.code, title: c.title, merchantId: c.merchantId, merchant: (M(c.merchantId) || {}).name, slug: (M(c.merchantId) || {}).slug,
        state: couponMeta(c).label, stateColor: couponMeta(c).color,
        why: followShops.indexOf(c.merchantId) >= 0 ? 'Because you follow ' + (M(c.merchantId) || {}).name : 'Because this shop has a high trust score',
      })),
    });
    const simBase = savedIds.length ? savedIds : (sig.compares || []).slice(0, 1);
    const sim = [];
    simBase.slice(0, 2).forEach((pid) => {
      const p = P(pid);
      if (!p) return;
      related(p).slice(0, 3).forEach((r) => sim.push({
        productId: r.product.id, name: r.product.name, slug: r.product.slug, score: r.score,
        why: 'Because you ' + (savedIds.indexOf(pid) >= 0 ? 'saved ' : 'compared ') + p.name + ' — ' + r.reasons[0].toLowerCase(),
      }));
    });
    out.push({ key: 'similar', title: 'Similar to what you saved', rows: sim.slice(0, 4) });
    out.push({
      key: 'community', title: 'New community discussions',
      rows: (S.forumThreads || []).slice(0, 3).map((t) => ({
        id: t.id, title: t.title, slug: t.slug, replies: t.replies, cat: t.categoryId,
        why: 'Active discussion in your market',
      })),
    });
    const cmp = (sig.compares || []).slice(0, 2);
    out.push({
      key: 'compare', title: 'Comparisons to finish',
      rows: cmp.map((pid) => { const p = P(pid); return p ? { productId: p.id, name: p.name, slug: p.slug, why: 'You added this to a comparison' } : null; }).filter(Boolean),
    });
    return out.filter((s) => s.rows.length);
  }

  /* ================= FUNNEL / JOURNEYS ================= */
  function funnel(extra) {
    const ev = (extra || []).concat(S.ix.events || []);
    const count = (t) => ev.filter((e) => e.type === t).length;
    const steps = [
      { label: 'Searches', value: count('search') },
      { label: 'Search clicks', value: count('search_click') },
      { label: 'Product views', value: count('product_view') },
      { label: 'Comparisons', value: count('compare') },
      { label: 'Merchant clicks', value: count('merchant_click') },
      { label: 'Conversions', value: ev.filter((e) => e.converted).length },
    ];
    return steps.map((s, i) => Object.assign({}, s, {
      pct: steps[0].value ? Math.round((s.value / steps[0].value) * 100) : 0,
      drop: i === 0 ? null : steps[i - 1].value ? Math.round((1 - s.value / steps[i - 1].value) * 100) : 0,
    }));
  }
  function journeys(extra) {
    const ev = (extra || []).concat(S.ix.events || []);
    const bySession = {};
    ev.forEach((e) => { (bySession[e.session] = bySession[e.session] || []).push(e); });
    return Object.keys(bySession).map((s) => {
      const rows = bySession[s].slice().sort((a, b) => a.ts - b.ts);
      return {
        session: s, country: rows[0].country, device: rows[0].device || 'desktop',
        steps: rows.map((r) => r.type), converted: rows.some((r) => r.converted),
        started: rows[0].ts, length: rows.length,
        path: rows.map((r) => r.type.replace(/_/g, ' ')).join(' → '),
      };
    }).sort((a, b) => b.started - a.started);
  }

  /* ================= AUTOMATIONS, TASKS, DATA HEALTH ================= */
  function automationRuns() {
    return memo('autoruns', () => {
      const out = [];
      let id = 1;
      const push = (rule, entity, action, outcome, ts) => out.push({ id: id++, rule, entity, action, outcome, ts });
      S.offers.filter((o) => NOW - o.updated > 48 * HOUR).slice(0, 6).forEach((o) => {
        push('Stale offer guard', (P(o.productId) || {}).name + ' @ ' + (M(o.merchantId) || {}).name, 'Deprioritised + merchant notified', 'applied', o.updated + 48 * HOUR);
      });
      anomalies().filter((a) => a.offerId).slice(0, 5).forEach((a) => {
        push('Price anomaly hold', a.product + ' @ ' + a.merchant, 'Flagged, excluded from Best value', 'queued for review', NOW - 5 * HOUR);
      });
      (S.ix.fraudCases || []).forEach((f) => {
        push('Review burst screen', f.label + ' — ' + (f.targetType === 'merchant' ? (M(f.targetId) || {}).name : (P(f.targetId) || {}).name), 'Queued for moderation', 'held', NOW - 30 * HOUR);
      });
      S.merchants.forEach((m) => {
        const r = risk(m);
        if (r.level === 'HIGH' || r.level === 'CRITICAL') push('Merchant risk escalation', m.name, 'Task created for Risk & Integrity', r.level, NOW - 18 * HOUR);
      });
      (S.ix.linkHealth || []).forEach((l) => push('Broken affiliate link', l.product + ' @ ' + (M(l.merchantId) || {}).name, 'Offer hidden + task created', l.label, l.detected));
      S.coupons.filter((c) => c.ends < NOW + DAY && c.ends > NOW - DAY).slice(0, 4).forEach((c) => push('Deal expiry sweep', c.code + ' @ ' + (M(c.merchantId) || {}).name, 'Marked ending soon', 'applied', NOW - 2 * HOUR));
      const unmatched = (S.feedItems || []).filter((f) => match(f).bucket === 'unmatched');
      if (unmatched.length) push('Unmatched feed item digest', unmatched.length + ' rows across ' + new Set(unmatched.map((u) => u.merchantId)).size + ' feeds', 'Digest sent + task created', 'applied', NOW - 9 * HOUR);
      S.complianceRules.filter((r) => r.status === 'unknown').slice(0, 4).forEach((r) => push('Compliance unknown block', (P(r.productId) || {}).name + ' / ' + r.country, 'Recommendation suppressed', 'applied', NOW - 26 * HOUR));
      return out.sort((a, b) => b.ts - a.ts);
    });
  }
  function tasks() {
    return memo('tasks', () => {
      const out = [];
      let id = 1;
      const add = (title, kind, entity, href, risk, traffic, revenue, compliance, user) => {
        const priority = Math.round(risk * 0.34 + traffic * 0.22 + revenue * 0.22 + compliance * 0.14 + user * 0.08);
        out.push({ id: 'TSK-' + (2100 + id++), title, kind, entity, href, priority, level: priority >= 70 ? 'P0' : priority >= 50 ? 'P1' : priority >= 32 ? 'P2' : 'P3' });
      };
      S.merchants.forEach((m) => {
        const r = risk(m);
        if (r.level === 'CRITICAL') add('Review critical-risk merchant', 'Risk', m.name, '#/shops/' + m.slug, 100, 60, 55, 40, 70);
        else if (r.level === 'HIGH') add('Investigate high-risk merchant', 'Risk', m.name, '#/shops/' + m.slug, 74, 50, 45, 30, 55);
      });
      (S.duplicateCandidates || []).filter((d) => d.status === 'open' && d.similarity > 0.8).forEach((d) => {
        const a = P(d.aId), b = P(d.bId);
        add('Resolve duplicate product', 'Matching', (a ? a.name : '—') + ' ↔ ' + (b ? b.name : '—'), '#/seo', 40, 78, 40, 20, 50);
      });
      S.complianceRules.filter((r) => r.status === 'unknown').forEach((r) => {
        const p = P(r.productId);
        add('Verify market status', 'Compliance', (p ? p.name : '—') + ' / ' + r.country, '#/admin', 45, 40, 30, 96, 40);
      });
      (S.ix.linkHealth || []).forEach((l) => add('Fix broken affiliate link', 'Affiliate', l.product + ' @ ' + (M(l.merchantId) || {}).name, '#/admin', 50, 60, 82, 10, 45));
      anomalies().filter((a) => a.offerId).slice(0, 6).forEach((a) => add('Approve or exclude price anomaly', 'Pricing', a.product + ' @ ' + a.merchant, '#/admin', 55, 55, 48, 20, 72));
      (S.ix.fraudCases || []).forEach((f) => add('Moderate ' + f.label.toLowerCase(), 'Integrity', f.targetType === 'merchant' ? (M(f.targetId) || {}).name : (P(f.targetId) || {}).name, '#/admin', 78, 42, 30, 25, 80));
      (S.ix.newProductCandidates || []).forEach((c) => add('Approve new canonical product', 'Catalogue', c.title, '#/admin', 20, 74, 52, 15, 40));
      (S.ix.tickets || []).filter((t) => t.state === 'Open' && t.priority === 'Action required').forEach((t) => add('Handle support ticket ' + t.id, 'Support', t.subject, '#/admin', 30, 20, 25, t.kind === 'GDPR' ? 92 : 30, 88));
      return out.sort((a, b) => b.priority - a.priority);
    });
  }
  function dataHealth() {
    return memo('dh', () => {
      const offers = S.offers, prods = S.products;
      const fresh = offers.filter((o) => NOW - o.updated < 24 * HOUR).length;
      const healthyOffers = offers.filter((o) => o.price > 0 && !(o.ix && o.ix.anomaly) && !(o.ix && o.ix.link)).length;
      const ean = prods.filter((p) => !!p.ean).length;
      const comp = prods.filter((p) => S.complianceRules.some((r) => r.productId === p.id && r.status !== 'unknown')).length;
      const dupes = (S.duplicateCandidates || []).filter((d) => d.status === 'open').length;
      const feedsFresh = S.feeds.filter((f) => NOW - f.lastRun < 12 * HOUR).length;
      const rows = [
        { label: 'Products healthy', value: Math.round(prods.filter((p) => completion(p.id).pct >= 75).length / prods.length * 100), unit: '%' },
        { label: 'Offers healthy', value: Math.round(healthyOffers / offers.length * 100), unit: '%' },
        { label: 'Feeds fresh (< 12 h)', value: Math.round(feedsFresh / S.feeds.length * 100), unit: '%' },
        { label: 'Offer freshness (< 24 h)', value: Math.round(fresh / offers.length * 100), unit: '%' },
        { label: 'EAN coverage', value: Math.round(ean / prods.length * 100), unit: '%' },
        { label: 'Compliance coverage', value: Math.round(comp / prods.length * 100), unit: '%' },
        { label: 'Broken links', value: (S.ix.linkHealth || []).length, unit: '' },
        { label: 'Duplicate candidates', value: dupes, unit: '' },
        { label: 'Price anomalies', value: anomalies().filter((a) => a.offerId).length, unit: '' },
        { label: 'Unmatched feed rows', value: matchBuckets().unmatched.length, unit: '' },
      ];
      const score = Math.round(avg(rows.slice(0, 6).map((r) => r.value)) - (S.ix.linkHealth || []).length * 0.4 - dupes * 0.5);
      const series = [];
      for (let i = 29; i >= 0; i--) series.push(clamp(Math.round(score - i * 0.32 + Math.sin(i / 3) * 1.4), 0, 100));
      return { rows, score: clamp(score, 0, 100), series };
    });
  }
  function platformHealth() {
    const zero = (S.searchQueries || []).filter((q) => !q.hasResults).length;
    const total = (S.searchQueries || []).length || 1;
    return [
      { label: 'Search success rate', value: Math.round((1 - zero / total) * 100) + ' %', good: true },
      { label: 'Feed freshness', value: Math.round(S.feeds.filter((f) => NOW - f.lastRun < 12 * HOUR).length / S.feeds.length * 100) + ' %', good: true },
      { label: 'Affiliate redirect success', value: r1(100 - (S.ix.linkHealth || []).length / Math.max(1, S.offers.length) * 100) + ' %', good: true },
      { label: 'Error rate (24 h)', value: (S.ix.errors || []).filter((e) => e.severity === 'error').reduce((a, b) => a + b.count, 0) + ' events', good: false },
      { label: 'Moderation backlog', value: S.reviews.filter((r) => r.status === 'pending' || r.status === 'flagged').length + ' items', good: false },
    ];
  }

  return {
    /* text */ similarity,
    /* trust & risk */ trust, trustHistory, risk, trustDefs,
    /* fraud */ reviewTrust, dupClusters, burst, manipulation, userTrust, abuseLimits,
    /* matching */ match, variantGuard, clusters, matchBuckets, completion,
    /* pricing */ histStats, priceBadge, timing, forecast, priceConfidence, anomalies, fakeDiscount, unitEconomics,
    /* coupons & deals */ couponMeta, dealScore, stockConfidence, offerLifecycle, deliveryReliability, shippingIntel,
    /* ranking */ rank,
    /* discovery */ related, similarShops, trends, personal,
    /* market */ coverage, productCoverage, categoryCoverage, searchSupply,
    /* commercial */ affiliateMetrics, commercial, performance, benchmarks, competitiveness, merchantRecs, opportunities,
    /* basket */ basket,
    /* ops */ funnel, journeys, automationRuns, tasks, dataHealth, platformHealth,
    /* utils */ med, avg, clamp,
    _cache: cache,
  };
};
