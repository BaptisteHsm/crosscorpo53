// Construit l'extension WordPress autonome « cc53-resultats » à partir de index.html.
// Sortie : dist/cc53-resultats/ et dist/cc53-resultats.zip (à téléverser dans WordPress).
// L'extension n'a aucun lien avec GitHub : application, polices, bibliothèques de carte,
// lecteur de PDF et données de départ de la saison sont inclus dans le zip.
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { execSync } from "node:child_process";
import postcss from "postcss";
import prefixer from "postcss-prefix-selector";

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), "..");
const dist = path.join(root, "dist");
const plug = path.join(dist, "cc53-resultats");
const nm = (...p) => path.join(root, "node_modules", ...p);
fs.rmSync(dist, { recursive: true, force: true });
fs.cpSync(path.join(root, "wordpress", "cc53-resultats"), plug, { recursive: true });
const A = path.join(plug, "assets");
for (const d of ["fonts", "vendor", "vendor/images"]) fs.mkdirSync(path.join(A, d), { recursive: true });

const html = fs.readFileSync(path.join(root, "index.html"), "utf8");
const css = html.slice(html.indexOf("<style>") + 7, html.indexOf("</style>"));
let js = html.slice(html.indexOf("<script>") + 8, html.lastIndexOf("</script>"));

/* ---------- Déclarations « const X=… » : lecture (données de départ) et remplacement ---------- */
function declSpan(src, name) {
  const start = src.indexOf(`const ${name}=`);
  if (start < 0) throw new Error("Déclaration introuvable : " + name);
  let i = start + `const ${name}=`.length;
  const open = src[i];
  if (open !== "[" && open !== "{") { const end = src.indexOf(";", i); return [start, end + 1, src.slice(i, end)]; }
  const close = open === "[" ? "]" : "}";
  let depth = 0, q = null;
  for (; i < src.length; i++) {
    const c = src[i];
    if (q) { if (c === "\\") { i++; continue; } if (c === q) q = null; continue; }
    if (c === '"' || c === "'" || c === "`") { q = c; continue; }
    if (c === open) depth++;
    else if (c === close && --depth === 0) break;
  }
  const end = src[i + 1] === ";" ? i + 2 : i + 1;
  return [start, end, src.slice(start + `const ${name}=`.length, i + 1)];
}
const ctx = { OFF: "https://www.crosscorpo53.fr/cross/" };
vm.createContext(ctx);
const evalDecl = (name) => vm.runInContext("(" + declSpan(js, name)[2] + ")", ctx);
ctx.OFF = evalDecl("OFF");
const CAL = evalDecl("CAL"), ORGS = evalDecl("ORGS"), LOGOS = evalDecl("LOGOS"), INFO = evalDecl("COURSE_INFO"), TOWNS = evalDecl("TOWNS");
const replaceDecl = (name, expr) => { const [s, e] = declSpan(js, name); js = js.slice(0, s) + `const ${name}=${expr};` + js.slice(e); };

/* ---------- Données de départ : les cross de la saison et les résultats déjà publiés ---------- */
const seed = path.join(plug, "seed");
fs.mkdirSync(path.join(seed, "logos"), { recursive: true });
fs.mkdirSync(path.join(seed, "data"), { recursive: true });
const crosses = CAL.map((c) => {
  const logos = LOGOS.filter((l) => l.r === c.n);
  const sponsors = (ORGS[c.n] || []).map((name, i) => {
    let file = null;
    const l = logos[i];
    if (l && /^data:image\/(\w+);base64,/.test(l.src)) {
      const ext = l.src.match(/^data:image\/(\w+);/)[1].replace("jpeg", "jpg");
      file = `cross${c.n}-${String(l.name || i).replace(/[^a-z0-9-]+/gi, "-").toLowerCase()}.${ext}`;
      fs.writeFileSync(path.join(seed, "logos", file), Buffer.from(l.src.split(",")[1], "base64"));
    }
    return { name, file };
  });
  const inf = INFO[c.n] || {};
  const t = TOWNS[c.n];
  return {
    n: c.n, date: c.date, lieu: c.lieu, url: c.url,
    note: inf.note || "", pknote: inf.pkNote || "",
    start: t && t[2] ? `${t[1]}, ${t[0]}` : "",
    parkings: (inf.parkings || []).map((p) => ({ label: p.label || "Parking", pos: `${p.ll[1]}, ${p.ll[0]}`, sub: p.sub || "" })),
    sponsors,
  };
});
fs.writeFileSync(path.join(seed, "crosses.json"), JSON.stringify(crosses, null, 1));
for (const f of fs.readdirSync(path.join(root, "data")).filter((f) => /^(cross|track)-\d+\.json$/.test(f)))
  fs.copyFileSync(path.join(root, "data", f), path.join(seed, "data", f));

