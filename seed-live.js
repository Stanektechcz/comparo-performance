/* Comparo Performance — live layer: real-time rooms, events, groups, polls, bounties.

   The forum answers questions that stay useful for a year. This layer is for the other half of
   what people actually do: ask something now, while they have the checkout open in the next
   tab. Every forum section gets a room, every market gets a room, and groups and scheduled
   events get their own.

   Three deliberate decisions:

   1. A room is attached to a section, not free-floating. #shipping chat sits under the Shipping
      section, shares its moderators, and its useful exchanges can be promoted into a topic —
      so the chat feeds the durable archive instead of competing with it.
   2. Price events are first-class messages. When an offer in a room's scope moves, the room
      says so, with the record attached. That is the reason to sit in a room at all, and it is
      information only a comparison engine can broadcast.
   3. Nothing in a room is sold. No sponsored messages, no paid pins. Advertising lives in
      declared placements; a room is not one of them.

   Loaded after seed-community.js (users, categories) and seed-geo.js (markets). */
(function () {
  const S = window.SEED;
  if (!S) return;
  const DAY = S.DAY, NOW = S.NOW;
  let seed = 0x6c1f3aa9;
  const R = () => { seed |= 0; seed = (seed + 0x6d2b79f5) | 0; let t = Math.imul(seed ^ (seed >>> 15), 1 | seed); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
  const int = (a, b) => Math.floor(R() * (b - a + 1)) + a;
  const pick = (a) => a[Math.floor(R() * a.length)];
  const chance = (p) => R() < p;
  const slug = S.helpers.slug;

  /* a seeded thread used the category 'price-history', which had no record — a topic whose
     category does not exist renders a breadcrumb to nowhere, so the category is declared */
  if (!S.forumCategories.some((c) => c.slug === 'price-history')) {
    S.forumCategories.splice(5, 0, { slug: 'price-history', name: 'Price history', desc: 'Price movement, fake discounts and when to wait.', icon: '⌃' });
  }

  const lv = {};
  S.lv = lv;
  lv.rules = [
    'One question per message. Long write-ups belong in a topic, where they stay findable.',
    'No affiliate or referral links. Comparo links are fine — they carry the same disclosure everywhere.',
    'Prices must name the shop and the market. "€28" without either cannot be checked by anyone.',
    'Shop staff must post under a verified shop account. Undisclosed shop staff is a permanent ban.',
    'Rooms are moderated by the same people who moderate the section, under the same policy.',
  ];

  /* ---------------- rooms ---------------- */
  const modPool = S.users.slice().sort((a, b) => b.rep - a.rep).slice(0, 6).map((u) => u.username);
  lv.rooms = [];
  S.forumCategories.forEach((c, i) => {
    lv.rooms.push({
      key: 'sec:' + c.slug, kind: 'section', scope: c.slug,
      name: c.name, sub: 'Live room for the ' + c.name + ' section',
      href: '#/forum/' + c.slug, icon: c.icon,
      slow: c.slug === 'deals' ? 10 : c.slug === 'beginner-questions' ? 0 : 5,
      mods: [modPool[i % modPool.length], modPool[(i + 3) % modPool.length]],
      baseOnline: [34, 52, 18, 41, 96, 29, 23, 31, 14, 9, 17, 46, 21, 12, 8][i % 15] || 12,
      priceFeed: ['deals', 'price-history', 'products', 'shops', 'comparisons'].indexOf(c.slug) >= 0,
    });
  });
  ['DE', 'CZ', 'PL', 'FR', 'IT', 'ES', 'GB', 'SE', 'NL', 'AT'].forEach((iso, i) => {
    const co = S.countries.find((c) => c.iso === iso);
    if (!co) return;
    lv.rooms.push({
      key: 'mkt:' + iso, kind: 'market', scope: iso,
      name: co.name, sub: 'Shops, delivery and prices in ' + co.name,
      href: '#/countries/' + slug(co.name), icon: iso,
      slow: 5, mods: [modPool[(i + 1) % modPool.length]],
      baseOnline: [61, 44, 27, 33, 25, 22, 38, 15, 19, 21][i] || 10,
      priceFeed: true,
    });
  });

  /* ---------------- groups (member-run, moderated, never commercial) ---------------- */
  const groupDefs = [
    ['Powerlifting Central Europe', 'powerlifting-ce', 'Strength training, heavy stacks and what actually shows up in a total.', 'CZ', 2140, ['creatine', 'protein']],
    ['Plant-based performance', 'plant-based', 'Vegan protein, iron, B12 and getting the amino profile right without dairy.', 'DE', 3380, ['vegan', 'protein']],
    ['Endurance & hydration', 'endurance', 'Marathon, trail and cycling: carbohydrate, sodium and gut tolerance.', 'FR', 1870, ['endurance', 'electrolytes']],
    ['Label readers', 'label-readers', 'People who photograph labels and check the numbers. Dosing, carriers, proprietary blends.', 'NL', 1290, ['labels']],
    ['Budget stacks', 'budget-stacks', 'Maximum result per euro. Price per gram of active, nothing else.', 'PL', 4260, ['price-history', 'deal']],
    ['Women in strength', 'women-strength', 'Dosing by bodyweight, iron and cycle-aware supplementation.', 'ES', 1640, ['beginner']],
    ['Masters 40+', 'masters-40', 'Joints, recovery and sleep when training past forty.', 'GB', 980, ['recovery']],
    ['Shop watch', 'shop-watch', 'Delivery reports, support experiences and feed errors, market by market.', 'DE', 2510, ['shop-review', 'shipping']],
  ];
  lv.groups = groupDefs.map((g, i) => ({
    id: i + 1, name: g[0], slug: g[1], desc: g[2], market: g[3], members: g[4], tags: g[5],
    created: NOW - int(120, 900) * DAY,
    visibility: i === 6 ? 'members' : 'open',
    plusOnly: i === 3,
    owner: S.users[(i * 3) % S.users.length].username,
    mods: [S.users[(i * 3) % S.users.length].username, S.users[(i * 5 + 2) % S.users.length].username],
    postsWeek: int(9, 140), roomKey: 'grp:' + g[1],
    contributions: int(40, 620),
  }));
  lv.groups.forEach((g) => {
    lv.rooms.push({
      key: g.roomKey, kind: 'group', scope: g.slug, name: g.name, sub: g.desc,
      href: '#/groups/' + g.slug, icon: '◍', slow: 5, mods: g.mods,
      baseOnline: Math.max(3, Math.round(g.members / 90)), priceFeed: false, plusOnly: g.plusOnly,
    });
  });

  /* ---------------- scheduled live sessions ---------------- */
  const evDefs = [
    ['Ask the lab: how a batch certificate is produced', 'lab-batch-certificate', 'expert', 6, 'A contract lab QA manager answers questions on what a certificate of analysis does and does not prove.', 'Dr. H. Vogel', 'QA manager, accredited testing lab', 1840],
    ['IRONFORGE opens its dosing decisions', 'ironforge-dosing', 'brand', 30, 'Why the pre-workout is dosed at 3.2 g beta-alanine and not 6 g, and what the label does not have to say.', 'IRONFORGE formulation team', 'Brand session — questions are not screened', 920],
    ['Delivery clinic: why your parcel was late', 'delivery-clinic', 'staff', -2, 'Comparo delivery data team walks through the measured medians and takes claims questions.', 'Comparo delivery team', 'Recorded — transcript published', 2260],
    ['Beginner hour: build a first stack in 40 minutes', 'beginner-hour', 'community', 3, 'Four senior members take beginner questions live. No product recommendations from staff.', 'Community moderators', 'Recurring, every second Tuesday', 3140],
    ['Price-history deep dive: reading a fake discount', 'fake-discount-clinic', 'staff', 12, 'How the fake-discount detector works, what it flags, and where it is still wrong.', 'Comparo price intelligence', 'Live Q&A after a 15-minute walkthrough', 1420],
  ];
  lv.events = evDefs.map((e, i) => {
    const startsAt = NOW + e[3] * DAY + int(-4, 6) * 3600000;
    return {
      id: i + 1, title: e[0], slug: e[1], kind: e[2], startsAt: startsAt,
      endsAt: startsAt + 90 * 60000, desc: e[4], host: e[5], hostRole: e[6],
      rsvp: e[7], roomKey: 'evt:' + e[1],
      past: startsAt < NOW, transcript: startsAt < NOW,
      questions: int(14, 120), market: pick(['DE', 'CZ', 'FR', 'GB', null]),
    };
  });
  lv.events.forEach((e) => {
    lv.rooms.push({
      key: e.roomKey, kind: 'event', scope: e.slug, name: e.title, sub: e.host + ' · ' + e.hostRole,
      href: '#/events/' + e.slug, icon: '●', slow: e.kind === 'brand' ? 15 : 8,
      mods: [modPool[0], modPool[1]], baseOnline: e.past ? 0 : Math.round(e.rsvp / 40),
      priceFeed: false, eventId: e.id,
    });
  });

  /* ---------------- verified experts ---------------- */
  lv.experts = [
    { username: S.users[2].username, nick: S.users[2].nick, field: 'Sports nutrition', credential: 'MSc Sports Nutrition, registered dietitian', verifiedBy: 'Registry number checked against the national register', since: NOW - 300 * DAY, answers: 214 },
    { username: S.users[5].username, nick: S.users[5].nick, field: 'Analytical chemistry', credential: 'Lab technician, accredited testing laboratory', verifiedBy: 'Employer letter and accreditation number', since: NOW - 180 * DAY, answers: 88 },
    { username: S.users[8].username, nick: S.users[8].nick, field: 'Strength coaching', credential: 'Level 3 strength & conditioning coach', verifiedBy: 'Certification body lookup', since: NOW - 420 * DAY, answers: 331 },
    { username: S.users[11].username, nick: S.users[11].nick, field: 'Customs & logistics', credential: 'Customs declarant, 9 years', verifiedBy: 'Licence number checked', since: NOW - 95 * DAY, answers: 47 },
  ];
  lv.expertNote = 'An expert flag names a checked credential and the field it covers. It is never for sale, it does not weight a review, and it can be withdrawn in one click by moderation.';

  /* ---------------- polls: structured collection, not opinion theatre ---------------- */
  const pollDefs = [
    ['Which delivery window would make you switch shops?', ['Next day', '2 days', '3–4 days', 'I only look at price'], 'shipping', [1840, 2610, 980, 1420]],
    ['Do you check price history before buying?', ['Always', 'For expensive items', 'Rarely', 'Never knew it existed'], 'price-history', [3120, 2280, 640, 410]],
    ['What stops you leaving a review?', ['Nothing, I do', 'Too much effort', 'Nobody reads them', 'Privacy'], 'general', [1290, 2640, 890, 1130]],
    ['Which label detail do you actually read?', ['Amount per serving', 'Ingredient order', 'Sweeteners', 'Certifications'], 'products', [3860, 1240, 1610, 730]],
    ['Has a coupon ever failed at a shop checkout?', ['Yes, in the last month', 'Yes, but a while ago', 'Never', 'I never use coupons'], 'deals', [2140, 1880, 960, 1310]],
    ['Would you pay for a delivery guarantee?', ['Yes, per order', 'Only if the shop pays', 'No', 'Depends on the amount'], 'shipping', [620, 3480, 1290, 1740]],
  ];
  lv.polls = pollDefs.map((p, i) => ({
    id: i + 1, question: p[0], options: p[1], section: p[2], votes: p[3],
    created: NOW - int(4, 90) * DAY, closes: NOW + int(2, 40) * DAY,
    total: p[3].reduce((a, b) => a + b, 0),
    /* a poll is a data product: the breakdown is what makes it worth answering */
    byMarket: ['DE', 'CZ', 'PL', 'FR', 'GB'].map((iso) => ({ iso: iso, share: p[3].map((v) => Math.max(1, Math.round(v * (0.6 + R() * 0.8)))) })),
  }));

  /* ---------------- bounties: XP staked on an unanswered question ---------------- */
  const open = S.forumThreads.filter((t) => t.kind === 'question' && !t.acceptedReplyId);
  lv.bounties = open.slice(0, 6).map((t, i) => ({
    id: i + 1, threadId: t.id, slug: t.slug, title: t.title,
    stake: [120, 80, 250, 60, 150, 100][i] || 80,
    backers: int(1, 7), opened: NOW - int(1, 20) * DAY,
    expires: NOW + int(2, 18) * DAY, section: t.category,
  }));

  /* ---------------- seeded message history ---------------- */
  const chatPools = {
    deals: [
      'Anyone else seeing the PeakSupps code fail on already-discounted items?',
      'Code worked for me on the 1 kg pack, failed on the 2.5 kg. Same basket.',
      'Threshold trick: adding a €4 shaker took me over free delivery and saved €6.',
      'That "45 % off" is against an RRP nobody charges. Real drop is about 12 %.',
      'Just to be clear, is that price with VAT for DE? Otherwise it is not comparable.',
      'Checked the history chart before buying — it was cheaper three weeks ago, waiting.',
      'Careful, that deal expires tonight and the countdown on the card is accurate.',
    ],
    shipping: [
      'Ordered Tuesday 15:40, dispatched same day. Cutoff is real at that shop.',
      'Anyone shipped to Norway recently? Trying to work out the handling fee before I order.',
      'CH orders: VAT is charged at import on everything, there is no de minimis anymore.',
      'PostNord was three days late twice in a row for me, PPL has been flawless.',
      'The promised window on the offer row is what the shop commits to, not an estimate?',
      'Yes — if it slips you can open a claim, that is the whole point of the promise badge.',
      'Pickup point delivery was two days faster than home delivery from the same shop.',
    ],
    products: [
      'Is the 3.2 g beta-alanine in that pre-workout per serving or per two scoops?',
      'Per serving, it is on the dosing tab. Two scoops is 6.4 g, which is above the study dose.',
      'Carrier is listed at 2 g of the 30 g scoop, so the declared actives are 26 g.',
      'Anyone compared the clear whey per 100 g of protein instead of per serving?',
      'Did that yesterday: clear whey came out 38 % more expensive per 100 g protein.',
      'Proprietary blend means you cannot do that maths at all, which is rather the point.',
      'Melatonin is prescription-only in my market so the product page blocks it. Correct behaviour.',
    ],
    shops: [
      'Return from NorthLift took nine days to refund, but they did pay the return postage.',
      'Support answered in under an hour on a weekday. Weekend query took until Tuesday.',
      'Their feed says in stock but the shop page says backorder. Reported the offer.',
      'Reported feed errors get fixed fast, I have had two corrected within a day.',
      'Delivery median on their shop page matches what I actually experienced, which is reassuring.',
    ],
    'price-history': [
      'Monohydrate has drifted down about 14 % across nine shops this year.',
      'That is raw material pricing, not competition. Bulk creatine fell all spring.',
      'Fake discount flag on that listing is correct — the "before" price lasted four days.',
      'Price per gram of active is the only comparison that survives a pack size change.',
      'Alert fired eleven minutes after the drop, which is faster than the shop newsletter.',
    ],
    comparisons: [
      'Two products, same molecule, 10× difference per gram. That is the whole category summarised.',
      'If you compare per serving you will pick the wrong one nine times out of ten.',
      'Put both in the compare tray, the dosing row does the work for you.',
      'The cheaper one has a smaller scoop. Cost per serving hides it, cost per gram does not.',
    ],
    general: [
      'Does the best-value badge have anything to do with who pays you?',
      'No — sponsored slots are labelled and sit outside the ranking. There is a page about it.',
      'Anything a shop pays for is listed on the transparency page, including what it cannot buy.',
      'Nice to see the delivery numbers suppressed when there are not enough orders.',
    ],
    default: [
      'Asking here because the topic form felt too heavy for one question.',
      'Someone promoted a chat exchange into a topic last week and it answered mine.',
      'Is there a room for my market? Found it under the market list, thanks.',
      'Moderators are the same as the section, which explains why it is calm in here.',
    ],
  };
  const marketPool = (iso) => [
    'Which shop here actually delivers next day in ' + iso + '?',
    'VAT shown on the offer rows is the ' + iso + ' rate, so the totals are comparable.',
    'Anyone used a pickup point in ' + iso + '? Cheaper and faster for me.',
    'Two domestic shops added delivery promise in ' + iso + ' this month.',
    'Cross-border from the neighbouring market was €3 cheaper including shipping.',
  ];

  lv.messages = [];
  let mid = 1;
  const usersPool = S.users.slice();
  lv.rooms.forEach((room) => {
    const pool = room.kind === 'market' ? marketPool(room.scope)
      : (chatPools[room.scope] || chatPools.default);
    const n = room.kind === 'event' ? (room.baseOnline ? int(3, 6) : int(6, 10)) : int(5, 9);
    let t = NOW - int(30, 300) * 60000;
    for (let i = 0; i < n; i++) {
      const u = usersPool[(mid * 7 + i * 3) % usersPool.length];
      t -= int(2, 40) * 60000;
      lv.messages.push({
        id: 'lm' + mid++, room: room.key, userId: u.id, body: pool[i % pool.length],
        ts: t, kind: 'msg', pinned: false,
        reply: i > 1 && chance(0.3) ? 'lm' + (mid - 2) : null,
      });
    }
    /* price events: the reason a comparison engine is worth sitting in */
    if (room.priceFeed) {
      const k = int(1, 2);
      for (let j = 0; j < k; j++) {
        const o = S.offers[(mid * 13 + j * 29) % S.offers.length];
        const p = S.products.find((x) => x.id === o.productId);
        const m = S.merchants.find((x) => x.id === o.merchantId);
        if (!p || !m) continue;
        const drop = int(4, 19);
        lv.messages.push({
          id: 'lm' + mid++, room: room.key, userId: null, kind: 'price',
          body: p.name + ' fell ' + drop + ' % at ' + m.name,
          ts: NOW - int(4, 180) * 60000, pinned: false,
          ref: { kind: 'product', slug: p.slug, name: p.name, merchant: m.name, merchantSlug: m.slug, drop: drop, price: o.price },
        });
      }
    }
    /* one pinned house message per room, written by moderation */
    lv.messages.push({
      id: 'lm' + mid++, room: room.key, userId: null, kind: 'system', pinned: true,
      body: room.kind === 'event' ? 'Questions are collected here and answered live. Upvote instead of reposting.'
        : room.kind === 'group' ? 'Group room. Section rules apply, plus whatever the owners added on the group page.'
        : room.kind === 'market' ? 'Post prices with the shop name and the market. Anything else cannot be checked.'
        : 'Useful exchanges get promoted into a topic. If you want an answer to last, write it there.',
      ts: NOW - int(5, 40) * DAY,
    });
  });
  lv.messages.sort((a, b) => a.ts - b.ts);

  /* ---------------- ambient script: what happens while you watch ---------------- */
  /* The engine drips these into the open room on a timer so a single reader sees a room that
     moves. Each line is attributed to a seeded member and is the kind of message the room
     already contains — no invented facts, no prices that contradict an offer record. */
  lv.script = {};
  lv.rooms.forEach((room) => {
    const pool = room.kind === 'market' ? marketPool(room.scope) : (chatPools[room.scope] || chatPools.default);
    const extra = [
      'Just ordered, will report the delivery when it lands.',
      'Reported that offer, the price on the shop page is €1.40 higher.',
      'Promoted this to a topic so it does not scroll away.',
      'Thanks — that answered it, buying the bigger pack.',
      'Anyone in ' + (room.kind === 'market' ? room.scope : 'DE') + ' seen a faster shop for this?',
      'Checked the history chart: it is at a 90-day low right now.',
    ];
    lv.script[room.key] = pool.concat(extra).map((body, i) => ({
      body: body,
      userId: usersPool[(i * 5 + room.key.length) % usersPool.length].id,
      after: 9000 + i * 7000 + Math.round(R() * 6000),
    }));
  });

  /* ---------------- promoted exchanges: chat feeding the archive ---------------- */
  lv.promotions = lv.rooms.filter((r) => r.kind === 'section').slice(0, 5).map((r, i) => ({
    room: r.key, roomName: r.name,
    title: ['Coupon stacking: which shops still allow it', 'Carrier reliability in winter, by market',
      'Reading a label when the blend is proprietary', 'Nine-day refund at NorthLift, documented',
      'Creatine raw material pricing, explained by a buyer'][i],
    slug: ['coupon-stacking-shops', 'carrier-reliability-winter', 'reading-proprietary-blends', 'northlift-refund-timeline', 'creatine-raw-pricing'][i],
    messages: int(6, 22), promotedBy: modPool[i % modPool.length], at: NOW - int(2, 40) * DAY,
    views: int(400, 9000),
  }));

  lv.stats = {
    rooms: lv.rooms.length,
    sectionRooms: lv.rooms.filter((r) => r.kind === 'section').length,
    marketRooms: lv.rooms.filter((r) => r.kind === 'market').length,
    groups: lv.groups.length,
    groupMembers: lv.groups.reduce((a, b) => a + b.members, 0),
    events: lv.events.length,
    polls: lv.polls.length,
    pollVotes: lv.polls.reduce((a, b) => a + b.total, 0),
    bounties: lv.bounties.length,
    bountyXp: lv.bounties.reduce((a, b) => a + b.stake, 0),
    promoted: lv.promotions.length,
  };
})();
