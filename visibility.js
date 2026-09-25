/* Comparo Performance — visibility engine.

   Availability, eligibility and price in one place, so the rate card a shop reads, the gate that
   stops a booking and the row that renders on a list are all the same arithmetic.

   The one function worth reading is assemble(): it shows what "promoted" means here. It does not
   sort. It takes the organic list, walks the surface's reserved slot indices, and *inserts* a
   promoted row at each one it can fill. Every organic row keeps the position its score earned;
   the promoted rows push the tail down by one each, and position 1 is untouchable.

   window.ComparoVisibility(SEED, labelsEngine) -> engine. */
(function () {
  window.ComparoVisibility = function (S, LB) {
    if (!S || !S.vs) return null;
    const vs = S.vs, DAY = S.DAY, NOW = S.NOW;
    const surface = (k) => vs.surfaces.find((s) => s.key === k) || null;
    const gateDef = (k) => vs.gateDefs.find((g) => g.key === k) || { key: k, name: k, how: '' };
    const M = (id) => (S.merchants || []).find((m) => m.id === id) || null;
    const r0 = (n) => Math.round(n);

    /* ---------------- data completeness: drives the discount, not the gate ---------------- */
    function quality(merchantId) {
      const m = M(merchantId);
      if (!m) return { score: 0, parts: [] };
      const offers = (S.offers || []).filter((o) => o.merchantId === merchantId);
      const withEan = offers.filter((o) => o.ean).length;
      const prods = offers.map((o) => (S.products || []).find((p) => p.id === o.productId)).filter(Boolean);
      const dosed = prods.filter((p) => p.doses && p.doses.length).length;
      const orders = (S.orders || []).filter((o) => o.merchantId === merchantId && o.deliveredAt);
      const revs = (S.reviews || []).filter((r) => r.type === 'merchant' && r.targetId === merchantId);
      const replied = revs.filter((r) => r.reply).length;
      const parts = [
        { label: 'EAN coverage', value: offers.length ? Math.round((withEan / offers.length) * 100) : 0, weight: 25 },
        { label: 'Dosing declared on listed products', value: prods.length ? Math.round((dosed / prods.length) * 100) : 0, weight: 25 },
        { label: 'Markets with a measured delivery record', value: Math.min(100, Math.round((new Set(orders.map((o) => o.market)).size / Math.max(1, (m.shipsTo || []).length)) * 100)), weight: 25 },
        { label: 'Reviews answered', value: revs.length ? Math.round((replied / revs.length) * 100) : 0, weight: 25 },
      ];
      const score = Math.round(parts.reduce((a, p) => a + p.value * p.weight, 0) / 100);
      return { score: score, parts: parts, discount: Math.min(25, Math.round(score / 4)) };
    }

    /* ---------------- gates ---------------- */
    function gateState(merchantId, key, market) {
      const m = M(merchantId);
      if (!m) return { ok: false, detail: 'no shop' };
      const labels = LB ? LB.forMerchant(m, market) : [];
      const held = (k) => !!(labels.find((x) => x.key === k) || {}).earned;
      const orders = (S.orders || []).filter((o) => o.merchantId === merchantId && o.deliveredAt && (!market || o.market === market));
      const offers = (S.offers || []).filter((o) => o.merchantId === merchantId);
      const feed = (S.feeds || []).find((f) => f.merchantId === merchantId);
      const revs = (S.reviews || []).filter((r) => r.type === 'merchant' && r.targetId === merchantId);
      const replied = revs.filter((r) => r.reply).length;
      switch (key) {
        case 'verified': return { ok: !!m.verified, detail: m.verified ? 'Verified tier' : 'not yet verified' };
        case 'feed_fresh': return { ok: !feed || !feed.status || feed.status === 'ok' || feed.status === 'healthy', detail: feed && feed.status ? 'feed ' + feed.status : 'feed reachable' };
        case 'no_ref_flag': return { ok: held('no_fake_discounts'), detail: held('no_fake_discounts') ? 'no upheld flag' : 'a reference price is above the market high' };
        case 'measured_delivery': return { ok: orders.length >= ((S.orderMeta || {}).minSample || 8), detail: orders.length + ' delivered orders' + (market ? ' in ' + market : '') };
        case 'in_stock': { const n = offers.filter((o) => o.availability === 'in_stock').length; return { ok: n > 0, detail: n + ' offers in stock' }; }
        case 'dosing_declared': { const dosed = offers.filter((o) => { const p = (S.products || []).find((x) => x.id === o.productId); return p && p.doses && p.doses.length; }).length; return { ok: dosed > 0, detail: dosed + ' listed products with declared dosing' }; }
        case 'coupon_works': { const cs = (S.coupons || []).filter((c) => c.merchantId === merchantId); return { ok: cs.length > 0, detail: cs.length + ' live codes' }; }
        case 'relevance': return { ok: true, detail: 'checked per query at render' };
        case 'ships_there': return { ok: !market || (m.shipsTo || []).indexOf(market) >= 0, detail: market ? ((m.shipsTo || []).indexOf(market) >= 0 ? 'lane live' : 'no lane to ' + market) : 'any market' };
        case 'answers_questions': { const share = revs.length ? replied / revs.length : 0; return { ok: share >= 0.4, detail: Math.round(share * 100) + ' % of reviews answered' }; }
        case 'claims_checkable': return { ok: true, detail: 'checked at creative review' };
        default: return { ok: true, detail: '' };
      }
    }
    function eligibility(merchantId, surfaceKey, market) {
      const sf = surface(surfaceKey);
      if (!sf) return { ok: false, checks: [] };
      const checks = (sf.gates || []).map((g) => {
        const st = gateState(merchantId, g, market);
        const d = gateDef(g);
        return { key: g, name: d.name, how: d.how, ok: st.ok, detail: st.detail };
      });
      return { ok: checks.every((c) => c.ok), checks: checks, missing: checks.filter((c) => !c.ok) };
    }

    /* ---------------- availability and price ---------------- */
    function inv(surfaceKey, market) {
      return vs.inventory.find((i) => i.surface === surfaceKey && i.market === market) || null;
    }
    function priceFor(surfaceKey, market, merchantId, months) {
      const i = inv(surfaceKey, market);
      if (!i) return null;
      const q = merchantId ? quality(merchantId) : { discount: 0 };
      const commit = months >= 6 ? 0.18 : months >= 3 ? 0.1 : 0;
      const gross = i.price;
      const net = Math.round(gross * (1 - q.discount / 100) * (1 - commit));
      return { gross: gross, net: net, qualityDiscount: q.discount, commitDiscount: Math.round(commit * 100), months: months || 1 };
    }
    function rateCard(merchantId, market, months) {
      return vs.surfaces.map((sf) => {
        const i = inv(sf.key, market);
        const el = eligibility(merchantId, sf.key, market);
        const p = priceFor(sf.key, market, merchantId, months);
        const booked = (vs.bookings || []).find((b) => b.merchantId === merchantId && b.surface === sf.key && b.market === market && b.status === 'Running');
        return {
          key: sf.key, name: sf.name, page: sf.page, note: sf.note,
          format: (vs.formats.find((f) => f.key === sf.format) || {}).name || sf.format,
          formatLook: (vs.formats.find((f) => f.key === sf.format) || {}).look || '',
          quiet: (vs.formats.find((f) => f.key === sf.format) || {}).quiet || 0,
          slots: sf.slots, hasSlots: !!(sf.slots || []).length,
          slotLabel: (sf.slots || []).length ? 'positions ' + sf.slots.join(' and ') + ' of ' + sf.listLength : 'beside the content, not in the list',
          capacity: i ? i.capacity : 0, holdback: i ? i.holdback : 0,
          sellable: i ? i.sellable : 0, booked: i ? i.booked : 0, free: i ? i.free : 0,
          soldOut: !i || i.free <= 0,
          nextFree: i && i.nextFree ? i.nextFree : null,
          fill: i && i.sellable ? Math.round((i.booked / i.sellable) * 100) : 0,
          price: p, eligible: el.ok, checks: el.checks, missing: el.missing,
          mine: !!booked,
        };
      });
    }

    /* ---------------- assembly: what "promoted" actually does ---------------- */
    function assemble(organic, surfaceKey, promoted) {
      const sf = surface(surfaceKey);
      const out = (organic || []).map((row, i) => Object.assign({}, row, { position: i + 1, promoted: false, organicPosition: i + 1 }));
      if (!sf || !(sf.slots || []).length || !(promoted || []).length) return out;
      const queue = promoted.slice(0, sf.cap);
      sf.slots.forEach((slot, i) => {
        if (!queue[i]) return;
        if (slot <= 1) return;                        /* position 1 is never for sale */
        const idx = Math.min(slot - 1, out.length);
        out.splice(idx, 0, Object.assign({}, queue[i], { promoted: true, slot: slot }));
      });
      /* renumber: an organic row keeps the rank it earned, shown beside its new position */
      return out.map((row, i) => Object.assign({}, row, { position: i + 1 }));
    }

    /* ---------------- bookings ---------------- */
    function bookings(merchantId) {
      return (vs.bookings || []).filter((b) => !merchantId || b.merchantId === merchantId);
    }
    function canBook(merchantId, surfaceKey, market) {
      const i = inv(surfaceKey, market);
      const el = eligibility(merchantId, surfaceKey, market);
      const already = bookings(merchantId).some((b) => b.surface === surfaceKey && b.market === market && b.status === 'Running');
      if (!i || i.free <= 0) return { ok: false, why: 'Sold out this month' + (i && i.nextFree ? ' \u2014 next free ' + S.helpers.dateShort(i.nextFree) : '') + '. We do not oversell a slot.' };
      if (already) return { ok: false, why: 'You already hold this slot in this market. One per shop per surface per market.' };
      if (!el.ok) return { ok: false, why: 'Not eligible yet: ' + el.missing.map((c) => c.name.toLowerCase()).join(', ') + '.' };
      return { ok: true, why: '' };
    }

    /* ---------------- portfolio view ---------------- */
    function summary(merchantId, market) {
      const card = rateCard(merchantId, market, 1);
      const q = quality(merchantId);
      return {
        eligible: card.filter((r) => r.eligible).length,
        total: card.length,
        available: card.filter((r) => r.eligible && !r.soldOut).length,
        cheapest: Math.min.apply(null, card.filter((r) => r.price).map((r) => r.price.net)),
        quality: q,
        running: bookings(merchantId).filter((b) => b.status === 'Running').length,
        spend: bookings(merchantId).filter((b) => b.status === 'Running').reduce((a, b) => a + b.price, 0),
      };
    }

    return {
      quality: quality, eligibility: eligibility, gateState: gateState,
      inv: inv, priceFor: priceFor, rateCard: rateCard, assemble: assemble,
      bookings: bookings, canBook: canBook, summary: summary,
      surface: surface, surfaces: vs.surfaces, markers: vs.markers,
      formats: vs.formats, forbidden: vs.forbidden, caps: vs.caps,
    };
  };
})();
