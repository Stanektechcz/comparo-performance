/* Comparo Performance — Growth OS seed data.
   Extends window.SEED in place under S.gx. Loaded after seed-intel.js. Deterministic, all fictional. */
(function () {
  const S = window.SEED;
  if (!S) return;
  const mk = (s) => () => { s |= 0; s = s + 0x6D2B79F5 | 0; let t = Math.imul(s ^ s >>> 15, 1 | s); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; };
  const R = mk(20260908);
  const pick = (a) => a[Math.floor(R() * a.length)];
  const int = (a, b) => a + Math.floor(R() * (b - a + 1));
  const flt = (a, b, d = 2) => Math.round((a + R() * (b - a)) * 10 ** d) / 10 ** d;
  const DAY = S.DAY, NOW = S.NOW, HOUR = 3600000;
  const gx = {};
  S.gx = gx;
  const P = S.products, M = S.merchants;
  const byName = (n) => P.find((p) => p.name === n);

  /* ---------------- 1. merchant prospects & pipeline ---------------- */
  /* the ordered funnel and the terminal states are separate lists on purpose: advancing must never
     walk off the end of the funnel into a negative state (see GROWTH-OS.md) */
  gx.pipelineStages = ['Discovered', 'Qualified', 'Contact ready', 'Contacted', 'Replied', 'Negotiation', 'Onboarding', 'Feed integration', 'Verified', 'Live'];
  gx.terminalStages = ['Rejected', 'Dormant'];
  gx.stages = gx.pipelineStages.concat(gx.terminalStages);
  gx.sources = ['User suggestion', 'Unmatched outbound domain', 'Community mention', 'Search query', 'Admin entry', 'Imported prospect list'];
  const prospectDefs = [
    ['NutriNord', 'nutrinord.se', 'SE', ['SE', 'NO', 'DK'], 'Live', 'Search query', 68, 2400, 'growth@comparo'],
    ['SupleMax', 'suplemax.pl', 'PL', ['PL', 'CZ', 'SK'], 'Negotiation', 'User suggestion', 74, 3900, 'merchants@comparo'],
    ['FitDirect', 'fitdirect.nl', 'NL', ['NL', 'BE', 'DE'], 'Feed integration', 'Community mention', 82, 5200, 'merchants@comparo'],
    ['ProteinPoint', 'proteinpoint.cz', 'CZ', ['CZ', 'SK'], 'Verified', 'Search query', 59, 1800, 'merchants@comparo'],
    ['Balkan Sports Nutrition', 'bsn-shop.hr', 'HR', ['HR', 'SI'], 'Discovered', 'Imported prospect list', 41, 1100, null],
    ['MegaSupps', 'megasupps.eu', 'DE', ['DE', 'AT'], 'Rejected', 'Unmatched outbound domain', 88, 7400, 'compliance@comparo'],
    ['IberFuel', 'iberfuel.es', 'ES', ['ES', 'PT'], 'Contacted', 'Search query', 63, 2900, 'growth@comparo'],
    ['Gymfood France', 'gymfood.fr', 'FR', ['FR', 'BE'], 'Replied', 'User suggestion', 71, 3400, 'growth@comparo'],
    ['NordicWhey', 'nordicwhey.fi', 'FI', ['FI', 'SE', 'EE'], 'Qualified', 'Community mention', 54, 1500, null],
    ['AlpsNutrition', 'alpsnutrition.ch', 'CH', ['CH', 'AT', 'DE'], 'Contact ready', 'Search query', 66, 2100, 'growth@comparo'],
    ['MuscleIsland', 'muscleisland.ie', 'IE', ['IE', 'GB'], 'Discovered', 'Unmatched outbound domain', 48, 1700, null],
    ['LiftHouse UK', 'lifthouse.co.uk', 'GB', ['GB'], 'Contacted', 'Search query', 77, 4100, 'growth@comparo'],
    ['SportNahrung DE', 'sportnahrung.de', 'DE', ['DE', 'AT', 'CH'], 'Negotiation', 'User suggestion', 85, 6200, 'merchants@comparo'],
    ['VitaBrno', 'vitabrno.cz', 'CZ', ['CZ'], 'Dormant', 'Admin entry', 38, 900, 'merchants@comparo'],
    ['NutriPolska', 'nutripolska.pl', 'PL', ['PL'], 'Qualified', 'Community mention', 61, 2600, null],
    ['ItalFit', 'italfit.it', 'IT', ['IT', 'CH'], 'Contact ready', 'Search query', 57, 2200, 'growth@comparo'],
    ['CanadaSupps', 'canadasupps.ca', 'CA', ['CA'], 'Discovered', 'Imported prospect list', 44, 3100, null],
    ['USPerformance', 'usperformance.com', 'US', ['US', 'CA'], 'Qualified', 'Unmatched outbound domain', 72, 8600, 'growth@comparo'],
    ['DanskProtein', 'danskprotein.dk', 'DK', ['DK', 'SE'], 'Discovered', 'Search query', 46, 1300, null],
    ['SlovakGains', 'slovakgains.sk', 'SK', ['SK', 'CZ'], 'Onboarding', 'User suggestion', 55, 1600, 'merchants@comparo'],
    ['BeneluxNutri', 'beneluxnutri.be', 'BE', ['BE', 'NL', 'FR'], 'Replied', 'Community mention', 64, 2400, 'growth@comparo'],
    ['ViennaFit', 'viennafit.at', 'AT', ['AT', 'DE'], 'Contacted', 'Search query', 69, 1900, 'growth@comparo'],
  ];
  gx.prospects = prospectDefs.map((d, i) => {
    const cats = [];
    S.categories.forEach((c) => { if (R() < 0.55) cats.push(c.id); });
    const brands = [];
    S.brands.forEach((b) => { if (R() < 0.4) brands.push(b.id); });
    return {
      id: 'MP-' + (1100 + i), name: d[0], domain: d[1], country: d[2], markets: d[3],
      stage: d[4], source: d[5], overlap: d[6], catalog: d[7], owner: d[8],
      categories: cats.length ? cats : [1], brands: brands.length ? brands : [1],
      searchMentions: int(4, 180), communityMentions: int(0, 22), outboundClicks: int(0, 940),
      shippingMarkets: d[3].length + int(0, 4), affiliateNetwork: pick(['Direct', 'Awin', 'Tradedoubler', 'Impact', 'none', 'none']),
      created: NOW - int(4, 180) * DAY, updated: NOW - int(0, 20) * DAY,
      notes: [
        { ts: NOW - int(1, 40) * DAY, actor: d[8] || 'system', text: 'Discovered via ' + d[5].toLowerCase() + '.' },
        { ts: NOW - int(0, 12) * DAY, actor: d[8] || 'system', text: d[4] === 'Rejected' ? 'Rejected: unclear ownership and repeated consumer complaints.' : d[4] === 'Dormant' ? 'No reply after three follow-ups. Parked for a quarter.' : 'Qualification data refreshed from search demand.' },
      ],
    };
  });

  /* ---------------- 2. outreach templates & tasks ---------------- */
  gx.templates = [
    { id: 'T1', name: 'Initial outreach', subject: 'Comparo — {market} shoppers are searching for your range', body: 'Hi {merchant} team,\n\nComparo compares total landed prices across {market}. Last month {searches} shoppers searched for products you stock, and {gap} of those searches had fewer than three offers.\n\nA free profile lists your offers with shipping and coupons included, ranked organically — commission never changes position.\n\nWould a 20-minute call this week work?' },
    { id: 'T2', name: 'Affiliate proposal', subject: 'Affiliate partnership for {merchant}', body: 'Hi {merchant},\n\nWe already send you {clicks} outbound clicks a month with no affiliate relationship in place. Standard terms in {market} are {rate} % CPS with a 30-day cookie.\n\nHappy to run a two-month pilot and share the click and conversion data both ways.' },
    { id: 'T3', name: 'Feed request', subject: 'Product feed for your Comparo profile', body: 'Hi {merchant},\n\nTo keep your prices and stock accurate we need a product feed (XML, CSV, JSON or API) with: title, brand, EAN/GTIN, price, shipping, availability, landing URL.\n\nWe import every 6 hours and report unmatched rows back to you in the Match centre.' },
    { id: 'T4', name: 'Partnership proposal', subject: 'Comparo partnership — {market}', body: 'Hi {merchant},\n\nBeyond the free listing we run market launches, newsletter placements and research features. For {market} we are looking for two anchor partners this quarter.' },
    { id: 'T5', name: 'Exclusive deal proposal', subject: 'Comparo exclusive for {merchant}', body: 'Hi {merchant},\n\nA Comparo-exclusive code performs roughly 3× a standard coupon on our deal hub. Based on {clicks} monthly clicks we estimate {conv} conversions over a 30-day window.\n\nWe never hide the exclusive label, and the offer still ranks organically.' },
    { id: 'T6', name: 'Verification request', subject: 'Business verification for {merchant}', body: 'Hi {merchant},\n\nTo show the Verified badge we need: company register extract, terms of sale, and the food-supplement distribution authorisation where your market requires one.' },
    { id: 'T7', name: 'Follow-up', subject: 'Following up — Comparo listing for {merchant}', body: 'Hi {merchant},\n\nCircling back on my note about listing in {market}. Demand there is up {trend} % quarter on quarter. Happy to close the loop either way.' },
  ];
  gx.taskTypes = ['Send introduction', 'Request affiliate terms', 'Request feed', 'Request business verification', 'Follow up', 'Review integration', 'Qualify prospect', 'Create SEO page', 'Publish research', 'Answer community question', 'Negotiate affiliate', 'Improve feed'];
  gx.owners = ['growth@comparo', 'seo@comparo', 'affiliate@comparo', 'merchants@comparo', 'content@comparo', 'community@comparo'];
  gx.teams = [
    { key: 'growth', name: 'Growth', email: 'growth@comparo' },
    { key: 'seo', name: 'SEO', email: 'seo@comparo' },
    { key: 'affiliate', name: 'Affiliate', email: 'affiliate@comparo' },
    { key: 'merchant', name: 'Merchant', email: 'merchants@comparo' },
    { key: 'content', name: 'Content', email: 'content@comparo' },
    { key: 'community', name: 'Community', email: 'community@comparo' },
  ];
  gx.tasks = [
    { id: 'GT-201', title: 'Negotiate affiliate terms with PeakSupps', type: 'Negotiate affiliate', entity: 'merchant:1', owner: 'affiliate@comparo', priority: 'P0', due: NOW + 3 * DAY, status: 'In progress', impact: 'High', effort: 'Medium', created: NOW - 6 * DAY },
    { id: 'GT-202', title: 'Create SEO landing page for “creatine gummies”', type: 'Create SEO page', entity: 'query:creatine gummies', owner: 'seo@comparo', priority: 'P1', due: NOW + 6 * DAY, status: 'Planned', impact: 'Medium', effort: 'Low', created: NOW - 4 * DAY },
    { id: 'GT-203', title: 'Qualify SportNahrung DE', type: 'Qualify prospect', entity: 'prospect:MP-1112', owner: 'merchants@comparo', priority: 'P0', due: NOW + 2 * DAY, status: 'In progress', impact: 'High', effort: 'Low', created: NOW - 2 * DAY },
    { id: 'GT-204', title: 'Publish shipping-cost research for Germany', type: 'Publish research', entity: 'research:RS-2', owner: 'content@comparo', priority: 'P1', due: NOW + 9 * DAY, status: 'Review', impact: 'High', effort: 'High', created: NOW - 12 * DAY },
    { id: 'GT-205', title: 'Answer “best delivery rating in Germany”', type: 'Answer community question', entity: 'question:Q-14', owner: 'community@comparo', priority: 'P2', due: NOW + 12 * DAY, status: 'Backlog', impact: 'Medium', effort: 'Low', created: NOW - 8 * DAY },
    { id: 'GT-206', title: 'Fix affiliate tracking for SupplementBay', type: 'Improve feed', entity: 'merchant:8', owner: 'affiliate@comparo', priority: 'P0', due: NOW + 1 * DAY, status: 'In progress', impact: 'High', effort: 'Low', created: NOW - 1 * DAY },
    { id: 'GT-207', title: 'Czechia market launch checklist', type: 'Create SEO page', entity: 'market:CZ', owner: 'growth@comparo', priority: 'P2', due: NOW + 20 * DAY, status: 'Planned', impact: 'Medium', effort: 'High', created: NOW - 15 * DAY },
    { id: 'GT-208', title: 'Onboard FitDirect feed', type: 'Request feed', entity: 'prospect:MP-1102', owner: 'merchants@comparo', priority: 'P1', due: NOW + 4 * DAY, status: 'In progress', impact: 'High', effort: 'Medium', created: NOW - 3 * DAY },
    { id: 'GT-209', title: 'Invite three trusted reviewers to contributor programme', type: 'Answer community question', entity: 'community:contributors', owner: 'community@comparo', priority: 'P2', due: NOW + 14 * DAY, status: 'Backlog', impact: 'Medium', effort: 'Low', created: NOW - 5 * DAY },
    { id: 'GT-210', title: 'Review creator tier for Lena Hart', type: 'Review integration', entity: 'creator:CR-3', owner: 'growth@comparo', priority: 'P2', due: NOW + 7 * DAY, status: 'Done', impact: 'Low', effort: 'Low', created: NOW - 22 * DAY, result: 'Moved to tier 2 — commission 8 %, 3 exclusive codes issued.' },
  ];
  gx.impactLog = [
    { ts: NOW - 12 * DAY, task: 'Onboard CoreNutri', result: 'Merchant live', detail: '+318 offers · France coverage 41 → 58' },
    { ts: NOW - 26 * DAY, task: 'Publish protein price research', result: 'Research published', detail: '4 backlinks · 1,240 organic entrances · 96 newsletter signups' },
    { ts: NOW - 34 * DAY, task: 'Negotiate affiliate with PerformanceHub', result: 'Programme live', detail: 'EPC €0.00 → €0.31 · commission 7.2 %' },
    { ts: NOW - 48 * DAY, task: 'Launch Czech market hub', result: 'Market launched', detail: '5 merchants · 62 SEO pages · 41 reviews' },
  ];

  /* ---------------- 3. affiliate sales CRM ---------------- */
  gx.programStatuses = ['No programme', 'Researching', 'Available', 'Applied', 'Approved', 'Rejected', 'Direct negotiation', 'Live', 'Paused'];
  gx.affiliateDeals = M.map((m, i) => {
    const status = m.partner ? 'Live' : i % 7 === 0 ? 'No programme' : i % 7 === 1 ? 'Direct negotiation' : i % 7 === 2 ? 'Applied' : i % 7 === 3 ? 'Available' : i % 7 === 4 ? 'Researching' : i % 7 === 5 ? 'Live' : 'Paused';
    return {
      merchantId: m.id, status: status, network: m.affiliate.network, model: m.affiliate.network === 'Direct' ? 'CPS' : m.affiliate.network === 'Awin' ? 'CPA' : m.affiliate.network === 'Impact' ? 'Hybrid' : 'CPC',
      rate: m.affiliate.commission, cookie: m.affiliate.cookie, contact: 'partners@' + m.web,
      negotiation: status === 'Direct negotiation' ? 'Terms offered' : status === 'Applied' ? 'Initial contact' : status === 'Live' ? 'Live' : null,
      history: [
        { ts: NOW - 220 * DAY, rate: Math.round((m.affiliate.commission - 1.4) * 10) / 10, note: 'Programme opened' },
        { ts: NOW - 90 * DAY, rate: Math.round((m.affiliate.commission - 0.6) * 10) / 10, note: 'Volume tier reached' },
        { ts: NOW - 20 * DAY, rate: m.affiliate.commission, note: 'Renegotiated at quarterly review' },
      ],
    };
  });
  /* funnel and terminal state kept separate, same rule as prospects and creators */
  gx.exclusiveStages = ['Idea', 'Proposed', 'Negotiating', 'Scheduled', 'Active'];
  gx.exclusiveTerminal = ['Ended'];
  gx.exclusivePipeline = [
    { id: 'EX-1', merchantId: 1, idea: 'Whey isolate −12 % for 30 days', stage: 'Active', expClicks: 5200, expConv: 410, expRevenue: 29400, starts: NOW - 12 * DAY, ends: NOW + 18 * DAY },
    { id: 'EX-2', merchantId: 3, idea: 'Free shipping, no minimum, GB + DE', stage: 'Scheduled', expClicks: 3100, expConv: 240, expRevenue: 14100, starts: NOW + 6 * DAY, ends: NOW + 36 * DAY },
    { id: 'EX-3', merchantId: 5, idea: 'Creatine bundle at cost-per-serving record', stage: 'Negotiating', expClicks: 2400, expConv: 190, expRevenue: 9800, starts: null, ends: null },
    { id: 'EX-4', merchantId: 2, idea: 'Czech market launch code', stage: 'Proposed', expClicks: 1800, expConv: 130, expRevenue: 7200, starts: null, ends: null },
    { id: 'EX-5', merchantId: 7, idea: '48-hour flash on electrolytes', stage: 'Ended', expClicks: 2000, expConv: 168, expRevenue: 8940, starts: NOW - 40 * DAY, ends: NOW - 38 * DAY },
    { id: 'EX-6', merchantId: 4, idea: 'Student discount, PL', stage: 'Idea', expClicks: 900, expConv: 60, expRevenue: 3100, starts: null, ends: null },
  ];

  /* ---------------- 4. creators ---------------- */
  const creatorDefs = [
    ['Lena Hart', '@lenahart', 'YouTube', 'DE', 148000, 'Strength training', 'Active', 'LENA10', 4820, 386, 22400, 1568, 'tier2'],
    ['Marek Dvorak', '@marekgains', 'Instagram', 'CZ', 62000, 'Bodybuilding', 'Active', 'MAREK10', 2140, 148, 8900, 623, 'tier1'],
    ['Endurance Emma', '@enduranceemma', 'YouTube', 'GB', 91000, 'Endurance', 'Active', 'EMMA12', 3310, 264, 15600, 1092, 'tier2'],
    ['Kraft & Kaffee', '@kraftkaffee', 'Podcast', 'DE', 34000, 'Nutrition science', 'Active', 'KRAFT8', 980, 44, 2600, 182, 'tier1'],
    ['Nordic Lift', '@nordiclift', 'Instagram', 'SE', 57000, 'Powerlifting', 'Onboarding', 'NORDIC10', 0, 0, 0, 0, 'tier1'],
    ['FitPolska', '@fitpolska', 'TikTok', 'PL', 210000, 'General fitness', 'Active', 'FITPL10', 6400, 121, 5400, 378, 'tier1'],
    ['Dr. Sofia Ruiz', '@drsofiaruiz', 'Newsletter', 'ES', 18000, 'Sports medicine', 'Interested', null, 0, 0, 0, 0, 'tier1'],
    ['Gains Français', '@gainsfr', 'YouTube', 'FR', 76000, 'Hypertrophy', 'Contacted', null, 0, 0, 0, 0, 'tier1'],
    ['IronViking', '@ironviking', 'Instagram', 'NO', 43000, 'Strongman', 'Qualified', null, 0, 0, 0, 0, 'tier1'],
    ['Supp Skeptic', '@suppskeptic', 'Blog', 'GB', 26000, 'Label analysis', 'Active', 'SKEPTIC5', 1420, 132, 7100, 497, 'tier2'],
    ['Milano Muscle', '@milanomuscle', 'TikTok', 'IT', 132000, 'Aesthetics', 'Paused', 'MILANO10', 3900, 39, 1700, 119, 'tier1'],
    ['Vienna Vegan Lifts', '@viennaveganlifts', 'Instagram', 'AT', 29000, 'Vegan nutrition', 'Discovered', null, 0, 0, 0, 0, 'tier1'],
    ['Coach Katri', '@coachkatri', 'YouTube', 'FI', 51000, 'Women’s strength', 'Rejected', null, 0, 0, 0, 0, 'tier1'],
  ];
  gx.creatorPipeline = ['Discovered', 'Qualified', 'Contacted', 'Interested', 'Onboarding', 'Active'];
  gx.creatorTerminal = ['Paused', 'Rejected'];
  gx.creatorStages = gx.creatorPipeline.concat(gx.creatorTerminal);
  gx.creators = creatorDefs.map((c, i) => ({
    id: 'CR-' + (i + 1), name: c[0], handle: c[1], platform: c[2], country: c[3], audience: c[4],
    topic: c[5], status: c[6], code: c[7], clicks: c[8], conversions: c[9], revenue: c[10], commission: c[11], tier: c[12],
    subId: 'creator_' + c[1].replace('@', ''),
    relevance: int(48, 96), brandSafety: int(62, 99), engagement: flt(1.2, 8.4, 1),
    newUsers: Math.round(c[8] * flt(0.05, 0.22, 3)),
    joined: c[6] === 'Active' || c[6] === 'Paused' ? NOW - int(30, 400) * DAY : null,
    disclosure: c[6] === 'Active' || c[6] === 'Paused',
    posts: c[6] === 'Active' ? [
      { url: 'https://example.com/' + c[1].replace('@', '') + '/post-1', platform: c[2], campaign: 'CMP-3', ts: NOW - int(2, 40) * DAY, status: 'Published' },
      { url: 'https://example.com/' + c[1].replace('@', '') + '/post-2', platform: c[2], campaign: 'CMP-5', ts: NOW - int(41, 120) * DAY, status: 'Published' },
    ] : [],
    products: [byName('Whey Isolate 90'), byName('Strength Core'), byName('Electrolyte Hydration')].filter(Boolean).slice(0, int(1, 3)).map((p) => p.id),
  }));

  /* ---------------- 5. referrals & points ---------------- */
  gx.referralRewards = [
    { at: 1, reward: 'Badge: Inviter', kind: 'badge' },
    { at: 3, reward: '+50 reputation', kind: 'reputation' },
    { at: 5, reward: '250 Comparo points', kind: 'points' },
    { at: 10, reward: 'Early access to price-history exports', kind: 'feature' },
  ];
  gx.referralCohorts = [
    { week: 'W-4', invites: 240, visits: 168, registrations: 62, activated: 31 },
    { week: 'W-3', invites: 310, visits: 214, registrations: 88, activated: 47 },
    { week: 'W-2', invites: 288, visits: 201, registrations: 79, activated: 44 },
    { week: 'W-1', invites: 402, visits: 291, registrations: 122, activated: 71 },
    { week: 'W-0', invites: 176, visits: 121, registrations: 48, activated: 22 },
  ];
  gx.referralAbuse = [
    { id: 1, kind: 'Self-referral', detail: '4 registrations share the referrer’s device fingerprint', code: 'CMP-8F2A', action: 'Rewards withheld pending review', ts: NOW - 2 * DAY },
    { id: 2, kind: 'Rapid signups', detail: '11 registrations in 6 minutes from one session', code: 'CMP-4K9Z', action: 'Cohort quarantined', ts: NOW - 9 * DAY },
    { id: 3, kind: 'Duplicate session', detail: 'Referrer and referee share a session id', code: 'CMP-2M7Q', action: 'Reward voided', ts: NOW - 17 * DAY },
  ];
  gx.pointRules = [
    { action: 'Review approved with photos', points: 60 },
    { action: 'Accepted data correction', points: 40 },
    { action: 'Helpful answer (5+ votes)', points: 30 },
    { action: 'Referral activated', points: 50 },
    { action: 'Approved community deal', points: 35 },
    { action: 'Coupon report confirmed', points: 10 },
  ];

  /* ---------------- 6. campaigns & calendar ---------------- */
  gx.campaignTypes = ['Merchant launch', 'Exclusive deal', 'Price drop', 'Market launch', 'Community campaign', 'Content campaign', 'Newsletter campaign', 'Creator campaign'];
  gx.campaigns = [
    { id: 'CMP-1', name: 'CoreNutri France launch', type: 'Merchant launch', goal: 'Merchant clicks', market: 'FR', starts: NOW - 20 * DAY, ends: NOW + 10 * DAY, status: 'Running', impressions: 84000, clicks: 5120, conversions: 288, revenue: 16400, subscribers: 210, reviews: 34, community: 12, owner: 'merchants@comparo' },
    { id: 'CMP-2', name: 'Whey isolate exclusive', type: 'Exclusive deal', goal: 'Conversions', market: 'DE', starts: NOW - 12 * DAY, ends: NOW + 18 * DAY, status: 'Running', impressions: 41200, clicks: 5140, conversions: 402, revenue: 28940, subscribers: 96, reviews: 21, community: 8, owner: 'affiliate@comparo' },
    { id: 'CMP-3', name: 'Creator week — strength', type: 'Creator campaign', goal: 'Registrations', market: 'DE', starts: NOW - 34 * DAY, ends: NOW - 20 * DAY, status: 'Completed', impressions: 268000, clicks: 9840, conversions: 512, revenue: 31200, subscribers: 1240, reviews: 88, community: 142, owner: 'growth@comparo' },
    { id: 'CMP-4', name: 'Czechia market launch', type: 'Market launch', goal: 'Traffic', market: 'CZ', starts: NOW + 8 * DAY, ends: NOW + 45 * DAY, status: 'Scheduled', impressions: 0, clicks: 0, conversions: 0, revenue: 0, subscribers: 0, reviews: 0, community: 0, owner: 'growth@comparo' },
    { id: 'CMP-5', name: 'Price-drop digest push', type: 'Newsletter campaign', goal: 'Email signups', market: 'ALL', starts: NOW - 6 * DAY, ends: NOW + 1 * DAY, status: 'Running', impressions: 38400, clicks: 4210, conversions: 176, revenue: 9100, subscribers: 2140, reviews: 4, community: 2, owner: 'content@comparo' },
    { id: 'CMP-6', name: 'Review week', type: 'Community campaign', goal: 'Reviews', market: 'ALL', starts: NOW - 48 * DAY, ends: NOW - 41 * DAY, status: 'Completed', impressions: 21000, clicks: 1840, conversions: 42, revenue: 2100, subscribers: 88, reviews: 412, community: 388, owner: 'community@comparo' },
    { id: 'CMP-7', name: 'Shipping-cost research launch', type: 'Content campaign', goal: 'Traffic', market: 'ALL', starts: NOW + 4 * DAY, ends: NOW + 30 * DAY, status: 'Scheduled', impressions: 0, clicks: 0, conversions: 0, revenue: 0, subscribers: 0, reviews: 0, community: 0, owner: 'content@comparo' },
    { id: 'CMP-8', name: 'Creatine price-drop alert', type: 'Price drop', goal: 'Merchant clicks', market: 'DE', starts: NOW - 3 * DAY, ends: NOW + 4 * DAY, status: 'Running', impressions: 16800, clicks: 2410, conversions: 141, revenue: 6800, subscribers: 34, reviews: 6, community: 3, owner: 'growth@comparo' },
    { id: 'CMP-9', name: 'Poland merchant push', type: 'Merchant launch', goal: 'Traffic', market: 'PL', starts: NOW + 6 * DAY, ends: NOW + 40 * DAY, status: 'Scheduled', impressions: 0, clicks: 0, conversions: 0, revenue: 0, subscribers: 0, reviews: 0, community: 0, owner: 'merchants@comparo' },
  ];

  /* ---------------- 7. content engine ---------------- */
  gx.contentTypes = ['Guide', 'Comparison', 'Market page', 'Brand page', 'Shop comparison', 'Data report', 'FAQ', 'Community answer', 'Research article'];
  const contentDefs = [
    ['creatine gummies vs powder: cost per gram', 'Comparison', 'creatine gummies', 142, 'high', 1],
    ['Which shop has the best delivery rating in Germany?', 'Market page', 'best delivery rating germany', 96, 'high', 1],
    ['Clear whey: what it is and what it costs', 'Guide', 'clear whey refresh', 96, 'medium', 0],
    ['Shipping costs across 12 European markets', 'Data report', 'shipping cost comparison', 210, 'high', 1],
    ['Ashwagandha gummies: availability by market', 'Market page', 'ashwagandha gummies', 61, 'medium', 0],
    ['Electrolyte tablets vs sticks: price per serving', 'Comparison', 'electrolyte tablets 60', 54, 'medium', 1],
    ['Cheapest protein per 100 g of protein — September', 'Data report', 'cheapest protein per 100g', 320, 'high', 1],
    ['Is melatonin legal to buy in your market?', 'FAQ', 'melatonin legal germany', 188, 'high', 1],
    ['IRONFORGE vs NORDKRAFT: label comparison', 'Brand page', 'ironforge vs nordkraft', 74, 'medium', 1],
    ['PeakSupps vs IronLab Store: total price and trust', 'Shop comparison', 'peaksupps vs ironlab', 118, 'high', 1],
    ['How Comparo verifies coupons', 'Guide', 'coupon verification', 42, 'low', 1],
    ['Creatine monohydrate: how much does a year cost?', 'Guide', 'creatine yearly cost', 156, 'high', 1],
    ['Vegan protein: complete amino profile on a budget', 'Guide', 'vegan protein budget', 132, 'medium', 1],
    ['Which markets have the cheapest whey isolate?', 'Data report', 'cheapest whey isolate market', 174, 'high', 1],
    ['Free shipping thresholds compared', 'Comparison', 'free shipping threshold', 88, 'medium', 1],
    ['Do I need EAA if I already take whey?', 'Community answer', 'eaa vs whey', 204, 'medium', 0],
    ['Pre-workout caffeine limits by country', 'FAQ', 'caffeine limit pre workout', 112, 'high', 1],
    ['Fastest-improving shops this quarter', 'Data report', 'best improving shops', 46, 'medium', 1],
    ['Magnesium forms: bisglycinate vs oxide cost', 'Comparison', 'magnesium bisglycinate cost', 92, 'medium', 1],
    ['Collagen for joints: what the labels actually say', 'Guide', 'collagen joint support', 78, 'low', 1],
    ['Czechia: where to buy supplements online', 'Market page', 'supplements czechia', 134, 'high', 1],
    ['Poland: cheapest creatine shops', 'Market page', 'creatine poland', 148, 'high', 1],
    ['How price history reveals fake discounts', 'Research article', 'fake discount detection', 66, 'high', 1],
    ['Casein before bed: is the premium worth it?', 'Guide', 'casein night protein', 84, 'medium', 1],
    ['Mass gainers: cost per 1,000 kcal', 'Comparison', 'mass gainer cost per calorie', 104, 'medium', 1],
    ['Which brands are gaining shelf space?', 'Research article', 'brand availability growth', 58, 'medium', 1],
  ];
  gx.contentPipeline = ['Idea', 'Brief', 'Draft', 'Review', 'Approved', 'Published'];
  gx.contentTerminal = ['Needs update'];
  gx.contentStatuses = gx.contentPipeline.concat(gx.contentTerminal);
  gx.taskPipeline = ['Backlog', 'Planned', 'In progress', 'Review'];
  gx.taskTerminal = ['Done'];
  gx.leadPipeline = ['Identified', 'Contacted', 'Interested', 'Onboarding', 'Verified'];
  gx.leadTerminal = ['Live', 'Rejected'];
  gx.content = contentDefs.map((c, i) => ({
    id: 'CO-' + (i + 1), title: c[0], type: c[1], query: c[2], searches: c[3], intent: c[4], dataReady: !!c[5],
    status: i < 4 ? 'Published' : i < 7 ? 'Approved' : i < 11 ? 'Draft' : i < 15 ? 'Brief' : i === 22 ? 'Needs update' : 'Idea',
    owner: i % 3 === 0 ? 'content@comparo' : i % 3 === 1 ? 'seo@comparo' : null,
    created: NOW - int(2, 90) * DAY,
    entrances: i < 4 ? int(400, 3200) : 0, merchantClicks: i < 4 ? int(40, 420) : 0,
    conversions: i < 4 ? int(2, 34) : 0, revenue: i < 4 ? int(120, 2400) : 0,
    signups: i < 4 ? int(4, 120) : 0, saves: i < 4 ? int(10, 240) : 0,
    updatedAt: i === 22 ? NOW - 240 * DAY : NOW - int(1, 60) * DAY,
    links: int(0, 9),
  }));
  gx.internalLinkTasks = [
    { from: 'Guide: How price history works', to: 'Product: Whey Isolate 90', reason: 'Guide references price history but never links the flagship product', status: 'open' },
    { from: 'Research: What protein really costs', to: 'Category: Protein', reason: 'Pillar page missing its category hub link', status: 'open' },
    { from: 'Guide: Shipping is the hidden price', to: 'Country: Germany', reason: 'Market hub has no inbound link from the shipping guide', status: 'open' },
    { from: 'FAQ: Melatonin legality', to: 'Product: Melatonin Sleep 1 mg', reason: 'Compliance answer should link the affected product', status: 'done' },
    { from: 'Shop comparison: PeakSupps vs IronLab', to: 'Shop: IronLab Store', reason: 'Comparison links only one of the two shops', status: 'open' },
  ];
  gx.refreshQueue = [
    { page: '/research/what-protein-really-costs', reason: 'Prices quoted are 94 days old', severity: 'high' },
    { page: '/guides/how-price-history-works', reason: 'References a merchant that is no longer live', severity: 'high' },
    { page: '/countries/czechia', reason: 'Merchant count changed from 3 to 6', severity: 'medium' },
    { page: '/brands/titan-range', reason: 'Review statistics 6 months stale', severity: 'medium' },
    { page: '/guides/spot-a-fake-review', reason: 'Broken internal link to moderation policy', severity: 'low' },
  ];
  gx.aiAnswerGaps = [
    { question: 'Which shop has the best delivery rating in Germany?', asks: 96, hasAnswer: false, suggestion: 'Market comparison page ranking shops by delivery reliability' },
    { question: 'Where are the largest price drops right now?', asks: 142, hasAnswer: false, suggestion: 'Live price-drop board with 30-day context' },
    { question: 'Which merchants are trending in Germany?', asks: 74, hasAnswer: false, suggestion: 'Trending shops module on the market hub' },
    { question: 'Is melatonin legal to buy in Germany?', asks: 188, hasAnswer: true, suggestion: 'Answer exists — add FAQ schema and link from the product page' },
    { question: 'What is the cheapest creatine per serving?', asks: 210, hasAnswer: true, suggestion: 'Answer exists — refresh monthly and add to Ask Comparo suggestions' },
  ];

  /* ---------------- 8. newsletter ---------------- */
  gx.segments = [
    { key: 'all', name: 'All subscribers', size: 18420 },
    { key: 'de', name: 'Germany', size: 7240 },
    { key: 'followers', name: 'Follows a merchant', size: 4110 },
    { key: 'watchers', name: 'Price watchers', size: 3860 },
    { key: 'community', name: 'Community contributors', size: 1290 },
    { key: 'deals', name: 'Deal hunters', size: 6480 },
    { key: 'dormant', name: 'Inactive 30 days', size: 2940 },
  ];
  gx.newsletterBlocks = ['Hero', 'Deals', 'Price drops', 'Products', 'Shops', 'Reviews', 'Community', 'Research'];
  gx.newsletters = [
    { id: 'NL-41', title: 'Weekly deals — week 36', type: 'Weekly deals', segment: 'deals', status: 'Sent', scheduled: NOW - 3 * DAY, blocks: ['Hero', 'Deals', 'Price drops', 'Shops'], sent: 6480, opened: 2721, clicked: 918, merchantClicks: 512, unsub: 14 },
    { id: 'NL-40', title: 'Price watch: your saved products', type: 'Price watch', segment: 'watchers', status: 'Sent', scheduled: NOW - 7 * DAY, blocks: ['Hero', 'Price drops', 'Products'], sent: 3860, opened: 1930, clicked: 811, merchantClicks: 466, unsub: 6 },
    { id: 'NL-39', title: 'Germany market digest — August', type: 'Market digest', segment: 'de', status: 'Sent', scheduled: NOW - 14 * DAY, blocks: ['Hero', 'Research', 'Shops', 'Deals'], sent: 7240, opened: 3113, clicked: 742, merchantClicks: 388, unsub: 21 },
    { id: 'NL-42', title: 'Community digest — September', type: 'Community digest', segment: 'community', status: 'Scheduled', scheduled: NOW + 2 * DAY, blocks: ['Hero', 'Community', 'Reviews'], sent: 0, opened: 0, clicked: 0, merchantClicks: 0, unsub: 0 },
    { id: 'NL-43', title: 'Shipping-cost research', type: 'Research digest', segment: 'all', status: 'Draft', scheduled: null, blocks: ['Hero', 'Research'], sent: 0, opened: 0, clicked: 0, merchantClicks: 0, unsub: 0 },
    { id: 'NL-44', title: 'Come back: 5 saved products dropped', type: 'Price watch', segment: 'dormant', status: 'Draft', scheduled: null, blocks: ['Hero', 'Price drops'], sent: 0, opened: 0, clicked: 0, merchantClicks: 0, unsub: 0 },
  ];

  /* ---------------- 9. research & PR ---------------- */
  gx.research = [
    { id: 'RS-1', headline: 'Whey isolate is 41 % cheaper in Poland than in Sweden', kind: 'Market spread', confidence: 'high', recency: NOW - 2 * DAY, uniqueness: 92, status: 'Ready', charts: ['market spread', 'per-100g protein'], finding: 'Normalised to price per 100 g of protein including shipping, the gap between the cheapest and most expensive market is 41 %.' },
    { id: 'RS-2', headline: 'Average shipping cost in Germany fell 6 % this quarter', kind: 'Shipping trend', confidence: 'high', recency: NOW - 5 * DAY, uniqueness: 84, status: 'Draft', charts: ['shipping median by month'], finding: 'Median shipping to Germany moved from €4.90 to €4.60 as two merchants lowered thresholds.' },
    { id: 'RS-3', headline: 'The largest single price drop this month was 34 %', kind: 'Price drop', confidence: 'high', recency: NOW - 1 * DAY, uniqueness: 71, status: 'Ready', charts: ['drop leaderboard'], finding: 'Creatine Monohydrate Micronized fell 34 % against its 90-day average at two independent merchants.' },
    { id: 'RS-4', headline: 'Shop trust scores improved fastest in Czechia', kind: 'Trust trend', confidence: 'medium', recency: NOW - 9 * DAY, uniqueness: 66, status: 'Pitched', charts: ['trust delta by market'], finding: 'Czech merchants gained an average of 7.4 trust points over 90 days, driven by feed uptime.' },
    { id: 'RS-5', headline: 'One in nine advertised discounts rests on an inflated reference price', kind: 'Fake discount', confidence: 'high', recency: NOW - 12 * DAY, uniqueness: 96, status: 'Published', charts: ['reference price vs median'], finding: '11 % of discounted offers carry a reference price more than 25 % above the 12-month median.' },
    { id: 'RS-6', headline: 'Brand availability grew fastest for vegan-first ranges', kind: 'Brand growth', confidence: 'medium', recency: NOW - 20 * DAY, uniqueness: 58, status: 'Idea', charts: ['brand offer count'], finding: 'Vegan-first brands added 38 % more offers quarter on quarter.' },
  ];
  gx.prContacts = [
    { id: 'PR-1', publication: 'Handelsblatt', contact: 'retail desk', market: 'DE', topic: 'Retail pricing', status: 'Pitched', notes: 'Interested in the shipping-cost trend if we can supply the market table.' },
    { id: 'PR-2', publication: 'Hospodářské noviny', contact: 'consumer desk', market: 'CZ', topic: 'Consumer protection', status: 'Published', notes: 'Ran the fake-discount study with methodology link.' },
    { id: 'PR-3', publication: 'Les Échos', contact: 'e-commerce', market: 'FR', topic: 'E-commerce', status: 'Ready', notes: 'Waiting on French market coverage to be strong enough to quote.' },
    { id: 'PR-4', publication: 'The Grocer', contact: 'nutrition', market: 'GB', topic: 'Supplements', status: 'Idea', notes: null },
    { id: 'PR-5', publication: 'Rzeczpospolita', contact: 'business', market: 'PL', topic: 'Pricing', status: 'Rejected', notes: 'Asked for exclusive data we cannot yet stand behind.' },
  ];
  gx.backlinks = [
    { id: 1, source: 'Hospodářské noviny', url: 'https://example-hn.cz/fake-discounts', target: '/research/fake-discount-detection', date: NOW - 11 * DAY, status: 'Live' },
    { id: 2, source: 'Reddit r/supplements', url: 'https://example-reddit.com/comparo-price-history', target: '/guides/how-price-history-works', date: NOW - 24 * DAY, status: 'Live' },
    { id: 3, source: 'FitBlog DE', url: 'https://example-fitblog.de/protein-preise', target: '/research/what-protein-really-costs', date: NOW - 38 * DAY, status: 'Live' },
    { id: 4, source: 'Nutrition Weekly', url: 'https://example-nw.com/shipping-costs', target: '/research/shipping-costs-europe', date: NOW - 3 * DAY, status: 'Pending' },
    { id: 5, source: 'Gym Forum PL', url: 'https://example-gymforum.pl/kreatyna-ceny', target: '/countries/poland', date: NOW - 52 * DAY, status: 'Lost' },
  ];
  gx.linkableAssets = [
    { name: 'Price history explorer', kind: 'Data explorer', links: 14, status: 'Live' },
    { name: 'Cost per serving calculator', kind: 'Calculator', links: 9, status: 'Live' },
    { name: 'Market shipping table', kind: 'Chart', links: 6, status: 'Live' },
    { name: 'Fake discount study', kind: 'Research', links: 11, status: 'Live' },
    { name: 'Trust score methodology', kind: 'Report', links: 4, status: 'Live' },
  ];
  gx.socialQueue = [
    { id: 'SC-1', research: 'RS-1', channel: 'LinkedIn', copy: 'Whey isolate costs 41 % less in Poland than in Sweden once shipping is included. Full market table and method inside.', status: 'Approved' },
    { id: 'SC-2', research: 'RS-1', channel: 'X', copy: 'Same product. Same week. 41 % price gap between two EU markets — after shipping.', status: 'Draft' },
    { id: 'SC-3', research: 'RS-5', channel: 'Community post', copy: 'We checked every discounted offer against its 12-month median. One in nine "discounts" rests on an inflated reference price.', status: 'Published' },
    { id: 'SC-4', research: 'RS-2', channel: 'Newsletter', copy: 'Shipping to Germany got 6 % cheaper this quarter. Here is which shops moved.', status: 'Draft' },
    { id: 'SC-5', research: 'RS-3', channel: 'Instagram', copy: 'Biggest drop this month: −34 % on micronised creatine, at two independent shops.', status: 'Draft' },
    { id: 'SC-6', research: 'RS-5', channel: 'Short video', copy: 'How to spot a fake discount in 20 seconds, using price history.', status: 'Draft' },
  ];
  gx.dataCards = [
    { stat: 'Average shipping cost in Germany dropped 6 % this month', source: 'offer shipping medians, 30 d' },
    { stat: '41 % price gap for the same whey isolate between PL and SE', source: 'per-100g protein, shipping included' },
    { stat: '11 % of advertised discounts use an inflated reference price', source: 'reference price vs 12-month median' },
    { stat: 'Czech shops gained 7.4 trust points in 90 days', source: 'trust score history' },
  ];

  /* every status stepper declares its ordered lifecycle and its terminal state separately, so
     "advance" can never run into the terminal value and report a transition that did not happen */
  gx.experimentStages = ['Planned', 'Running'];
  gx.experimentTerminal = ['Concluded'];
  gx.researchStages = ['Idea', 'Draft', 'Ready', 'Pitched'];
  gx.researchTerminal = ['Published'];
  gx.socialStages = ['Draft', 'Approved'];
  gx.socialTerminal = ['Published'];

  /* ---------------- 10. growth experiments ---------------- */
  gx.experiments = [
    { id: 'GX-1', hypothesis: 'A total-price-first search result row raises merchant click rate', metric: 'Merchant click rate', area: 'Search', audience: 'All visitors', variants: ['Control', 'Total-first row'], status: 'Running', impact: 4, confidence: 3, effort: 2, result: null },
    { id: 'GX-2', hypothesis: 'Showing the free-shipping threshold nudge on product pages raises basket usage', metric: 'Basket compare starts', area: 'Comparison', audience: 'DE visitors', variants: ['Control', 'Nudge'], status: 'Running', impact: 3, confidence: 4, effort: 1, result: null },
    { id: 'GX-3', hypothesis: 'Referral prompt after a price alert converts better than after registration', metric: 'Invites sent', area: 'Referral', audience: 'Registered users', variants: ['After registration', 'After alert'], status: 'Concluded', impact: 3, confidence: 4, effort: 1, result: 'Winner: after alert (+38 % invites)' },
    { id: 'GX-4', hypothesis: 'Merchant onboarding in 3 steps instead of 5 lifts completion', metric: 'Onboarding completion', area: 'Merchant onboarding', audience: 'New merchants', variants: ['5 steps', '3 steps'], status: 'Planned', impact: 5, confidence: 2, effort: 3, result: null },
    { id: 'GX-5', hypothesis: 'Deal digest performs better on Thursday than Monday', metric: 'Open rate', area: 'Newsletter', audience: 'Deal hunters', variants: ['Monday', 'Thursday'], status: 'Concluded', impact: 2, confidence: 5, effort: 1, result: 'Inconclusive (+0.4 pp, inside noise)' },
    { id: 'GX-6', hypothesis: 'Homepage trending module beats editorial picks for return visits', metric: 'Return rate', area: 'Homepage', audience: 'Returning visitors', variants: ['Editorial', 'Trending'], status: 'Running', impact: 4, confidence: 3, effort: 2, result: null },
    { id: 'GX-7', hypothesis: 'Community answer CTA on zero-result searches produces usable content', metric: 'Answers published', area: 'Search', audience: 'All visitors', variants: ['Control', 'Ask the community'], status: 'Planned', impact: 3, confidence: 3, effort: 2, result: null },
  ];

  /* ---------------- 11. channels, lifecycle, cohorts ---------------- */
  gx.channels = [
    { key: 'organic', name: 'Organic Search', users: 184200, activated: 21400, merchantClicks: 21400, conversions: 1480, revenue: 96400, cac: null },
    { key: 'ai', name: 'AI Search', users: 9400, activated: 1580, merchantClicks: 1580, conversions: 121, revenue: 8900, cac: null },
    { key: 'direct', name: 'Direct', users: 31600, activated: 4900, merchantClicks: 4900, conversions: 402, revenue: 26100, cac: null },
    { key: 'community', name: 'Community', users: 14300, activated: 1240, merchantClicks: 1240, conversions: 78, revenue: 4700, cac: null },
    { key: 'referral', name: 'Referral', users: 6100, activated: 720, merchantClicks: 720, conversions: 44, revenue: 2800, cac: null },
    { key: 'creator', name: 'Creator', users: 22800, activated: 3140, merchantClicks: 3140, conversions: 268, revenue: 16800, cac: null },
    { key: 'newsletter', name: 'Newsletter', users: 18420, activated: 2440, merchantClicks: 2440, conversions: 194, revenue: 11900, cac: null },
    { key: 'social', name: 'Social', users: 8900, activated: 610, merchantClicks: 610, conversions: 31, revenue: 1900, cac: null },
    { key: 'merchant', name: 'Merchant referral', users: 3400, activated: 420, merchantClicks: 420, conversions: 26, revenue: 1600, cac: null },
  ];
  gx.lifecycle = [
    { stage: 'Visitor', users: 268000, def: 'Any session' },
    { stage: 'Registered', users: 41200, def: 'Created an account' },
    { stage: 'Activated', users: 18640, def: 'Saved a product, set an alert, followed a shop, wrote a review or ran a comparison' },
    { stage: 'Engaged', users: 9840, def: 'Two or more activation actions in 30 days' },
    { stage: 'Contributor', users: 2140, def: 'Published a review, answer, guide or correction' },
    { stage: 'Returning', users: 12600, def: 'Returned within 30 days' },
    { stage: 'Dormant', users: 8920, def: 'No session in 30 days' },
  ];
  gx.activationFunnel = [
    { step: 'Registration', users: 41200 },
    { step: 'First search', users: 36400 },
    { step: 'First save', users: 21800 },
    { step: 'First alert', users: 12400 },
    { step: 'First return', users: 12600 },
  ];
  gx.cohorts = [
    { cohort: 'Week −5', size: 3120, w0: 100, w1: 41, w4: 22 },
    { cohort: 'Week −4', size: 3480, w0: 100, w1: 44, w4: 24 },
    { cohort: 'Week −3', size: 3960, w0: 100, w1: 46, w4: 26 },
    { cohort: 'Week −2', size: 4210, w0: 100, w1: 49, w4: null },
    { cohort: 'Week −1', size: 4640, w0: 100, w1: 52, w4: null },
  ];
  gx.retention = { d1: 34, d7: 21, d30: 12 };
  gx.communityActivation = [
    { step: 'Registered', users: 41200 }, { step: 'Read a discussion', users: 18400 },
    { step: 'Followed something', users: 9100 }, { step: 'First reply', users: 3240 }, { step: 'First contribution', users: 2140 },
  ];
  gx.reviewActivation = [
    { step: 'Viewed reviews', users: 96400 }, { step: 'Started a review', users: 8200 },
    { step: 'Submitted', users: 5140 }, { step: 'Approved', users: 4620 },
  ];
  gx.merchantActivation = [
    { step: 'Registered', users: 41 }, { step: 'Verified', users: 28 }, { step: 'Feed connected', users: 24 },
    { step: 'Products matched', users: 22 }, { step: 'First offer live', users: 21 }, { step: 'First deal', users: 14 }, { step: 'First conversion', users: 12 },
  ];
  gx.pageTypeRevenue = [
    { type: 'Product', entrances: 96400, clicks: 12800, conversions: 892, revenue: 58200 },
    { type: 'Comparison', entrances: 41200, clicks: 6100, conversions: 441, revenue: 28900 },
    { type: 'Shop', entrances: 32800, clicks: 3900, conversions: 268, revenue: 17400 },
    { type: 'Deal', entrances: 28400, clicks: 5200, conversions: 388, revenue: 24100 },
    { type: 'Guide', entrances: 24100, clicks: 1840, conversions: 96, revenue: 6200 },
    { type: 'Country', entrances: 18600, clicks: 2140, conversions: 132, revenue: 8600 },
  ];
  gx.assists = [
    { content: 'Guide: Shipping is the hidden price', assists: 1840, conversions: 142 },
    { content: 'Research: What protein really costs', assists: 1620, conversions: 128 },
    { content: 'Guide: How price history works', assists: 1210, conversions: 96 },
    { content: 'Forum: Best shop for cross-border delivery', assists: 880, conversions: 61 },
    { content: 'Guide: How we read labels', assists: 640, conversions: 38 },
  ];
  gx.dropoff = [
    { point: 'Offer table — no shipping data', sessions: 4120, note: '38 % of these sessions end without a merchant click', fix: 'Improve merchant shipping coverage' },
    { point: 'Search — zero results', sessions: 3480, note: 'Highest exit rate of any surface', fix: 'Create SEO landing pages for repeated queries' },
    { point: 'Product — compliance blocked', sessions: 1240, note: 'Users leave rather than switching market', fix: 'Suggest legal alternatives in-market' },
    { point: 'Basket compare — single shop only', sessions: 860, note: 'Split option unavailable for narrow catalogues', fix: 'Onboard merchants with broader catalogues' },
  ];
  gx.marketGoals = [
    { iso: 'DE', phase: 'Mature', goals: { merchants: [13, 10], offers: [267, 500], reviews: [192, 100], seoPages: [96, 50], affiliates: [6, 5] } },
    { iso: 'CZ', phase: 'Growth', goals: { merchants: [6, 10], offers: [128, 400], reviews: [64, 100], seoPages: [62, 50], affiliates: [3, 5] } },
    { iso: 'FR', phase: 'Opportunity', goals: { merchants: [4, 10], offers: [86, 400], reviews: [28, 100], seoPages: [41, 50], affiliates: [2, 5] } },
    { iso: 'PL', phase: 'Growth', goals: { merchants: [5, 10], offers: [104, 400], reviews: [41, 100], seoPages: [48, 50], affiliates: [2, 5] } },
    { iso: 'ES', phase: 'Early', goals: { merchants: [3, 8], offers: [61, 300], reviews: [14, 80], seoPages: [22, 40], affiliates: [1, 4] } },
    { iso: 'SE', phase: 'Coverage gap', goals: { merchants: [2, 8], offers: [38, 300], reviews: [9, 80], seoPages: [18, 40], affiliates: [1, 4] } },
    { iso: 'US', phase: 'Early', goals: { merchants: [2, 8], offers: [44, 300], reviews: [11, 80], seoPages: [20, 40], affiliates: [1, 4] } },
  ];
  gx.playbook = [
    { step: 'Merchant acquisition', detail: '10 verified merchants, at least 3 with national coverage', owner: 'merchants@comparo' },
    { step: 'SEO', detail: 'Market hub, 50 programmatic pages above the quality threshold, hreflang complete', owner: 'seo@comparo' },
    { step: 'Community', detail: 'Local forum category, 5 seeded discussions, 2 ambassadors', owner: 'community@comparo' },
    { step: 'Affiliate', detail: '5 live programmes, tracking verified end to end', owner: 'affiliate@comparo' },
    { step: 'Newsletter', detail: 'Market segment with a localised weekly digest', owner: 'content@comparo' },
    { step: 'Research', detail: 'One market-specific data story with methodology', owner: 'content@comparo' },
  ];
  gx.launchChecklist = ['Country config', 'Currency', 'Localisation', 'Compliance rules', 'Merchant coverage', 'SEO landing pages', 'Deals', 'Community hub', 'Affiliate programmes', 'Newsletter segment'];

  /* ---------------- 12. growth alerts ---------------- */
  gx.alerts = [
    { id: 1, severity: 'Opportunity', text: 'PeakSupps received 2,840 outbound clicks in 30 days with no affiliate programme', entity: 'merchant:1', action: 'Negotiate affiliate', ts: NOW - 4 * HOUR },
    { id: 2, severity: 'Critical', text: 'Affiliate tracking failing on 5 SupplementBay links', entity: 'merchant:8', action: 'Improve feed', ts: NOW - 18 * HOUR },
    { id: 3, severity: 'Opportunity', text: '“creatine gummies” searched 142× in 30 days with zero results', entity: 'query:creatine gummies', action: 'Create SEO page', ts: NOW - 2 * DAY },
    { id: 4, severity: 'Important', text: 'Sweden merchant coverage dropped to 2 active merchants', entity: 'market:SE', action: 'Onboard merchant', ts: NOW - 3 * DAY },
    { id: 5, severity: 'Opportunity', text: 'SportNahrung DE scores 85 on qualification and is not yet contacted', entity: 'prospect:MP-1112', action: 'Qualify prospect', ts: NOW - 5 * DAY },
    { id: 6, severity: 'Opportunity', text: 'Price spread across markets reached 41 % — suitable for a data story', entity: 'research:RS-1', action: 'Publish research', ts: NOW - 6 * DAY },
    { id: 7, severity: 'Info', text: 'Creator FitPolska drove 6,400 clicks at 1.9 % conversion — below tier median', entity: 'creator:CR-6', action: 'Review integration', ts: NOW - 8 * DAY },
    { id: 8, severity: 'Important', text: 'Search demand for electrolytes up 46 % week on week', entity: 'query:electrolytes', action: 'Create SEO page', ts: NOW - 10 * DAY },
  ];
  gx.growthAutomations = [
    { id: 1, name: 'Non-monetised traffic', trigger: 'merchant clicks > 500 AND affiliate programme = none', action: 'Create affiliate outreach task', enabled: true, runs: 14 },
    { id: 2, name: 'Zero-result SEO opportunity', trigger: 'zero-result query > 50 searches / 30 d', action: 'Create SEO opportunity', enabled: true, runs: 23 },
    { id: 3, name: 'Research story from price anomaly', trigger: 'confirmed price anomaly AND market impact high', action: 'Create research suggestion', enabled: true, runs: 6 },
    { id: 4, name: 'Prospect qualification', trigger: 'prospect score > 85', action: 'Create qualification task', enabled: true, runs: 9 },
    { id: 5, name: 'Creator tier review', trigger: 'creator conversions > 250', action: 'Suggest higher tier', enabled: true, runs: 4 },
    { id: 6, name: 'Unanswered question escalation', trigger: 'unanswered question views > 500', action: 'Surface to trusted contributors', enabled: true, runs: 11 },
    { id: 7, name: 'Merchant churn risk', trigger: 'no login 30 d OR feed stale 14 d', action: 'Create re-engagement task', enabled: false, runs: 7 },
  ];
  gx.unanswered = [
    { id: 'Q-14', question: 'Which shop has the best delivery rating in Germany?', views: 1840, asks: 96, market: 'DE' },
    { id: 'Q-15', question: 'Is creatine HCl worth the premium over monohydrate?', views: 1210, asks: 74, market: 'ALL' },
    { id: 'Q-16', question: 'Which shops actually ship to Sweden without customs fees?', views: 880, asks: 41, market: 'SE' },
    { id: 'Q-17', question: 'How do I read a proprietary blend label?', views: 760, asks: 38, market: 'ALL' },
    { id: 'Q-18', question: 'Best vegan protein under €30 in Poland?', views: 640, asks: 32, market: 'PL' },
  ];
  gx.contributorCandidates = [
    { user: 'martina_k', reviews: 18, helpfulRatio: 0.91, answers: 24, status: 'Invite ready' },
    { user: 'PetrGains', reviews: 22, helpfulRatio: 0.86, answers: 31, status: 'Invite ready' },
    { user: 'FitLena', reviews: 14, helpfulRatio: 0.88, answers: 12, status: 'Invited' },
    { user: 'IronMike', reviews: 9, helpfulRatio: 0.79, answers: 18, status: 'Watching' },
    { user: 'squatking', reviews: 11, helpfulRatio: 0.74, answers: 8, status: 'Watching' },
  ];
  gx.challenges = [
    { name: 'Review week', goal: '400 verified reviews', progress: 412, target: 400, status: 'Completed' },
    { name: 'Data correction drive', goal: '100 accepted corrections', progress: 68, target: 100, status: 'Running' },
    { name: 'Best guide of the month', goal: '10 published guides', progress: 6, target: 10, status: 'Running' },
    { name: 'Deal discovery week', goal: '150 community deals', progress: 0, target: 150, status: 'Scheduled' },
  ];
  gx.milestones = [
    { label: '10,000 verified reviews', progress: 4620, target: 10000 },
    { label: '500 merchants live', progress: 13, target: 500 },
    { label: '100 markets covered', progress: 12, target: 100 },
    { label: '1,000 community answers', progress: 388, target: 1000 },
  ];
  gx.flywheel = [
    { stage: 'Discovery', metrics: [['New users', '41,200'], ['Searches / 30 d', '268,000'], ['Community posts', '1,842']] },
    { stage: 'Supply', metrics: [['Live merchants', '13'], ['Products', '46'], ['Offers', '267']] },
    { stage: 'Intelligence', metrics: [['Price snapshots', '16,790'], ['Reviews', '4,620'], ['Trust scores', '13']] },
    { stage: 'Distribution', metrics: [['Indexable pages', '176'], ['Newsletter subscribers', '18,420'], ['Creator reach', '22,800']] },
    { stage: 'Monetisation', metrics: [['Merchant clicks / 30 d', '84,422'], ['Affiliate revenue', '€283,143'], ['Exclusives live', '3']] },
  ];
})();
