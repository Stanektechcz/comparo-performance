/* Orders — the entity the prototype was missing.

   Verification, delivery reliability, returns and disputes all used to be inferred from
   redirects and receipts, or seeded as a bare percentage. An order is the single record all
   four hang off: it names who bought what, from whom, for how much, when it was dispatched
   and when it arrived. Delivery figures are measured from these rows, not stated.

   Construction rule: every review already marked as a verified purchase gets the order it
   implies, so a "verified purchase" badge is always backed by a record that can be opened.
   Additional orders exist without reviews, because most purchases are never reviewed. */
(function () {
  const S = window.SEED;
  if (!S) return;
  const DAY = S.DAY, NOW = S.NOW;

  let seed = 0x9e3779b9;
  const R = () => { seed |= 0; seed = (seed + 0x6d2b79f5) | 0; let t = Math.imul(seed ^ (seed >>> 15), 1 | seed); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
  const int = (a, b) => Math.floor(R() * (b - a + 1)) + a;
  const pick = (a) => a[Math.floor(R() * a.length)];
  const chance = (p) => R() < p;
  const r2 = (n) => Math.round(n * 100) / 100;

  const M = (id) => S.merchants.find((m) => m.id === id);
  const P = (id) => S.products.find((p) => p.id === id);
  const U = (id) => S.users.find((u) => u.id === id);
  const carriers = ['DHL', 'DPD', 'GLS', 'UPS', 'PPL', 'Packeta', 'PostNL', 'FedEx'];

  const returnReasons = [
    'Changed mind within the withdrawal period',
    'Wrong flavour dispatched',
    'Damaged in transit — tub seal broken',
    'Duplicate order placed by mistake',
    'Arrived after the date promised at checkout',
  ];
  const disputeReasons = [
    'Charged shipping the checkout said was free',
    'Marked delivered, never arrived',
    'Refund not issued after return was received',
  ];

  const orders = [];
  let n = 0;

  function make(userId, merchantId, productId, placedAt, reviewId) {
    const m = M(merchantId), p = P(productId), u = U(userId);
    if (!m || !p || !u) return null;
    /* the market is where the buyer is, if the shop ships there — otherwise this order
       could not have happened and we place it in the shop's home market */
    const iso = (m.shipsTo || []).indexOf(u.country) >= 0 ? u.country : m.country;
    const zone = (m.zones || {})[iso] || { cost: 4.9, days: [2, 5] };
    const offer = S.offers.find((o) => o.productId === productId && o.merchantId === merchantId);
    const qty = chance(0.18) ? 2 : 1;
    const unit = r2(offer ? offer.price : p.base);
    const itemTotal = r2(unit * qty);
    const freeOver = m.freeOverEur || 999;
    const shipping = itemTotal >= freeOver ? 0 : r2(zone.cost);
    /* The promise a shop makes at checkout covers dispatch AND transit — the zone's upper
       transit bound plus the day it takes to hand the parcel over. Comparing an order-to-door
       actual against a transit-only promise was making every shop look late. */
    const promisedMax = zone.days[1] + 1;

    /* dispatch and transit are measured, not assumed: a slow shop is slow here in the data */
    const dispatchH = m.verified ? int(2, 18) : int(8, 58);
    /* a real carrier network has a thin tail of genuine failures — a depot hold, a missed
       attempt, a parcel that sits over a weekend. Roughly one order in thirty. Without it the
       data has no late deliveries at all, which would make a delivery guarantee meaningless
       and a p90 suspiciously tidy. */
    const disruption = chance(0.034) ? int(2, 6) : 0;
    const transit = int(zone.days[0], zone.days[1] + (m.verified ? 0 : 2)) + disruption;
    const shippedAt = placedAt + dispatchH * 3600000;
    const deliveredAt = shippedAt + transit * DAY;

    let status = 'delivered';
    if (deliveredAt > NOW) status = shippedAt > NOW ? 'placed' : 'in_transit';
    else if (chance(0.055)) status = 'returned';
    else if (chance(0.02)) status = 'disputed';

    n++;
    const o = {
      id: 'ORD-' + (10000 + n),
      userId, merchantId, productId,
      offerId: offer ? offer.id : null,
      market: iso,
      qty,
      unitPrice: unit,
      itemTotal,
      shipping,
      total: r2(itemTotal + shipping),
      currency: 'EUR',
      placedAt,
      shippedAt: status === 'placed' ? null : shippedAt,
      deliveredAt: status === 'delivered' || status === 'returned' || status === 'disputed' ? deliveredAt : null,
      promisedDays: promisedMax,
      actualDays: status === 'delivered' || status === 'returned' || status === 'disputed' ? r2((deliveredAt - placedAt) / DAY) : null,
      status,
      carrier: pick((m.carriers && m.carriers.length ? m.carriers : carriers)),
      tracking: (m.slug || 'shop').slice(0, 3).toUpperCase() + String(700000 + n * 37).slice(0, 6) + iso,
      /* where the purchase came from decides what it can prove: a click we logged is
         evidence we hold ourselves, a direct purchase is only the buyer's word */
      source: reviewId ? (chance(0.62) ? 'comparo_click' : 'direct') : (chance(0.34) ? 'comparo_click' : 'direct'),
      reviewId: reviewId || null,
      returnedAt: null, returnReason: null, disputeReason: null, refund: null,
    };
    if (o.source === 'comparo_click') o.clickId = 'CLK-' + (500000 + n * 17);
    if (status === 'returned') {
      o.returnedAt = deliveredAt + int(1, 12) * DAY;
      o.returnReason = pick(returnReasons);
      o.refund = r2(o.total - (chance(0.4) ? o.shipping : 0));
    }
    if (status === 'disputed') {
      o.disputeReason = pick(disputeReasons);
    }
    orders.push(o);
    return o;
  }

  /* 1. every verified-purchase review becomes an order placed before the review was written */
  S.reviews.forEach((r) => {
    if (!r.verifiedPurchase) return;
    const productId = r.type === 'product' ? r.targetId : null;
    const merchantId = r.type === 'merchant' ? r.targetId : r.merchantId;
    if (!merchantId) return;
    const pid = productId || (S.offers.filter((o) => o.merchantId === merchantId)[0] || {}).productId;
    if (!pid) return;
    const placed = r.date - int(9, 40) * DAY;
    const o = make(r.userId, merchantId, pid, placed, r.id);
    /* the review's own verification method must describe the order that backs it */
    if (o) r.verifyMethod = o.source === 'comparo_click' ? 'affiliate_conversion' : 'order_reference';
  });

  /* 2. purchases nobody reviewed — most of them. Delivery reliability is measured over these
        as well, which is why a shop's measured figure differs from its review sample. */
  S.merchants.forEach((m) => {
    const os = S.offers.filter((o) => o.merchantId === m.id);
    if (!os.length) return;
    const extra = Math.round(9 + (m.reviews || 0) / 200);
    /* domestic e-commerce dominates: most of a shop's orders are in its own market, which is
       also why a home market reaches a publishable sample long before a cross-border one */
    const homeUsers = S.users.filter((u) => u.country === m.country);
    for (let i = 0; i < extra; i++) {
      const buyer = homeUsers.length && chance(0.58) ? pick(homeUsers) : pick(S.users);
      make(buyer.id, m.id, pick(os).productId, NOW - int(2, 300) * DAY, null);
    }
  });

  orders.sort((a, b) => b.placedAt - a.placedAt);
  S.orders = orders;
  S.orderMeta = {
    note: 'Delivery, return and dispute rates on shop pages are medians and shares over these rows for the selected market. A shop with fewer than 8 delivered orders in a market shows no measured figure at all rather than a figure built on three orders.',
    minSample: 8,
  };
})();
