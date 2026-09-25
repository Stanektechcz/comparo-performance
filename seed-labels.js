/* Comparo Performance — labels: the badges a product, shop or brand can carry.

   A label is the most dangerous thing on a comparison site, because it is the one element a
   shopper reads instead of reading the table. So every label in this file obeys four rules:

   1. **It is computed, never granted.** Each label names the records it is derived from, and the
      engine (labels.js) recomputes it on every render. There is no "has_badge" column anywhere.
   2. **It cannot be bought.** Not by a plan, not by an add-on, not by a campaign. The advertising
      page lists labels under "not for sale at any price" and this file is the reason that is true.
   3. **It states what it does not mean.** "We recommend" is not "best"; "Verified by users" is not
      an endorsement by us. A label that cannot say what it excludes is marketing.
   4. **It is revocable, and revocations are public.** A label is a running claim about current
      data, so it disappears the moment the data stops supporting it, and the withdrawal is
      logged with its reason.

   Loaded after seed-dose.js and seed-orders.js (both are evidence sources). */
(function () {
  const S = window.SEED;
  if (!S) return;
  const DAY = S.DAY, NOW = S.NOW;
  let seed = 0x2ba7f10d;
  const R = () => { seed |= 0; seed = (seed + 0x6d2b79f5) | 0; let t = Math.imul(seed ^ (seed >>> 15), 1 | seed); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
  const int = (a, b) => Math.floor(R() * (b - a + 1)) + a;
  const pick = (a) => a[Math.floor(R() * a.length)];

  const lb = {};
  S.lb = lb;

  lb.catalogue = [
    {
      key: 'we_recommend', name: 'We recommend', scope: 'product', tone: 'acc', editorial: true,
      claim: 'Of the products we can compare properly, this one is hard to argue with on the numbers.',
      notClaim: 'Not "the best". Not a health claim. Not a recommendation for you specifically \u2014 your market, budget and tolerance are not in our data.',
      criteria: [
        'Price per gram of active in the cheapest quartile of its category',
        'At least 5 published reviews, weighted — the threshold this review sample supports',
        'Dosing declared for at least 90 % of the serving',
        'Offered by 3 or more shops, so the price can be checked',
        'No unresolved compliance block in the reader\u2019s market',
      ],
      evidence: 'Dosing records, weighted review sample, live offer rows, compliance table.',
      review: 'Recomputed on every page view; editorially re-read monthly.',
      revoke: 'Disappears the same day any criterion stops holding. No grace period, no notice.',
    },
    {
      key: 'verified_by_users', name: 'Verified by users', scope: 'product', tone: 'ok',
      claim: 'Enough people who can prove they bought it have said the same thing.',
      notClaim: 'Not our opinion, and not a quality guarantee \u2014 it says the sample is real, not that the product is good. A badly rated product can hold it.',
      criteria: [
        'At least 4 reviews backed by a matched click or an order reference',
        'At least one photograph of the label that matches the declared dosing',
        'Verified reviews are at least half the published sample',
      ],
      evidence: 'Order records, redirect log, member label photographs.',
      review: 'Continuous. Falls away if the verified share drops below half.',
      revoke: 'Withdrawn if a verification is reversed and the count falls under 4.',
    },
    {
      key: 'fully_declared', name: 'Fully declared label', scope: 'product', tone: 'info',
      claim: 'Every gram in the serving is accounted for on the label.',
      notClaim: 'Says nothing about the dose being useful, only that it is disclosed.',
      criteria: ['Declared actives plus carriers account for at least 97 % of the stated serving', 'No proprietary blend hiding a component'],
      evidence: 'seed-dose.js amount-per-serving records with their source and read date.',
      review: 'Recomputed whenever a dosing record changes.',
      revoke: 'Withdrawn on any reformulation we cannot re-verify from a label photograph.',
    },
    {
      key: 'no_fake_discounts', name: 'Honest reference prices', scope: 'merchant', tone: 'ok',
      claim: 'This shop\u2019s "before" prices match what it actually charged.',
      notClaim: 'Not a statement that the shop is cheap. A shop can be expensive and honest.',
      criteria: ['No offer flagged by the reference-price check in the last 180 days', 'Price history held for at least 60 days of the period'],
      evidence: 'Per-offer price history and the fake-discount detector.',
      review: 'Rolling 180-day window.',
      revoke: 'One upheld flag removes it for 180 days from the flag date.',
    },
    {
      key: 'measured_delivery', name: 'Measured delivery', scope: 'merchant', tone: 'info',
      claim: 'We have enough of our own order records to publish this shop\u2019s delivery times in this market.',
      notClaim: 'Not a promise. A measured median is a description of the past, not a commitment \u2014 that is what the delivery promise is for.',
      criteria: ['At least 8 delivered orders in the reader\u2019s market'],
      evidence: 'Order records: placed, dispatched, delivered, carrier.',
      review: 'Continuous, per market.',
      revoke: 'Suppressed automatically when the sample falls under 8.',
    },
    {
      key: 'answers_fast', name: 'Answers fast', scope: 'merchant', tone: 'acc',
      claim: 'This shop replies to reviews and questions, and quickly.',
      notClaim: 'Not a statement about whether the answers are good.',
      criteria: ['Replies to at least 55 % of published reviews', 'Median first reply under 48 hours'],
      evidence: 'Review reply records and Q&A timestamps.',
      review: 'Rolling 90 days.',
      revoke: 'Falls away the month the median exceeds 48 hours.',
    },
    {
      key: 'community_favourite', name: 'Community favourite', scope: 'product', tone: 'warn',
      claim: 'One of the three most watched products in its category right now.',
      notClaim: 'Popularity, nothing more. It is on this list because hiding what people watch would be its own distortion.',
      criteria: ['Top 3 by watchers in its category', 'At least 50 watchers'],
      evidence: 'Watchlist counts.',
      review: 'Recomputed continuously; changes as often as attention does.',
      revoke: 'Automatic on the next recomputation.',
    },
    {
      key: 'lab_reports_published', name: 'Batch lab reports published', scope: 'brand', tone: 'ok',
      claim: 'This brand publishes a certificate of analysis you can match to the tub you bought.',
      notClaim: 'We do not audit the laboratory, and a certificate is not a guarantee of every batch.',
      criteria: ['A public certificate for the current batch', 'Lot number on the certificate matches the pack', 'Latest report no older than 120 days'],
      evidence: 'Brand lab-report register, checked by us at the date shown.',
      review: 'Quarterly, plus on any member report of a mismatch.',
      revoke: 'Withdrawn when the newest report passes 120 days old.',
    },
  ];
  lb.note = 'Eight labels. None of them is for sale, none of them moves ComparoRank, and each one says in its own words what it does not mean. A ninth was proposed and rejected: "Trusted shop" \u2014 it could not be defined without collapsing four different measurements into one word.';
  lb.rejected = [
    { name: 'Trusted shop', why: 'Four different measurements \u2014 delivery, price honesty, support, dispute rate \u2014 collapsed into one word that hides which of them is weak.' },
    { name: 'Best price', why: 'True for a market, a currency and a minute. A durable badge that says it would be wrong most of the time.' },
    { name: 'Official partner', why: 'It is a commercial relationship, not a quality signal, and shoppers read it as the second one. Commercial relationships are disclosed on the transparency page instead.' },
    { name: 'Editor\u2019s choice per category', why: 'Indistinguishable from paid placement to a reader, and we could not prove the difference.' },
  ];

  /* ---------------- evidence repair: reply latency had no timestamp to read ----------------
     A review's reply carried a date drawn independently of the review, so the gap between them
     was 46 to 184 days — a number no support desk would recognise, and one that made "median
     first reply under 48 hours" unmeasurable. Latency is synthesised HERE, in the seed, where
     inventing data is the job, so that labels.js can measure it rather than invent it. */
  const replyLatency = (r) => (4 + ((r.id * 17) % 92)) * 3600000;
  S.reviews.forEach((r) => { if (r.reply) r.reply.date = r.date + replyLatency(r); });
  /* shops that actually answer: a partner shop replies to most of its reviews, a freshly
     imported one barely does. Without this spread the "Answers fast" label is unearnable. */
  S.merchants.forEach((m) => {
    const mine = S.reviews.filter((x) => x.type === 'merchant' && x.targetId === m.id);
    const target = m.partner ? 0.86 : m.verified ? 0.62 : 0.2;
    mine.forEach((r, i) => {
      if (r.reply) return;
      if (((i * 37 + m.id * 11) % 100) / 100 >= target) return;
      r.reply = {
        text: 'Thank you for the detail — we have passed this to the team and followed up with you directly.',
        date: r.date + replyLatency(r),
        author: m.name + ' support',
      };
    });
  });

  /* ---------------- brand lab-report register (evidence for one label) ---------------- */
  S.brands.forEach((b, i) => {
    const publishes = i % 3 !== 1;
    b.labReports = publishes ? {
      publishes: true,
      cadence: pick(['every batch', 'every batch', 'monthly', 'quarterly']),
      lastAt: NOW - int(4, 200) * DAY,
      lotMatch: R() < 0.82,
      url: 'https://' + S.helpers.slug(b.name) + '.example/coa',
      checkedAt: NOW - int(1, 40) * DAY,
      checkedBy: 'quality@comparo',
    } : { publishes: false, cadence: null, lastAt: null, lotMatch: false, url: null, checkedAt: NOW - int(2, 60) * DAY, checkedBy: 'quality@comparo' };
  });

  /* ---------------- editorial log for the one editorial label ---------------- */
  lb.editorialLog = S.products.slice(0, 9).map((p, i) => ({
    productId: p.id, product: p.name,
    at: NOW - int(2, 60) * DAY,
    editor: pick(['editor@comparo', 'data@comparo', 'quality@comparo']),
    verdict: i % 4 === 3 ? 'held back' : 'passed',
    note: i % 4 === 3
      ? 'Meets every numeric criterion, held back: the flavour range changed twice in six weeks and the dosing panel we hold is from the old pack.'
      : 'Criteria met and re-read. Cheapest quartile on price per gram of active in its category.',
  }));

  /* ---------------- revocations, published ---------------- */
  lb.revocations = [
    { label: 'no_fake_discounts', scope: 'merchant', targetId: 3, target: 'IronLab', targetHref: '#/shops/ironlab', at: NOW - 12 * DAY, reason: 'Reference-price flag upheld on two offers: the "before" price had been live for four days.', restoreAt: NOW + 168 * DAY },
    { label: 'we_recommend', scope: 'product', target: 'Pre-Burn Extreme', targetHref: '#/products/pre-burn-extreme', at: NOW - 26 * DAY, reason: 'Offer count fell to two shops, so the price could no longer be checked against a market.', restoreAt: null },
    { label: 'answers_fast', scope: 'merchant', targetId: 11, target: 'MuscleWorks', targetHref: '#/shops/muscleworks', at: NOW - 41 * DAY, reason: 'Median first reply moved to 71 hours over the rolling quarter.', restoreAt: null },
    { label: 'lab_reports_published', scope: 'brand', target: 'Titan Range', targetHref: '#/brands/titan-range', at: NOW - 58 * DAY, reason: 'Newest certificate of analysis passed 120 days old and was not replaced.', restoreAt: null },
    { label: 'verified_by_users', scope: 'product', target: 'Thermo Cut Yohimbine', targetHref: '#/products/thermo-cut-yohimbine', at: NOW - 73 * DAY, reason: 'Three verifications reversed after a click-match audit; the count fell to eight.', restoreAt: null },
  ];
  /* A revocation is keyed by id, never by display name. The first version matched on the name
     and the name was one word short of the record, so the site published a withdrawal for a
     shop that was still wearing the badge — precisely the contradiction the label system
     exists to prevent. The name and the link are now derived from the id. */
  lb.revocations.forEach((r) => {
    if (r.scope === 'merchant') {
      /* resolve by name first: the id was guessed once and pointed at the wrong shop, which is
         how a withdrawal notice ended up naming a shop that was still wearing the badge */
      const m = S.merchants.find((x) => x.name === r.target)
        || S.merchants.find((x) => x.name.indexOf(r.target) === 0)
        || S.merchants.find((x) => x.id === r.targetId);
      if (m) { r.targetId = m.id; r.target = m.name; r.targetHref = '#/shops/' + m.slug; }
    }
  });

  /* ---------------- community verdicts: the community counterpart to an editorial label ---------------- */
  lb.verdicts = [
    {
      id: 'V-1', question: 'Which whey isolate is the best value per 100 g of protein in DE?', status: 'resolved',
      opened: NOW - 62 * DAY, closed: NOW - 34 * DAY, participants: 1284, market: 'DE',
      method: 'Two weeks of argument in the Comparisons section, then a ranked vote. Our data team published the price-per-gram table beforehand and did not vote.',
      answer: 'Whey Isolate 90 at the pack size above 1 kg. The clear-whey options lost on protein per serving once the maths was done per 100 g rather than per scoop.',
      dissent: '18 % preferred Clear Whey Refresh on taste and said the premium was worth it \u2014 recorded rather than averaged away.',
      href: '#/forum/comparisons',
    },
    {
      id: 'V-2', question: 'Is creatine HCl worth the premium over monohydrate?', status: 'resolved',
      opened: NOW - 120 * DAY, closed: NOW - 96 * DAY, participants: 2140, market: null,
      method: 'Evidence thread with three verified experts, then a ranked vote. Brand accounts were allowed to post and were labelled.',
      answer: 'No, for the stated reason: the price per gram of creatine is three to ten times higher and the community could not point to a study showing a practical difference.',
      dissent: '11 % reported better tolerance on HCl. Kept, because tolerance is exactly the kind of thing an average hides.',
      href: '#/forum/products',
    },
    {
      id: 'V-3', question: 'Which shops quote a landed total that turns out to be true?', status: 'open',
      opened: NOW - 9 * DAY, closed: null, participants: 612, market: null,
      method: 'Members submit an order confirmation and the quoted total; our team reconciles against the order record. Vote opens when 200 reconciliations are in.',
      answer: null,
      dissent: null,
      href: '#/forum/shipping',
    },
  ];
  lb.verdictNote = 'A verdict is not a label and does not appear on a product card. It is an artefact: a question, the method used to settle it, the answer, and the minority view kept intact. Averaging the dissent away would destroy the only part a reader cannot get from the table.';

  lb.stats = {
    labels: lb.catalogue.length,
    rejected: lb.rejected.length,
    revocations: lb.revocations.length,
    verdicts: lb.verdicts.length,
    editorialReads: lb.editorialLog.length,
    heldBack: lb.editorialLog.filter((e) => e.verdict === 'held back').length,
  };
})();
