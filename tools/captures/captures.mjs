// Captures d'écran automatiques du manuel d'aide, sans aucune dépendance : pilote Chrome par son protocole de débogage.
// Lancé par « php artisan aide:captures » (qui prépare le fichier de configuration JSON passé en paramètre).
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const cfg = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const dormir = (ms) => new Promise((r) => setTimeout(r, ms));
const PORT = 9333 + Math.floor(Math.random() * 400);
const profil = fs.mkdtempSync(path.join(os.tmpdir(), 'tpi-captures-'));

const chrome = spawn(cfg.chrome, ['--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${profil}`, '--window-size=1440,900', '--hide-scrollbars', '--disable-gpu',
  '--no-first-run', '--no-default-browser-check', '--lang=fr-FR', 'about:blank'], { stdio: 'ignore' });

async function cible() {
  for (let i = 0; i < 60; i++) {
    try {
      const l = await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json();
      const p = l.find((x) => x.type === 'page' && x.webSocketDebuggerUrl);
      if (p) return p.webSocketDebuggerUrl;
    } catch (e) { /* Chrome démarre */ }
    await dormir(250);
  }
  throw new Error('Chrome ne répond pas : vérifiez le chemin du navigateur.');
}

const ws = new WebSocket(await cible());
await new Promise((ok, ko) => { ws.onopen = ok; ws.onerror = ko; });
let n = 0;
const attente = new Map();
const evenements = [];
ws.onmessage = (m) => {
  const d = JSON.parse(m.data);
  if (d.id && attente.has(d.id)) { const { ok, ko } = attente.get(d.id); attente.delete(d.id); d.error ? ko(new Error(d.error.message)) : ok(d.result); }
  else if (d.method) { evenements.push(d.method); }
};
const cdp = (method, params = {}) => new Promise((ok, ko) => { const id = ++n; attente.set(id, { ok, ko }); ws.send(JSON.stringify({ id, method, params })); });
const js = async (expression) => {
  const r = await cdp('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
  if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || 'Erreur de script');
  return r.result.value;
};

async function aller(url, pause = 1800) {
  evenements.length = 0;
  await cdp('Page.navigate', { url });
  for (let i = 0; i < 80 && !evenements.includes('Page.loadEventFired'); i++) await dormir(250);
  await dormir(pause);
}

async function attendreUrl(test, delaiMs, quoi) {
  const fin = Date.now() + delaiMs;
  while (Date.now() < fin) {
    const u = await js('location.href');
    if (test(u)) return u;
    await dormir(400);
  }
  throw new Error(`Délai dépassé : ${quoi}`);
}

async function photo(fichier, clip) {
  const r = await cdp('Page.captureScreenshot', { format: 'png', fromSurface: true, ...(clip ? { clip: { ...clip, scale: 1 } } : {}) });
  fs.mkdirSync(cfg.sortie, { recursive: true });
  fs.writeFileSync(path.join(cfg.sortie, fichier), Buffer.from(r.data, 'base64'));
  console.log('  capture :', fichier);
}

try {
  await cdp('Page.enable');
  await cdp('Runtime.enable');
  await cdp('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });

  // Connexion avec le compte de capture (adresse @tpi.test, mot de passe d'essai) puis code de secours.
  console.log('Connexion…');
  await aller(`${cfg.base}/login`, 800);
  await js(`(() => { const f = document.querySelector('form[action$="/login"]'); f.querySelector('[name=email]').value = ${JSON.stringify(cfg.compte)}; f.querySelector('[name=password]').value = ${JSON.stringify(cfg.mdp)}; f.requestSubmit(); })()`);
  await attendreUrl((u) => u.includes('two-factor'), 90000, 'page du code (la messagerie est peut-être lente)');
  await dormir(1200);
  await js(`document.getElementById('lien-secours').click()`);
  await dormir(600);
  await photo('connexion-code.png', { x: 420, y: 60, width: 600, height: 780 });
  await js(`(() => { document.getElementById('code-secours').value = ${JSON.stringify(cfg.code)}; document.getElementById('bloc-secours').requestSubmit(); })()`);
  await attendreUrl((u) => !u.includes('two-factor') && !u.includes('/login'), 40000, 'validation du code de secours');
  await dormir(1500);

  for (const c of cfg.captures) {
    try {
      await aller(c.url.startsWith('http') ? c.url : cfg.base + c.url, c.pause ?? 2200);
      if (c.avant) { await js(c.avant); await dormir(c.apres ?? 1200); }
      await photo(c.fichier, c.clip);
    } catch (e) { console.log('  ÉCHEC', c.fichier, ':', e.message); }
  }
  console.log('Terminé.');
} catch (e) {
  console.error('Erreur :', e.message);
  process.exitCode = 1;
} finally {
  try { await cdp('Browser.close'); } catch (e) { /* déjà fermé */ }
  chrome.kill();
  await dormir(500);
  fs.rmSync(profil, { recursive: true, force: true });
}
