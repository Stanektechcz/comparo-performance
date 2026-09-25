/* Comparo Performance — network: promotion ladder, partners, affiliate plumbing, demand signals.

   Two questions this file answers.

   **"How can a shop get noticed here?"** With a ladder, ordered by how much it interrupts a
   shopper — not by how much it costs. The first four rungs are free and are the ones that work
   best, because they feed the ranking inputs rather than sitting beside them. The paid rungs
   start where the interruption starts, and every one of them is labelled. A shop that reads this
   ladder top to bottom learns that the cheapest promotion we sell is doing the unglamorous data
   work, and we would rather say that than sell a banner to a shop with a broken feed.

   **"What is a partner, exactly?"** Ten kinds, four tiers, and a benefit list that contains no
   ranking, no badge and no editorial influence — because those are the three things a network
   partner would most like to buy.

   Loaded after seed-geo.js, seed-orders.js and seed-commercial.js. */
(function () {
  const S = window.SEED;
  if (!S) return;
  const DAY = S.DAY, NOW = S.NOW;
  let seed = 0x5e11cd03;
  const R = () => { seed |= 0; seed = (seed + 0x6d2b79f5) | 0; let t = Math.imul(seed ^ (seed >>> 15), 1 | seed); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
  const int = (a, b) => Math.floor(R() * (b - a + 1)) + a;
  const pick = (a) => a[Math.floor(R() * a.length)];
  const flt = (a, b, d) => Math.round((a + R() * (b - a)) * 10 ** (d || 1)) / 10 ** (d || 1);
  const net = {};
  S.net = net;

  /* ================= 1. the promotion ladder ================= */
  net.intrusionScale = [
    { n: 0, label: 'Invisible', meaning: 'The shopper cannot tell you did anything. It changes what the data says about you.' },
    { n: 1, label: 'Passive', meaning: 'Visible only to a shopper who opened your profile on purpose.' },
    { n: 2, label: 'Contextual', meaning: 'Appears where the shopper was already looking, in the same shape as the surrounding content.' },
    { n: 3, label: 'Declared', meaning: 'Its own box, labelled Sponsored, outside the organic ordering.' },
    { n: 4, label: 'Prominent', meaning: 'Above the fold on a high-traffic surface. Labelled, capped, one per session.' },
    { n: 5, label: 'Unmissable', meaning: 'Multi-surface for a period. The only tier we cap by market as well as by surface.' },
  ];
  net.ladder = [
    {
      key: 'data_quality', rung: 1, intrusion: 0, name: 'Fix your data', price: 0, paid: false,
      what: 'Complete feed attributes, correct prices, real stock, EANs that match. Every one of these is a ComparoRank input.',
      effect: 'Largest single lever we can measure: shops that closed their feed-attribute gaps moved a median of 6 rank positions and took 23 % more clicks with no spend at all.',
      cannot: 'Nothing to disclaim \u2014 this is not promotion, it is the product being correct about you.',
      who: 'Every shop, on every plan.',
    },
    {
      key: 'dosing', rung: 2, intrusion: 0, name: 'Declare your dosing', price: 0, paid: false,
      what: 'Amount per serving for your catalogue, or let us photograph it (label verification add-on).',
      effect: 'Unlocks price per gram of active on your listings. A fully declared product converts better than an undeclared one at the same price, because the comparison can be made at all.',
      cannot: 'Declaring a weak dose does not hide it. The number is the number.',
      who: 'Every shop. The add-on only saves you the work.',
    },
    {
      key: 'delivery', rung: 3, intrusion: 0, name: 'Earn the delivery labels', price: 0, paid: false,
      what: 'Dispatch inside your own cutoff for 90 days. Measured delivery appears at 8 delivered orders per market.',
      effect: 'Measured delivery is the most-read element on a shop card after price. The promise badge (paid, \u20ac39/market) only becomes purchasable once the measurement supports it.',
      cannot: 'The badge cannot be bought ahead of the measurement, and the claim rate is published beside it.',
      who: 'Every shop. Eligibility is measured from orders.',
    },
    {
      key: 'answer', rung: 4, intrusion: 1, name: 'Answer people', price: 0, paid: false,
      what: 'Reply to reviews, answer Q&A, post announcements, run a shop desk hour in a market room.',
      effect: 'Answers fast is a computed label. Shops that answer above 55 % of reviews hold 0.4 more stars on the same product mix \u2014 the same goods, described by calmer customers.',
      cannot: 'A reply cannot remove a review, and staff must post from a verified shop account.',
      who: 'Every shop. Q&A concierge (\u20ac69) does the chasing for you.',
    },
    {
      key: 'desk', rung: 5, intrusion: 1, name: 'Shop desk hour', price: 49, paid: true,
      what: 'A scheduled, labelled hour in one market room where your staff answer anything. Moderated by the section moderators.',
      effect: 'Small and durable: a desk hour produces a median of 14 answered questions and two promoted topics that keep answering for months.',
      cannot: 'No selling, no links, no price claims we cannot check against your feed. One desk per shop per market per week.',
      who: 'Pro and above.',
    },
    {
      key: 'demand', rung: 6, intrusion: 1, name: 'Answer a demand signal', price: 0, paid: false,
      what: 'Members pledge what they would pay for a product in their market. You can answer publicly with a price commitment and a window.',
      effect: 'The highest-intent surface we have: a pledge is a shopper telling you their price before you spend anything to find them.',
      cannot: 'A commitment is published as a commitment. Missing it is recorded on your profile like a missed delivery promise.',
      who: 'Every shop.',
    },
    {
      key: 'deals', rung: 7, intrusion: 2, name: 'Run deals', price: 29, paid: true,
      what: 'Coupons and price drops in the deal hub and on product pages.',
      effect: 'Deal-hub placement is ordered by community confidence and total price, so a good deal outranks a bigger budget. Extra slots buy volume, not position.',
      cannot: 'Slots do not affect deal ordering. A code that fails gets voted down and drops.',
      who: 'Free plan gets 3 slots; \u20ac29 buys 5 more.',
    },
    {
      key: 'native', rung: 8, intrusion: 3, name: 'Native comparison card', price: 900, paid: true,
      what: 'A sponsored card after the organic offer table, with the same columns as an organic row.',
      effect: 'Best-performing paid unit we sell: 4.1 % click rate, because it carries real data rather than a slogan.',
      cannot: 'It sits after the table, never inside it, and it never changes the order of a single organic row.',
      who: 'Any advertiser. Creative checked against your feed.',
    },
    {
      key: 'featured', rung: 9, intrusion: 4, name: 'Category or market featured card', price: 1400, paid: true,
      what: 'A labelled card in the rail of a category page or market hub, targeted to markets you actually ship to.',
      effect: 'Reach at the point of category intent. We check the lane before the creative goes live \u2014 a shop that cannot ship there cannot advertise there.',
      cannot: 'One sponsored unit per surface per session, capped at 12 % of the visible area.',
      who: 'Any advertiser.',
    },
    {
      key: 'homepage', rung: 10, intrusion: 4, name: 'Homepage featured', price: 2400, paid: true,
      what: 'Featured shop or featured deal on the homepage, labelled, two and three slots respectively.',
      effect: 'Largest single audience on the site. Brand recall rather than conversion \u2014 the click rate is half the native card\u2019s.',
      cannot: 'Cannot appear as an offer row, cannot carry a best-value claim, cannot be targeted at a named person.',
      who: 'Any advertiser.',
    },
    {
      key: 'takeover', rung: 11, intrusion: 5, name: 'Market launch package', price: 3900, paid: true,
      what: 'Six weeks across the market hub, a category card, a newsletter slot and a market-digest mention.',
      effect: 'Built for a shop entering one of the 27 markets from zero. Includes the onboarding work, not just the inventory.',
      cannot: 'Capped at one package per market per quarter, so a market cannot be bought outright.',
      who: 'Growth and above, or a brand with a market launch.',
    },
  ];
  net.ladderNote = 'Eleven rungs. The first six cost either nothing or less than a hundred euro a month, and they are the ones we push, because they improve the thing a shopper is actually reading. The paid rungs above them buy attention in a labelled box \u2014 never a position in the comparison, never a label, never an editorial mention.';
  net.ladderCannotEver = [
    'A position in the offer table or a change to ComparoRank',
    'Any computed label \u2014 We recommend, Verified by users, Honest reference prices, Answers fast',
    'A delivery promise the measurement does not support',
    'Review visibility, review weight or the published average',
    'A place in an editorial comparison, a community verdict or an Ask Comparo answer',
    'Presence in a live room, a group or a forum topic',
  ];

  /* ================= 2. partner network ================= */
  net.partnerTypes = [
    { key: 'shop', name: 'Shops', gives: 'Feed, stock, price accuracy, delivery performance', gets: 'Comparison listing, analytics, deals, campaigns, delivery promise', count: null, note: 'We never take a cut of their basket \u2014 we are paid for capability and for clicks, not for goods.' },
    { key: 'brand', name: 'Brands', gives: 'Dosing specifications, batch certificates, product imagery, launch calendars', gets: 'Brand pages, ingredient authority, launch reach, dosing verification', count: 13, note: 'Brands do not sell to shoppers here either; a brand slot points at a comparison, never a checkout.' },
    { key: 'lab', name: 'Testing laboratories', gives: 'Assay results, method notes, batch verification', gets: 'Named credit on every result, funded community assays, category visibility', count: 3, note: 'Paid per assay, never per finding. A result is published whatever it says.' },
    { key: 'creator', name: 'Creators and coaches', gives: 'Audience, honest product context, working coupons', gets: 'Tracked links, our price data, working codes, a public performance page', count: 24, note: 'Commission is disclosed on every creator link, on the creator page and in the link itself.' },
    { key: 'publisher', name: 'Publishers and media', gives: 'Distribution, citations, editorial context', gets: 'Embeddable live price tables, research data under licence, revenue share on clicks', count: 9, note: 'Our data leaves with attribution and a canonical link back, or it does not leave.' },
    { key: 'syndication', name: 'Syndication partners', gives: 'Placement of our comparison inside their product', gets: 'White-label offer table, dosing data, delivery medians via API', count: 4, note: 'They may not reorder our rows. Reordering voids the licence, and we check.' },
    { key: 'data', name: 'Data customers', gives: 'Licence fee', gets: 'Price series, dosing tables, delivery benchmarks, market share estimates', count: 11, note: 'No personal data, ever, at any price. Aggregates only.' },
    { key: 'gym', name: 'Gyms, clubs and events', gives: 'Local audience, real-world credibility', gets: 'Market-targeted placements, group hosting, event rooms', count: 17, note: 'No health claims, no prescriptions, no product recommendations from us to their members.' },
    { key: 'integrator', name: 'Integrators', gives: 'Feed plumbing, logistics and payment data, one-click onboarding from a shop platform', gets: 'Listed integration, shared onboarding funnel, technical support', count: 6, note: 'Shopify, WooCommerce, Shoptet-style platform apps. They shorten onboarding from days to minutes.' },
    { key: 'research', name: 'Research and charity', gives: 'Independent scrutiny, subject expertise', gets: 'Free data access, co-published studies, funded assays', count: 2, note: 'They are allowed to publish findings that embarrass us. That is the point of them.' },
  ];
  net.tiers = [
    { key: 'listed', name: 'Listed', requirement: 'A working feed or a verified profile. Nothing else.', benefits: ['Full comparison listing in all 27 markets', 'Review replies', '3 deal slots', 'Basic analytics'], fee: 0 },
    { key: 'verified', name: 'Verified', requirement: 'Company registry and VAT checked, returns policy published, support address answered within 72 h.', benefits: ['Verified marker on the profile', 'Q&A', 'Announcements', 'Feed health console'], fee: 0 },
    { key: 'partner', name: 'Partner', requirement: 'Verified, plus a Pro plan or above, plus a measured delivery record in at least one market.', benefits: ['Delivery promise eligibility', 'Campaign booking', 'Market packs', 'Quarterly review with our data team'], fee: 'plan' },
    { key: 'strategic', name: 'Strategic', requirement: 'Partner, plus a signed data agreement, plus co-operation on one open data gap per year (dosing, delivery, or a thin market).', benefits: ['Co-op marketing fund', 'Early access to data products', 'Named in the methodology credits', 'Joint research'], fee: 'negotiated' },
  ];
  net.tierNote = 'No tier buys a ranking position, a label, or a mention in an editorial comparison. The highest tier buys co-operation on data gaps, which is the only thing we actually want from a partner.';

  net.pipeline = [
    { stage: 'Applied', sla: '1 working day to first response', count: 34, exit: 'Feed reachable and legal entity identified' },
    { stage: 'Feed validation', sla: '2 days', count: 19, exit: '90 % of required attributes present, EANs match on 80 % of lines' },
    { stage: 'Matching', sla: '3 days', count: 12, exit: 'Products matched to canonical records, duplicates merged' },
    { stage: 'Compliance review', sla: '2 days', count: 8, exit: 'Restricted products flagged per market' },
    { stage: 'Live', sla: '\u2014', count: 13, exit: 'Listed tier reached' },
    { stage: 'Verification', sla: '5 days', count: 6, exit: 'Registry, VAT and support response confirmed' },
    { stage: 'Partner review', sla: 'quarterly', count: 4, exit: 'Measured delivery in one market plus a Pro plan' },
  ];
  net.processes = [
    { key: 'onboarding', name: 'Onboarding', steps: ['Apply with a feed URL or a platform app', 'We validate attributes and report what is missing, line by line', 'Matching and duplicate merge, with the merge log shared', 'Compliance pass per market', 'Live, in every market you ship to'], sla: '8 working days end to end', who: 'Partner operations' },
    { key: 'dispute', name: 'Data dispute', steps: ['Shop contests a price, a review or a delivery figure', 'We re-read the record and show it to both sides', 'Corrections are made within 24 h; contested reviews get a public right of reply, not deletion', 'Escalation to a named reviewer if unresolved'], sla: '24 h first response, 5 days to close', who: 'Data quality' },
    { key: 'claim', name: 'Delivery claim', steps: ['Buyer presses "parcel was late"', 'Automatic check against the promise, dispatch and delivery record', 'Shop may contest with carrier evidence for 48 h', 'Paid from the deposit; the miss is counted publicly'], sla: '5 working days to payment', who: 'Trust operations' },
    { key: 'offboarding', name: 'Offboarding', steps: ['Shop cancels, or fails validation for 30 days', 'Offers are suppressed within 24 h so nobody clicks into a dead checkout', 'Reviews, delivery history and price history stay published \u2014 they belong to the record, not to the subscription', 'Profile marked "no longer listed" with the date'], sla: '24 h to suppression', who: 'Partner operations' },
    { key: 'coop', name: 'Co-op marketing', steps: ['Strategic partner proposes a campaign with a data-gap component', 'We match up to 50 % of spend from the co-op fund', 'Creative is reviewed against the feed like any other campaign', 'Results published to both sides, including failures'], sla: '10 days to approval', who: 'Commercial' },
  ];

  const partnerDefs = [
    ['Awin', 'affiliate', 'GB', 'strategic', 'Network', 'Click tracking and validated conversions across 6 markets', 'Standard network terms, 30-day cookie, 45-day validation'],
    ['Tradedoubler', 'affiliate', 'SE', 'partner', 'Network', 'Nordic and DACH coverage', '14-day cookie, 30-day validation'],
    ['Impact', 'affiliate', 'US', 'partner', 'Network', 'US and UK programmes, creator tracking', '30-day cookie, 60-day validation'],
    ['Daisycon', 'affiliate', 'NL', 'listed', 'Network', 'Benelux coverage', '30-day cookie, 45-day validation'],
    ['Partnerize', 'affiliate', 'GB', 'listed', 'Network', 'Enterprise programmes', '30-day cookie, 60-day validation'],
    ['Shoptet App Store', 'integrator', 'CZ', 'partner', 'Platform', 'One-click feed connection for 6,000 Czech shops', 'Revenue share on referred plans, 12 %'],
    ['Shopify App', 'integrator', 'CA', 'partner', 'Platform', 'One-click feed and stock webhook', 'Revenue share on referred plans, 15 %'],
    ['WooCommerce plugin', 'integrator', 'US', 'listed', 'Platform', 'Self-hosted feed generator', 'Free, maintained by us'],
    ['Nordlab Analytics', 'lab', 'SE', 'strategic', 'Laboratory', 'Batch assays for community-funded tests', '\u20ac340 per assay, result published whatever it says'],
    ['Institut f\u00fcr Sportern\u00e4hrung', 'research', 'DE', 'strategic', 'Research', 'Independent review of our dosing methodology', 'Unpaid; free data access and right to publish criticism'],
    ['Velo Club M\u00fcnchen', 'gym', 'DE', 'listed', 'Club', 'Endurance audience, 2,400 members', 'Market placement at club rate'],
    ['StrengthMag', 'publisher', 'GB', 'partner', 'Media', 'Embeds our live price table in 40 reviews a month', '18 % revenue share on referred clicks, attribution mandatory'],
    ['Kraftsport Podcast', 'creator', 'AT', 'partner', 'Creator', '38k listeners, working coupon codes', '7.5 % commission, disclosed on every link'],
    ['PriceRadar CZ', 'syndication', 'CZ', 'listed', 'Syndication', 'White-label offer table inside a general comparison site', 'Licence plus per-click fee; may not reorder our rows'],
  ];
  net.partners = partnerDefs.map((p, i) => ({
    id: i + 1, name: p[0], type: p[1], country: p[2], tier: p[3], kind: p[4],
    what: p[5], terms: p[6],
    since: NOW - int(60, 900) * DAY,
    status: i === 4 ? 'Paused' : i === 13 ? 'Under review' : 'Active',
    monthlyValue: [0, 0, 0, 0, 0, 640, 820, 0, 0, 0, 0, 1240, 480, 390][i] || 0,
    clicksMonth: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1840, 9600, 4200, 26400][i] || 0,
    obligationOnUs: [
      'Pay validated commission within network terms',
      'Report click quality monthly',
      'Maintain tracking parity across markets',
      'Keep programme terms published',
      'Keep programme terms published',
      'Maintain the app and answer platform support in 2 days',
      'Maintain the app and answer platform support in 2 days',
      'Maintain the plugin and its changelog',
      'Publish every assay result, including the ones that embarrass a partner',
      'Free data access and no editorial veto',
      'Honour the club rate and check the delivery lane',
      'Attribution, canonical link and live data or nothing',
      'Disclose the commission on every link',
      'Licence terms, and an audit that our rows are not reordered',
    ][i],
  }));

  /* ================= 3. affiliate plumbing ================= */
  net.affiliateNetworks = ['Direct', 'Awin', 'Tradedoubler', 'Impact', 'Daisycon', 'Partnerize'].map((n, i) => {
    const mine = S.merchants.filter((m) => (m.affiliate || {}).network === n);
    return {
      network: n, shops: mine.length,
      markets: [['all'], ['GB', 'DE', 'FR', 'IT', 'ES', 'NL'], ['SE', 'DK', 'FI', 'NO', 'DE', 'AT'], ['US', 'GB'], ['NL', 'BE'], ['GB', 'DE']][i],
      cookie: [null, 30, 14, 30, 30, 30][i],
      validation: [14, 45, 30, 60, 45, 60][i],
      payment: ['30 days from invoice', '45 days', '60 days', '60 days', '45 days', '60 days'][i],
      dedup: ['Last click, our own log is the record', 'Last click, network arbitrates', 'Last click', 'Last click, 7-day lookback window', 'Last click', 'Last click'][i],
      ourIntegration: [i === 0 ? 'Server-to-server postback' : 'Network API + postback'][0],
      note: [
        'Direct programmes are the ones we can audit ourselves, because the click log is ours. Preferred for any shop large enough to run one.',
        'Broadest market coverage; slowest validation of the five.',
        'Strong Nordic coverage, which is why the thin northern markets are reachable at all.',
        'Used for US and creator tracking.',
        'Benelux only.',
        'Enterprise programmes, one shop.',
      ][i],
    };
  });
  net.commissionTiers = [
    { category: 'Protein', base: 6, volume: [[0, 6], [5000, 7], [20000, 8.5]], note: 'Highest-volume category, thinnest shop margin.' },
    { category: 'Creatine', base: 5, volume: [[0, 5], [5000, 6], [20000, 7]], note: 'Commodity pricing; commission moves slowly.' },
    { category: 'Pre-workout', base: 9, volume: [[0, 9], [5000, 10.5], [20000, 12]], note: 'Best margin in the catalogue.' },
    { category: 'Vitamins & minerals', base: 8, volume: [[0, 8], [5000, 9], [20000, 10]], note: 'High repeat rate, so lifetime value exceeds the first order.' },
    { category: 'Gainers & carbs', base: 4.5, volume: [[0, 4.5], [5000, 5], [20000, 5.5]], note: 'Heavy, expensive to ship, lowest commission.' },
  ];
  net.affiliateRules = [
    'Our redirect log is the record. When a network and our log disagree we publish both figures and reconcile, rather than quietly taking the higher one.',
    'A click we cannot attribute is not billed to anyone. Unattributed clicks are reported as unattributed.',
    'Commission never touches ComparoRank. The rank inputs are price, availability, trust, delivery and data quality \u2014 the commission field is not read by the ranking code at all.',
    'Where two shops offer the same total, the one paying us more does not win. The tie-break is measured delivery, then review weight.',
    'Creator commission is disclosed on the link, on the creator\u2019s page and in the redirect interstitial.',
    'A shop can leave a network and stay listed. Listing is not conditional on a commercial relationship \u2014 that is the whole argument for trusting the table.',
  ];

  /* ================= 4. demand signals ================= */
  const demandDefs = [
    ['whey-isolate-90', 'DE', 1840, [28, 30, 32, 34], 36.13, 'open'],
    ['creatine-monohydrate-500-g', 'CZ', 1240, [14, 16, 18, 20], 19.9, 'answered'],
    ['clear-whey-refresh', 'PL', 620, [26, 28, 30, 32], 34.9, 'open'],
    ['electrolyte-salt-caps', 'FR', 410, [13, 15, 17, 19], 18.9, 'open'],
    ['night-recovery-blend', 'GB', 880, [22, 24, 26, 28], 29.9, 'answered'],
    ['vitamin-b-complex', 'RO', 260, [10, 12, 14, 16], 15.9, 'thin'],
  ];
  net.demand = demandDefs.map((d, i) => {
    const p = S.products.find((x) => x.slug === d[0]);
    const hist = [int(40, 220), int(120, 460), int(200, 700), int(90, 340)];
    const total = hist.reduce((a, b) => a + b, 0);
    /* the clearing price is where the pledges stop, not where we would like them to be */
    let cum = 0, clearing = d[3][0];
    for (let k = d[3].length - 1; k >= 0; k--) { cum += hist[k]; if (cum >= total * 0.5) { clearing = d[3][k]; break; } }
    return {
      id: 'DS-' + (100 + i), productId: p ? p.id : null, product: p ? p.name : d[0], slug: d[0],
      market: d[1], pledges: d[2], bands: d[3], histogram: hist, total: total,
      bestPrice: d[4], clearing: clearing, gap: Math.round((d[4] - clearing) * 100) / 100,
      status: d[5], opened: NOW - int(6, 70) * DAY,
      responses: d[5] === 'answered' ? [{
        merchantId: [1, 3, 5, 2][i % 4],
        price: Math.round((clearing + 0.4) * 100) / 100,
        window: int(10, 30) + ' days',
        at: NOW - int(1, 12) * DAY,
        honoured: i !== 4,
        note: 'Public price commitment. Missing it is recorded on the shop profile like a missed delivery promise.',
      }] : [],
    };
  });
  net.demandNote = 'A pledge is a shopper saying what they would pay, in a market, for a product we already hold a price for. It is the only high-intent signal on this site that does not involve buying anything \u2014 and the only one a shop can act on before spending a cent on reach. We publish the distribution, not just the average, because the average is where a shop would look to justify doing nothing.';
  net.demandRules = [
    'One pledge per member per product per market, revocable.',
    'A pledge is not an order and creates no obligation on the shopper. We do not sell, so there is nothing to commit to.',
    'A shop\u2019s answer is a public price commitment with a window. Honouring it is measured from offer history; missing it appears on the profile.',
    'We never tell a shop who pledged. Distribution, count and market only.',
    'A thin signal (under 300 pledges) is labelled thin and no shop is asked to answer it.',
  ];

  /* ================= 5. shop desks ================= */
  net.desks = [1, 2, 3, 5, 11].map((mid, i) => {
    const m = S.merchants.find((x) => x.id === mid) || {};
    const at = NOW + [1, 3, -2, 6, 9][i] * DAY + int(-5, 8) * 3600000;
    return {
      merchantId: mid, shop: m.name, market: (m.shipsTo || ['DE'])[0],
      roomKey: 'mkt:' + (m.country || 'DE'), at: at, past: at < NOW,
      staff: ['Support lead', 'Head of e-commerce', 'Customer care', 'Founder', 'Logistics manager'][i],
      answered: at < NOW ? int(9, 28) : 0, promoted: at < NOW ? int(0, 3) : 0,
      rules: 'Labelled shop account, moderated by the section moderators, no links, no price claims we cannot check against the feed.',
    };
  });

  net.stats = {
    rungs: net.ladder.length,
    freeRungs: net.ladder.filter((r) => !r.paid).length,
    partnerTypes: net.partnerTypes.length,
    partners: net.partners.length,
    networks: net.affiliateNetworks.length,
    affiliateShops: S.merchants.filter((m) => (m.affiliate || {}).network && m.affiliate.network !== 'Direct').length,
    directShops: S.merchants.filter((m) => (m.affiliate || {}).network === 'Direct').length,
    demandPledges: net.demand.reduce((a, b) => a + b.pledges, 0),
    desks: net.desks.length,
    pipeline: net.pipeline.reduce((a, b) => a + b.count, 0),
  };
})();
