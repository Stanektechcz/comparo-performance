/* Comparo Performance — governance engine.

   Jury draw and tally, wiki proposal review, returns index lookup. The draw is the only part
   worth reading twice: it is seeded from the case id rather than from Math.random, so the panel
   that decided a case last month can be reproduced and checked by anybody holding the same
   data. A random panel nobody can verify is not a panel, it is an assertion.

   window.ComparoGovernance(SEED, gamifyEngine) -> engine. */
(function () {
  window.ComparoGovernance = function (S, GM) {
    if (!S || !S.gv) return null;
    const gv = S.gv, DAY = S.DAY;
    const now = () => (S.helpers && S.helpers.now ? S.helpers.now() : S.NOW);
    const U = (name) => (S.users || []).find((u) => u.username === name) || null;
    const caseType = (k) => gv.caseTypes.find((t) => t.key === k) || { name: k, standard: '' };
    const field = (k) => gv.wikiFields.find((f) => f.key === k) || { name: k, level: 9, levelName: 'Authority' };

    /* ---------------- jury ---------------- */
    /* reproducible draw: same case id, same pool, same nine names, forever */
    function draw(caseId, size) {
      const pool = (S.users || []).filter((u) => (u.rep || 0) >= 900);
      if (!pool.length) return [];
      let h = 0;
      for (let i = 0; i < caseId.length; i++) h = (h * 31 + caseId.charCodeAt(i)) >>> 0;
      const out = [], taken = {};
      for (let k = 0; k < Math.min(size || 9, pool.length); k++) {
        h = (h * 1103515245 + 12345) >>> 0;
        let idx = h % pool.length;
        while (taken[idx]) idx = (idx + 1) % pool.length;
        taken[idx] = 1;
        out.push(pool[idx].username);
      }
      return out;
    }
    function myEligibility(me) {
      const m = me || {};
      const checks = gv.juryEligibility.map((e) => {
        let ok = false, detail = '';
        if (e.key === 'level') { ok = (m.xp || 0) >= 900; detail = (m.xp || 0) + ' XP'; }
        else if (e.key === 'tenure') { ok = (m.tenureDays || 0) >= 90; detail = (m.tenureDays || 0) + ' days'; }
        else if (e.key === 'reversal') { ok = (m.reversalRate || 0) < 15; detail = (m.reversalRate || 0) + ' %'; }
        else if (e.key === 'conflict') { ok = true; detail = 'none declared'; }
        else { ok = true; detail = 'not drawn recently'; }
        return { key: e.key, label: e.label, why: e.why, ok: ok, detail: detail };
      });
      return { ok: checks.every((c) => c.ok), checks: checks, missing: checks.filter((c) => !c.ok) };
    }
    function tally(c, myVote, seats) {
      /* Seats come from the drawn panel, never from an aspiration. Seeded tallies are scaled to
         fit rather than printed as "9 of 9 voted" above seven chips — and on an open case they
         are scaled to leave one seat free, because a reader who votes has to occupy a seat.
         The first version filled the panel exactly, so a cast vote changed nothing while the
         app still paid 120 XP for it. */
      const s = Math.max(1, seats || 9);
      const open = c.status === 'open';
      const room = open ? Math.max(1, s - 1) : s;
      const raw = c.votes || { uphold: 0, overturn: 0, abstain: 0 };
      const v = { uphold: raw.uphold, overturn: raw.overturn, abstain: raw.abstain };
      let total = v.uphold + v.overturn + v.abstain;
      if (total > room) {
        const f = room / total;
        v.uphold = Math.round(v.uphold * f);
        v.overturn = Math.round(v.overturn * f);
        v.abstain = Math.max(0, room - v.uphold - v.overturn);
        total = v.uphold + v.overturn + v.abstain;
      }
      if (myVote) { v[myVote] = (v[myVote] || 0) + 1; total++; }
      const quorum = Math.floor(s / 2) + 1;
      const decisive = Math.max(v.uphold, v.overturn);
      return {
        uphold: v.uphold, overturn: v.overturn, abstain: v.abstain,
        cast: total, seats: s, quorum: quorum, free: Math.max(0, s - total),
        reached: decisive >= quorum,
        leading: v.uphold === v.overturn ? 'tied' : v.uphold > v.overturn ? 'uphold' : 'overturn',
        upholdPct: total ? Math.round((v.uphold / total) * 100) : 0,
        overturnPct: total ? Math.round((v.overturn / total) * 100) : 0,
      };
    }
    function caseRows(myVotes, me) {
      const votes = myVotes || {};
      const panelMe = myEligibility(me).ok;
      return gv.cases.map((c) => {
        const panel = draw(c.id, 9);
        const t = tally(c, votes[c.id], panel.length);
        const left = c.deadline - now();
        return {
          id: c.id, type: c.type, typeName: caseType(c.type).name, standard: caseType(c.type).standard,
          raisedBy: caseType(c.type).who,
          title: c.title, detail: c.detail, status: c.status,
          open: c.status === 'open', decided: c.status === 'decided',
          verdict: c.verdict, appealed: !!c.appealed,
          panel: panel.map((u) => ({ nick: (U(u) || {}).nick || u, href: '#/users/' + u })),
          onPanel: panelMe,
          myVote: votes[c.id] || null,
          tally: t,
          deadlineLeft: left > 0 ? Math.max(1, Math.round(left / 3600000)) + ' h left' : 'closed',
          urgent: left > 0 && left < 24 * 3600000,
          reasons: c.reasons,
          opened: c.opened, decidedAt: c.decidedAt,
        };
      });
    }

    /* ---------------- wiki ---------------- */
    function canEdit(fieldKey, level) {
      const f = field(fieldKey);
      return { ok: (level || 1) >= f.level, need: f.level, needName: f.levelName, evidence: f.evidence };
    }
    function proposalRows(mine, level) {
      const extra = (mine || []).map((p, i) => Object.assign({ id: 'WIK-M' + i, status: 'pending', mine: true }, p));
      return extra.concat(gv.wikiProposals).map((p) => {
        const f = field(p.field);
        const gate = canEdit(p.field, level);
        return {
          id: p.id, product: p.product, slug: p.slug, href: '#/products/' + p.slug,
          field: p.field, fieldName: p.fieldName || f.name,
          level: f.levelName, evidenceNeeded: f.evidence,
          author: p.authorNick || p.author, authorHref: '#/users/' + (p.author || 'you'),
          at: p.at, before: p.before, after: p.after, evidence: p.evidence,
          status: p.status, mine: !!p.mine, note: p.note || '',
          reviewers: (p.reviewers || []).length,
          canReview: gate.ok && p.status === 'pending' && !p.mine,
          blocked: !gate.ok,
          blockedWhy: gate.ok ? '' : 'Needs ' + gate.needName + ' and ' + gate.evidence.toLowerCase(),
        };
      });
    }
    function coverage() {
      return gv.wikiFields.map((f) => {
        /* how much of the catalogue actually carries this field today */
        const held = f.key === 'dosing' ? (S.products || []).filter((p) => p.doses && p.doses.length).length
          : f.key === 'flavours' ? (S.products || []).filter((p) => (p.variants || []).length > 1).length
          : Math.round((S.products || []).length * ({ summary: 0.34, allergens: 0.22, storage: 0.28, certifications: 0.17, usage: 0.19 }[f.key] || 0.2));
        return {
          key: f.key, name: f.name, level: f.levelName, evidence: f.evidence, why: f.why,
          held: held, pool: (S.products || []).length,
          pct: Math.round((held / Math.max(1, (S.products || []).length)) * 100),
        };
      });
    }

    /* ---------------- returns ---------------- */
    function returnsFor(merchantId, market) {
      return gv.returnsIndex.find((r) => r.merchantId === merchantId && (!market || r.market === market)) || null;
    }
    function returnsRows(market) {
      const rows = gv.returnsIndex.filter((r) => (!market || r.market === market) && r.enough);
      return rows.map((r) => {
        const m = (S.merchants || []).find((x) => x.id === r.merchantId) || {};
        /* Fault needs a handful of returns before it means anything, but refund speed is
           measurable from the first one. So a pair with one or two returns is scored on how
           fast the money came back, and only a pair with three or more is scored on fault —
           the alternative, calling everything "strong" because nothing went wrong twice, tells
           a reader nothing and flatters every shop equally. */
        const rd = r.medianRefundDays;
        let verdict, color;
        if (!r.returns) { verdict = 'no returns yet'; color = 'var(--text-4)'; }
        else if (r.returns < 3) {
          verdict = rd === null ? 'unscored' : rd <= 5 ? 'fast refunds' : rd <= 9 ? 'ordinary' : 'slow refunds';
          color = rd !== null && rd <= 5 ? 'var(--ok)' : rd !== null && rd > 9 ? 'var(--danger)' : 'var(--text-3)';
        } else if (r.faultRate <= 1.5 && (rd === null || rd <= 7)) { verdict = 'strong'; color = 'var(--ok)'; }
        else if (r.faultRate > 3.5 || (rd !== null && rd > 12)) { verdict = 'weak'; color = 'var(--danger)'; }
        else { verdict = 'ordinary'; color = 'var(--text-3)'; }
        return Object.assign({}, r, {
          shop: m.name, slug: m.slug, verdict: verdict, color: color,
          scoredOnFault: r.returns >= 3,
        });
      }).sort((a, b) => (a.faultRate - b.faultRate) || ((a.medianRefundDays || 99) - (b.medianRefundDays || 99)));
    }

    function poolSize() { return (S.users || []).filter((u) => (u.rep || 0) >= 900).length; }
    function seatCount() { return Math.min(9, poolSize()); }

    return {
      draw: draw, myEligibility: myEligibility, tally: tally, caseRows: caseRows,
      poolSize: poolSize, seatCount: seatCount,
      caseType: caseType, field: field, canEdit: canEdit,
      proposalRows: proposalRows, coverage: coverage,
      returnsFor: returnsFor, returnsRows: returnsRows,
    };
  };
})();
