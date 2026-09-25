# Prototype limitations

Honest list of what this prototype does **not** do, so nobody mistakes it for a running platform.

## Data

* All shops, brands, products, prices, reviews and analytics are fictional and generated from a
  fixed seed. Nothing is fetched from a real merchant, feed or affiliate network.
* Price history is synthesised, not observed. Trend and timing indicators describe that synthetic
  series and are labelled as indicators, not guarantees.
* "30-day" analytics are seed constants, not a rolling window.

## Engine

* Scoring is deterministic arithmetic over the seed graph. There is no machine learning anywhere,
  including in the predictive price signal.
* Fraud detection uses seeded fingerprints (device, session, account age) that a real system would
  derive from request metadata.
* Matching similarity is token Jaccard + character trigram, not an embedding model.

## Platform

* Everything runs in the browser: no server, no database, no queue, no auth. Roles are simulated,
  and the role switcher in `/intel → Roles` is a demo control — a real deployment binds the role to
  the session.
* Affiliate redirects do not leave the prototype and record no real clicks.
* Emails, digests and notifications are simulated in-app only.
* GDPR export/erase operates on browser storage, not on a backend record.

## Accessibility and platform gaps

* Modals close on Escape and expose a labelled close control, but focus is not programmatically
  trapped.
* Charts are decorative SVG with textual summaries; production needs described data tables.
* No service worker, so there is no true offline mode — only graceful recovery from unreadable
  stored state.

## Scale

* Long lists are not virtualised. The seed set (tens of products, hundreds of offers) renders
  comfortably; tens of thousands of rows would need the virtualisation and server-side scoring
  described in PERFORMANCE.md and ARCHITECTURE.md.
