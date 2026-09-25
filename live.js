/* Comparo Performance — live room engine.

   Real-time here is real, not animated: messages you send are written to shared browser
   storage and broadcast on a channel, so a second tab — or a second window on the same
   machine — receives them as they are typed, with presence and typing indicators. That is the
   honest limit of a prototype without a server, and it is stated in the UI.

   Two message populations, deliberately separated:

   • Persisted messages — what the reader sends. Written to localStorage, broadcast to other
     tabs, survive reload, moderatable.
   • Ambient messages — seeded members talking while you watch. Local to the tab, never
     persisted, never broadcast, so two tabs do not double them and a reload does not
     accumulate a fake history.

   window.ComparoLive(SEED) -> engine. */
(function () {
  const KEY = 'comparo.live.v1';
  const PKEY = 'comparo.live.presence';
  const CH = 'comparo.live';

  window.ComparoLive = function (S) {
    if (!S || !S.lv) return null;
    const lv = S.lv;
    let store = { msgs: [], read: {}, pins: {}, hidden: {}, reports: {} };
    let ambient = [];            /* tab-local messages */
    let subs = [];
    let openRoom = null;
    let timers = [];
    let typing = {};             /* roomKey -> { nick: untilTs } */
    let lastSent = {};           /* roomKey -> ts */
    const tabId = 'tab-' + Math.random().toString(36).slice(2, 8);
    let bc = null;

    const now = () => Date.now();
    const emit = () => subs.slice().forEach((f) => { try { f(); } catch (e) {} });

    /* Seeded history is authored relative to the catalogue's NOW; messages the reader sends
       are stamped by the real clock. Two clocks in one list would sort wrongly and print
       nonsense relative times, so the seeded history is shifted onto the real clock once. */
    if (!lv._shifted) {
      const offset = Date.now() - S.NOW;
      (lv.messages || []).forEach((m) => { m.ts += offset; });
      lv._shifted = true;
    }

    /* ---------- persistence ---------- */
    function load() {
      try {
        const raw = localStorage.getItem(KEY);
        if (raw) {
          const p = JSON.parse(raw);
          store = Object.assign({ msgs: [], read: {}, pins: {}, hidden: {}, reports: {} }, p);
        }
      } catch (e) {}
    }
    function save() {
      try { localStorage.setItem(KEY, JSON.stringify(store)); } catch (e) {}
    }
    load();

    /* ---------- transport ---------- */
    try {
      bc = new BroadcastChannel(CH);
      bc.onmessage = (ev) => {
        const d = ev.data || {};
        if (d.tab === tabId) return;
        if (d.type === 'msg') { if (!store.msgs.some((m) => m.id === d.msg.id)) { store.msgs.push(d.msg); emit(); } }
        else if (d.type === 'typing') { typing[d.room] = Object.assign({}, typing[d.room], { [d.nick]: now() + 3000 }); emit(); }
        else if (d.type === 'sync') { load(); emit(); }
      };
    } catch (e) { bc = null; }
    const onStorage = (e) => {
      if (e.key === KEY) { load(); emit(); }
      if (e.key === PKEY) emit();
    };
    try { window.addEventListener('storage', onStorage); } catch (e) {}
    const post = (obj) => { try { if (bc) bc.postMessage(Object.assign({ tab: tabId }, obj)); } catch (e) {} };

    /* ---------- presence ---------- */
    function heartbeat(room, nick) {
      try {
        const p = JSON.parse(localStorage.getItem(PKEY) || '{}');
        Object.keys(p).forEach((k) => { if (now() - p[k].ts > 45000) delete p[k]; });
        p[tabId] = { ts: now(), room: room || null, nick: nick || 'You' };
        localStorage.setItem(PKEY, JSON.stringify(p));
      } catch (e) {}
    }
    function liveTabs(room) {
      try {
        const p = JSON.parse(localStorage.getItem(PKEY) || '{}');
        return Object.keys(p).filter((k) => now() - p[k].ts < 45000 && (!room || p[k].room === room)).map((k) => Object.assign({ tab: k }, p[k]));
      } catch (e) { return []; }
    }

    /* ---------- rooms ---------- */
    const room = (key) => lv.rooms.find((r) => r.key === key) || null;
    const U = (id) => (S.users || []).find((u) => u.id === id) || null;

    /* a room's roster is seeded members plus the tabs actually watching it. The seeded count
       drifts on a slow sine so it reads as a room rather than a constant. */
    function presence(key) {
      const r = room(key);
      if (!r) return { online: 0, members: [], tabs: 0 };
      const drift = Math.round(Math.sin(now() / 120000 + key.length) * Math.max(1, r.baseOnline * 0.12));
      const tabs = liveTabs(key).length;
      const seededMembers = (S.users || []).slice(0, Math.min(8, Math.max(3, Math.round(r.baseOnline / 6)))).map((u) => ({
        nick: u.nick, username: u.username, avatar: u.avatar, level: u.level,
        idle: (u.id + Math.floor(now() / 60000)) % 5 === 0,
        expert: (lv.experts || []).some((e) => e.username === u.username),
        mod: (r.mods || []).indexOf(u.username) >= 0,
      }));
      return { online: Math.max(1, r.baseOnline + drift + tabs), members: seededMembers, tabs: tabs, mods: r.mods || [] };
    }

    function messages(key) {
      const seeded = (lv.messages || []).filter((m) => m.room === key);
      const mine = store.msgs.filter((m) => m.room === key);
      const amb = ambient.filter((m) => m.room === key);
      return seeded.concat(mine, amb)
        .filter((m) => !store.hidden[m.id])
        .map((m) => Object.assign({}, m, { pinned: m.pinned || !!store.pins[m.id] }))
        .sort((a, b) => a.ts - b.ts);
    }

    function unread(key) {
      const last = store.read[key] || 0;
      return messages(key).filter((m) => m.ts > last && m.kind !== 'system').length;
    }
    function markRead(key) {
      const list = messages(key);
      store.read[key] = list.length ? list[list.length - 1].ts : now();
      save(); emit();
    }

    /* ---------- sending ---------- */
    function slowLeft(key) {
      const r = room(key);
      if (!r || !r.slow) return 0;
      const last = lastSent[key] || 0;
      return Math.max(0, Math.ceil((r.slow * 1000 - (now() - last)) / 1000));
    }
    function send(key, body, author) {
      const r = room(key);
      if (!r) return { ok: false, reason: 'No such room.' };
      const text = (body || '').trim();
      if (!text) return { ok: false, reason: 'Nothing to send.' };
      if (text.length > 480) return { ok: false, reason: 'Rooms are for short messages. Over 480 characters, write a topic instead — it stays findable.' };
      const left = slowLeft(key);
      if (left) return { ok: false, reason: 'Slow mode: ' + left + ' s before your next message.' };
      const recent = store.msgs.filter((m) => m.room === key && m.own && now() - m.ts < 10000).length;
      if (recent >= 5) return { ok: false, reason: 'Five messages in ten seconds is the limit. Rooms stay readable that way.' };
      const msg = {
        id: 'um' + now().toString(36) + Math.random().toString(36).slice(2, 5),
        room: key, userId: 0, own: true, kind: 'msg',
        nick: (author && author.nick) || 'You', avatar: (author && author.avatar) || 'YO',
        body: text, ts: now(), pinned: false,
      };
      store.msgs.push(msg);
      lastSent[key] = now();
      save();
      post({ type: 'msg', msg: msg });
      emit();
      return { ok: true, msg: msg };
    }
    function typeSignal(key, nick) {
      post({ type: 'typing', room: key, nick: nick || 'Someone' });
    }
    function typers(key) {
      const t = typing[key] || {};
      return Object.keys(t).filter((n) => t[n] > now());
    }

    /* ---------- moderation ---------- */
    function pin(id) { store.pins[id] = !store.pins[id]; save(); emit(); return !!store.pins[id]; }
    function hide(id) { store.hidden[id] = true; save(); emit(); }
    function report(id, reason) { store.reports[id] = { reason: reason || 'unspecified', at: now() }; save(); emit(); }
    function reported() { return Object.keys(store.reports).map((id) => ({ id: id, reason: store.reports[id].reason, at: store.reports[id].at })); }

    /* ---------- ambient life ---------- */
    function stopAmbient() { timers.forEach(clearTimeout); timers = []; }
    function startAmbient(key) {
      stopAmbient();
      const script = (lv.script || {})[key] || [];
      script.slice(0, 10).forEach((line) => {
        /* a typing indicator, then the message: the room reads as people, not a feed */
        timers.push(setTimeout(() => {
          const u = U(line.userId);
          typing[key] = Object.assign({}, typing[key], { [(u && u.nick) || 'A member']: now() + 2600 });
          emit();
        }, Math.max(1200, line.after - 2400)));
        timers.push(setTimeout(() => {
          const u = U(line.userId);
          ambient.push({
            id: 'am' + Math.random().toString(36).slice(2, 8), room: key, userId: line.userId,
            nick: u ? u.nick : 'A member', avatar: u ? u.avatar : '··', level: u ? u.level : '',
            body: line.body, ts: now(), kind: 'msg', ambient: true,
          });
          if (typing[key] && u) delete typing[key][u.nick];
          emit();
        }, line.after));
      });
    }

    let hb = null;
    function open(key, nick) {
      openRoom = key;
      heartbeat(key, nick);
      clearInterval(hb);
      hb = setInterval(() => { heartbeat(openRoom, nick); emit(); }, 12000);
      startAmbient(key);
      markRead(key);
    }
    function close() {
      openRoom = null;
      stopAmbient();
      clearInterval(hb);
      heartbeat(null);
    }
    function destroy() {
      close();
      try { window.removeEventListener('storage', onStorage); } catch (e) {}
      try { if (bc) bc.close(); } catch (e) {}
      subs = [];
    }

    /* ---------- derived views ---------- */
    function roomRows(kind) {
      return lv.rooms.filter((r) => !kind || r.kind === kind).map((r) => {
        const list = messages(r.key);
        const last = list.filter((m) => m.kind !== 'system').slice(-1)[0] || null;
        const p = presence(r.key);
        return {
          key: r.key, kind: r.kind, name: r.name, sub: r.sub, icon: r.icon, href: r.href,
          online: p.online, unread: unread(r.key), slow: r.slow, plusOnly: !!r.plusOnly,
          count: list.length, lastAt: last ? last.ts : 0,
          lastBody: last ? last.body : 'No messages yet — be the first.',
          lastNick: last ? (last.nick || (U(last.userId) || {}).nick || 'Comparo') : '',
          priceFeed: !!r.priceFeed, mods: r.mods || [],
        };
      }).sort((a, b) => b.online - a.online);
    }
    function totalOnline() {
      return lv.rooms.reduce((a, r) => a + presence(r.key).online, 0);
    }

    /* promotion: the mechanism that stops a room competing with the archive */
    function promote(key, ids, title) {
      const list = messages(key).filter((m) => ids.indexOf(m.id) >= 0);
      if (!list.length) return null;
      return {
        title: title || list[0].body.slice(0, 64),
        body: list.map((m) => (m.nick || (U(m.userId) || {}).nick || 'member') + ': ' + m.body).join('\n\n'),
        room: key, count: list.length,
      };
    }

    return {
      subscribe: (f) => { subs.push(f); return () => { subs = subs.filter((x) => x !== f); }; },
      open: open, close: close, destroy: destroy,
      room: room, rooms: () => lv.rooms, roomRows: roomRows,
      messages: messages, send: send, unread: unread, markRead: markRead,
      presence: presence, totalOnline: totalOnline,
      typeSignal: typeSignal, typers: typers, slowLeft: slowLeft,
      pin: pin, hide: hide, report: report, reported: reported, promote: promote,
      tabs: liveTabs, tabId: tabId,
      note: 'Messages are shared across every tab of this browser through local storage and a broadcast channel. A real deployment replaces that transport with a socket; nothing above it changes.',
    };
  };
})();
