const cache = new Map();
const TTL = 120000;

function toRepo(repo) {
  return {
    name: repo.name,
    full_name: repo.full_name,
    description: repo.description || '',
    html_url: repo.html_url,
    language: repo.language || '',
    stars: repo.stargazers_count || 0,
    forks: repo.forks_count || 0,
    watchers: repo.watchers_count || 0,
    open_issues: repo.open_issues_count || 0,
    fork: !!repo.fork,
    updated_at: repo.updated_at || ''
  };
}

async function fetchReposRaw(username, token) {
  if (!username) return { repos: [], error: null };
  const hit = cache.get(username);
  if (hit && Date.now() - hit.ts < TTL) return hit;
  const headers = { Accept: 'application/vnd.github+json', 'User-Agent': 'NobitaHost' };
  if (token) headers.Authorization = 'Bearer ' + token;
  try {
    const r = await fetch(`https://api.github.com/users/${encodeURIComponent(username)}/repos?per_page=100&sort=updated`, { headers });
    if (!r.ok) {
      const result = { repos: [], error: `GitHub API ${r.status}` };
      cache.set(username, { ts: Date.now(), ...result });
      return result;
    }
    const data = await r.json();
    const repos = (Array.isArray(data) ? data : []).map(toRepo);
    const result = { repos, error: null };
    cache.set(username, { ts: Date.now(), ...result });
    return result;
  } catch (e) {
    return { repos: [], error: e.message };
  }
}

async function getRepos(username, token) {
  const { repos, error } = await fetchReposRaw(username, token);
  return { repos: repos.filter((r) => !r.fork), error };
}

async function getUserStats(username, token) {
  if (!username) return { stats: null, error: null };
  const hit = cache.get('stats:' + username);
  if (hit && Date.now() - hit.ts < TTL) return hit;
  const headers = { Accept: 'application/vnd.github+json', 'User-Agent': 'NobitaHost' };
  if (token) headers.Authorization = 'Bearer ' + token;
  try {
    const ur = await fetch(`https://api.github.com/users/${encodeURIComponent(username)}`, { headers });
    if (!ur.ok) {
      const result = { stats: null, error: `GitHub API ${ur.status}` };
      cache.set('stats:' + username, { ts: Date.now(), ...result });
      return result;
    }
    const u = await ur.json();
    const raw = await fetchReposRaw(username, token);
    let stars = 0, forks = 0, watchers = 0, issues = 0;
    if (raw.repos) {
      for (const r of raw.repos) {
        stars += r.stars;
        forks += r.forks;
        watchers += r.watchers;
        issues += r.open_issues;
      }
    }
    const stats = {
      stars, forks, watchers, issues,
      repos: u.public_repos || 0,
      followers: u.followers || 0,
      avatar: u.avatar_url || '',
      name: u.name || username
    };
    const result = { stats, error: raw.error };
    cache.set('stats:' + username, { ts: Date.now(), ...result });
    return result;
  } catch (e) {
    return { stats: null, error: e.message };
  }
}

module.exports = { getRepos, getUserStats };
