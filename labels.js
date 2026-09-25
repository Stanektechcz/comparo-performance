/* Comparo Performance — label engine.

   One resolver per scope. Every label returns the same shape: whether it is earned, the sentence
   that justifies it, the records it was read from, and — when it is not earned — exactly what is
   missing. The last part is the important one: a shop or a brand can see the distance to a label
   without anybody being able to buy the distance away.

   window.ComparoLabels(SEED) -> engine. */
(function () {
  window.ComparoLabels = function (S) {
    if (!S || !S.lb) return null;
    const lb = S.lb, DAY = S.DAY, NOW = S.NOW;
    const def = (key) => lb.catalogue.find((l) => l.key === key) || { key: key, name: key };
    const tone = { acc: 'var(--acc-text)', ok: 'var(--ok)', info: 'var(--info)', warn: 'var(--warn)' };
    const r1 = (n) => Math.round(n * 10) / 10;
    const median = (a) => { if (!a.length) return null; const s = a.slice().sort((x, y) => x - y); return s.length % 2 ? s[(s.length - 1) / 2] : (s[s.length / 2 - 1] + s[s.length / 2]) / 2; };

    const revsFor = (type, id) => (S.reviews || []).filter((r) => r.type === type && r.targetId === id);
    const offersFor = (pid) => (S.offers || []).filter((o) => o.productId === pid);
    const servingMg = (p) => {
      if (!p || p.unit !== 'g') return null;
      const packG = parseFloat(p.pack);
      if (!packG || !p.servings) return null;
      return (packG / p.servings) * 1000;
    };
    const declaredShare = (p) => {
      if (!p.doses || !p.doses.length) return null;
      const mg = servingMg(p);
      if (!mg) return null;
      return Math.min(1, p.doses.reduce((a, d) => a + d.mg, 0) / mg);
    };
    const activeMgPerServing = (p) => (p.doses || []).filter((d) => !d.carrier).reduce((a, d) => a + d.mg, 0);
    /* price per gram of active, the number the whole catalogue is really compared on */
    const pricePerGramActive = (p) => {
      const active = activeMgPerServing(p);
      if (!active || !p.servings) return null;
      const rows = offersFor(p.id);
      const price = rows.length ? Math.min.apply(null, rows.map((o) => o.price)) : p.base;
      return price / ((active / 1000) * p.servings);
    };
    const categoryQuartile = (p) => {
      const peers = (S.products || []).filter((x) => x.categoryId === p.categoryId).map((x) => pricePerGramActive(x)).filter((v) => v !== null).sort((a, b) => a - b);
      const mine = pricePerGramActive(p);
      if (mine === null || peers.length < 4) return null;
      const idx = peers.indexOf(mine);
      return { rank: idx + 1, of: peers.length, quartile: Math.ceil(((idx + 1) / peers.length) * 4), value: mine };
    };
    const compBlocked = (pid, iso) => (S.complianceRules || []).some((r) => r.productId === pid && r.country === iso && (r.status === 'not_allowed' || r.status === 'prescription_only'));

    function row(key, earned, why, evidence, missing) {
      const d = def(key);
      return {
        key: key, name: d.name, scope: d.scope, earned: !!earned,
        why: why || '', evidence: evidence || d.evidence || '',
        missing: missing || [], hasMissing: !!(missing && missing.length),
        claim: d.claim, notClaim: d.notClaim, criteria: d.criteria || [],
        review: d.review, revoke: d.revoke, editorial: !!d.editorial,
        color: tone[d.tone] || 'var(--text-3)',
      };
    }

    /* ---------------- products ---------------- */
    function forProduct(p, iso) {
      if (!p) return [];
      const out = [];
      const revs = revsFor('product', p.id);
      const verified = revs.filter((r) => r.verifiedPurchase);
      const photos = revs.filter((r) => (r.photos || 0) > 0).length;
      const rows = offersFor(p.id);
      const q = categoryQuartile(p);
      const share = declaredShare(p);
      const blocked = iso ? compBlocked(p.id, iso) : false;

      /* we recommend */
      const missA = [];
      if (!q || q.quartile > 1) missA.push(q ? 'price per gram of active is rank ' + q.rank + ' of ' + q.of + ' in the category \u2014 needs the cheapest quartile' : 'no dosing record, so price per gram of active cannot be computed');
      if (revs.length < 5) missA.push(revs.length + ' published reviews of 5 needed');
      if (share === null || share < 0.9) missA.push(share === null ? 'dosing not held' : Math.round(share * 100) + ' % of the serving declared, 90 % needed');
      if (rows.length < 3) missA.push('offered by ' + rows.length + ' shops, 3 needed so the price can be checked');
      if (blocked) missA.push('compliance block in this market');
      const editorial = (lb.editorialLog || []).find((e) => e.productId === p.id);
      if (editorial && editorial.verdict === 'held back') missA.push('held back editorially: ' + editorial.note);
      out.push(row('we_recommend', !missA.length,
        q ? 'Rank ' + q.rank + ' of ' + q.of + ' on price per gram of active in its category, ' + revs.length + ' reviews, ' + Math.round((share || 0) * 100) + ' % of the serving declared, ' + rows.length + ' shops.' : '',
        'Dosing records, weighted review sample, ' + rows.length + ' live offer rows, compliance table.', missA));

      /* verified by users */
      const missB = [];
      if (verified.length < 4) missB.push(verified.length + ' verified-purchase reviews of 4 needed');
      if (photos < 1) missB.push('no review with a photograph, one needed');
      if (revs.length && verified.length / revs.length < 0.5) missB.push(Math.round((verified.length / revs.length) * 100) + ' % of the sample is verified, half needed');
      out.push(row('verified_by_users', !missB.length,
        verified.length + ' of ' + revs.length + ' published reviews are backed by a matched click or an order reference, with ' + photos + ' photographed.',
        'Order records, redirect log, member photographs.', missB));

      /* fully declared */
      const missC = [];
      if (share === null) missC.push('no dosing record held');
      else if (share < 0.97) missC.push(Math.round(share * 100) + ' % of the serving accounted for, 97 % needed');
      out.push(row('fully_declared', !missC.length,
        share !== null ? Math.round(share * 100) + ' % of the stated serving is accounted for by declared ingredients and carriers.' : '',
        p.doseSourceLabel ? p.doseSourceLabel + ', read ' + S.helpers.dateShort(p.doseUpdated) : 'No dosing source', missC));

      /* community favourite */
      const peers = (S.products || []).filter((x) => x.categoryId === p.categoryId).slice().sort((a, b) => b.watchers - a.watchers);
      const pos = peers.findIndex((x) => x.id === p.id) + 1;
      const missD = [];
      if (pos > 3 || pos === 0) missD.push('watched rank ' + (pos || '\u2014') + ' in its category, top 3 needed');
      if ((p.watchers || 0) < 50) missD.push(p.watchers + ' watchers of 50 needed');
      out.push(row('community_favourite', !missD.length,
        'Watched rank ' + pos + ' in ' + ((S.categories.find((c) => c.id === p.categoryId) || {}).name || 'its category') + ' with ' + p.watchers + ' watchers.',
        'Watchlist counts.', missD));

      return out;
    }

    /* ---------------- merchants ---------------- */
    function forMerchant(m, iso) {
      if (!m) return [];
      const out = [];
      const revs = revsFor('merchant', m.id);
      const replied = revs.filter((r) => r.reply && r.reply.date);
      /* measured from the two timestamps we hold, not from anything derived from a primary key */
      const replyHours = replied.map((r) => (r.reply.date - r.date) / 3600000);
      const orders = (S.orders || []).filter((o) => o.merchantId === m.id && o.deliveredAt && (!iso || o.market === iso));
      const minSample = (S.orderMeta || {}).minSample || 8;

      /* Honest reference prices. There is no fake-discount flag on an offer record, so the check
         runs here over the evidence that does exist: a "before" price above anything the market
         ever charged for that product — the brand RRP, and the 90-day high of this shop's own
         series where we hold it — cannot be a price this shop struck through. */
      const refFlagged = (S.offers || []).filter((o) => {
        if (o.merchantId !== m.id || !o.oldPrice || o.oldPrice <= o.price) return false;
        const p = (S.products || []).find((x) => x.id === o.productId);
        if (!p) return false;
        const own = p.hist && p.hist.byMerchant ? p.hist.byMerchant[m.id] : null;
        const ownHigh = own && own.length ? Math.max.apply(null, own) : null;
        const ceiling = ownHigh ? Math.max(ownHigh, p.rrp || 0) : (p.rrp || p.base * 1.22);
        return o.oldPrice > ceiling * 1.06;
      });
      const mineOffers = (S.offers || []).filter((o) => o.merchantId === m.id).length;
      const missA = [];
      if (refFlagged.length) missA.push(refFlagged.length + ' offer' + (refFlagged.length === 1 ? '' : 's') + ' with a reference price above anything the market charged');
      const revoked = (lb.revocations || []).find((r) => r.label === 'no_fake_discounts' && r.targetId === m.id && r.restoreAt && r.restoreAt > NOW);
      if (revoked) missA.push('withdrawn until ' + S.helpers.dateShort(revoked.restoreAt) + ': ' + revoked.reason);
      out.push(row('no_fake_discounts', !missA.length,
        'No reference price above the market high on any of this shop\u2019s ' + mineOffers + ' live offers, and no flag upheld in 180 days.',
        'Per-offer price history, the shop\u2019s own 90-day series and the brand RRP.', missA));

      /* measured delivery */
      const missB = [];
      if (orders.length < minSample) missB.push(orders.length + ' delivered orders' + (iso ? ' in this market' : '') + ' of ' + minSample + ' needed');
      out.push(row('measured_delivery', !missB.length,
        orders.length >= minSample ? 'Median ' + r1(median(orders.map((o) => o.actualDays))) + ' days over ' + orders.length + ' delivered orders' + (iso ? ' in this market' : '') + '.' : '',
        'Order records: placed, dispatched, delivered, carrier.', missB));

      /* answers fast */
      const shareReplied = revs.length ? replied.length / revs.length : 0;
      const med = median(replyHours);
      const missC = [];
      if (shareReplied < 0.55) missC.push(Math.round(shareReplied * 100) + ' % of reviews answered, 55 % needed');
      if (med === null || med >= 48) missC.push(med === null ? 'no answered reviews to measure' : 'median first reply ' + Math.round(med) + ' h, under 48 h needed');
      out.push(row('answers_fast', !missC.length,
        'Answers ' + Math.round(shareReplied * 100) + ' % of published reviews (' + replied.length + ' of ' + revs.length + '), median first reply ' + (med === null ? '\u2014' : Math.round(med) + ' h') + '.',
        'Review timestamps against their reply timestamps.', missC));

      return out;
    }

    /* ---------------- brands ---------------- */
    function forBrand(b) {
      if (!b) return [];
      const lr = b.labReports || { publishes: false };
      const miss = [];
      if (!lr.publishes) miss.push('no public certificate of analysis');
      else {
        if (!lr.lotMatch) miss.push('lot number on the certificate does not match the pack we checked');
        if (lr.lastAt && NOW - lr.lastAt > 120 * DAY) miss.push('newest report is ' + Math.round((NOW - lr.lastAt) / DAY) + ' days old, 120 is the limit');
      }
      return [row('lab_reports_published', !miss.length,
        lr.publishes ? 'Publishes ' + lr.cadence + '; newest report ' + S.helpers.dateShort(lr.lastAt) + ', checked by us ' + S.helpers.dateShort(lr.checkedAt) + '.' : '',
        lr.url ? 'Brand register at ' + lr.url + ', checked ' + S.helpers.dateShort(lr.checkedAt) + ' by ' + lr.checkedBy : 'No register held', miss)];
    }

    /* ---------------- catalogue-wide counts, for the public labels page ---------------- */
    function coverage(iso) {
      return lb.catalogue.map((d) => {
        let held = 0, pool = 0;
        if (d.scope === 'product') {
          (S.products || []).forEach((p) => { pool++; if (forProduct(p, iso).find((x) => x.key === d.key && x.earned)) held++; });
        } else if (d.scope === 'merchant') {
          (S.merchants || []).forEach((m) => { pool++; if (forMerchant(m, iso).find((x) => x.key === d.key && x.earned)) held++; });
        } else {
          (S.brands || []).forEach((b) => { pool++; if (forBrand(b).find((x) => x.key === d.key && x.earned)) held++; });
        }
        return {
          key: d.key, name: d.name, scope: d.scope, held: held, pool: pool,
          share: pool ? Math.round((held / pool) * 1000) / 10 : 0,
          color: tone[d.tone] || 'var(--text-3)',
          claim: d.claim, notClaim: d.notClaim, criteria: d.criteria,
          evidence: d.evidence, review: d.review, revoke: d.revoke, editorial: !!d.editorial,
        };
      });
    }
    /* chips: only what is earned, shortest form, for a card or a page header */
    const chips = (rows) => rows.filter((r) => r.earned).map((r) => ({ key: r.key, name: r.name, color: r.color, why: r.why, notClaim: r.notClaim }));

    return {
      forProduct: forProduct, forMerchant: forMerchant, forBrand: forBrand,
      coverage: coverage, chips: chips, def: def,
      pricePerGramActive: pricePerGramActive, declaredShare: declaredShare,
      catalogue: lb.catalogue, rejected: lb.rejected, revocations: lb.revocations,
      verdicts: lb.verdicts, editorialLog: lb.editorialLog,
    };
  };
})();
