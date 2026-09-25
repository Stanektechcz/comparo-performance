# Personalisation

## Signals used

Country, currency, saved products, followed brands, followed shops, recent searches, recent
comparisons, deal clicks.

## Signals never used

No health profile, no condition profile, no medication or drug-use profile, no inference from
product categories to a health state. Personalisation reads platform interaction only. This is a
hard product rule, not a setting.

## For You feed (`/for-you`)

* Price drops you may care about — saved products and followed brands first, then market-popular.
* Deals from shops you follow — falls back to highest-trust shops when you follow none.
* Similar to what you saved — related graph seeded by your saves and comparisons.
* New community discussions.
* Comparisons to finish.
* Trending right now.

Every row carries **"Why am I seeing this?"** with the concrete reason ("Because you follow
PeakSupps", "Because you saved Whey Isolate 90", "Popular in Germany"). The feed header lists the
exact signals in use, including "Sensitive data used: none".

## Compliance interaction

Products with UNKNOWN market status never receive recommendation labels; they display
"Market status not verified". Products that are restricted or prescription-only in the selected
market are excluded from personalised surfaces entirely, along with their offers.

## Storage

Signals live in browser storage in the prototype. A future backend keeps them in a per-account
preference record with data minimisation: only the fields listed above, no derived profiles.

Future service: **PersonalizationService** (signal store + feed assembler, GDPR-exportable).
