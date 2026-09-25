/* Comparo Performance — Growth OS engine.
   Deterministic scoring over SEED + SEED.gx. No AI, no randomness at call time.
   Usage: const gr = window.ComparoGrowth(window.SEED, ix);  */
window.ComparoGrowth = function (S, ix) {
  const H = S.helpers, NOW = S.NOW, DAY = S.DAY, HOUR = 3600000, gx = S.gx;
  const cache = {};
  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
  const r1 = (v) => Math.round(v * 10) / 10;
  const avg = (a) => (a.length ? a.reduce((x, y) => x + y, 0) / a.length : 0);
  const M = (id) => S.merchants.find((m) => m.id === +id);
  const P = (id) => S.products.find((p) => p.id === +id);
  const demand = (pid) => (S.ix.demand || []).find((d) => d.productId === +pid) || { searches30: 0, offers: 0 };

  /* ---------- merchant qualification (0–100) ---------- */
  function qualify(pr) {
    if (cache['q' + pr.id]) return cache['q' + pr.id];
    const parts = [
      { label: 'Market relevance', pts: clamp(pr.markets.length * 4, 0, 14) },
      { label: 'Catalogue overlap', pts: clamp(pr.overlap * 0.18, 0, 18) },
      { label: 'User demand (search mentions)', pts: clamp(pr.searchMentions * 0.09, 0, 16) },
      { label: 'Community mentions', pts: clamp(pr.communityMentions * 0.5, 0, 8) },
      { label: 'Brand coverage', pts: clamp(pr.brands.length * 1.6, 0, 12) },
      { label: 'Catalogue size', pts: clamp(Math.log10(1 + pr.catalog) * 4.4, 0, 12) },
      { label: 'Shipping coverage', pts: clamp(pr.shippingMarkets * 1.3, 0, 10) },
      { label: 'Affiliate potential', pts: pr.affiliateNetwork === 'none' ? 2 : 10 },
    ];
    const score = clamp(Math.round(parts.reduce((a, b) => a + b.pts, 0)), 0, 100);
    const out = {
      score, parts: parts.map((p) => ({ label: p.label, pts: Math.round(p.pts) })),
      label: score >= 80 ? 'Priority prospect' : score >= 65 ? 'Strong fit' : score >= 48 ? 'Worth contacting' : 'Low fit',
      color: score >= 80 ? 'var(--ok)' : score >= 65 ? 'var(--acc-text)' : score >= 48 ? 'var(--text-2)' : 'var(--text-3)',
    };
    cache['q' + pr.id] = out;
    return out;
  }
  function nextBestAction(pr) {
    const q = qualify(pr).score;
    if (pr.stage === 'Rejected') return { action: 'Pause prospect', why: 'Rejected — do not re-contact this quarter.' };
    if (pr.stage === 'Dormant') return { action: 'Pause prospect', why: 'No reply after three attempts. Re-qualify next quarter.' };
    if (pr.stage === 'Discovered') return q >= 65 ? { action: 'Qualify prospect', why: 'Score ' + q + ' — qualify before outreach.' } : { action: 'Pause prospect', why: 'Score ' + q + ' is below the outreach threshold of 65.' };
    if (pr.stage === 'Qualified') return { action: 'Contact', why: 'Qualified at ' + q + '. Use the initial outreach template.' };
    if (pr.stage === 'Contact ready') return { action: 'Contact', why: 'Contact details confirmed and template prepared.' };
    if (pr.stage === 'Contacted') return { action: 'Follow up', why: 'No reply yet — one follow-up after 5 working days.' };
    if (pr.stage === 'Replied') return { action: pr.affiliateNetwork === 'none' ? 'Negotiate affiliate' : 'Request feed', why: pr.affiliateNetwork === 'none' ? 'No programme on file — propose direct terms.' : 'Programme exists on ' + pr.affiliateNetwork + '; move to feed.' };
    if (pr.stage === 'Negotiation') return { action: 'Offer premium profile', why: 'Terms under discussion — premium profile is the usual closer.' };
    if (pr.stage === 'Onboarding' || pr.stage === 'Feed integration') return { action: 'Request feed', why: 'Integration in progress; unblock the feed URL.' };
    if (pr.stage === 'Verified') return { action: 'Negotiate exclusive deal', why: 'Verified — an exclusive is the fastest launch lever.' };
    return { action: 'Review integration', why: 'Live — check offer freshness and matching quality.' };
  }
  function acquisitionFunnel() {
    const stageOf = (names) => (gx.prospects || []).filter((p) => names.indexOf(p.stage) >= 0).length;
    const steps = [
      { label: 'Prospects', value: (gx.prospects || []).length },
      { label: 'Qualified', value: stageOf(['Qualified', 'Contact ready', 'Contacted', 'Replied', 'Negotiation', 'Onboarding', 'Feed integration', 'Verified', 'Live']) },
      { label: 'Contacted', value: stageOf(['Contacted', 'Replied', 'Negotiation', 'Onboarding', 'Feed integration', 'Verified', 'Live']) },
      { label: 'Replies', value: stageOf(['Replied', 'Negotiation', 'Onboarding', 'Feed integration', 'Verified', 'Live']) },
      { label: 'Onboarding', value: stageOf(['Onboarding', 'Feed integration', 'Verified', 'Live']) },
      { label: 'Live', value: stageOf(['Live']) },
    ];
    return steps.map((s, i) => Object.assign({}, s, {
      pct: steps[0].value ? Math.round((s.value / steps[0].value) * 100) : 0,
      conv: i === 0 ? null : steps[i - 1].value ? Math.round((s.value / steps[i - 1].value) * 100) : 0,
    }));
  }
  function acquisitionByMarket() {
    const byIso = {};
    (gx.prospects || []).forEach((p) => {
      const k = p.country;
      byIso[k] = byIso[k] || { iso: k, prospects: 0, live: 0, contacted: 0 };
      byIso[k].prospects++;
      if (p.stage === 'Live') byIso[k].live++;
      if (['Contacted', 'Replied', 'Negotiation', 'Onboarding', 'Feed integration', 'Verified', 'Live'].indexOf(p.stage) >= 0) byIso[k].contacted++;
    });
    return Object.keys(byIso).map((k) => {
      const co = S.countries.find((c) => c.iso === k);
      const merchants = S.merchants.filter((m) => m.shipsTo.indexOf(k) >= 0).length;
      return Object.assign(byIso[k], { name: co ? co.name : k, merchants });
    }).sort((a, b) => b.prospects - a.prospects);
  }

  /* ---------- affiliate sales ---------- */
  function affiliateOpportunity(m) {
    const a = ix.affiliateMetrics(m), t = ix.trust(m);
    const deal = (gx.affiliateDeals || []).find((d) => d.merchantId === m.id) || { status: 'No programme' };
    const inventory = S.offers.filter((o) => o.merchantId === m.id).length;
    const monetised = deal.status === 'Live';
    const parts = [
      { label: 'Outbound clicks', pts: clamp(Math.log10(1 + a.clicks) * 9, 0, 26) },
      { label: 'Conversion proxy', pts: clamp(a.cvr * 3.4, 0, 18) },
      { label: 'User demand', pts: clamp(inventory * 0.4, 0, 14) },
      { label: 'Merchant trust', pts: clamp((t.score - 50) / 50 * 14, 0, 14) },
      { label: 'Market coverage', pts: clamp(m.shipsTo.length * 1.4, 0, 12) },
      { label: 'Not yet monetised', pts: monetised ? 0 : 16 },
    ];
    const score = clamp(Math.round(parts.reduce((x, y) => x + y.pts, 0)), 0, 100);
    return {
      score, parts: parts.map((p) => ({ label: p.label, pts: Math.round(p.pts) })), deal, monetised,
      label: score >= 78 ? 'Act now' : score >= 60 ? 'High value' : score >= 42 ? 'Worth a pitch' : 'Low priority',
      color: score >= 78 ? 'var(--ok)' : score >= 60 ? 'var(--acc-text)' : score >= 42 ? 'var(--text-2)' : 'var(--text-3)',
      clicks: a.clicks, revenue: a.revenue, commission: a.commission, epc: a.epc, cvr: a.cvr,
    };
  }
  function nonMonetised() {
    return S.merchants.map((m) => affiliateOpportunity(m))
      .map((o, i) => Object.assign(o, { merchant: S.merchants[i] }))
      .filter((o) => !o.monetised && o.clicks > 300)
      .sort((a, b) => b.clicks - a.clicks);
  }
  function lowEpc() {
    const all = S.merchants.map((m) => ({ m, a: ix.affiliateMetrics(m) }));
    const median = (() => { const v = all.map((x) => x.a.epc).sort((a, b) => a - b); return v[Math.floor(v.length / 2)] || 0; })();
    return all.filter((x) => x.a.clicks > 1500 && x.a.epc < median * 0.7).map((x) => ({
      merchant: x.m, epc: x.a.epc, median: Math.round(median * 1000) / 1000, clicks: x.a.clicks, cvr: x.a.cvr,
      causes: [
        x.a.cvr < 3 ? 'Conversion rate below 3 % — check landing pages' : null,
        (S.ix.linkHealth || []).some((l) => l.merchantId === x.m.id) ? 'Broken or untracked links on file' : null,
        x.a.commissionRate < 6 ? 'Commission ' + x.a.commissionRate + ' % is below market' : null,
      ].filter(Boolean),
    })).sort((a, b) => a.epc - b.epc);
  }

  /* ---------- market expansion ---------- */
  function marketScores() {
    if (cache.mkt) return cache.mkt;
    const rows = (S.ix.markets || []).map((mk) => {
      const goals = (gx.marketGoals || []).find((g) => g.iso === mk.iso);
      const searchDemand = (S.searchQueries || []).reduce((a, q) => a + q.volume, 0) / 12;
      const affiliates = mk.affiliates || 0;
      const community = (S.forumThreads || []).filter((t) => t.country === mk.iso).length;
      const parts = [
        { label: 'Search demand', pts: clamp(searchDemand / 900, 0, 18) },
        { label: 'Merchant count', pts: clamp(mk.merchants * 1.6, 0, 20) },
        { label: 'Product coverage', pts: clamp(mk.products * 0.34, 0, 18) },
        { label: 'Review activity', pts: clamp(mk.reviews * 0.09, 0, 14) },
        { label: 'Community activity', pts: clamp(community * 2.4, 0, 8) },
        { label: 'Affiliate programmes', pts: clamp(affiliates * 2.6, 0, 12) },
        { label: 'SEO coverage', pts: goals ? clamp(goals.goals.seoPages[0] * 0.12, 0, 10) : 4 },
      ];
      const score = clamp(Math.round(parts.reduce((a, b) => a + b.pts, 0)), 0, 100);
      const phase = goals ? goals.phase : score >= 75 ? 'Mature' : score >= 55 ? 'Growth' : score >= 38 ? 'Opportunity' : 'Early';
      return {
        iso: mk.iso, name: mk.name, score, phase, parts: parts.map((p) => ({ label: p.label, pts: Math.round(p.pts) })),
        merchants: mk.merchants, products: mk.products, offers: mk.offers, reviews: mk.reviews, affiliates,
        color: score >= 75 ? 'var(--ok)' : score >= 55 ? 'var(--acc-text)' : score >= 38 ? 'var(--warn)' : 'var(--danger)',
        goals: goals ? Object.keys(goals.goals).map((k) => ({
          key: k, have: goals.goals[k][0], target: goals.goals[k][1],
          pct: clamp(Math.round((goals.goals[k][0] / goals.goals[k][1]) * 100), 0, 100),
        })) : [],
      };
    }).sort((a, b) => b.score - a.score);
    cache.mkt = rows;
    return rows;
  }

  /* ---------- content engine ---------- */
  function contentScore(c) {
    const parts = [
      { label: 'Search demand', pts: clamp(c.searches * 0.09, 0, 24) },
      { label: 'Commercial intent', pts: c.intent === 'high' ? 20 : c.intent === 'medium' ? 12 : 5 },
      { label: 'Data availability', pts: c.dataReady ? 20 : 4 },
      { label: 'Competition proxy', pts: c.type === 'Data report' || c.type === 'Research article' ? 14 : c.type === 'Comparison' ? 10 : 7 },
      { label: 'Internal link potential', pts: clamp((c.links || 0) * 1.4 + 6, 0, 12) },
      { label: 'Freshness', pts: clamp(10 - (NOW - c.updatedAt) / DAY / 20, 0, 10) },
    ];
    const score = clamp(Math.round(parts.reduce((a, b) => a + b.pts, 0)), 0, 100);
    return {
      score, parts: parts.map((p) => ({ label: p.label, pts: Math.round(p.pts) })),
      label: score >= 78 ? 'Publish next' : score >= 60 ? 'Strong' : score >= 42 ? 'Consider' : 'Park',
      color: score >= 78 ? 'var(--ok)' : score >= 60 ? 'var(--acc-text)' : score >= 42 ? 'var(--text-2)' : 'var(--text-3)',
      gate: score >= 60 && c.dataReady ? 'Publishable' : !c.dataReady ? 'Needs data' : score >= 42 ? 'Too thin' : 'Park',
    };
  }
  function brief(c) {
    const ent = c.query || c.title;
    const rel = (S.products.filter((p) => H.norm(ent).indexOf(H.norm(p.name).split(' ')[0]) >= 0).slice(0, 3).map((p) => p.name));
    return {
      intent: c.intent === 'high' ? 'Commercial investigation' : c.intent === 'medium' ? 'Informational with commercial tail' : 'Informational',
      entity: ent,
      related: (rel.length ? rel : ['Category: ' + (S.categories[0] || {}).name, 'Market: Germany']).concat(['Trust methodology']),
      title: c.title,
      h1: c.title,
      questions: [
        'What does it actually cost, including shipping, in the reader’s market?',
        'How does the price compare with its own 90-day history?',
        'Which shops carry it, and what are their trust scores?',
        'What changes the answer between markets?',
      ],
      data: c.dataReady ? 'Comparo price history, normalised totals, trust scores and review credibility for this entity' : 'Insufficient — needs at least two independent merchant feeds before drafting',
      links: ['Product pages for the compared entities', 'Category hub', 'Market hub', 'Methodology'],
      schema: c.type === 'FAQ' ? 'FAQPage + BreadcrumbList' : c.type === 'Data report' || c.type === 'Research article' ? 'Article + Dataset + BreadcrumbList' : c.type === 'Comparison' ? 'ItemList + Product + BreadcrumbList' : 'Article + BreadcrumbList',
    };
  }
  function contentValue(c) {
    const score = clamp(Math.round(Math.log10(1 + c.entrances) * 14 + Math.log10(1 + c.revenue) * 10 + (c.saves || 0) * 0.06 + (c.links || 0) * 2.2 + clamp(10 - (NOW - c.updatedAt) / DAY / 24, 0, 10)), 0, 100);
    return { score, label: score >= 70 ? 'Core asset' : score >= 45 ? 'Performing' : score > 0 ? 'Underperforming' : 'Not published' };
  }
  function contentQueue() {
    return (gx.content || []).map((c) => {
      const s = contentScore(c);
      return Object.assign({}, c, { score: s.score, gate: s.gate, scoreLabel: s.label, color: s.color, value: contentValue(c) });
    }).sort((a, b) => b.score - a.score);
  }

  /* ---------- creators ---------- */
  function creatorScore(c) {
    const parts = [
      { label: 'Audience relevance', pts: clamp(c.relevance * 0.22, 0, 22) },
      { label: 'Market fit', pts: S.countries.some((x) => x.iso === c.country) ? 16 : 6 },
      { label: 'Engagement proxy', pts: clamp(c.engagement * 2.4, 0, 18) },
      { label: 'Content quality', pts: clamp(c.brandSafety * 0.14, 0, 14) },
      { label: 'Brand safety', pts: clamp((c.brandSafety - 60) * 0.3, 0, 12) },
      { label: 'Conversion potential', pts: c.clicks ? clamp((c.conversions / c.clicks) * 100 * 2.2, 0, 18) : 8 },
    ];
    const score = clamp(Math.round(parts.reduce((a, b) => a + b.pts, 0)), 0, 100);
    return {
      score, parts: parts.map((p) => ({ label: p.label, pts: Math.round(p.pts) })),
      label: score >= 76 ? 'Priority partner' : score >= 58 ? 'Good fit' : score >= 42 ? 'Test' : 'Pass',
      color: score >= 76 ? 'var(--ok)' : score >= 58 ? 'var(--acc-text)' : score >= 42 ? 'var(--text-2)' : 'var(--text-3)',
    };
  }
  function creatorPerf(c) {
    const cvr = c.clicks ? r1((c.conversions / c.clicks) * 100) : 0;
    const epc = c.clicks ? Math.round((c.commission / c.clicks) * 1000) / 1000 : 0;
    const all = (gx.creators || []).filter((x) => x.clicks > 0);
    const medCvr = (() => { const v = all.map((x) => (x.conversions / x.clicks) * 100).sort((a, b) => a - b); return v[Math.floor(v.length / 2)] || 0; })();
    return {
      cvr, epc, clicks: c.clicks, revenue: c.revenue, commission: c.commission, newUsers: c.newUsers,
      vsMedian: r1(cvr - medCvr), below: cvr < medCvr * 0.7 && c.clicks > 1000,
      tierSuggestion: c.conversions > 250 ? 'Promote a tier' : cvr < medCvr * 0.6 && c.clicks > 2000 ? 'Review placement quality' : 'Keep current tier',
    };
  }

  /* ---------- referrals ---------- */
  function referralCode(session) {
    const base = (session && session.email) || 'anonymous';
    let h = 5381;
    for (let i = 0; i < base.length; i++) h = ((h << 5) + h + base.charCodeAt(i)) >>> 0;
    return 'CMP-' + h.toString(36).toUpperCase().slice(0, 5);
  }
  function referralFunnel() {
    const c = gx.referralCohorts || [];
    const sum = (k) => c.reduce((a, b) => a + b[k], 0);
    const steps = [
      { label: 'Invites', value: sum('invites') },
      { label: 'Visits', value: sum('visits') },
      { label: 'Registrations', value: sum('registrations') },
      { label: 'Activated', value: sum('activated') },
    ];
    return steps.map((s, i) => Object.assign({}, s, {
      pct: steps[0].value ? Math.round((s.value / steps[0].value) * 100) : 0,
      conv: i === 0 ? null : steps[i - 1].value ? Math.round((s.value / steps[i - 1].value) * 100) : 0,
    }));
  }

  /* ---------- opportunities (the spine of the Growth OS) ---------- */
  function opportunityScore(o) {
    const parts = [
      { label: 'Demand', pts: clamp(o.demand || 0, 0, 20) },
      { label: 'Commercial value', pts: clamp(o.commercial || 0, 0, 18) },
      { label: 'Data availability', pts: clamp(o.data || 0, 0, 12) },
      { label: 'Market gap', pts: clamp(o.gap || 0, 0, 14) },
      { label: 'User impact', pts: clamp(o.user || 0, 0, 12) },
      { label: 'SEO potential', pts: clamp(o.seo || 0, 0, 12) },
      { label: 'Revenue potential', pts: clamp(o.revenue || 0, 0, 14) },
      { label: 'Low effort bonus', pts: o.effort === 'Low' ? 8 : o.effort === 'Medium' ? 4 : 0 },
    ];
    const score = clamp(Math.round(parts.reduce((a, b) => a + b.pts, 0)), 0, 100);
    return { score, parts: parts.map((p) => ({ label: p.label, pts: Math.round(p.pts) })) };
  }
  function opportunities() {
    if (cache.opps) return cache.opps;
    const out = [];
    const add = (o) => {
      const s = opportunityScore(o);
      out.push(Object.assign({}, o, {
        score: s.score, scoreParts: s.parts,
        impact: s.score >= 75 ? 'High' : s.score >= 50 ? 'Medium' : 'Low',
        confidence: o.confidence || (o.data >= 10 ? 'High' : o.data >= 6 ? 'Medium' : 'Low'),
      }));
    };
    /* uncovered demand → merchant + SEO opportunities */
    (S.ix.zeroSupply || []).forEach((z, i) => add({
      id: 'OPP-S' + i, kind: 'SEO page', title: '“' + z.query + '” — ' + z.searches30 + ' searches, ' + z.offers + ' offers',
      entity: 'query:' + z.query, action: 'Create SEO page', owner: 'seo@comparo', status: 'Open', effort: 'Low',
      demand: clamp(z.searches30 * 0.13, 0, 20), commercial: 12, data: z.offers ? 10 : 6, gap: 14, user: 10, seo: 12, revenue: 8,
      evidence: z.note + ' Markets: ' + z.markets.join(', ') + '.', dataUsed: 'Search log (30 d) + active offer count', range: '30 days',
    }));
    ((S.ix.demand || []).filter((d) => d.offers <= 2 && d.searches30 > 120)).slice(0, 8).forEach((d, i) => {
      const p = P(d.productId);
      add({
        id: 'OPP-P' + i, kind: 'Product coverage', title: (p ? p.name : '—') + ' — ' + d.searches30 + ' searches, only ' + d.offers + ' offer' + (d.offers === 1 ? '' : 's'),
        entity: 'product:' + d.productId, action: 'Onboard merchant', owner: 'merchants@comparo', status: 'Open', effort: 'High',
        demand: clamp(d.searches30 * 0.1, 0, 20), commercial: 14, data: 12, gap: d.offers === 0 ? 14 : 10, user: 12, seo: 6, revenue: 12,
        evidence: 'Demand-to-supply ratio ' + Math.round(d.searches30 / Math.max(1, d.offers)) + ':1.', dataUsed: 'Demand signals + offer index', range: '30 days',
      });
    });
    /* non-monetised traffic */
    nonMonetised().forEach((n, i) => add({
      id: 'OPP-A' + i, kind: 'Affiliate', title: n.merchant.name + ' — ' + H.num(n.clicks) + ' clicks, no affiliate programme',
      entity: 'merchant:' + n.merchant.id, action: 'Negotiate affiliate', owner: 'affiliate@comparo', status: 'Open', effort: 'Medium',
      demand: 12, commercial: 18, data: 12, gap: 8, user: 4, seo: 0, revenue: 14,
      evidence: H.num(n.clicks) + ' outbound clicks in 30 days produced €0 commission. Trust ' + ix.trust(n.merchant).score + '/100.',
      dataUsed: 'Affiliate click ledger + programme status', range: '30 days',
    }));
    /* high-scoring prospects not yet contacted */
    (gx.prospects || []).filter((p) => ['Discovered', 'Qualified', 'Contact ready'].indexOf(p.stage) >= 0).map((p) => ({ p, q: qualify(p) }))
      .filter((x) => x.q.score >= 62).sort((a, b) => b.q.score - a.q.score).slice(0, 6).forEach((x, i) => add({
        id: 'OPP-M' + i, kind: 'Merchant acquisition', title: x.p.name + ' — qualification ' + x.q.score + ', stage ' + x.p.stage,
        entity: 'prospect:' + x.p.id, action: 'Qualify prospect', owner: x.p.owner || 'merchants@comparo', status: 'Open', effort: 'Medium',
        demand: clamp(x.p.searchMentions * 0.1, 0, 20), commercial: 14, data: 10, gap: 12, user: 8, seo: 6, revenue: 12,
        evidence: x.p.searchMentions + ' search mentions, ' + x.p.overlap + ' % catalogue overlap, ' + H.num(x.p.catalog) + ' items.',
        dataUsed: 'Prospect qualification model', range: 'lifetime',
      }));
    /* market gaps */
    marketScores().filter((m) => m.score < 55).slice(0, 5).forEach((m, i) => add({
      id: 'OPP-K' + i, kind: 'Market expansion', title: m.name + ' — expansion score ' + m.score + ' (' + m.phase + ')',
      entity: 'market:' + m.iso, action: 'Onboard merchant', owner: 'growth@comparo', status: 'Open', effort: 'High',
      demand: 14, commercial: 12, data: 10, gap: 14, user: 10, seo: 10, revenue: 10,
      evidence: m.merchants + ' merchants, ' + m.products + ' products, ' + m.reviews + ' reviews.',
      dataUsed: 'Market coverage + expansion model', range: 'current',
    }));
    /* content ready to publish */
    contentQueue().filter((c) => c.gate === 'Publishable' && c.status !== 'Published').slice(0, 6).forEach((c, i) => add({
      id: 'OPP-C' + i, kind: 'Content', title: c.title + ' — ' + c.searches + ' searches, ' + c.status.toLowerCase(),
      entity: 'content:' + c.id, action: 'Create page', owner: c.owner || 'content@comparo', status: 'Open', effort: c.type === 'Data report' ? 'High' : 'Medium',
      demand: clamp(c.searches * 0.08, 0, 20), commercial: c.intent === 'high' ? 18 : 10, data: 12, gap: 8, user: 10, seo: 12, revenue: 8,
      evidence: 'Data available, opportunity score ' + c.score + ', status ' + c.status + '.', dataUsed: 'Search log + internal data inventory', range: '30 days',
    }));
    /* research stories */
    (gx.research || []).filter((r) => r.status === 'Ready' || r.status === 'Draft').forEach((r, i) => add({
      id: 'OPP-R' + i, kind: 'PR / research', title: r.headline,
      entity: 'research:' + r.id, action: 'Publish research', owner: 'content@comparo', status: 'Open', effort: 'High',
      demand: 10, commercial: 8, data: r.confidence === 'high' ? 12 : 7, gap: 6, user: 8, seo: 12, revenue: 6,
      evidence: r.finding, dataUsed: r.kind + ' derived from internal price and trust history', range: '90 days',
      confidence: r.confidence === 'high' ? 'High' : 'Medium',
    }));
    /* unanswered community questions */
    (gx.unanswered || []).slice(0, 4).forEach((q, i) => add({
      id: 'OPP-Q' + i, kind: 'Community', title: q.question + ' — ' + H.num(q.views) + ' views, unanswered',
      entity: 'question:' + q.id, action: 'Answer community question', owner: 'community@comparo', status: 'Open', effort: 'Low',
      demand: clamp(q.asks * 0.14, 0, 20), commercial: 6, data: 10, gap: 10, user: 12, seo: 10, revenue: 4,
      evidence: H.num(q.views) + ' views and ' + q.asks + ' repeat asks with no accepted answer.', dataUsed: 'Search log + forum answer state', range: '30 days',
    }));
    const sorted = out.sort((a, b) => b.score - a.score);
    cache.opps = sorted;
    return sorted;
  }

  /* ---------- insights (deterministic, evidence-backed) ---------- */
  function insights() {
    if (cache.ins) return cache.ins;
    const out = [];
    const nm = nonMonetised();
    if (nm.length) out.push({
      id: 'INS-1', text: nm.length + ' merchants received ' + H.num(nm.reduce((a, b) => a + b.clicks, 0)) + ' outbound clicks in 30 days with no affiliate programme — the largest single gap is ' + nm[0].merchant.name + ' at ' + H.num(nm[0].clicks) + ' clicks.',
      dataUsed: 'Affiliate click ledger, programme status', range: '30 days', confidence: 'High', action: 'Negotiate affiliate', entity: 'merchant:' + nm[0].merchant.id,
    });
    const gapRows = (S.ix.zeroSupply || []);
    if (gapRows.length) out.push({
      id: 'INS-2', text: gapRows.length + ' repeated searches return no purchasable offer, together worth ' + H.num(gapRows.reduce((a, b) => a + b.searches30, 0)) + ' searches a month. Each is a landing page and a merchant conversation.',
      dataUsed: 'Zero-result search log, offer index', range: '30 days', confidence: 'High', action: 'Create SEO page', entity: 'query:' + gapRows[0].query,
    });
    const weak = marketScores().filter((m) => m.score < 45);
    if (weak.length) out.push({
      id: 'INS-3', text: weak.map((m) => m.name).slice(0, 3).join(', ') + ' score below 45 on market expansion — merchant coverage, not demand, is the binding constraint in each.',
      dataUsed: 'Market coverage, search demand, review activity', range: 'current', confidence: 'Medium', action: 'Onboard merchant', entity: 'market:' + weak[0].iso,
    });
    const dropoff = (gx.dropoff || [])[0];
    if (dropoff) out.push({
      id: 'INS-4', text: dropoff.point + ' costs ' + H.num(dropoff.sessions) + ' sessions a month: ' + dropoff.note.toLowerCase() + '.',
      dataUsed: 'Session event stream', range: '30 days', confidence: 'Medium', action: 'Improve feed', entity: 'platform:offers',
    });
    const le = lowEpc();
    if (le.length) out.push({
      id: 'INS-5', text: le[0].merchant.name + ' earns €' + le[0].epc + ' per click against a €' + le[0].median + ' median on ' + H.num(le[0].clicks) + ' clicks' + (le[0].causes.length ? ' — likely cause: ' + le[0].causes[0].toLowerCase() : '') + '.',
      dataUsed: 'EPC by merchant, link health', range: '30 days', confidence: 'Medium', action: 'Negotiate affiliate', entity: 'merchant:' + le[0].merchant.id,
    });
    const stale = (gx.refreshQueue || []).filter((r) => r.severity === 'high');
    if (stale.length) out.push({
      id: 'INS-6', text: stale.length + ' published pages quote prices or merchants that no longer exist — refreshing them protects the assets that already rank.',
      dataUsed: 'Content inventory, offer index', range: 'current', confidence: 'High', action: 'Create page', entity: 'content:refresh',
    });
    const best = (gx.assists || [])[0];
    if (best) out.push({
      id: 'INS-7', text: '“' + best.content + '” precedes ' + H.num(best.assists) + ' merchant clicks and ' + best.conversions + ' conversions without being the last touch — assisting content is under-credited in channel reporting.',
      dataUsed: 'Assisted-conversion paths', range: '30 days', confidence: 'Medium', action: 'Create page', entity: 'content:assists',
    });
    cache.ins = out;
    return out;
  }

  /* ---------- growth automations over live data ---------- */
  function automationRuns() {
    const out = [];
    let id = 1;
    const push = (rule, entity, action, outcome, ts) => out.push({ id: id++, rule, entity, action, outcome, ts });
    nonMonetised().forEach((n) => push('Non-monetised traffic', n.merchant.name + ' · ' + H.num(n.clicks) + ' clicks', 'Affiliate outreach task created', 'task', NOW - 4 * HOUR));
    (S.ix.zeroSupply || []).forEach((z) => push('Zero-result SEO opportunity', '“' + z.query + '” · ' + z.searches30 + ' searches', 'SEO opportunity created', 'opportunity', NOW - 2 * DAY));
    (S.ix.anomalySeeds || []).slice(0, 3).forEach((a) => push('Research story from price anomaly', (P(a.productId) || {}).name + ' · ' + a.mode.replace('_', ' '), 'Research suggestion created', 'suggestion', NOW - 6 * DAY));
    (gx.prospects || []).filter((p) => qualify(p).score > 85).forEach((p) => push('Prospect qualification', p.name + ' · score ' + qualify(p).score, 'Qualification task created', 'task', NOW - 5 * DAY));
    (gx.creators || []).filter((c) => c.conversions > 250).forEach((c) => push('Creator tier review', c.name + ' · ' + c.conversions + ' conversions', 'Tier upgrade suggested', 'suggestion', NOW - 8 * DAY));
    (gx.unanswered || []).filter((q) => q.views > 500).forEach((q) => push('Unanswered question escalation', q.question, 'Surfaced to trusted contributors', 'notify', NOW - 10 * DAY));
    return out.sort((a, b) => b.ts - a.ts);
  }

  /* ---------- briefs ---------- */
  function dailyBrief() {
    const opps = opportunities();
    const alerts = gx.alerts || [];
    return {
      changes: [
        'Merchant clicks ' + H.num(S.affiliate.daily.slice(-1)[0].clicks) + ' yesterday across all placements.',
        (gx.campaigns || []).filter((c) => c.status === 'Running').length + ' campaigns running, ' + (gx.campaigns || []).filter((c) => c.status === 'Scheduled').length + ' scheduled.',
        (S.feeds || []).filter((f) => NOW - f.lastRun < 12 * HOUR).length + ' of ' + (S.feeds || []).length + ' feeds imported in the last 12 hours.',
      ],
      opportunities: opps.slice(0, 3).map((o) => o.title),
      risks: alerts.filter((a) => a.severity === 'Critical' || a.severity === 'Important').map((a) => a.text),
      actions: opps.slice(0, 3).map((o) => o.action + ' — ' + o.title),
    };
  }
  function marketBrief(iso) {
    const m = marketScores().find((x) => x.iso === iso) || marketScores()[0];
    const cov = ix.coverage().find((c) => c.iso === m.iso) || {};
    return {
      iso: m.iso, name: m.name, phase: m.phase, score: m.score,
      lines: [
        'Merchant coverage: ' + m.merchants + ' shops, ' + m.products + ' products, ' + H.num(m.offers) + ' offers.',
        'Review activity: ' + m.reviews + ' shop reviews on record.',
        'Affiliate: ' + m.affiliates + ' live programmes.',
        'Coverage verdict: ' + (cov.label || m.phase) + (cov.gap ? ' — ' + cov.gap : '') + '.',
        'Top opportunity: ' + (opportunities().filter((o) => o.entity === 'market:' + m.iso)[0] || opportunities()[0]).title + '.',
      ],
      goals: m.goals,
    };
  }
  function affiliateBrief() {
    const rows = S.merchants.map((m) => ix.affiliateMetrics(m));
    const revenue = rows.reduce((a, b) => a + b.revenue, 0);
    const commission = rows.reduce((a, b) => a + b.commission, 0);
    const clicks = rows.reduce((a, b) => a + b.clicks, 0);
    return {
      lines: [
        '30-day revenue €' + H.num(Math.round(revenue)) + ', commission €' + H.num(Math.round(commission)) + '.',
        'Blended EPC €' + (commission / Math.max(1, clicks)).toFixed(3) + ' on ' + H.num(clicks) + ' clicks.',
        nonMonetised().length + ' merchants with traffic and no programme (' + H.num(nonMonetised().reduce((a, b) => a + b.clicks, 0)) + ' clicks).',
        (S.ix.linkHealth || []).length + ' tracking or link problems open.',
        lowEpc().length + ' merchants below 70 % of the median EPC.',
      ],
    };
  }
  function acquisitionBrief() {
    const f = acquisitionFunnel();
    return { lines: f.map((s) => s.label + ': ' + s.value + (s.conv === null ? '' : ' (' + s.conv + ' % of previous stage)')) };
  }

  /* ---------- growth overview metrics, mapped to the flywheel ---------- */
  function overview() {
    const rows = S.merchants.map((m) => ix.affiliateMetrics(m));
    const clicks = rows.reduce((a, b) => a + b.clicks, 0);
    const conv = rows.reduce((a, b) => a + b.conv, 0);
    const revenue = rows.reduce((a, b) => a + b.revenue, 0);
    const stage = (s) => (gx.lifecycle || []).find((l) => l.stage === s) || { users: 0 };
    return [
      { stage: 'Discovery', label: 'New users', value: H.num(stage('Registered').users) },
      { stage: 'Discovery', label: 'Active users', value: H.num(stage('Engaged').users) },
      { stage: 'Discovery', label: 'Community contributions', value: H.num(stage('Contributor').users) },
      { stage: 'Supply', label: 'New merchants', value: (gx.prospects || []).filter((p) => p.stage === 'Live').length + '' },
      { stage: 'Supply', label: 'Active merchants', value: S.merchants.length + '' },
      { stage: 'Supply', label: 'Offers', value: H.num(S.offers.length) },
      { stage: 'Intelligence', label: 'Reviews', value: H.num(S.reviews.length) },
      { stage: 'Intelligence', label: 'Trust scores tracked', value: S.merchants.length + '' },
      { stage: 'Distribution', label: 'Organic landing pages', value: '176' },
      { stage: 'Distribution', label: 'Content published', value: (gx.content || []).filter((c) => c.status === 'Published').length + '' },
      { stage: 'Distribution', label: 'Email subscribers', value: H.num((gx.segments || [])[0] ? gx.segments[0].size : 0) },
      { stage: 'Distribution', label: 'Creator traffic', value: H.num((gx.creators || []).reduce((a, b) => a + b.clicks, 0)) },
      { stage: 'Distribution', label: 'Referral users', value: H.num((gx.referralCohorts || []).reduce((a, b) => a + b.registrations, 0)) },
      { stage: 'Monetisation', label: 'Merchant clicks', value: H.num(clicks) },
      { stage: 'Monetisation', label: 'Affiliate conversions', value: H.num(conv) },
      { stage: 'Monetisation', label: 'Affiliate revenue', value: '€' + H.num(Math.round(revenue)) },
    ];
  }
  function ltvProxy() {
    return (gx.channels || []).map((c) => ({
      channel: c.name,
      value: Math.round((c.revenue / Math.max(1, c.users)) * 1000) / 1000,
      activationRate: r1((c.activated / Math.max(1, c.users)) * 100),
      note: 'Revenue per user, prototype proxy — not financial LTV',
    })).sort((a, b) => b.value - a.value);
  }
  function revenueByMarket() {
    const total = S.affiliate.daily.reduce((a, b) => a + b.revenue, 0);
    const markets = (S.ix.markets || []).slice();
    const weights = markets.map((m) => m.merchants * m.products);
    const sum = weights.reduce((a, b) => a + b, 0) || 1;
    return markets.map((m, i) => ({
      iso: m.iso, name: m.name, revenue: Math.round(total * (weights[i] / sum)),
      share: r1((weights[i] / sum) * 100),
    })).sort((a, b) => b.revenue - a.revenue);
  }
  function campaignConflicts() {
    const running = (gx.campaigns || []).filter((c) => c.status === 'Running' || c.status === 'Scheduled');
    const out = [];
    running.forEach((a, i) => running.slice(i + 1).forEach((b) => {
      if (a.market !== b.market && a.market !== 'ALL' && b.market !== 'ALL') return;
      const overlap = a.starts < b.ends && b.starts < a.ends;
      if (overlap && (a.type === b.type || a.market === b.market)) out.push({ a: a.name, b: b.name, market: a.market === 'ALL' ? b.market : a.market, note: a.type === b.type ? 'Two ' + a.type.toLowerCase() + ' campaigns overlap' : 'Two campaigns overlap in the same market' });
    }));
    return out;
  }

  return {
    qualify, nextBestAction, acquisitionFunnel, acquisitionByMarket,
    affiliateOpportunity, nonMonetised, lowEpc,
    marketScores,
    contentScore, brief, contentValue, contentQueue,
    creatorScore, creatorPerf,
    referralCode, referralFunnel,
    opportunityScore, opportunities, insights, automationRuns,
    dailyBrief, marketBrief, affiliateBrief, acquisitionBrief,
    overview, ltvProxy, revenueByMarket, campaignConflicts,
    _cache: cache,
  };
};
