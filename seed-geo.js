/* Comparo Performance — delivery geography.

   Twelve markets became twenty-seven. The point is not a longer country list: a market only
   exists here if a shop can actually be reached from it, so every new country arrives with
   the lanes that serve it (carrier, transit band, duty treatment) and with the shops whose
   shipping zones were extended to cover it.

   Two rules this file is built on, both of them commercial positions:

   1. We do not sell. A market is a *delivery destination for somebody else's checkout*, so a
      country record holds VAT for display honesty, the legal adult age for restricted
      products, and whether the destination is inside the EU customs union — never a price.
   2. Organic visibility is never sold per market. Market packs (below) unlock merchant-side
      capability — localised profile, market analytics, market campaigns, delivery promise —
      and never the right to be listed or ranked. A shop that pays nothing is still compared
      in all twenty-seven.

   Loaded after seed-community.js so the merchants added there get lanes too, and before
   seed-orders.js so orders are placed in the full market set. */
(function () {
  const S = window.SEED;
  if (!S) return;
  let seed = 0x3f1a7c5d;
  const R = () => { seed |= 0; seed = (seed + 0x6d2b79f5) | 0; let t = Math.imul(seed ^ (seed >>> 15), 1 | seed); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
  const int = (a, b) => Math.floor(R() * (b - a + 1)) + a;
  const pick = (a) => a[Math.floor(R() * a.length)];
  const r1 = (n) => Math.round(n * 10) / 10;

  /* ---------------- currencies for the new markets ---------------- */
  [['DKK', 'kr', 7.46, 'da-DK'], ['NOK', 'kr', 11.62, 'nb-NO'], ['CHF', 'CHF', 0.94, 'de-CH'],
   ['HUF', 'Ft', 395.0, 'hu-HU'], ['RON', 'lei', 4.97, 'ro-RO'], ['BGN', 'лв', 1.96, 'bg-BG']]
    .forEach((c) => { if (!S.currencies.some((x) => x.code === c[0])) S.currencies.push({ code: c[0], symbol: c[1], rate: c[2], locale: c[3] }); });

  /* ---------------- regions: what makes two markets neighbours ---------------- */
  S.regions = [
    { key: 'DACH', name: 'DACH', markets: ['DE', 'AT', 'CH'], near: ['CE', 'WEST'] },
    { key: 'CE', name: 'Central Europe', markets: ['CZ', 'SK', 'PL', 'HU', 'SI'], near: ['DACH', 'SEE', 'BALT'] },
    { key: 'WEST', name: 'Western Europe', markets: ['FR', 'BE', 'NL', 'IE', 'GB'], near: ['DACH', 'SOUTH', 'NORD'] },
    { key: 'SOUTH', name: 'Southern Europe', markets: ['IT', 'ES', 'PT', 'GR', 'HR'], near: ['WEST', 'SEE'] },
    { key: 'NORD', name: 'Nordics', markets: ['SE', 'DK', 'FI', 'NO'], near: ['WEST', 'BALT'] },
    { key: 'BALT', name: 'Baltics', markets: ['EE', 'LT'], near: ['NORD', 'CE'] },
    { key: 'SEE', name: 'South-East Europe', markets: ['RO', 'BG'], near: ['CE', 'SOUTH'] },
    { key: 'AMER', name: 'North America', markets: ['US'], near: [] },
  ];
  const regionOf = (iso) => (S.regions.find((r) => r.markets.indexOf(iso) >= 0) || { key: '?', near: [] });
  /* 1 = same region, 2 = adjacent region, 3 = elsewhere */
  const band = (a, b) => {
    if (a === b) return 0;
    const ra = regionOf(a), rb = regionOf(b);
    if (ra.key === rb.key) return 1;
    if ((ra.near || []).indexOf(rb.key) >= 0) return 2;
    return 3;
  };
  S.helpers.shipBand = band;
  S.helpers.regionOf = (iso) => regionOf(iso).name;

  /* ---------------- fifteen new delivery markets ---------------- */
  const newCountries = [
    ['BE', 'Belgium', 'EUR', 'nl', 21], ['PT', 'Portugal', 'EUR', 'pt', 23], ['IE', 'Ireland', 'EUR', 'en', 23],
    ['DK', 'Denmark', 'DKK', 'da', 25], ['FI', 'Finland', 'EUR', 'fi', 25.5], ['NO', 'Norway', 'NOK', 'nb', 25],
    ['CH', 'Switzerland', 'CHF', 'de', 8.1], ['HU', 'Hungary', 'HUF', 'hu', 27], ['RO', 'Romania', 'RON', 'ro', 19],
    ['BG', 'Bulgaria', 'BGN', 'bg', 20], ['GR', 'Greece', 'EUR', 'el', 24], ['SI', 'Slovenia', 'EUR', 'sl', 22],
    ['HR', 'Croatia', 'EUR', 'hr', 25], ['LT', 'Lithuania', 'EUR', 'lt', 21], ['EE', 'Estonia', 'EUR', 'et', 22],
  ];
  const NON_EU = ['GB', 'US', 'CH', 'NO'];
  newCountries.forEach((c) => {
    if (S.countries.some((x) => x.iso === c[0])) return;
    S.countries.push({ iso: c[0], name: c[1], currency: c[2], lang: c[3], vat: c[4], age: 18 });
  });
  /* customs treatment is a property of the destination, so it lives on the country, not in copy */
  const CUSTOMS = {
    CH: { vatAtImport: true, threshold: 0, handling: 16, note: 'Swiss VAT and a carrier handling fee are charged at import on every parcel.' },
    NO: { vatAtImport: true, threshold: 0, handling: 14, note: 'Norwegian VAT is collected at import; most carriers add a customs handling fee.' },
    GB: { vatAtImport: true, threshold: 0, handling: 12, note: 'Import VAT applies. Shops registered for UK VAT collect it at checkout, so the total shown is what you pay.' },
    US: { vatAtImport: false, threshold: 800, handling: 0, note: 'No duty below USD 800 per shipment; state sales tax is added by the seller at checkout.' },
  };
  S.countries.forEach((c) => {
    c.eu = NON_EU.indexOf(c.iso) < 0;
    c.region = regionOf(c.iso).name;
    c.customs = c.eu ? null : (CUSTOMS[c.iso] || null);
    /* localisation is deliberately narrower than delivery: we ship to 27, we translate 10 */
    c.localised = ['DE', 'AT', 'CZ', 'SK', 'PL', 'FR', 'IT', 'ES', 'NL', 'SE', 'GB', 'US'].indexOf(c.iso) >= 0;
  });

  /* ---------------- extend shipping zones onto the new markets ---------------- */
  const carrierByRegion = {
    DACH: ['DHL', 'DPD', 'GLS'], CE: ['PPL', 'Packeta', 'GLS', 'DPD'], WEST: ['DPD', 'PostNL', 'UPS', 'GLS'],
    SOUTH: ['BRT', 'Correos', 'GLS', 'DHL'], NORD: ['PostNord', 'DHL', 'Bring'], BALT: ['Omniva', 'DPD', 'Venipak'],
    SEE: ['Cargus', 'Speedy', 'DPD'], AMER: ['FedEx', 'UPS'],
  };
  const bandDays = { 1: [2, 4], 2: [3, 6], 3: [4, 9] };
  const bandMult = { 1: 1.25, 2: 1.7, 3: 2.3 };
  S.shipLanes = [];
  S.merchants.forEach((m) => {
    const home = m.country;
    const baseCost = (m.zones[home] ? m.zones[home].cost / 0.7 : 5.2);
    /* how far a shop is willing to ship is a function of how developed it is: verified partners
       reach further than a shop that has only just been imported */
    const reach = m.partner ? 3 : m.tier === 'PRO' || m.tier === 'GROWTH' ? 2 : 1;
    const candidates = S.countries.map((c) => c.iso).filter((iso) => m.shipsTo.indexOf(iso) < 0);
    candidates.forEach((iso) => {
      const b = band(home, iso);
      if (b > reach) return;
      if (NON_EU.indexOf(iso) >= 0 && !m.partner) return;
      if (b === 3 && R() < 0.45) return;
      const days = bandDays[b];
      m.shipsTo.push(iso);
      m.zones[iso] = {
        cost: r1(baseCost * bandMult[b] * (0.85 + R() * 0.4)),
        days: [days[0] + int(0, 1), days[1] + int(0, 2)],
      };
    });
    /* one lane record per served market: this is what a delivery estimate reads */
    m.shipsTo.forEach((iso) => {
      const z = m.zones[iso], b = band(home, iso), reg = regionOf(iso).key;
      const co = S.countries.find((c) => c.iso === iso) || {};
      z.carrier = pick(carrierByRegion[reg] || ['DHL']);
      z.duty = !co.eu;
      S.shipLanes.push({
        merchantId: m.id, from: home, to: iso, band: b, carrier: z.carrier,
        cost: z.cost, days: z.days, duty: !co.eu,
        cutoff: b === 0 ? '17:00' : b === 1 ? '16:00' : '14:00',
        tracked: true, pickup: b <= 1 && R() < 0.7, cod: b === 0 && R() < 0.5,
      });
    });
    m.marketCount = m.shipsTo.length;
  });

  /* ---------------- market packs (merchant-side capability, never visibility) ---------------- */
  S.marketPacks = S.regions.filter((r) => r.key !== 'AMER').map((r, i) => ({
    key: r.key, name: r.name, markets: r.markets,
    price: [59, 49, 69, 59, 49, 29, 29][i] || 39,
    includes: ['Localised shop profile per market', 'Delivery promise eligibility per market', 'Market-level analytics and price position', 'Campaign booking in that market'],
  })).concat([{ key: 'AMER', name: 'North America', markets: ['US'], price: 79, includes: ['Localised shop profile', 'Delivery promise eligibility', 'Market-level analytics', 'Campaign booking'] }]);
  S.geoNote = 'Organic listing and ComparoRank position are free in all ' + S.countries.length + ' markets. A market pack buys merchant-side capability, never placement.';

  /* ---------------- compliance baseline for the new markets ---------------- */
  const byName = (n) => { const p = S.products.find((x) => x.name === n); return p ? p.id : null; };
  let crid = (S.complianceRules.reduce((a, b) => Math.max(a, b.id), 0) || 0) + 1;
  const addRule = (pid, c, status, reason, source) => {
    if (!pid || S.complianceRules.some((r) => r.productId === pid && r.country === c)) return;
    S.complianceRules.push({ id: crid++, productId: pid, country: c, status: status, reason: reason, source: source, reviewedBy: status === 'unknown' ? null : 'compliance@comparo', reviewedAt: status === 'unknown' ? null : S.NOW - int(3, 160) * S.DAY });
  };
  const mel = byName('Melatonin Sleep 1 mg'), yoh = byName('Thermo Cut Yohimbine'), pre = byName('Pre-Burn Extreme'), ash = byName('Ashwagandha KSM-66');
  ['HU', 'RO', 'BG', 'SI', 'HR', 'GR', 'PT', 'BE'].forEach((c) => addRule(mel, c, 'prescription_only', 'Melatonin above the food supplement threshold is classified as a medicinal product in this market.', 'National medicines agency'));
  ['DK', 'FI', 'NO', 'IE', 'EE', 'LT'].forEach((c) => addRule(mel, c, 'restricted', 'Permitted as a supplement up to 1 mg per serving with mandatory labelling.', 'National food authority guidance'));
  ['BE', 'DK', 'NO', 'CH'].forEach((c) => addRule(yoh, c, 'not_allowed', 'Yohimbine is not permitted in food supplements.', 'National food safety authority decision'));
  ['PT', 'GR', 'HR', 'RO', 'BG', 'HU', 'SI'].forEach((c) => addRule(yoh, c, 'restricted', 'Sale allowed with a mandatory warning and a capped daily dose.', 'National decree'));
  ['BE', 'PT', 'GR', 'HU'].forEach((c) => addRule(pre, c, 'restricted', 'Caffeine above 200 mg per serving requires a mandatory on-pack warning.', 'EU 1169/2011, Art. 10'));
  ['DK', 'FI', 'NO', 'CH', 'IE'].forEach((c) => addRule(ash, c, 'unknown', '', 'Automated feed import'));

  /* ---------------- market health: what a country hub can state as measured ---------------- */
  S.marketStats = S.countries.map((c) => {
    const shops = S.merchants.filter((m) => m.shipsTo.indexOf(c.iso) >= 0);
    const lanes = S.shipLanes.filter((l) => l.to === c.iso);
    const costs = lanes.map((l) => l.cost).sort((a, b) => a - b);
    const fastest = lanes.length ? Math.min.apply(null, lanes.map((l) => l.days[0])) : null;
    return {
      iso: c.iso, name: c.name, shops: shops.length, lanes: lanes.length,
      medianShip: costs.length ? costs[Math.floor(costs.length / 2)] : null,
      fastestDays: fastest, domestic: shops.filter((m) => m.country === c.iso).length,
      carriers: Array.from(new Set(lanes.map((l) => l.carrier))).sort(),
      freeShipShops: shops.filter((m) => (m.freeOverEur || 999) <= 60).length,
      localised: c.localised, eu: c.eu,
    };
  });
})();