/* ---------- Application publique ---------- */
const rep = (a, b) => { if (!js.includes(a)) throw new Error("Motif introuvable dans index.html : " + a); js = js.split(a).join(b); };
replaceDecl("CAL", "CFG.cal||[]");
replaceDecl("ORGS", "CFG.orgs||{}");
replaceDecl("LOGOS", "CFG.logos||[]");
replaceDecl("COURSE_INFO", "CFG.info||{}");
replaceDecl("TOWNS", "CFG.towns||{}");
replaceDecl("DEP53", "[]");
replaceDecl("AUDIENCE", "{}");
replaceDecl("CONTACT_URL", 'CFG.contact||""');
replaceDecl("RACE_START", "CFG.raceStart||{court:9*3600+15*60,long:9*3600+45*60}");
rep('fetch("data/index.json"', 'fetch(DATA+"index.json"');
rep('fetch("data/"+m.file', "fetch(DATA+m.file");
rep("fetch(`data/track-${n}.json`", "fetch(`${DATA}track-${n}.json`");
rep('fetch("data/"+meta.track', "fetch(DATA+meta.track");
rep('"https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css"', 'CFG.vendor+"maplibre-gl.css"');
rep('"https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"', 'CFG.vendor+"maplibre-gl.js"');
rep('"https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"', 'CFG.vendor+"leaflet.css"');
rep('"https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"', 'CFG.vendor+"leaflet.js"');
rep("t.textContent=msg;document.body.appendChild(t)", "t.textContent=msg;ROOT.appendChild(t)");
rep("window.scrollTo({top:0})", "cc53Top()");
rep("function audStart(){", "function audStart(){return;");
rep("(site non officiel)", "");
rep("Saison 2026-2027 · ${races.length}", "Saison ${SEASON} · ${races.length}");
// Page « Données personnelles » adaptée au site de l'association.
const [ms] = [js.indexOf("function pageMentions(){")];
const me = js.indexOf("/* ---------- Rendu principal ---------- */", ms);
if (ms < 0 || me < 0) throw new Error("pageMentions introuvable");
js = js.slice(0, ms) + `function pageMentions(){
  const c=CONTACT_URL.trim();const href=/^[^@\\s]+@[^@\\s]+$/.test(c)?"mailto:"+c:c;
  return\`<a class="back" href="#accueil" data-go="accueil">← Accueil</a>
  <div class="pagehead"><div><h2>Résultats et données personnelles</h2></div></div>
  <div class="legal">
    <h3>Données publiées</h3>
    <p>Les résultats proviennent des classements établis par le chronométreur (GmCAP) après chaque cross. Pour chaque coureur sont affichés : nom, prénom, dossard, sexe, catégorie, équipe ou entreprise, place, temps et allure. L'année de naissance n'est pas publiée : seules des tranches d'âge servent aux statistiques.</p>
    <h3>Pourquoi</h3>
    <p>Permettre aux participants de consulter leurs résultats, ceux de leur équipe et les classements de la saison \${SEASON}. Ces pages ne déposent aucun cookie.</p>
    <h3>Vos droits</h3>
    <p>Vous pouvez vous opposer à la publication de vos résultats ou en demander la rectification. Votre nom est alors remplacé par « Coureur masqué », sans modifier les classements des autres participants.\${c?\` Pour toute demande : <a href="\${esc(href)}" target="_blank" rel="noopener">\${esc(c.replace(/^https?:\\/\\//,"").replace(/^mailto:/,""))}</a>, en indiquant votre nom et votre numéro de dossard.\`:""} Vous pouvez aussi adresser une réclamation à la <a href="https://www.cnil.fr/fr/plaintes" target="_blank" rel="noopener">CNIL</a>.</p>
    <h3>Cartes</h3>
    <p>Les pages « Analyse du parcours » et « Revivre la course » affichent des fonds de carte fournis par OpenFreeMap, Esri et Mapzen / AWS : votre navigateur les télécharge auprès de ces services.</p>
  </div>\`;
}
` + js.slice(me);
js = `/* Cross Corpo 53 – résultats · extension WordPress (générée depuis index.html) */
(function(){
const ROOT=document.getElementById("cc53-app");if(!ROOT||ROOT.dataset.ready)return;ROOT.dataset.ready="1";
const CFG=window.CC53_CONFIG||{};const DATA=CFG.data||"";const SEASON=CFG.season||"";
function cc53Top(){const y=ROOT.getBoundingClientRect().top+window.scrollY-(CFG.offset||90);if(window.scrollY>y)window.scrollTo({top:Math.max(0,y)})}
${js}
})();
`;
new Function(js); // contrôle de syntaxe
fs.writeFileSync(path.join(A, "app.js"), js);

