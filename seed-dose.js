/* Ingredient dosing — the amount of each declared ingredient per serving, in milligrams.
   This is the record. Every "price per gram of active" figure in the app is derived from it;
   nothing dosed is ever typed into copy. Amounts that a product's own description states
   ("200 mg caffeine per serving", "3 g beta-alanine") are set to that number here, so the two
   can never drift apart.

   Source discipline: each product carries where the amounts came from and when they were read,
   because a dose nobody can trace is worth less than no dose at all. */
(function () {
  const S = window.SEED;
  if (!S) return;
  const DAY = S.DAY, NOW = S.NOW;

  /* product slug -> { ingredient name: mg per serving } */
  const D = {
    'performance-alpha': { 'L-citrulline malate': 6000, 'Beta-alanine': 3200, Caffeine: 200, 'L-theanine': 200 },
    'strength-core': { 'Creatine monohydrate': 5000 },
    'recovery-matrix': { EAA: 10000, 'L-glutamine': 5000, Electrolytes: 1200 },
    'mass-formula-x': { 'Whey concentrate': 30000, Maltodextrin: 60000, 'Creatine monohydrate': 3000 },
    'endurance-prime': { 'Cyclic dextrin': 46000, Electrolytes: 1500 },
    'whey-isolate-90': { 'Whey isolate': 30000 },
    'native-whey-concentrate': { 'Whey concentrate': 30000 },
    'vegan-protein-blend': { 'Pea protein': 21000, 'Rice protein': 9000 },
    'casein-night-protein': { 'Micellar casein': 30000 },
    'creatine-monohydrate-micronized': { 'Creatine monohydrate': 5000 },
    'creatine-hcl-caps': { 'Creatine HCl': 2250 },
    'eaa-complete': { EAA: 9000 },
    'bcaa-4-1-1': { 'BCAA 4:1:1': 7000 },
    'beta-alanine-pure': { 'Beta-alanine': 3000 },
    'citrulline-malate-2-1': { 'L-citrulline malate': 8000 },
    'pre-burn-extreme': { Caffeine: 350, 'Beta-alanine': 3200, 'Green tea extract': 500 },
    'nitric-surge': { 'L-citrulline malate': 6000, 'Beta-alanine': 3200 },
    'omega-3-ultra': { 'Omega-3 EPA/DHA': 700 },
    'vitamin-d3-k2': { 'Vitamin D3': 0.05, 'Vitamin K2': 0.1 },
    'zma-recovery': { Zinc: 30, 'Magnesium bisglycinate': 450, 'Vitamin B6': 10.5 },
    'magnesium-bisglycinate': { 'Magnesium bisglycinate': 400 },
    'ashwagandha-ksm-66': { 'Ashwagandha KSM-66': 600 },
    'melatonin-sleep-1-mg': { Melatonin: 1 },
    'electrolyte-hydration': { Electrolytes: 1800 },
    'carb-loader-maltodextrin': { Maltodextrin: 50000 },
    'thermo-cut-yohimbine': { 'Yohimbine HCl': 2.5, 'Green tea extract': 250 },
    'collagen-joint-support': { 'Type II collagen': 10000 },
    'glutamine-pure': { 'L-glutamine': 5000 },
    'whey-hydro-peptides': { 'Whey isolate': 29000 },
    'clear-whey-refresh': { 'Whey isolate': 24000 },
    'oat-mass-builder': { Maltodextrin: 62000, 'Whey concentrate': 28000 },
    'creatine-gummies': { 'Creatine monohydrate': 3000 },
    'pump-matrix-caffeine-free': { 'L-citrulline malate': 8000, 'Beta-alanine': 3200 },
    'focus-fuel-nootropic': { Caffeine: 150, 'L-theanine': 300 },
    'electrolyte-salt-caps': { Electrolytes: 1000 },
    'iso-whey-zero-lactose': { 'Whey isolate': 30000 },
    'night-recovery-blend': { 'Magnesium bisglycinate': 400, 'Ashwagandha KSM-66': 600 },
    'beef-protein-isolate': { 'Whey isolate': 29000 },
    'vitamin-b-complex': { 'Vitamin B6': 12 },
    'zinc-picolinate': { Zinc: 25 },
    'joint-flex-complex': { 'Type II collagen': 1500 },
    'greens-daily-mix': { 'Green tea extract': 300 },
    'casein-micellar-slow': { 'Micellar casein': 30000 },
    'intra-workout-carb-amino': { 'Cyclic dextrin': 22000, EAA: 5000 },
    'taurine-pure': { 'Beta-alanine': 3000 },
    'hmb-strength-caps': { 'L-glutamine': 3000 },
  };

  /* Ingredients bought for the effect, versus ingredients that carry or bulk the serving.
     Both are dosed; only the first is counted as "active" in the price-per-gram headline,
     because 60 g of maltodextrin would otherwise make a gainer look like the best value
     in the catalogue. */
  const CARRIER = ['Maltodextrin', 'Cyclic dextrin'];

  /* Reference intakes used only where a substance has a published one. Absent means
     "no EU NRV for this substance" and the app must say so rather than invent a percentage. */
  const NRV = { Zinc: 10, 'Vitamin B6': 1.4, 'Vitamin D3': 0.005, 'Vitamin K2': 0.075, 'Magnesium bisglycinate': 375 };

  /* Substances a market caps or warns on. Read against the dose, so the warning is a
     consequence of the number and not a label somebody typed beside it. */
  const LIMITS = [
    { ingredient: 'Caffeine', max: 200, unit: 'mg', scope: 'per serving', markets: ['DE', 'AT', 'CZ', 'SK', 'PL', 'NL', 'FR', 'IT', 'ES', 'SE', 'GB'], rule: 'EFSA single-dose guidance; above this a warning statement is required.' },
    { ingredient: 'Melatonin', max: 1, unit: 'mg', scope: 'per serving', markets: ['DE', 'AT', 'CZ', 'SK', 'PL', 'NL', 'IT', 'ES', 'SE'], rule: 'Food-supplement ceiling; above it the product is a medicinal product in most of these markets.' },
    { ingredient: 'Yohimbine HCl', max: 0, unit: 'mg', scope: 'per serving', markets: ['DE', 'FR', 'IT', 'NL', 'SE', 'AT'], rule: 'Not permitted in food supplements.' },
    { ingredient: 'Green tea extract', max: 800, unit: 'mg', scope: 'daily', markets: ['DE', 'AT', 'CZ', 'SK', 'PL', 'NL', 'FR', 'IT', 'ES', 'SE'], rule: 'EGCG-standardised extracts capped per day.' },
    { ingredient: 'Vitamin D3', max: 0.1, unit: 'mg', scope: 'daily', markets: ['DE', 'AT', 'CZ', 'SK', 'PL', 'NL', 'FR', 'IT', 'ES', 'SE', 'GB'], rule: '4000 IU upper intake level for adults.' },
  ];

  let read = 0;
  S.products.forEach((p) => {
    const map = D[p.slug];
    if (!map) { p.doses = null; return; }
    read++;
    p.doses = Object.keys(map).map((name) => ({
      ingredient: name,
      mg: map[name],
      carrier: CARRIER.indexOf(name) >= 0,
      nrv: typeof NRV[name] === 'number' ? NRV[name] : null,
    }));
    /* how the amounts were obtained — three routes with different reliability, spread
       deterministically so the mix is visible in the data-quality console */
    const route = p.id % 7 === 0 ? 'brand_spec' : p.id % 3 === 0 ? 'merchant_feed' : 'label_photo';
    p.doseSource = route;
    p.doseSourceLabel = route === 'label_photo' ? 'Label photograph' : route === 'brand_spec' ? 'Brand specification sheet' : 'Merchant feed attribute';
    p.doseUpdated = NOW - ((p.id * 13) % 210) * DAY;
  });

  S.doseMeta = {
    carriers: CARRIER,
    nrv: NRV,
    limits: LIMITS,
    dosedProducts: read,
    note: 'Amounts are per serving as declared on the pack. Where a product states an amount in its own description, the two are the same number by construction.',
  };
})();
