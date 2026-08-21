const db = require('./db');

const API = 'https://api.cloudflare.com/client/v4';
const GRAPHQL = 'https://api.cloudflare.com/client/v4/graphql';
const cache = new Map();
const TTL = 5 * 60 * 1000;

function getSetting(key) {
  const row = db.prepare('SELECT value FROM settings WHERE key = ?').get(key);
  return row ? row.value : '';
}

function setSetting(key, value) {
  db.prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value').run(key, String(value == null ? '' : value));
}

function getConfig() {
  return {
    email: getSetting('cf_email'),
    authMode: getSetting('cf_auth_mode') || 'token',
    apiToken: getSetting('cf_api_token'),
    accountId: getSetting('cf_account_id'),
    zoneId: getSetting('cf_zone_id'),
    analyticsEnabled: getSetting('cf_analytics_enabled') === 'on',
    analyticsToken: getSetting('cf_analytics_token'),
    ztEnabled: getSetting('cf_zerotrust_enabled') === 'on',
    ztTeam: getSetting('cf_zerotrust_team')
  };
}

function saveConfig(b) {
  const fields = {
    cf_email: b.email,
    cf_auth_mode: b.authMode === 'global' ? 'global' : 'token',
    cf_account_id: b.accountId,
    cf_zone_id: b.zoneId,
    cf_analytics_token: b.analyticsToken,
    cf_zerotrust_team: b.ztTeam
  };
  Object.keys(fields).forEach((k) => setSetting(k, fields[k] || ''));
  const newToken = String(b.apiToken || '').trim();
  if (newToken && !newToken.includes('•')) setSetting('cf_api_token', newToken);
  setSetting('cf_analytics_enabled', b.analytics_enabled ? 'on' : '');
  setSetting('cf_zerotrust_enabled', b.zerotrust_enabled ? 'on' : '');
  cache.clear();
}

function maskToken(t) {
  if (!t) return '';
  const s = String(t);
  return s.length <= 8 ? '••••' : s.slice(0, 4) + '••••••••' + s.slice(-4);
}

function authHeaders(cfg) {
  if (cfg.authMode === 'global') {
    return { 'X-Auth-Email': cfg.email, 'X-Auth-Key': cfg.apiToken, 'Content-Type': 'application/json' };
  }
  return { Authorization: 'Bearer ' + cfg.apiToken, 'Content-Type': 'application/json' };
}

async function cfFetch(path, opts = {}, timeoutMs = 12000) {
  const cfg = getConfig();
  if (!cfg.apiToken || (cfg.authMode === 'global' && !cfg.email)) throw new Error('No credentials configured');
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), timeoutMs);
  try {
    const res = await fetch(API + path, {
      method: opts.method || 'GET',
      headers: authHeaders(cfg),
      body: opts.body ? JSON.stringify(opts.body) : undefined,
      signal: ctrl.signal
    });
    const j = await res.json();
    if (!j.success) throw new Error((j.errors && j.errors[0] && j.errors[0].message) || 'API error');
    return j.result;
  } finally {
    clearTimeout(t);
  }
}

function zid() { return getConfig().zoneId; }
function accId() { return getConfig().accountId; }
function bust() { ['dns', 'settings', 'fw', 'ar', 'zt', 'zone', 'zones', 'analytics', 'members'].forEach((k) => cache.delete(k)); }

async function gql(query, variables = {}, timeoutMs = 9000) {
  const cfg = getConfig();
  if (!cfg.apiToken || !cfg.zoneId) throw new Error('Credentials + Zone ID required');
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), timeoutMs);
  try {
    const res = await fetch(GRAPHQL, {
      method: 'POST',
      headers: authHeaders(cfg),
      body: JSON.stringify({ query, variables }),
      signal: ctrl.signal
    });
    const j = await res.json();
    if (j.errors && j.errors.length) throw new Error(j.errors[0].message);
    return j.data;
  } finally {
    clearTimeout(t);
  }
}

async function cached(key, fn) {
  const hit = cache.get(key);
  if (hit && Date.now() - hit.at < TTL) return hit.data;
  const data = await fn();
  cache.set(key, { at: Date.now(), data });
  return data;
}

async function verifyToken() {
  return cached('verify', async () => {
    const cfg = getConfig();
    if (cfg.authMode === 'global') {
      const user = await cfFetch('/user');
      return { status: 'Active', id: user.id };
    }
    const result = await cfFetch('/user/tokens/verify');
    return { status: result.status, id: result.id };
  });
}

