/* Comparo Performance — contribution & progression layer.

   Gamification here has one job: collect verifiable information. Every point in this file is
   paid for a contribution that a stranger could check — a price that was wrong, a coupon that
   failed, a parcel that was late, a label photographed at the right angle. Nothing is paid for
   presence, streak length alone, or opinion volume.

   Three rules that keep it from becoming a farm:

   1. Points are provisional until the contribution is confirmed. A price report that
      moderation cannot reproduce is reversed, and the reversal is visible on the profile.
   2. Every source has a daily cap. The cap exists because the twentieth price report of a day
      is worth less than the first, and because an unbounded source is an invitation.
   3. Rewards are platform capability only — days of Comparo Plus, alert capacity, bounty
      stake, profile flair. Never money, never products, never rank. We do not sell goods, so
      a reward that looked like a product would be a lie about what we are.

   Loaded after seed-community.js and seed-live.js. */
(function () {
  const S = window.SEED;
  if (!S) return;
  const DAY = S.DAY, NOW = S.NOW;
  let seed = 0x1d7b44c1;
  const R = () => { seed |= 0; seed = (seed + 0x6d2b79f5) | 0; let t = Math.imul(seed ^ (seed >>> 15), 1 | seed); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
  const int = (a, b) => Math.floor(R() * (b - a + 1)) + a;
  const gm = {};
  S.gm = gm;

  /* ---------------- what we pay for, and what it buys us ---------------- */
  gm.xpSources = [
    { key: 'price_report', label: 'Price correction', xp: 25, cap: 5, group: 'Data', why: 'A feed price that no longer matches the shop page. Confirmed against the shop within the hour.', proof: 'Screenshot or the shop URL; moderation re-checks the page.', collects: 'Offer accuracy' },
    { key: 'stock_report', label: 'Stock correction', xp: 15, cap: 5, group: 'Data', why: 'An offer listed in stock that is not. Stock confidence is otherwise guessed from feed freshness alone.', proof: 'Shop page state at the time of the report.', collects: 'Stock confidence' },
    { key: 'coupon_result', label: 'Coupon outcome', xp: 20, cap: 6, group: 'Data', why: 'Whether a code actually applied, on which basket, in which market. The single most perishable fact we hold.', proof: 'Basket total before and after.', collects: 'Coupon validity' },
    { key: 'delivery_report', label: 'Delivery report', xp: 30, cap: 3, group: 'Data', why: 'Order date, dispatch, arrival and carrier. This is what makes a delivery median real rather than modelled.', proof: 'Order confirmation date and the parcel arrival date.', collects: 'Delivery medians, promise compliance' },
    { key: 'label_photo', label: 'Label photograph', xp: 45, cap: 2, group: 'Data', why: 'The amount-per-serving panel, readable. Dosing is the comparison nobody else in this category can do.', proof: 'Photo of the panel with the lot number visible.', collects: 'Dosing, price per gram of active' },
    { key: 'dose_correction', label: 'Dosing correction', xp: 60, cap: 1, group: 'Data', why: 'A declared amount that differs from the tub. Highest-value correction we accept.', proof: 'Label photo plus the field being corrected.', collects: 'Dosing accuracy' },
    { key: 'review_verified', label: 'Verified review', xp: 80, cap: 2, group: 'Experience', why: 'A review backed by a matched click or an order reference.', proof: 'Automatic — the click log or an order number.', collects: 'Product and shop ratings' },
    { key: 'review_photo', label: 'Review with photos', xp: 25, cap: 2, group: 'Experience', why: 'Photographed experience, which reviewers who never bought cannot fake cheaply.', proof: 'At least one original photo.', collects: 'Review credibility' },
    { key: 'answer_accepted', label: 'Accepted answer', xp: 70, cap: 3, group: 'Community', why: 'The asker marked it as the answer. Paid to the answerer, never to the asker.', proof: 'Asker action.', collects: 'Question resolution rate' },
    { key: 'guide_published', label: 'Guide published', xp: 150, cap: 1, group: 'Community', why: 'A guide that passed editorial review.', proof: 'Editorial approval.', collects: 'Evergreen content' },
    { key: 'promoted_chat', label: 'Chat promoted to a topic', xp: 40, cap: 2, group: 'Community', why: 'Your live exchange was worth keeping. Paid to everyone quoted in it.', proof: 'Moderator promotion.', collects: 'Archive quality' },
    { key: 'poll_answer', label: 'Poll answered', xp: 5, cap: 3, group: 'Community', why: 'Structured answers we can break down by market.', proof: 'One vote per member per poll.', collects: 'Market research' },
    { key: 'shop_answer', label: 'Shop question answered', xp: 35, cap: 3, group: 'Community', why: 'Answering another buyer about a shop you have actually ordered from.', proof: 'Order on file with that shop.', collects: 'Shop Q&A' },
    { key: 'translation', label: 'Translation accepted', xp: 30, cap: 4, group: 'Community', why: 'Guide or category copy in a market language.', proof: 'Native-speaker review.', collects: 'Localisation' },
  ];
  gm.reversals = [
    { key: 'unreproducible', label: 'Could not be reproduced', effect: 'Points reversed, no penalty', note: 'Prices move. A report we cannot confirm is not treated as bad faith.' },
    { key: 'contradicted', label: 'Contradicted by the shop', effect: 'Points reversed', note: 'The shop provided evidence the report was wrong.' },
    { key: 'duplicate', label: 'Duplicate of an open report', effect: 'No points', note: 'First reporter is paid; the rest are recorded as confirmations.' },
    { key: 'bad_faith', label: 'Bad faith', effect: 'Points reversed, source suspended 30 days', note: 'Fabricated evidence or a report placed to move a rating.' },
  ];

  /* ---------------- levels: capability, not decoration ---------------- */
  gm.levels = [
    { n: 1, key: 'new', label: 'New Member', min: 0, perks: ['Post, reply and vote', '3 price alerts'] },
    { n: 2, key: 'contributor', label: 'Contributor', min: 50, perks: ['Report prices and stock', '10 price alerts'] },
    { n: 3, key: 'regular', label: 'Regular', min: 150, perks: ['Submit deals without a queue wait', '20 price alerts'] },
    { n: 4, key: 'trusted', label: 'Trusted', min: 400, perks: ['Upload label photographs', 'Delivery reports count double in medians'] },
    { n: 5, key: 'expert', label: 'Expert', min: 900, perks: ['Write guides', 'Stake bounties', 'Room moderation tools in one section'] },
    { n: 6, key: 'curator', label: 'Curator', min: 1800, perks: ['Create groups', 'Promote chat exchanges into topics'] },
    { n: 7, key: 'authority', label: 'Authority', min: 3200, perks: ['Edit dosing records with a photo', 'Merge duplicate products'] },
    { n: 8, key: 'steward', label: 'Steward', min: 5200, perks: ['Trusted flagger: reports skip the first queue', 'Review other members\u2019 corrections'] },
    { n: 9, key: 'fellow', label: 'Fellow', min: 8000, perks: ['Seat on the methodology review', 'Early access to data products'] },
    { n: 10, key: 'founder', label: 'Founding Member', min: 12000, perks: ['Permanent Comparo Plus', 'Named in the methodology page credits'] },
  ];
  /* the older community layer had five levels; keep its labels resolvable so nothing breaks */
  S.levels = gm.levels.map((l) => ({ key: l.key, label: l.label, min: l.min }));

  gm.badgeTiers = [
    { badge: 'price_watch', label: 'Price Watch', tiers: [['Bronze', 10], ['Silver', 50], ['Gold', 200]], source: 'price_report' },
    { badge: 'label_reader', label: 'Label Reader', tiers: [['Bronze', 3], ['Silver', 15], ['Gold', 60]], source: 'label_photo' },
    { badge: 'delivery_witness', label: 'Delivery Witness', tiers: [['Bronze', 5], ['Silver', 25], ['Gold', 100]], source: 'delivery_report' },
    { badge: 'answer_machine', label: 'Answer Machine', tiers: [['Bronze', 5], ['Silver', 30], ['Gold', 120]], source: 'answer_accepted' },
    { badge: 'verified_buyer', label: 'Verified Buyer', tiers: [['Bronze', 1], ['Silver', 8], ['Gold', 30]], source: 'review_verified' },
  ];

  /* ---------------- streaks ---------------- */
  gm.streak = {
    rule: 'A day counts when at least one contribution is accepted. Votes, logins and page views never count.',
    multipliers: [[3, 1.1], [7, 1.25], [14, 1.4], [30, 1.6], [90, 2]],
    freezes: 2, freezeNote: 'Two freeze days per month, applied automatically. A streak is a habit, not a punishment for a holiday.',
    cap: 'The multiplier applies to data sources only, and never lifts a single contribution above 120 XP.',
  };

  /* ---------------- quests ---------------- */
  gm.quests = [
    { key: 'd_price', scope: 'daily', title: 'Check two prices', desc: 'Open two offers you have bought from before and confirm or correct the price.', xp: 40, target: 2, source: 'price_report' },
    { key: 'd_poll', scope: 'daily', title: 'Answer today\u2019s poll', desc: 'One question, four options, broken down by market.', xp: 10, target: 1, source: 'poll_answer' },
    { key: 'd_room', scope: 'daily', title: 'Answer one question in a room', desc: 'Someone is deciding right now. A one-line answer counts.', xp: 25, target: 1, source: 'answer_accepted' },
    { key: 'w_delivery', scope: 'weekly', title: 'Log a delivery', desc: 'Order date, arrival date, carrier. This is what makes the promise badge enforceable.', xp: 90, target: 1, source: 'delivery_report' },
    { key: 'w_label', scope: 'weekly', title: 'Photograph one label panel', desc: 'Any product in your cupboard whose dosing we do not hold yet.', xp: 120, target: 1, source: 'label_photo' },
    { key: 'w_review', scope: 'weekly', title: 'Review something you actually bought', desc: 'Verified reviews carry full weight in the average.', xp: 140, target: 1, source: 'review_verified' },
    { key: 'w_coupon', scope: 'weekly', title: 'Report three coupon outcomes', desc: 'Worked or failed, with the basket. Coupons decay faster than any other record we hold.', xp: 80, target: 3, source: 'coupon_result' },
    { key: 's_market', scope: 'season', title: 'Cover a thin market', desc: 'Five contributions in a market with fewer than 40 records. Currently: Romania, Bulgaria, Estonia.', xp: 500, target: 5, source: 'any' },
    { key: 's_dose', scope: 'season', title: 'Close ten dosing gaps', desc: 'Ten products whose amount-per-serving panel we do not hold.', xp: 800, target: 10, source: 'label_photo' },
  ];

  /* ---------------- season ---------------- */
  const seasonStart = NOW - 38 * DAY;
  gm.season = {
    key: 'S3', name: 'Season 3 — Dosing', starts: seasonStart, ends: seasonStart + 90 * DAY,
    theme: 'Amount per serving for every product in the catalogue.',
    goal: 46, goalLabel: 'products with a photographed dosing panel',
    progress: 31,
    reward: 'Every member with five accepted contributions gets 30 days of Comparo Plus. The top ten get a year.',
    note: 'A season names one data gap and closes it. When the catalogue is fully dosed, the next season moves to delivery coverage in the thin markets.',
  };
  /* leaderboard is derived from what members actually contributed, not from a typed rank */
  gm.season.leaderboard = S.users.map((u) => {
    const contribs = Math.round(u.reviews * 1.6 + u.helpful * 0.12 + (u.answers || 0) * 0.8);
    const xp = Math.round(contribs * 46 + (u.rep || 0) * 0.9);
    return { userId: u.id, username: u.username, nick: u.nick, avatar: u.avatar, xp: xp, contribs: contribs, market: u.country, streak: int(0, 41), accepted: Math.round(contribs * 0.86), reversed: int(0, 3) };
  }).sort((a, b) => b.xp - a.xp).map((r, i) => Object.assign(r, { rank: i + 1, delta: int(-3, 4) }));
  gm.season.past = [
    { key: 'S1', name: 'Season 1 — Coverage', theme: 'One review on every shop profile', closed: seasonStart - 95 * DAY, hit: true, result: '14 of 14 shops reviewed' },
    { key: 'S2', name: 'Season 2 — Delivery', theme: 'Eight delivered orders per shop per market', closed: seasonStart - 3 * DAY, hit: false, result: '31 of 42 shop-market pairs reached the threshold' },
  ];

  /* ---------------- rewards: platform capability only ---------------- */
  gm.rewards = [
    { key: 'plus_30', name: '30 days of Comparo Plus', cost: 1200, kind: 'entitlement', grants: { plusDays: 30 }, desc: 'Full price history, unlimited alerts, ad-free, basket optimiser.', stock: null },
    { key: 'plus_7', name: '7 days of Comparo Plus', cost: 350, kind: 'entitlement', grants: { plusDays: 7 }, desc: 'Try the paid tier without paying for it.', stock: null },
    { key: 'alerts_20', name: '+20 permanent price alerts', cost: 600, kind: 'entitlement', grants: { alerts: 20 }, desc: 'Added to your allowance for good, on any tier.', stock: null },
    { key: 'instant_alerts', name: 'Instant alerts for 90 days', cost: 900, kind: 'entitlement', grants: { instantDays: 90 }, desc: 'Alerts fire on the price change instead of the next digest.', stock: null },
    { key: 'bounty_credit', name: '250 XP bounty credit', cost: 250, kind: 'credit', grants: { bounty: 250 }, desc: 'Stake it on an unanswered question that matters to you.', stock: null },
    { key: 'flair', name: 'Profile flair: season contributor', cost: 400, kind: 'cosmetic', grants: { flair: 'S3' }, desc: 'A season marker on your profile and posts. Cosmetic, carries no weight anywhere.', stock: null },
    { key: 'early_deals', name: 'Early deal access for 30 days', cost: 1500, kind: 'entitlement', grants: { earlyDealsDays: 30 }, desc: 'Community deals 30 minutes before they go public. Never applies to shop-submitted deals.', stock: 200 },
    { key: 'data_export', name: 'One full data export', cost: 800, kind: 'entitlement', grants: { exports: 1 }, desc: 'Your watchlist, alerts and contributions as CSV, plus the price series behind them.', stock: null },
    { key: 'group_create', name: 'Create a group', cost: 1000, kind: 'entitlement', grants: { groupSlots: 1 }, desc: 'Normally unlocked at Curator. This buys one slot early.', stock: 50 },
    { key: 'donate_tests', name: 'Fund one lab test', cost: 2500, kind: 'donation', grants: { labTest: 1 }, desc: 'Pooled into an independent assay of a product the community picks. Result published whatever it says.', stock: 12 },
  ];
  gm.rewardNote = 'Nothing here can be bought with money, and nothing here affects ranking, review weight or what a shop pays. Points buy platform capability; that is the whole catalogue.';

  /* ---------------- seeded ledger for the signed-in member ---------------- */
  gm.myLedger = [
    { key: 'review_verified', at: NOW - 2 * DAY, xp: 80, status: 'confirmed', note: 'Review of Whey Isolate 90 — click matched' },
    { key: 'price_report', at: NOW - 2 * DAY, xp: 25, status: 'confirmed', note: 'PeakSupps · Creatine Monohydrate 500 g — was €0.90 low' },
    { key: 'delivery_report', at: NOW - 4 * DAY, xp: 30, status: 'confirmed', note: 'ORD-1042 delivered day 3 of a 2–4 day promise' },
    { key: 'coupon_result', at: NOW - 5 * DAY, xp: 20, status: 'confirmed', note: 'IRON10 failed on discounted items at IronLab' },
    { key: 'label_photo', at: NOW - 6 * DAY, xp: 45, status: 'confirmed', note: 'Night Recovery Blend — dosing panel' },
    { key: 'price_report', at: NOW - 7 * DAY, xp: 25, status: 'reversed', note: 'Could not be reproduced — price had already been corrected', reversal: 'unreproducible' },
    { key: 'answer_accepted', at: NOW - 9 * DAY, xp: 70, status: 'confirmed', note: 'Accepted answer on "Which shops ship to Germany without surprises"' },
    { key: 'poll_answer', at: NOW - 9 * DAY, xp: 5, status: 'confirmed', note: 'Delivery window poll' },
    { key: 'promoted_chat', at: NOW - 12 * DAY, xp: 40, status: 'confirmed', note: 'Coupon stacking exchange promoted to a topic' },
  ];
  gm.myStreak = { days: 11, best: 34, freezesLeft: 1, lastAt: NOW - 8 * 3600000 };

  gm.integrity = [
    'Points are provisional for 48 hours. A contradicted report is reversed and shown as reversed on the profile.',
    'One account per person. Referral-shaped point flows between two accounts on one device are blocked, not deducted later.',
    'Contributions from accounts under seven days old are held for review before they earn.',
    'A member cannot earn from a report about a shop they are commercially connected to; declared connections block the source.',
    'Reversal rate above 25 % suspends the data sources for 30 days. Community sources stay open.',
    'Points never weight a review, never move ComparoRank, and are not visible to shops.',
  ];

  gm.stats = {
    sources: gm.xpSources.length,
    dailyCapXp: gm.xpSources.reduce((a, b) => a + b.xp * b.cap, 0),
    levels: gm.levels.length,
    quests: gm.quests.length,
    rewards: gm.rewards.length,
    seasonMembers: gm.season.leaderboard.filter((r) => r.contribs > 0).length,
    seasonXp: gm.season.leaderboard.reduce((a, b) => a + b.xp, 0),
  };
})();
