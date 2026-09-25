/* Comparo Performance — governance: juries, the product wiki, and the returns index.

   Three mechanics that were missing, each one closing a loop the rest of the product opened.

   **Juries.** Moderation had a queue and a moderator. That works until the decision is
   contested — and the decisions most worth contesting are exactly the ones where we have an
   interest: a review a paying shop wants removed, a label we withdrew, a deal somebody says is
   fake. Those go to a drawn panel of qualified members instead of to us. We keep the power to
   remove illegal content and nothing else.

   **The product wiki.** Dosing, pack contents, allergens and storage are facts about a product,
   not about a shop, and no feed will ever carry all of them. They are editable by members,
   versioned, diffed, revertible, and protected by level: a plain-language summary needs
   Regular; a dosing figure needs Authority *and* a label photograph. A contested edit goes to a
   jury rather than to an edit war.

   **The returns index.** Delivery is measured; returns were not, which left the second half of
   a bad purchase invisible. Every returned order already carries its reason, its refund and its
   dates, so the index is computed from records rather than declared — and suppressed below the
   same eight-order threshold the delivery figures use.

   Loaded after seed-orders.js, seed-gamify.js and seed-labels.js. */
(function () {
  const S = window.SEED;
  if (!S) return;
  const DAY = S.DAY, NOW = S.NOW;
  let seed = 0x6fd21b47;
  const R = () => { seed |= 0; seed = (seed + 0x6d2b79f5) | 0; let t = Math.imul(seed ^ (seed >>> 15), 1 | seed); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
  const int = (a, b) => Math.floor(R() * (b - a + 1)) + a;
  const pick = (a) => a[Math.floor(R() * a.length)];
  const gv = {};
  S.gv = gv;

  /* ================= 1. juries ================= */
  gv.juryWhy = 'The decisions most worth contesting are the ones where we have an interest: a review a paying shop wants gone, a label we withdrew, a deal somebody called fake. A panel of members decides those. We keep exactly one power \u2014 removing illegal content \u2014 and we exercise it in public.';
  gv.juryEligibility = [
    { key: 'level', label: 'Expert level or above (900 XP)', why: 'Enough accepted contributions that the member has been wrong in public at least once.' },
    { key: 'tenure', label: 'Account older than 90 days', why: 'A panel seat is the most valuable thing a sockpuppet could want.' },
    { key: 'reversal', label: 'Reversal rate under 15 %', why: 'Somebody whose own reports do not hold up should not be judging others.' },
    { key: 'conflict', label: 'No declared connection to either party', why: 'Declared connections block the draw automatically; an undeclared one found later voids the verdict.' },
    { key: 'recency', label: 'Not drawn in the last 14 days', why: 'Rotation. A standing panel becomes a faction.' },
  ];
  gv.juryRules = [
    'Up to nine members are drawn and a majority of the seated panel decides. The draw is seeded from the case id, so it is reproducible and auditable after the fact — and the seat count follows the eligible pool rather than an aspiration, so it shrinks when the pool does.',
    'Seventy-two hours to vote. A member who does not vote twice in a row leaves the pool for a season.',
    'Every vote carries a written reason. Votes are published with the reason and without the name.',
    'The verdict, the tally and every reason are public. The evidence is public unless it contains an order number or an address.',
    'One appeal, to a fresh panel of nine, none of whom sat on the first. The second verdict stands.',
    'Comparo may remove illegal content without a panel, and must log it publicly within 24 hours with the legal basis.',
    'Panel service earns 120 XP. It cannot be bought, and it is not visible to shops.',
  ];
  gv.caseTypes = [
    { key: 'review_contested', name: 'Contested review', who: 'Raised by a shop', standard: 'Would a reasonable reader conclude the reviewer did not buy this, or that the review is about something the shop did not do?' },
    { key: 'label_appeal', name: 'Label withdrawal appeal', who: 'Raised by a shop or brand', standard: 'Did the withdrawal follow the published criteria, on the data held at the time?' },
    { key: 'deal_disputed', name: 'Disputed deal', who: 'Raised by members', standard: 'Does the code work as described, in the market and on the basket stated?' },
    { key: 'wiki_conflict', name: 'Wiki conflict', who: 'Raised automatically on a third revert', standard: 'Which version is better supported by the evidence attached to it?' },
    { key: 'sanction_appeal', name: 'Sanction appeal', who: 'Raised by a member', standard: 'Was the sanction proportionate and did the member have a chance to answer?' },
  ];
  const casesSeed = [
    ['review_contested', 'PeakSupps asks for a one-star review to be removed', 'The shop says the reviewer never ordered; the review is labelled unverified and describes a delivery we have no order for. The reviewer has produced a screenshot of a confirmation email with the order number blanked.', 'open', 2, 1, 0],
    ['label_appeal', 'IronLab Store appeals the Honest reference prices withdrawal', 'Two offers carried a struck-through price above anything we recorded for the product. The shop says the reference was the manufacturer RRP at launch, eighteen months ago.', 'open', 1, 2, 0],
    ['deal_disputed', 'PROTEIN20 fails on baskets under \u20ac60, which the deal does not say', 'Eleven members report the code failing; the shop says the minimum is on its own terms page. The deal record on Comparo has no minimum.', 'decided', 1, 5, 1],
    ['wiki_conflict', 'Beta-alanine per serving: 3.2 g or 4 g on Pre-Burn Extreme', 'Two label photographs from different batches. Neither editor will yield; the record has been reverted three times.', 'decided', 5, 2, 0],
    ['sanction_appeal', 'Data sources suspended after a 31 % reversal rate', 'The member says eight of the reversals were the same price change reported from a cached page, and asks for them to be counted once.', 'decided', 4, 2, 1],
    ['review_contested', 'Brand asks for a review that names a competitor to be edited', 'The review compares two products by price per gram. The brand says the comparison is misleading because the pack sizes differ.', 'open', 1, 0, 2],
  ];
  gv.cases = casesSeed.map((c, i) => {
    /* An open case is one a vote can still decide, so it is seeded inside the 72-hour window
       and short of quorum. Seeding "open" cases weeks past their own deadline, already at a
       majority, was a page arguing for a process it was visibly not following. */
    const opened = c[3] === 'open' ? NOW - int(3, 58) * 3600000 : NOW - int(6, 40) * DAY;
    /* No panel is stored here. The panel of record is the one draw() produces from the case id;
       a second copy on the record would be a second answer to the same question. */
    return {
      id: 'JUR-' + (2100 + i), type: c[0], title: c[1], detail: c[2],
      status: c[3], opened: opened, deadline: opened + 3 * DAY,
      votes: { uphold: c[4], overturn: c[5], abstain: c[6] },
      verdict: c[3] === 'decided' ? (c[4] > c[5] ? 'upheld' : 'overturned') : null,
      appealed: i === 4,
      decidedAt: c[3] === 'decided' ? opened + int(1, 3) * DAY : null,
      reasons: [
        'The published criterion is about the data held at the time, not about what was true eighteen months ago. Nothing in the record supports the reference.',
        'A confirmation email with the order number removed is not evidence we can check, but the shop has not produced an order list either. On the balance of what is in front of us, the review stays and stays labelled unverified.',
        'The minimum basket is not on the deal record, so the deal as published is wrong. Fix the record, not the reviews.',
        'Two batches, two numbers, both photographed. Both belong in the record with their batch codes rather than one replacing the other.',
      ].slice(0, int(2, 4)),
    };
  });
  gv.juryStats = {
    pool: S.users.filter((u) => (u.rep || 0) >= 900).length,
    cases: gv.cases.length,
    open: gv.cases.filter((c) => c.status === 'open').length,
    overturnRate: Math.round((gv.cases.filter((c) => c.verdict === 'overturned').length / Math.max(1, gv.cases.filter((c) => c.verdict).length)) * 100),
    medianHours: 41,
    serviceXp: 120,
  };
  gv.juryNote = 'Our own moderation decisions are overturned in ' + gv.juryStats.overturnRate + ' % of appealed cases. We publish that number because a panel that never overturns anything is decoration.';

  /* ================= 2. the product wiki ================= */
  gv.wikiWhy = 'A feed carries price, stock and a title. It does not carry the amount per serving, the allergen line, the storage instruction or a sentence explaining what the thing is for. Those are facts about a product, they do not change per shop, and nobody but the people who own the tub can supply them.';
  gv.wikiFields = [
    { key: 'summary', name: 'Plain-language summary', level: 3, levelName: 'Regular', evidence: 'None required', why: 'One sentence a beginner can read. The single most requested thing on the whole site.' },
    { key: 'allergens', name: 'Allergen line', level: 4, levelName: 'Trusted', evidence: 'Label photograph', why: 'Safety information. Wrong is worse than absent, so it needs the panel in shot.' },
    { key: 'storage', name: 'Storage and shelf life', level: 3, levelName: 'Regular', evidence: 'Label photograph or pack photo', why: 'Cheap to supply, and it is the second thing people ask in rooms.' },
    { key: 'flavours', name: 'Flavours actually available', level: 2, levelName: 'Contributor', evidence: 'A shop page link', why: 'Feeds disagree with shop pages constantly. This is the corrective.' },
    { key: 'certifications', name: 'Certifications', level: 4, levelName: 'Trusted', evidence: 'Certificate number', why: 'Informed Sport, vegan, halal. Checkable against a register.' },
    { key: 'dosing', name: 'Amount per serving', level: 7, levelName: 'Authority', evidence: 'Label photograph with the lot number legible', why: 'It drives price per gram of active, the comparison the whole catalogue rests on. Highest protection on the site.' },
    { key: 'usage', name: 'How it is typically used', level: 5, levelName: 'Expert', evidence: 'None required', why: 'Timing and dose in practice. Explicitly not advice, and the page says so.' },
  ];
  gv.wikiRules = [
    'Every field has a level and an evidence requirement, and both are shown on the edit form before you start typing.',
    'Edits are proposals until reviewed. Anything above Trusted is reviewed by two members at or above the field\u2019s level.',
    'Full version history, with a diff per revision and a one-click revert that is itself a revision.',
    'Three reverts on the same field opens a jury case automatically. There are no edit wars, only cases.',
    'A shop can propose an edit to its own listing like anybody else, from a labelled shop account, and it carries no extra weight.',
    'A brand can supply a specification sheet; it is recorded as a claim with a source, not as a fact, until a label photograph agrees with it.',
    'Contributors are credited by revision on the product page, and the credit cannot be bought or transferred.',
  ];
  const wikiProducts = S.products.slice(0, 6);
  gv.wikiProposals = wikiProducts.map((p, i) => ({
    id: 'WIK-' + (500 + i), productId: p.id, product: p.name, slug: p.slug,
    field: gv.wikiFields[i % gv.wikiFields.length].key,
    fieldName: gv.wikiFields[i % gv.wikiFields.length].name,
    author: S.users[(i * 4) % S.users.length].username,
    authorNick: S.users[(i * 4) % S.users.length].nick,
    at: NOW - int(1, 18) * DAY,
    before: ['—', 'Contains milk', 'Cool, dry place', 'Vanilla, Chocolate', '—', '3.2 g beta-alanine', '—'][i % 7],
    after: ['A fast-absorbing whey isolate with 22 g of protein per 30 g scoop and no added sugar.', 'Contains milk and soy lecithin', 'Below 25 °C, dry, away from light. Best before on the base of the tub.', 'Vanilla, Chocolate, Berry, Unflavoured', 'Informed Sport certified, batch register public', '4 g beta-alanine (batch 24-118)', 'Typically 1 scoop within an hour of training; splitting it makes no measurable difference.'][i % 7],
    evidence: ['none required', 'Label photograph attached', 'Pack photo attached', 'Shop page link attached', 'Certificate 2024-IS-4471', 'Label photograph, lot 24-118 legible', 'none required'][i % 7],
    status: i < 2 ? 'pending' : i < 4 ? 'merged' : i === 4 ? 'changes requested' : 'disputed',
    reviewers: i < 2 ? [] : [S.users[(i * 7) % S.users.length].username],
    note: i === 5 ? 'Third revert on this field \u2014 a jury case was opened automatically.' : '',
  }));
  gv.wikiHistory = wikiProducts.slice(0, 3).map((p, i) => ({
    productId: p.id, product: p.name, slug: p.slug,
    revisions: [3, 5, 2][i] + int(1, 6),
    contributors: int(2, 7),
    lastAt: NOW - int(1, 20) * DAY,
    protected: i === 0,
    protectedWhy: i === 0 ? 'Dosing field locked to Authority after two contested edits.' : '',
  }));
  gv.wikiStats = {
    fields: gv.wikiFields.length,
    products: S.products.length,
    withSummary: Math.round(S.products.length * 0.34),
    withAllergens: Math.round(S.products.length * 0.22),
    proposals: gv.wikiProposals.length,
    pending: gv.wikiProposals.filter((p) => p.status === 'pending').length,
    merged: gv.wikiProposals.filter((p) => p.status === 'merged').length,
  };

  /* ================= 3. the returns index ================= */
  gv.returnsWhy = 'Delivery is measured and published; returns were not, which left the second half of a bad purchase invisible. Every returned order already carries its reason, its refund and its dates, so this is computed from records \u2014 and suppressed below the same eight-order threshold the delivery figures use.';
  const returned = (S.orders || []).filter((o) => o.status === 'returned');
  /* who paid the return postage, and how long the refund took: derived per order, deterministically */
  returned.forEach((o, i) => {
    o.returnPostagePaidBy = (o.merchantId + i) % 3 === 0 ? 'shop' : 'buyer';
    o.refundedAt = o.returnedAt + (4 + ((o.merchantId * 7 + i * 5) % 26)) * DAY / 2;
    o.refundDays = Math.round(((o.refundedAt - o.returnedAt) / DAY) * 10) / 10;
  });
  gv.returnReasonGroups = [
    { key: 'changed_mind', label: 'Changed mind', counts: 'withdrawal period', note: 'A high share here is not a fault. It usually means the listing was accurate and the buyer simply changed their mind.' },
    { key: 'wrong_item', label: 'Wrong item sent', counts: 'shop error', note: 'Counts against the shop in the index. This is the one the index weights hardest.' },
    { key: 'damaged', label: 'Damaged in transit', counts: 'packaging and carrier', note: 'Weighted half: the carrier shares it, but packaging is the shop\u2019s choice.' },
    { key: 'late', label: 'Arrived after the promised date', counts: 'shop error', note: 'Also generates a delivery-promise claim where one is live.' },
    { key: 'duplicate', label: 'Duplicate order', counts: 'neutral', note: 'Not counted. A buyer double-clicking is nobody\u2019s failure.' },
  ];
  const reasonGroup = (r) => /wrong flavour|wrong/i.test(r) ? 'wrong_item'
    : /damaged/i.test(r) ? 'damaged'
    : /after the date|late/i.test(r) ? 'late'
    : /duplicate/i.test(r) ? 'duplicate' : 'changed_mind';
  gv.returnsIndex = [];
  (S.merchants || []).forEach((m) => {
    const markets = Array.from(new Set((S.orders || []).filter((o) => o.merchantId === m.id).map((o) => o.market)));
    markets.forEach((iso) => {
      const all = (S.orders || []).filter((o) => o.merchantId === m.id && o.market === iso && o.deliveredAt);
      const rets = all.filter((o) => o.status === 'returned');
      if (!all.length) return;
      const faulted = rets.filter((o) => { const g = reasonGroup(o.returnReason || ''); return g === 'wrong_item' || g === 'late'; }).length
        + rets.filter((o) => reasonGroup(o.returnReason || '') === 'damaged').length * 0.5;
      const refundDays = rets.map((o) => o.refundDays).filter((n) => typeof n === 'number').sort((a, b) => a - b);
      const shopPaid = rets.filter((o) => o.returnPostagePaidBy === 'shop').length;
      gv.returnsIndex.push({
        merchantId: m.id, market: iso, orders: all.length, returns: rets.length,
        enough: all.length >= ((S.orderMeta || {}).minSample || 8),
        returnRate: Math.round((rets.length / all.length) * 1000) / 10,
        faultRate: Math.round((faulted / all.length) * 1000) / 10,
        medianRefundDays: refundDays.length ? refundDays[Math.floor(refundDays.length / 2)] : null,
        shopPaysPostage: rets.length ? Math.round((shopPaid / rets.length) * 100) : null,
        window: m.returnDays || 14,
      });
    });
  });
  gv.returnsRules = [
    'The index counts fault, not volume. A shop with many changed-mind returns and no errors scores better than one with few returns that were all wrong items.',
    'Damaged in transit counts half: the carrier shares it, the packaging does not.',
    'Duplicate orders are not counted at all.',
    'Below eight delivered orders in a market nothing is published \u2014 the same threshold the delivery figures use.',
    'Fault needs three returns before it is scored. Below that a pair is scored on refund speed alone: one return says nothing about a shop\u2019s error rate and everything about how fast it pays.',
    'Refund time is measured from the day the return was logged, not from the day the shop chose to process it.',
    'Who pays the return postage is published as a fact, not scored. A shop that charges for returns and says so is not being dishonest.',
  ];
  gv.returnsStats = {
    orders: (S.orders || []).filter((o) => o.deliveredAt).length,
    returns: returned.length,
    rate: Math.round((returned.length / Math.max(1, (S.orders || []).filter((o) => o.deliveredAt).length)) * 1000) / 10,
    published: gv.returnsIndex.filter((r) => r.enough).length,
    suppressed: gv.returnsIndex.filter((r) => !r.enough).length,
    medianRefund: (() => {
      const d = returned.map((o) => o.refundDays).filter((n) => typeof n === 'number').sort((a, b) => a - b);
      return d.length ? d[Math.floor(d.length / 2)] : null;
    })(),
  };
  /* the merchant-side counterpart: what a shop can do about it */
  gv.returnsActions = [
    { key: 'window', label: 'Publish a longer window', effect: 'Return window is on the profile and in the comparison. 30 days converts measurably better than 14.', cost: 'free' },
    { key: 'postage', label: 'Pay return postage', effect: 'Published as a fact beside the window. The strongest single signal in this block.', cost: 'free' },
    { key: 'refund_sla', label: 'Refund SLA add-on', effect: 'We chase your return queue and escalate anything past your stated window; median refund time is published either way.', cost: '€59 / month' },
    { key: 'packaging', label: 'Fix packaging', effect: 'Damaged-in-transit is the one return reason a shop can engineer away. It counts half, and it is half of most bad indices.', cost: 'free' },
  ];
  /* two contribution sources these mechanics create, added to the same ledger everything else
     is paid from — a parallel points system would be a second truth about the same member */
  if (S.gm && S.gm.xpSources) {
    S.gm.xpSources.push(
      { key: 'jury_service', label: 'Jury service', xp: 120, cap: 1, group: 'Community', why: 'Sat on a panel and voted with a written reason. Paid per case, never for being drawn.', proof: 'Vote recorded against a drawn seat.', collects: 'Governance' },
      { key: 'wiki_edit', label: 'Wiki edit merged', xp: 55, cap: 4, group: 'Data', why: 'A proposal to a product record that two reviewers at or above the field\u2019s level accepted.', proof: 'The evidence the field requires — a label photograph for dosing and allergens.', collects: 'Product facts no feed carries' }
    );
  }
})();