async function listZones() {
  return cached('zones', async () => {
    const zones = await cfFetch('/zones?per_page=50');
    return zones.map((z) => ({ id: z.id, name: z.name, status: z.status, account_id: z.account && z.account.id, account_name: z.account && z.account.name }));
  });
}

async function zoneInfo() {
  return cached('zone', async () => {
    const cfg = getConfig();
    const z = await cfFetch('/zones/' + cfg.zoneId);
    return { name: z.name, status: z.status, plan: (z.plan && z.plan.name) || '' };
  });
}

async function webAnalytics() {
  const days = 7;
  const since = new Date(Date.now() - (days - 1) * 86400000).toISOString().slice(0, 10);
  const until = new Date().toISOString().slice(0, 10);
  return cached('analytics', async () => {
    const data = await gql(
      `query ($zone: String!, $since: Date!, $until: Date!) {
        viewer { zones(filter: { zoneTag: $zone }) {
          httpRequests1dGroups(limit: ${days}, filter: { date_geq: $since, date_leq: $until }, orderBy: [date_ASC]) {
            dimensions { date }
            sum { requests pageViews bytes threats }
            uniq { uniques }
          }
        } }
      }`,
      { zone: getConfig().zoneId, since, until }
    );
    const zones = data.viewer.zones || [];
    const groups = zones.length ? zones[0].httpRequests1dGroups : [];
    return groups.map((g) => ({
      date: g.dimensions.date,
      requests: g.sum.requests,
      pageViews: g.sum.pageViews,
      bytes: g.sum.bytes,
      threats: g.sum.threats,
      visitors: g.uniq.uniques
    }));
  });
}

async function zeroTrust() {
  return cached('zt', async () => {
    const cfg = getConfig();
    const base = '/accounts/' + cfg.accountId;
    const out = {};
    try {
      const apps = await cfFetch(base + '/access/apps');
      out.apps = Array.isArray(apps) ? apps.length : 0;
    } catch (e) { out.appsError = e.message; }
    try {
      const users = await cfFetch(base + '/access/users');
      out.users = Array.isArray(users) ? users.length : 0;
    } catch (e) { out.usersError = e.message; }
    try {
      const devices = await cfFetch(base + '/devices');
      out.devices = Array.isArray(devices) ? devices.length : 0;
    } catch (e) { out.devicesError = e.message; }
    return out;
  });
}

/* ---------- DNS ---------- */

async function dnsList() {
  return cached('dns', async () => cfFetch('/zones/' + zid() + '/dns_records?per_page=100'));
}
async function dnsCreate(b) {
  const proxied = b.proxied === 'on';
  const r = await cfFetch('/zones/' + zid() + '/dns_records', { method: 'POST', body: { type: b.type, name: b.name, content: b.content, ttl: proxied ? 1 : (parseInt(b.ttl, 10) || 1), proxied } });
  bust();
  return r;
}
async function dnsUpdate(id, b) {
  const proxied = b.proxied === 'on';
  const r = await cfFetch('/zones/' + zid() + '/dns_records/' + id, { method: 'PATCH', body: { type: b.type, name: b.name, content: b.content, ttl: proxied ? 1 : (parseInt(b.ttl, 10) || 1), proxied } });
  bust();
  return r;
}
async function dnsDelete(id) {
  const r = await cfFetch('/zones/' + zid() + '/dns_records/' + id, { method: 'DELETE' });
  bust();
  return r;
}

/* ---------- Zone settings ---------- */

async function zoneSettings() {
  return cached('settings', async () => {
    const all = await cfFetch('/zones/' + zid() + '/settings');
    const map = {};
    (all || []).forEach((s) => { map[s.id] = s.value; });
    return map;
  });
}
async function zoneSetSetting(key, value) {
  const r = await cfFetch('/zones/' + zid() + '/settings/' + key, { method: 'PATCH', body: { value } });
  bust();
  return r;
}

/* ---------- Security ---------- */

