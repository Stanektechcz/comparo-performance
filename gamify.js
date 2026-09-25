/* Comparo Performance — contribution engine.

   Pure functions over the ledger. Nothing here writes; the component owns state, this owns
   arithmetic, so a level, a cap and a multiplier cannot be computed two different ways on two
   different screens.

   window.ComparoGamify(SEED) -> engine. */
(function () {
  window.ComparoGamify = function (S) {
    if (!S || !S.gm) return null;
    const gm = S.gm, DAY = S.DAY;
    const src = (key) => gm.xpSources.find((x) => x.key === key) || null;
    const dayKey = (ts) => Math.floor(ts / DAY);

    /* ---------- totals ---------- */
    function total(ledger) {
      return (ledger || []).filter((e) => e.status !== 'reversed').reduce((a, b) => a + b.xp, 0);
    }
    function reversedCount(ledger) { return (ledger || []).filter((e) => e.status === 'reversed').length; }
    function reversalRate(ledger) {
      const n = (ledger || []).length;
      return n ? Math.round((reversedCount(ledger) / n) * 1000) / 10 : 0;
    }

    /* ---------- levels ---------- */
    function levelOf(xp) {
      const ls = gm.levels;
      let cur = ls[0];
      for (let i = 0; i < ls.length; i++) if (xp >= ls[i].min) cur = ls[i];
      const next = ls.find((l) => l.min > xp) || null;
      const span = next ? next.min - cur.min : 1;
      return {
        level: cur, next: next,
        toNext: next ? next.min - xp : 0,
        progress: next ? Math.max(0, Math.min(100, Math.round(((xp - cur.min) / span) * 100))) : 100,
        perksNow: cur.perks, perksNext: next ? next.perks : [],
      };
    }

    /* ---------- caps ---------- */
    function earnedToday(ledger, key, nowTs) {
      const d = dayKey(nowTs);
      return (ledger || []).filter((e) => e.key === key && e.status !== 'reversed' && dayKey(e.at) === d).length;
    }
    function capLeft(ledger, key, nowTs) {
      const s = src(key);
      if (!s) return 0;
      return Math.max(0, s.cap - earnedToday(ledger, key, nowTs));
    }

    /* ---------- streak ---------- */
    function streak(ledger, nowTs) {
      const days = {};
      (ledger || []).forEach((e) => { if (e.status !== 'reversed') days[dayKey(e.at)] = true; });
      const today = dayKey(nowTs);
      let n = 0, cursor = days[today] ? today : today - 1;
      while (days[cursor]) { n++; cursor--; }
      const mult = gm.streak.multipliers.slice().reverse().find((m) => n >= m[0]);
      return { days: n, multiplier: mult ? mult[1] : 1, next: gm.streak.multipliers.find((m) => m[0] > n) || null, activeToday: !!days[today] };
    }
    function awardValue(key, streakDays) {
      const s = src(key);
      if (!s) return 0;
      const mult = gm.streak.multipliers.slice().reverse().find((m) => streakDays >= m[0]);
      const m = s.group === 'Data' && mult ? mult[1] : 1;
      return Math.min(120, Math.round(s.xp * m));
    }

    /* ---------- quests ---------- */
    function questProgress(ledger, q, nowTs) {
      const since = q.scope === 'daily' ? nowTs - DAY : q.scope === 'weekly' ? nowTs - 7 * DAY : (gm.season.starts || 0);
      const rows = (ledger || []).filter((e) => e.at >= since && e.status !== 'reversed' && (q.source === 'any' || e.key === q.source));
      const done = Math.min(q.target, rows.length);
      return {
        key: q.key, scope: q.scope, title: q.title, desc: q.desc, xp: q.xp, target: q.target,
        done: done, complete: done >= q.target,
        pct: Math.round((done / q.target) * 100),
        source: q.source === 'any' ? 'Any accepted contribution' : (src(q.source) || {}).label || q.source,
      };
    }
    function quests(ledger, nowTs, scope) {
      return gm.quests.filter((q) => !scope || q.scope === scope).map((q) => questProgress(ledger, q, nowTs));
    }

    /* ---------- badges ---------- */
    function badgeTier(badgeKey, count) {
      const b = gm.badgeTiers.find((x) => x.badge === badgeKey);
      if (!b) return null;
      let tier = null;
      b.tiers.forEach((t) => { if (count >= t[1]) tier = t[0]; });
      const next = b.tiers.find((t) => t[1] > count) || null;
      return { label: b.label, tier: tier, next: next ? { name: next[0], need: next[1] - count } : null, count: count };
    }
    function badgesFrom(ledger) {
      return gm.badgeTiers.map((b) => {
        const count = (ledger || []).filter((e) => e.key === b.source && e.status !== 'reversed').length;
        return Object.assign({ badge: b.badge, source: b.source }, badgeTier(b.badge, count));
      }).filter((b) => b.tier || b.count > 0);
    }

    /* ---------- season & leaderboard ---------- */
    function leaderboard(opts) {
      const o = opts || {};
      let rows = gm.season.leaderboard.slice();
      if (o.market) rows = rows.filter((r) => r.market === o.market);
      if (o.mine) {
        const xp = o.mine.xp || 0;
        rows = rows.concat([{ userId: 0, username: 'you', nick: 'You', avatar: 'YO', xp: xp, contribs: o.mine.contribs || 0, market: o.mine.market || '', streak: o.mine.streak || 0, accepted: o.mine.contribs || 0, reversed: o.mine.reversed || 0, own: true }]);
      }
      return rows.sort((a, b) => b.xp - a.xp).map((r, i) => Object.assign({}, r, { rank: i + 1 }));
    }
    function seasonState(nowTs) {
      const s = gm.season;
      const span = s.ends - s.starts;
      return {
        key: s.key, name: s.name, theme: s.theme, note: s.note, reward: s.reward,
        goal: s.goal, goalLabel: s.goalLabel, progress: s.progress,
        pct: Math.round((s.progress / s.goal) * 100),
        daysLeft: Math.max(0, Math.round((s.ends - nowTs) / DAY)),
        elapsedPct: Math.max(0, Math.min(100, Math.round(((nowTs - s.starts) / span) * 100))),
        past: s.past,
      };
    }

    /* ---------- rewards ---------- */
    function rewards(xp, redeemed) {
      const done = redeemed || {};
      return gm.rewards.map((r) => ({
        key: r.key, name: r.name, cost: r.cost, kind: r.kind, desc: r.desc, stock: r.stock,
        grants: r.grants,
        affordable: xp >= r.cost,
        short: Math.max(0, r.cost - xp),
        owned: (done[r.key] || 0),
      }));
    }
    /* what redeemed rewards actually grant, folded into one entitlement delta */
    function earnedEntitlements(redeemed) {
      const out = { plusDays: 0, alerts: 0, instantDays: 0, bounty: 0, earlyDealsDays: 0, exports: 0, groupSlots: 0, flair: null, labTest: 0 };
      Object.keys(redeemed || {}).forEach((k) => {
        const r = gm.rewards.find((x) => x.key === k);
        if (!r) return;
        const n = redeemed[k];
        Object.keys(r.grants).forEach((g) => {
          if (typeof r.grants[g] === 'number') out[g] = (out[g] || 0) + r.grants[g] * n;
          else out[g] = r.grants[g];
        });
      });
      return out;
    }

    /* ---------- what a source collects, for the contribution page ---------- */
    function sourceRows(ledger, nowTs) {
      return gm.xpSources.map((s) => ({
        key: s.key, label: s.label, xp: s.xp, cap: s.cap, group: s.group,
        why: s.why, proof: s.proof, collects: s.collects,
        usedToday: earnedToday(ledger, s.key, nowTs),
        left: capLeft(ledger, s.key, nowTs),
        lifetime: (ledger || []).filter((e) => e.key === s.key && e.status !== 'reversed').length,
      }));
    }

    return {
      total: total, levelOf: levelOf, streak: streak, awardValue: awardValue,
      capLeft: capLeft, earnedToday: earnedToday,
      quests: quests, questProgress: questProgress,
      badgesFrom: badgesFrom, badgeTier: badgeTier,
      leaderboard: leaderboard, season: seasonState,
      rewards: rewards, earnedEntitlements: earnedEntitlements,
      sourceRows: sourceRows, reversalRate: reversalRate,
      source: src, levels: gm.levels, integrity: gm.integrity,
    };
  };
})();
