// Headless Chrome ile ekran görüntüsü + konsol/HTTP hata raporu (Chrome DevTools Protocol, bağımlılıksız).
// Kullanım: node screenshot.mjs <cookie-jar|-> <url> <out.png> [genişlik=1366] [yükseklik=900]
//   cookie-jar: curl -c ile üretilmiş Netscape formatlı dosya; oturumsuz için "-".
import { spawn } from 'node:child_process';
import { readFileSync, writeFileSync, mkdtempSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const [jar, url, out, width = '1366', height = '900'] = process.argv.slice(2);
if (!url || !out) {
  console.error('Kullanım: node screenshot.mjs <cookie-jar|-> <url> <out.png> [genişlik] [yükseklik]');
  process.exit(1);
}

const CHROME_CANDIDATES = [
  process.env.CHROME_PATH,
  'C:/Program Files/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
].filter(Boolean);
const chromePath = CHROME_CANDIDATES.find(p => existsSync(p));
if (!chromePath) { console.error('Chrome/Edge bulunamadı (CHROME_PATH ile verin).'); process.exit(1); }

const host = new URL(url).hostname;
const cookies = jar && jar !== '-' && existsSync(jar)
  ? readFileSync(jar, 'utf8').split('\n')
      .filter(l => l.trim() && (!l.startsWith('#') || l.startsWith('#HttpOnly_')))
      .map(l => l.replace('#HttpOnly_', '').split('\t'))
      .filter(p => p.length >= 7)
      .map(p => ({ name: p[5], value: p[6].trim(), domain: host, path: p[2] }))
  : [];

const port = 9300 + Math.floor(Math.random() * 500);
const chrome = spawn(chromePath, [
  '--headless=new', '--disable-gpu', `--remote-debugging-port=${port}`,
  `--user-data-dir=${mkdtempSync(join(tmpdir(), 'cdp-'))}`, `--window-size=${width},${height}`, 'about:blank',
]);
const sleep = ms => new Promise(r => setTimeout(r, ms));

let target;
for (let i = 0; i < 75 && !target; i++) {
  await sleep(200);
  try { target = (await (await fetch(`http://127.0.0.1:${port}/json`)).json()).find(t => t.type === 'page'); } catch {}
}
if (!target) { console.error('Chrome DevTools bağlantısı kurulamadı.'); chrome.kill(); process.exit(1); }

const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise(r => ws.addEventListener('open', r));
let id = 0; const pending = new Map(); const logs = [];
ws.addEventListener('message', e => {
  const m = JSON.parse(e.data);
  if (m.id && pending.has(m.id)) { pending.get(m.id)(m.result); pending.delete(m.id); }
  if (m.method === 'Runtime.exceptionThrown') logs.push('EXC ' + (m.params.exceptionDetails.exception?.description ?? m.params.exceptionDetails.text));
  if (m.method === 'Runtime.consoleAPICalled' && ['error', 'warning'].includes(m.params.type)) logs.push('CONSOLE ' + m.params.args.map(a => a.value ?? a.description).join(' '));
  if (m.method === 'Network.responseReceived' && m.params.response.status >= 400) logs.push(`HTTP ${m.params.response.status} ${m.params.response.url}`);
  if (m.method === 'Network.loadingFailed' && !m.params.canceled) logs.push(`FAIL ${m.params.errorText}`);
});
const send = (method, params = {}) => new Promise(r => { const i = ++id; pending.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });

await send('Runtime.enable'); await send('Network.enable'); await send('Page.enable');
for (const c of cookies) await send('Network.setCookie', c);
await send('Page.navigate', { url });
await sleep(2500);
const shot = await send('Page.captureScreenshot', { format: 'png' });
writeFileSync(out, Buffer.from(shot.data, 'base64'));
console.log(logs.length ? logs.join('\n') : 'konsol hatası / başarısız istek yok');
console.log(`ekran görüntüsü: ${out}`);
ws.close(); chrome.kill();
process.exit(0);
