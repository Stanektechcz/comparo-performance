# Delivery markets

Twelve markets became **27**. A market only exists here if a shop can actually be reached from
it, so every country arrives with the lanes that serve it.

New: BE, PT, IE, DK, FI, NO, CH, HU, RO, BG, GR, SI, HR, LT, EE.
New currencies: DKK, NOK, CHF, HUF, RON, BGN (12 in total).

## What a market record holds

VAT (for display honesty), the legal adult age for restricted products, EU membership, region,
and customs treatment for the four non-EU destinations — never a price, because we do not sell.

**155 shipping lanes.** For every shop–market pair: carrier, transit band, cost, dispatch cutoff,
tracking, pickup availability, cash on delivery and whether duty applies. Bands are computed from
a region graph (same region / adjacent / elsewhere), and how far a shop ships is a function of how
developed it is — a verified partner reaches further than a freshly imported shop.

**27 delivery markets, 12 localised storefronts.** Shipping to a country is a lane; translating a
country is a commitment. We do not pretend the second exists because the first does, so
`localeMarkets` was left alone and the difference is stated on the page.

**Market packs are merchant capability, never visibility.** Organic listing and ComparoRank
position are free in all 27 markets. A pack (€29–€79 per region per month) buys a localised
profile, delivery-promise eligibility, market analytics and campaign booking.

Compliance baselines were extended to the new markets: melatonin prescription-only in 8 more,
restricted in 6; yohimbine not allowed in 4, restricted in 7; caffeine warnings in 4.

Files: `seed-geo.js`. Surface: `#/markets`, plus every market hub, offer row and delivery
estimate in the product.