async function fwRules() {
  return cached('fw', async () => cfFetch('/zones/' + zid() + '/firewall/rules?per_page=100').catch(() => []));
}
async function fwRuleToggle(id, paused) {
  const r = await cfFetch('/zones/' + zid() + '/firewall/rules/' + id, { method: 'PATCH', body: { paused } });
  bust();
  return r;
}
async function fwRuleDelete(id) {
  const r = await cfFetch('/zones/' + zid() + '/firewall/rules/' + id, { method: 'DELETE' });
  bust();
  return r;
}
async function accessRules() {
  return cached('ar', async () => cfFetch('/zones/' + zid() + '/firewall/access_rules/rules?per_page=100').catch(() => []));
}
async function accessRuleCreate(b) {
  const r = await cfFetch('/zones/' + zid() + '/firewall/access_rules/rules', { method: 'POST', body: { mode: b.mode, notes: b.notes || '', configuration: { target: 'ip', value: b.value } } });
  bust();
  return r;
}
async function accessRuleDelete(id) {
  const r = await cfFetch('/zones/' + zid() + '/firewall/access_rules/rules/' + id, { method: 'DELETE' });
  bust();
  return r;
}
async function securityEvents(days = 7) {
  const since = new Date(Date.now() - days * 86400000).toISOString();
  return cached('sec-events', async () => {
    const data = await gql(
      `query ($zone: String!, $since: Timestamp!) {
        viewer { zones(filter: { zoneTag: $zone }) {
          firewallEventsAdaptiveGroups(limit: 60, filter: { datetime_geq: $since }, orderBy: [count_DESC]) {
            count dimensions { action }
          }
        } }
      }`,
      { zone: zid(), since }
    );
    const zones = data.viewer.zones || [];
    return zones.length ? zones[0].firewallEventsAdaptiveGroups : [];
  });
}

/* ---------- Accounts ---------- */

async function accounts() {
  return cached('accounts', async () => cfFetch('/accounts?per_page=50'));
}
async function accountMembers(acc) {
  return cached('members:' + acc, async () => cfFetch('/accounts/' + acc + '/members?per_page=50').catch(() => []));
}

/* ---------- Zero Trust lists ---------- */

async function ztApps() {
  return cached('zt-apps', async () => cfFetch('/accounts/' + accId() + '/access/apps?per_page=100').catch(() => []));
}
async function ztAppDelete(id) {
  const r = await cfFetch('/accounts/' + accId() + '/access/apps/' + id, { method: 'DELETE' });
  bust();
  return r;
}
async function ztUsers() {
  return cached('zt-users', async () => cfFetch('/accounts/' + accId() + '/access/users?per_page=100').catch(() => []));
}
async function ztDevices() {
  return cached('zt-devices', async () => cfFetch('/accounts/' + accId() + '/devices').catch(() => []));
}

/* ---------- Domains analytics (parallel per-zone totals) ---------- */

async function domainStats(limit = 20) {
  return cached('domain-stats', async () => {
    const zones = (await listZones()).slice(0, limit);
    const since = new Date(Date.now() - 6 * 86400000).toISOString().slice(0, 10);
    const until = new Date().toISOString().slice(0, 10);
    const results = await Promise.allSettled(zones.map((z) =>
      gql(
        `query ($zone: String!, $since: Date!, $until: Date!) {
          viewer { zones(filter: { zoneTag: $zone }) {
            httpRequests1dGroups(limit: 7, filter: { date_geq: $since, date_leq: $until }) {
              sum { requests pageViews bytes threats }
              uniq { uniques }
            }
          } }
        }`,
        { zone: z.id, since, until }
      ).then((d) => {
        const groups = (d.viewer.zones[0] && d.viewer.zones[0].httpRequests1dGroups) || [];
        let req = 0, pv = 0, bytes = 0, thr = 0, vis = 0;
        groups.forEach((g) => { req += g.sum.requests; pv += g.sum.pageViews; bytes += g.sum.bytes; thr += g.sum.threats; vis += g.uniq.uniques; });
        return { id: z.id, name: z.name, status: z.status, requests: req, pageViews: pv, bytes, threats: thr, visitors: vis };
      })
    ));
    return results.filter((r) => r.status === 'fulfilled' && r.value).map((r) => r.value)
      .sort((a, b) => b.requests - a.requests);
  });
}

module.exports = { getConfig, saveConfig, maskToken, verifyToken, listZones, zoneInfo, webAnalytics, zeroTrust, dnsList, dnsCreate, dnsUpdate, dnsDelete, zoneSettings, zoneSetSetting, fwRules, fwRuleToggle, fwRuleDelete, accessRules, accessRuleCreate, accessRuleDelete, securityEvents, accounts, accountMembers, ztApps, ztAppDelete, ztUsers, ztDevices, domainStats };
