/* Comparo Performance — visibility: what a shop can buy, and the wall in front of it.

   Three decisions shape this file.

   **1. Prices are behind a shop profile, and that is not a sales tactic.** A rate card cannot be
   published honestly here, because a price only exists once we know the market, the surface, how
   many slots are free this month and whether *this* shop passes the quality gate for that
   surface. A public number would be fiction, and a fiction about price on a price-comparison
   site is a bad way to start. So the rules, the caps and the refusals stay public; the numbers
   appear once a shop profile exists and has been approved.

   **2. Nothing flashes.** No banners, no animation, no interstitials, no countdown pressure, no
   "buy now". The forbidden list below is enforced at creative review and is published so a shop
   knows what it cannot ask for. What is sold instead is *position and presence*: a reserved slot
   further up a list, a spotlight card, a commercial marker, a labelled announcement.

   **3. A promoted row is inserted, never re-sorted.** Buying visibility does not change
   ComparoRank and does not move an organic row down by score. A surface reserves a small number
   of fixed slot indices; a promoted row occupies one of them, labelled, and every other row
   keeps the position its score earned. Position 1 is never for sale on any surface, and every
   list has a one-click "organic order only" control.

   Loaded after seed-network.js and seed-addons.js. */
(function () {
  const S = window.SEED;
  if (!S) return;
  const DAY = S.DAY, NOW = S.NOW;
  let seed = 0x41c9e7b3;
  const R = () => { seed |= 0; seed = (seed + 0x6d2b79f5) | 0; let t = Math.imul(seed ^ (seed >>> 15), 1 | seed); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
  const int = (a, b) => Math.floor(R() * (b - a + 1)) + a;
  const vs = {};
  S.vs = vs;

  /* ---------------- one clock, one anchor, and it survives a reload ----------------
     The catalogue clock was anchored twice: here at script-parse time, and again inside the
     component the first time anything called now(). A record stamped by the second and
     formatted against the first read as old the instant it was written — by exactly the lag
     between the two samples. Worse, both restarted at S.NOW on reload, so a stamp persisted
     from an earlier session landed in the future and printed "just now" above genuinely newer
     rows in the audit log.

     There is now one anchor. It lives beside the formatter, the component delegates to it, and
     the elapsed offset is persisted so the clock is monotonic across sessions: a stamp written
     yesterday is still in the past tomorrow. */
  (function () {
    /* The offset is versioned. The previous key was poisoned by a migration guard that read the
       newest persisted stamp and adopted it as the anchor — but those stamps predated this
       clock and carried Date.now(), so the guard swallowed the whole real-clock skew (8.6 days)
       and then re-satisfied its own condition on every load. There is no stamp scan any more:
       the anchor comes from this regime's own key or it starts at S.NOW, and a legacy stamp
       that lands in the future is handled by the formatter rather than by moving the clock. */
    const KEY = 'comparo.clock.v2';
    let offset = 0;
    try {
      offset = parseInt(localStorage.getItem(KEY) || '0', 10) || 0;
      localStorage.removeItem('comparo.clock.offset');
    } catch (e) {}
    if (!(offset >= 0) || offset > 30 * S.DAY) offset = 0;
    const BOOT = Date.now();
    const liveNow = () => S.NOW + offset + (Date.now() - BOOT);
    S.helpers.now = liveNow;

    /* One-time migration of stamps written under the two earlier regimes (real-clock
       Date.now(), and the poisoned 8.6-day offset). They sit in the future, so ago() collapsed
       all of them to "just now" and the ts-sorted notification list put them above genuinely
       newer rows. They are rewritten into the past here, spaced a minute apart so their
       relative order survives — the anchor is NOT moved to meet them, because doing that is
       what produced the skew in the first place.

       Only event-time fields are touched. starts / ends / expires / due are left alone: those
       are future by design and clamping them would retire a live coupon.

       This runs on EVERY load and is deliberately not marker-gated. The first version set a
       one-shot marker, and a stale snapshot persisted by another open tab afterwards could
       never be corrected again — the guard permanently suppressed the only code that fixes it.
       The walk is bounded (about a hundred fields) and a no-op once the state is clean. The
       same function is called by the component's load(), which is the authoritative point:
       storage can be rewritten by another tab at any time, but nothing reaches the app without
       passing through there. */
    const FIELDS = ['ts', 'at', 'created', 'date', 'submittedAt', 'decidedAt', 'lastActivity', 'issued', 'verifiedAt', 'resolvedAt', 'opened', 'updated', 'lastTopUp', 'since'];
    S.helpers.sanitizeStamps = (st) => {
      if (!st || typeof st !== 'object') return 0;
      const cutoff = liveNow() + 3600000;        /* >1 h ahead means a foreign regime */
      const hits = [];
      const seen = new Set();
      const walk = (o) => {
        if (!o || typeof o !== 'object' || seen.has(o)) return;
        seen.add(o);
        if (Array.isArray(o)) { o.forEach(walk); return; }
        FIELDS.forEach((f) => { if (typeof o[f] === 'number' && o[f] > cutoff) hits.push({ o: o, f: f }); });
        Object.keys(o).forEach((k) => { if (o[k] && typeof o[k] === 'object') walk(o[k]); });
      };
      walk(st);
      if (!hits.length) return 0;
      hits.sort((a, b) => a.o[a.f] - b.o[b.f]);
      const base = liveNow() - 60000;
      hits.forEach((x, i) => { x.o[x.f] = base - (hits.length - 1 - i) * 60000; });
      return hits.length;
    };
    try {
      const raw = localStorage.getItem('comparo.proto.v2');
      if (raw) {
        const st = JSON.parse(raw);
        if (S.helpers.sanitizeStamps(st)) localStorage.setItem('comparo.proto.v2', JSON.stringify(st));
      }
      localStorage.removeItem('comparo.clock.migrated.v2');
    } catch (e) {}
    const save = () => { try { localStorage.setItem(KEY, String(offset + (Date.now() - BOOT))); } catch (e) {} };
    try {
      setInterval(save, 5000);
      window.addEventListener('pagehide', save);
      window.addEventListener('beforeunload', save);
    } catch (e) {}
    S.helpers.ago = (t) => {
      const ms = liveNow() - t;
      if (ms < 45000) return 'just now';        /* also catches a stamp from the old regime */
      const mins = Math.round(ms / 60000);
      if (mins < 60) return mins + ' min ago';
      const hrs = Math.round(mins / 60);
      if (hrs < 24) return hrs + ' h ago';
      const d = Math.round(hrs / 24);
      if (d < 30) return d + ' day' + (d === 1 ? '' : 's') + ' ago';
      const mo = Math.round(d / 30.4);
      if (mo < 12) return mo + ' month' + (mo === 1 ? '' : 's') + ' ago';
      const y = Math.round(mo / 12);
      return y + ' year' + (y === 1 ? '' : 's') + ' ago';
    };
  })();

  /* ================= 1. the wall ================= */
  vs.gateReason = [
    { title: 'A price here is not a number, it is a calculation', body: 'What a slot costs depends on the market, the surface, how many of that month\u2019s slots are still free, and how complete your own data is. We can compute that for a shop we know. For a visitor we would have to invent it.' },
    { title: 'Half of the catalogue is not for sale to you specifically', body: 'Most surfaces have a quality gate: a measured delivery record, a fresh feed, no upheld reference-price flag. Showing a shop a price for something it cannot buy wastes everybody\u2019s afternoon.' },
    { title: 'The rules stay public', body: 'What we refuse to sell, what we refuse to build, the caps per surface and the disclosure requirements are published to everyone, signed in or not. Only the numbers and the booking are behind the profile.' },
  ];
  vs.gateSteps = [
    { n: 1, label: 'Sign in', detail: 'Any account. Ten seconds.' },
    { n: 2, label: 'Create a shop profile', detail: 'Legal entity, feed, markets, policies. Fifteen minutes, and you can save and come back.' },
    { n: 3, label: 'We review it', detail: 'Feed validation, registry and VAT check, duplicate detection, compliance scan. Eight working days at the outside, usually two.' },
    { n: 4, label: 'Published \u2014 the rate card opens', detail: 'Live availability per surface and market, your eligibility per slot, and what is missing where you are not eligible.' },
  ];

  /* ================= 2. the shop profile application ================= */
  vs.appSteps = [
    { key: 'identity', name: 'Shop identity', fields: ['Trading name', 'Legal entity and registration number', 'VAT number', 'Country of establishment', 'Support email answered by a human'], why: 'Everything downstream hangs off a real legal entity. It is also what makes the Verified tier possible later.' },
    { key: 'catalogue', name: 'Catalogue', fields: ['Feed URL or platform app', 'Feed format', 'Currency', 'EAN coverage'], why: 'We do not type your catalogue in. A feed we can read on a schedule is the whole relationship.' },
    { key: 'markets', name: 'Markets and delivery', fields: ['Markets you ship to', 'Carrier per market', 'Dispatch cutoff', 'Free-shipping threshold'], why: 'A shop is listed in every market it ships to, free. This is the list.' },
    { key: 'policies', name: 'Policies', fields: ['Returns window', 'Returns cost', 'Warranty handling', 'Complaint route'], why: 'Published on your profile verbatim. A policy we cannot show is a policy shoppers will not believe.' },
    { key: 'declarations', name: 'Declarations', fields: ['No incentivised or purchased reviews', 'Prices in the feed match the shop page', 'You will answer data disputes within 5 working days', 'Staff posting in the community will do so from a labelled shop account'], why: 'Four promises. Breaking the first one is the only thing that gets a shop delisted permanently.' },
  ];
  vs.appChecks = [
    { key: 'feed_reachable', name: 'Feed reachable and parses', auto: true, sla: '2 minutes', fail: 'We tell you which line broke it.' },
    { key: 'attributes', name: 'Required attributes present on 90 % of lines', auto: true, sla: '5 minutes', fail: 'You get the missing-attribute report per line, not a rejection.' },
    { key: 'ean', name: 'EAN matches a known product on 80 % of lines', auto: true, sla: '5 minutes', fail: 'Unmatched lines go to manual matching rather than being dropped.' },
    { key: 'registry', name: 'Company registry and VAT number check', auto: false, sla: '2 working days', fail: 'We ask once for a document; we do not guess.' },
    { key: 'duplicate', name: 'Not a duplicate of a listed shop', auto: true, sla: 'instant', fail: 'A second storefront of a listed shop is merged, not listed twice.' },
    { key: 'compliance', name: 'Restricted products flagged per market', auto: false, sla: '2 working days', fail: 'Flagged products are suppressed in the affected markets only.' },
    { key: 'support', name: 'Support address answers within 72 h', auto: false, sla: '3 working days', fail: 'We send one test enquiry. No answer, no Verified tier \u2014 the listing still goes live.' },
  ];
  vs.appStatuses = [
    { key: 'draft', label: 'Draft', meaning: 'Yours. Nobody has seen it.' },
    { key: 'submitted', label: 'Submitted', meaning: 'Queued. Automated checks run within the hour.' },
    { key: 'in_review', label: 'In review', meaning: 'A named reviewer has it. You can see who.' },
    { key: 'changes_requested', label: 'Changes requested', meaning: 'Specific, itemised, with the line numbers where it applies.' },
    { key: 'approved', label: 'Approved', meaning: 'Listing goes live in every market you ship to, free.' },
    { key: 'published', label: 'Published', meaning: 'Live. The visibility rate card is open.' },
    { key: 'suspended', label: 'Suspended', meaning: 'Offers suppressed within 24 h so nobody clicks into a dead checkout. Reviews and history stay published.' },
  ];

  /* ================= 3. formats: what we will and will not build ================= */
  vs.forbidden = [
    { what: 'Animated, blinking or auto-playing anything', why: 'It reads as an advertisement before it reads as information, and it makes the page feel like a worse site than it is.' },
    { what: 'Interstitials, pop-ups, sticky overlays', why: 'They interrupt a decision the shopper is in the middle of making. That is the one moment we exist for.' },
    { what: 'Countdown pressure and scarcity language we cannot verify', why: '"3 people are viewing this" is either unverifiable or a lie. A deal countdown is fine \u2014 it reads a real expiry date.' },
    { what: 'Imperative calls to action \u2014 "Buy now", "Don\u2019t miss out"', why: 'A promoted row says who it is and what it costs. It does not tell anybody what to do.' },
    { what: 'Position 1 on any list', why: 'The top of a list is the answer to the question the shopper asked. It cannot be for sale on a comparison site.' },
    { what: 'Reordering organic rows', why: 'A promoted row is inserted at a reserved index. Every other row keeps the position its score earned.' },
    { what: 'Any trust label, or anything shaped like one', why: 'Trust labels are computed. A commercial marker must not be mistakable for one, which is why the two sets look different and never share a row.' },
    { what: 'Retargeting pixels and third-party audience data', why: 'We buy no audience data at all, and we do not let anybody else collect it here.' },
  ];
  vs.formats = [
    { key: 'promoted_row', name: 'Promoted row', look: 'The same row as an organic one, same columns, same data, with a "Promoted" marker and a tinted left edge.', where: 'Lists', quiet: 5 },
    { key: 'spotlight_card', name: 'Spotlight card', look: 'One product card in a rail beside the content, labelled, with real price and delivery data pulled from your feed.', where: 'Category, ingredient, market pages', quiet: 4 },
    { key: 'partner_card', name: 'Partner card', look: 'Shop name, delivery window, return policy, one line of your own copy. No imagery beyond your logo.', where: 'Market hubs, category rails', quiet: 4 },
    { key: 'marker', name: 'Commercial marker', look: 'A small disclosed chip on your shop profile and your rows. Visually distinct from trust labels \u2014 outline, not filled, and never in the label row.', where: 'Your profile and your rows', quiet: 2 },
    { key: 'announcement', name: 'Section announcement', look: 'One labelled post a month in the forum section for your market. It is a post, so it can be replied to and argued with.', where: 'Forum sections', quiet: 3 },
    { key: 'desk', name: 'Shop desk hour', look: 'A scheduled hour in a market room where your staff answer questions under a labelled account.', where: 'Live rooms', quiet: 1 },
    { key: 'guide_sponsor', name: 'Sponsored guide', look: 'A guide you write, editorially reviewed for factual claims, labelled as sponsored at the top and in the index.', where: 'Guides', quiet: 3 },
    { key: 'newsletter_slot', name: 'Newsletter slot', look: 'One slot per send, disclosed in the subject line as well as the body.', where: 'Newsletter', quiet: 4 },
  ];
  vs.formatNote = 'Quiet is scored 1 to 5 where 1 is invisible to anybody not looking for it. Nothing above 5 exists, and nothing above 5 will be built.';

  /* ================= 4. commercial markers (never trust labels) ================= */
  vs.markers = [
    { key: 'supporting_shop', name: 'Supporting shop', price: 79, disclosure: 'Pays to support Comparo. Says nothing about price, delivery or quality \u2014 those are measured separately and shown beside it.', where: 'Shop profile header and shop directory rows', gate: 'Verified tier, no upheld reference-price flag.' },
    { key: 'market_partner', name: 'Market partner', price: 149, disclosure: 'A paid partnership in this market. Not a quality signal, not a recommendation.', where: 'Market hub and market-filtered lists', gate: 'Measured delivery in that market, Pro plan or above.' },
    { key: 'category_partner', name: 'Category partner', price: 199, disclosure: 'A paid partnership in this category. The ranking inside the category is unaffected and ordered by ComparoRank.', where: 'Category pages', gate: 'At least 20 live offers in the category, feed fresh.' },
    { key: 'guide_sponsor', name: 'Guide sponsor', price: 240, disclosure: 'Paid for this guide to be written. Had no say in its conclusions, and the editorial note says so at the top.', where: 'One guide', gate: 'No factual claim we cannot check against your feed.' },
  ];
  vs.markerRule = 'A commercial marker is outlined, never filled; sits in its own row under the shop name, never in the trust-label row; and carries its disclosure on hover and on the labels page. A shopper who reads nothing else still sees the word "paid".';

  /* ================= 5. surfaces and their reserved slots ================= */
  /* positions are 1-indexed slots in the rendered list. Position 1 never appears here. */
  vs.surfaces = [
    { key: 'deal_list', name: 'Deal hub list', page: 'Deals', listLength: 24, slots: [3, 11], cap: 2, format: 'promoted_row', base: 640, gates: ['feed_fresh', 'no_ref_flag', 'coupon_works'], note: 'A promoted deal must be a live deal record with a working code. A code that fails is pulled automatically and the slot is refunded pro rata.' },
    { key: 'shop_list', name: 'Shop directory', page: 'Shops', listLength: 14, slots: [4], cap: 1, format: 'promoted_row', base: 780, gates: ['verified', 'measured_delivery', 'no_ref_flag'], note: 'One slot only, and it is the fourth row. The three above it are whatever ComparoRank put there.' },
    { key: 'product_list', name: 'Product and category lists', page: 'Category', listLength: 30, slots: [5, 17], cap: 2, format: 'promoted_row', base: 560, gates: ['feed_fresh', 'in_stock'], note: 'The promoted product must be in stock and its price must match the shop page at the moment of render.' },
    { key: 'search_list', name: 'Search results', page: 'Search', listLength: 20, slots: [4], cap: 1, format: 'promoted_row', base: 900, gates: ['feed_fresh', 'relevance'], note: 'Relevance gate: a promoted row that does not match the query is not shown at all, and the slot goes unsold rather than filled with something irrelevant.' },
    { key: 'category_rail', name: 'Category spotlight', page: 'Category', listLength: 0, slots: [], cap: 2, format: 'spotlight_card', base: 480, gates: ['feed_fresh', 'dosing_declared'], note: 'Beside the ranking, never inside it. Requires declared dosing, because a spotlight without a comparable number is just a poster.' },
    { key: 'ingredient_rail', name: 'Ingredient spotlight', page: 'Ingredient', listLength: 0, slots: [], cap: 1, format: 'spotlight_card', base: 520, gates: ['dosing_declared'], note: 'Sits beside the price-per-gram ranking it cannot influence.' },
    { key: 'market_card', name: 'Market hub partner card', page: 'Market', listLength: 0, slots: [], cap: 2, format: 'partner_card', base: 700, gates: ['ships_there', 'measured_delivery'], note: 'We check the lane. A shop that cannot ship to that market cannot appear on its hub.' },
    { key: 'compare_native', name: 'Comparison native card', page: 'Comparison', listLength: 0, slots: [], cap: 1, format: 'spotlight_card', base: 950, gates: ['feed_fresh', 'in_stock'], note: 'After the organic table, with the same columns. Never inside the table.' },
    { key: 'forum_announcement', name: 'Section announcement', page: 'Forum', listLength: 0, slots: [], cap: 1, format: 'announcement', base: 260, gates: ['verified', 'answers_questions'], note: 'One a month per section. It is a post: it can be replied to, voted on and argued with, and it is not removable because the replies were unkind.' },
    { key: 'desk_hour', name: 'Shop desk hour', page: 'Live', listLength: 0, slots: [], cap: 4, format: 'desk', base: 49, gates: ['verified'], note: 'Cheapest thing on this list and the one that produces the most durable result.' },
    { key: 'guide_sponsorship', name: 'Sponsored guide', page: 'Guides', listLength: 0, slots: [], cap: 1, format: 'guide_sponsor', base: 240, gates: ['verified', 'claims_checkable'], note: 'Editorially reviewed. We have refused three so far, all for the same reason: a claim about a competitor we could not verify.' },
    { key: 'newsletter_slot', name: 'Newsletter slot', page: 'Newsletter', listLength: 0, slots: [], cap: 1, format: 'newsletter_slot', base: 520, gates: ['verified'], note: 'One per send. Disclosed in the subject line.' },
  ];
  vs.gateDefs = [
    { key: 'verified', name: 'Verified tier', how: 'Registry and VAT checked, support address answers.' },
    { key: 'feed_fresh', name: 'Feed fresh', how: 'Last successful import inside your plan interval.' },
    { key: 'no_ref_flag', name: 'No upheld reference-price flag', how: 'The Honest reference prices label, computed.' },
    { key: 'measured_delivery', name: 'Measured delivery in the market', how: '8 delivered orders, the same threshold the public page uses.' },
    { key: 'in_stock', name: 'Offer in stock', how: 'Checked at render, not at booking.' },
    { key: 'dosing_declared', name: 'Dosing declared', how: '90 % of the serving accounted for on the promoted product.' },
    { key: 'coupon_works', name: 'Coupon works', how: 'Community confidence above 70 % on the code.' },
    { key: 'relevance', name: 'Relevant to the query', how: 'The promoted row must score above the organic cutoff for that query.' },
    { key: 'ships_there', name: 'Ships to the market', how: 'A live lane in your shipping zones.' },
    { key: 'answers_questions', name: 'Answers questions', how: 'Replies to at least 40 % of reviews and Q&A.' },
    { key: 'claims_checkable', name: 'Claims checkable', how: 'Every factual claim in the copy can be checked against your feed or our records.' },
  ];

  /* ================= 6. caps that bound the whole system ================= */
  vs.caps = [
    'Position 1 of any list is never for sale.',
    'At most 2 promoted rows in any rendered list, and never two in the visible first screen.',
    'At least 70 % of any list above the fold is organic.',
    'One promoted slot per shop per surface per market. A shop cannot buy the list.',
    '20 % of every surface\u2019s monthly capacity is held back and never sold \u2014 it is how a new shop with a good price still gets discovered.',
    'Every list has an "organic order only" control, and the setting is remembered.',
    'A promoted row that fails its gate at render time is dropped and the slot is refunded pro rata, not filled with a fallback.',
    'Sponsored share of total clicks is published monthly on the transparency page.',
  ];

  /* ================= 7. live inventory ================= */
  /* capacity is per surface per market per month: slots × days, minus the 20 % holdback */
  const MARKETS = ['DE', 'CZ', 'PL', 'FR', 'IT', 'ES', 'GB', 'NL', 'AT', 'SE'];
  const marketWeight = { DE: 1, GB: 0.86, FR: 0.74, IT: 0.68, ES: 0.66, PL: 0.58, NL: 0.54, CZ: 0.52, AT: 0.46, SE: 0.42 };
  vs.inventory = [];
  vs.surfaces.forEach((sf) => {
    MARKETS.forEach((iso) => {
      const w = marketWeight[iso] || 0.5;
      /* monthly capacity: how many shops can hold this slot in a month, by rotation */
      const capacity = Math.max(1, Math.round(sf.cap * (sf.slots.length || 1) * (iso === 'DE' ? 4 : 3) * (sf.key === 'desk_hour' ? 4 : 1)));
      const holdback = Math.max(1, Math.round(capacity * 0.2));
      const sellable = capacity - holdback;
      const booked = Math.min(sellable, Math.round(sellable * (0.2 + R() * 0.75)));
      vs.inventory.push({
        surface: sf.key, market: iso, capacity: capacity, holdback: holdback,
        sellable: sellable, booked: booked, free: sellable - booked,
        nextFree: sellable - booked > 0 ? null : NOW + int(3, 26) * DAY,
        /* price: base × market weight × scarcity. Scarcity is bounded at +40 %, because a
           price that runs away with demand would price out exactly the small shops the
           holdback exists to protect. */
        price: Math.round(sf.base * w * (1 + Math.min(0.4, (booked / Math.max(1, sellable)) * 0.4))),
      });
    });
  });
  vs.priceModel = [
    { factor: 'Surface base', effect: 'Set per surface, published here, changed at most twice a year with 30 days\u2019 notice.' },
    { factor: 'Market weight', effect: 'Proportional to measured sessions in that market. Germany is 1.0; Sweden is 0.42.' },
    { factor: 'Scarcity', effect: 'Up to +40 % as a surface fills, and no further. A runaway price would price out the small shops the holdback protects.' },
    { factor: 'Data completeness discount', effect: 'Up to \u221225 %. A shop whose feed, dosing and policies are complete produces a promoted row that is actually useful, and we would rather be paid less for one of those.' },
    { factor: 'Commitment', effect: '\u221210 % on a three-month booking, \u221218 % on six. No annual lock-in exists.' },
  ];
  vs.discountRule = 'The only discount we will not give is one for spending more. Volume pricing rewards the shops that least need help being seen.';

  /* ================= 8. seeded bookings, for the console ================= */
  vs.bookings = [];
  let bid = 1;
  [[1, 'shop_list', 'DE'], [1, 'market_card', 'AT'], [3, 'deal_list', 'GB'], [5, 'category_rail', 'NL'],
   [2, 'forum_announcement', 'CZ'], [11, 'desk_hour', 'FR'], [8, 'newsletter_slot', 'DE'], [9, 'product_list', 'ES'],
   [12, 'ingredient_rail', 'AT'], [3, 'guide_sponsorship', 'GB']].forEach((b) => {
    const sf = vs.surfaces.find((x) => x.key === b[1]);
    const inv = vs.inventory.find((x) => x.surface === b[1] && x.market === b[2]);
    const starts = NOW - int(2, 60) * DAY;
    vs.bookings.push({
      id: 'VIS-' + (700 + bid++), merchantId: b[0], surface: b[1], market: b[2],
      starts: starts, ends: starts + int(30, 92) * DAY,
      price: inv ? inv.price : sf.base, status: starts + 30 * DAY < NOW ? 'Completed' : 'Running',
      impressions: int(2400, 48000), clicks: int(60, 1900),
      dropped: int(0, 3),
      droppedWhy: 'Gate failed at render (out of stock or feed stale). Slot refunded pro rata.',
    });
  });

  /* ================= 9. the transparency numbers we publish anyway ================= */
  const totalSellable = vs.inventory.reduce((a, b) => a + b.sellable, 0);
  const totalBooked = vs.inventory.reduce((a, b) => a + b.booked, 0);
  vs.stats = {
    surfaces: vs.surfaces.length,
    formats: vs.formats.length,
    markers: vs.markers.length,
    markets: MARKETS.length,
    capacity: vs.inventory.reduce((a, b) => a + b.capacity, 0),
    holdback: vs.inventory.reduce((a, b) => a + b.holdback, 0),
    sellable: totalSellable, booked: totalBooked, free: totalSellable - totalBooked,
    fill: Math.round((totalBooked / Math.max(1, totalSellable)) * 100),
    sponsoredClickShare: 3.8,
    refusedCreatives: 3,
    cheapest: Math.min.apply(null, vs.inventory.map((i) => i.price)),
  };
  vs.transparency = [
    'Sponsored placements took 3.8 % of all outbound clicks last month. We publish this whether it flatters us or not.',
    'Three creatives were refused this quarter, all for a claim about a competitor we could not verify.',
    '20 % of capacity went unsold on purpose. That is the holdback, and it is how a new shop with a good price gets found.',
    'No shop has ever been able to buy a trust label, a position at the top of a list, or a change to ComparoRank, because no code path exists that would allow it.',
  ];
})();