/* ---------- Styles : tout est limité au conteneur .cc53 ---------- */
const SCOPE = ".cc53";
const scoped = postcss([prefixer({
  prefix: SCOPE,
  transform(prefix, selector, prefixed) {
    if (selector === ":root" || selector === "body" || selector === "html") return prefix;
    if (selector === "*") return `${prefix}, ${prefix} *`;
    if (selector.startsWith(":focus-visible")) return `${prefix} ${selector}`;
    return prefixed;
  },
})]).process(css, { from: undefined }).css;
const fonts = [
  ["Contrail One", "contrail-one", "contrail-one-latin-400-normal.woff2", 400],
  ["Open Sans", "open-sans", "open-sans-latin-400-normal.woff2", 400],
  ["Open Sans", "open-sans", "open-sans-latin-600-normal.woff2", 600],
  ["Open Sans", "open-sans", "open-sans-latin-700-normal.woff2", 700],
];
let fontCss = "";
for (const [fam, pkg, file, w] of fonts) {
  fs.copyFileSync(nm("@fontsource", pkg, "files", file), path.join(A, "fonts", file));
  fontCss += `@font-face{font-family:"${fam}";font-style:normal;font-weight:${w};font-display:swap;src:url(fonts/${file}) format("woff2")}\n`;
}
// Remise à zéro placée AVANT les styles du module : elle bat les styles génériques du thème
// (button, h2, table, a…) mais laisse gagner les styles du module, plus précis.
const reset = `
${SCOPE} :where(h1,h2,h3,h4){color:inherit;border:0;padding:0;margin:0;background:none;font-size:inherit;letter-spacing:normal;text-transform:none;font-weight:400}
${SCOPE} :where(button){padding:0;margin:0;background:none;border:0;border-radius:0;color:inherit;font:inherit;text-transform:none;letter-spacing:normal;min-height:0;min-width:0;box-shadow:none;width:auto;line-height:inherit;text-shadow:none}
${SCOPE} :where(input,select,textarea){font:inherit;color:inherit;margin:0;min-height:0;box-shadow:none;letter-spacing:normal;max-width:100%}
${SCOPE} :where(table){border:0;border-collapse:separate;margin:0;width:100%;background:none}
${SCOPE} :where(th,td){border:0;padding:0;background:none}
${SCOPE} :where(a){font-weight:inherit;text-decoration:inherit;box-shadow:none}
${SCOPE} :where(img){max-width:none;border:0;box-shadow:none;border-radius:0;height:auto}
${SCOPE} :where(ul,ol){margin:0;padding:0}
${SCOPE} :where(label){font-weight:inherit;margin:0}
`;
const extra = `
/* Intégration WordPress */
${SCOPE}{display:block;width:100%;max-width:1180px;margin-inline:auto;padding-block:8px 40px;text-align:left;background:transparent}
${SCOPE}.cc53{max-width:min(1180px,100%)!important;margin-inline:auto!important}
${SCOPE} a{text-decoration:underline}
${SCOPE} a.btn,${SCOPE} .homebtns a,${SCOPE} .back,${SCOPE} .hr-b a{text-decoration:none}
${SCOPE} a[href="#cc53-nourl"]{display:none!important}
${SCOPE} #overlay{position:relative;z-index:99999}
${SCOPE} .homebtns{grid-template-columns:repeat(2,1fr)}
${SCOPE} .homebtns a{display:none}
${SCOPE} .aud{display:none!important}
${SCOPE} .cc53-loading{text-align:center;padding:40px 0;opacity:.6}
${SCOPE} .cc53-credit{margin-top:36px;font-size:12px;color:var(--ink-3);text-align:center}
${SCOPE} .cc53-credit a{color:inherit}
${SCOPE} .cc53-credit #syncState:empty{display:none}
`;
fs.writeFileSync(path.join(A, "app.css"), fontCss + reset + scoped + extra);

/* ---------- Bibliothèques (servies par WordPress, aucune dépendance à un CDN) ---------- */
const cp = (from, to) => fs.copyFileSync(from, path.join(A, "vendor", to));
cp(nm("maplibre-gl", "dist", "maplibre-gl.js"), "maplibre-gl.js");
cp(nm("maplibre-gl", "dist", "maplibre-gl.css"), "maplibre-gl.css");
cp(nm("leaflet", "dist", "leaflet.js"), "leaflet.js");
cp(nm("leaflet", "dist", "leaflet.css"), "leaflet.css");
for (const f of fs.readdirSync(nm("leaflet", "dist", "images"))) cp(nm("leaflet", "dist", "images", f), "images/" + f);
cp(nm("pdfjs-dist", "build", "pdf.min.js"), "pdf.min.js");
cp(nm("pdfjs-dist", "build", "pdf.worker.min.js"), "pdf.worker.min.js");
fs.copyFileSync(path.join(root, "scripts", "parse-core.mjs"), path.join(A, "parse-core.js"));

/* ---------- Zip ---------- */
execSync(`zip -qr cc53-resultats.zip cc53-resultats`, { cwd: dist });
const size = (fs.statSync(path.join(dist, "cc53-resultats.zip")).size / 1048576).toFixed(1);
console.log(`✓ extension WordPress → dist/cc53-resultats.zip (${size} Mo, ${crosses.length} cross, ${fs.readdirSync(path.join(seed, "logos")).length} logos)`);
