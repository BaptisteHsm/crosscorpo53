// Construit la version « module WordPress » du site à partir de index.html.
// Sortie : _site/wp/app.css, app.js, version.json, fonts/ et le plugin zippé (cc53-resultats.zip).
// Le plugin WordPress charge ces fichiers depuis GitHub Pages : toute mise à jour du site
// (nouveaux résultats, corrections) arrive sur WordPress sans réinstaller le plugin.
import fs from "node:fs";
import path from "node:path";
import crypto from "node:crypto";
import { execSync } from "node:child_process";
import postcss from "postcss";
import prefixer from "postcss-prefix-selector";

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), "..");
const out = path.join(root, "_site", "wp");
fs.mkdirSync(path.join(out, "fonts"), { recursive: true });

const html = fs.readFileSync(path.join(root, "index.html"), "utf8");
const css = html.slice(html.indexOf("<style>") + 7, html.indexOf("</style>"));
let js = html.slice(html.indexOf("<script>") + 8, html.lastIndexOf("</script>"));

// ---------- CSS : tout est limité au conteneur .cc53 pour ne pas toucher au thème ----------
const SCOPE = ".cc53";
const scoped = postcss([
  prefixer({
    prefix: SCOPE,
    transform(prefix, selector, prefixed) {
      if (selector === ":root" || selector === "body" || selector === "html") return prefix;
      if (selector === "*") return `${prefix}, ${prefix} *`;
      if (selector.startsWith(":focus-visible")) return `${prefix} ${selector}`;
      return prefixed;
    },
  }),
]).process(css, { from: undefined }).css;

const fontsDir = (p) => path.join(root, "node_modules", "@fontsource", p, "files");
const fonts = [
  ["Contrail One", "contrail-one", "contrail-one-latin-400-normal.woff2", 400],
  ["Open Sans", "open-sans", "open-sans-latin-400-normal.woff2", 400],
  ["Open Sans", "open-sans", "open-sans-latin-600-normal.woff2", 600],
  ["Open Sans", "open-sans", "open-sans-latin-700-normal.woff2", 700],
];
let fontCss = "";
for (const [fam, pkg, file, w] of fonts) {
  fs.copyFileSync(path.join(fontsDir(pkg), file), path.join(out, "fonts", file));
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
${SCOPE} a{text-decoration:underline}
${SCOPE} a.btn,${SCOPE} .homebtns a,${SCOPE} .back,${SCOPE} .hr-b a{text-decoration:none}
${SCOPE} #overlay{position:relative;z-index:99999}
/* Sur le site officiel, le bouton « Site officiel » ne sert à rien */
${SCOPE} .homebtns{grid-template-columns:repeat(2,1fr)}
${SCOPE} .homebtns a{display:none}
${SCOPE} .aud{display:none!important}
${SCOPE} .cc53-credit{margin-top:36px;font-size:12px;color:var(--ink-3);text-align:center}
${SCOPE} .cc53-credit a{color:inherit}
`;
fs.writeFileSync(path.join(out, "app.css"), fontCss + reset + scoped + extra);

// ---------- JS : données lues sur GitHub Pages, conteneur au lieu de la page entière ----------
const rep = (a, b) => {
  if (!js.includes(a)) throw new Error("Motif introuvable dans index.html : " + a);
  js = js.split(a).join(b);
};
rep('fetch("data/index.json"', 'fetch(DATA+"index.json"');
rep('fetch("data/"+m.file', "fetch(DATA+m.file");
rep("fetch(`data/track-${n}.json`", "fetch(`${DATA}track-${n}.json`");
rep('fetch("data/"+meta.track', "fetch(DATA+meta.track");
rep('t.textContent=msg;document.body.appendChild(t)', "t.textContent=msg;ROOT.appendChild(t)");
rep("window.scrollTo({top:0})", "cc53Top()");
rep("function audStart(){", "function audStart(){return;");
rep("(site non officiel)", "");
js = `/* Cross Corpo 53 – résultats · module WordPress (généré depuis index.html) */
(function(){
const ROOT=document.getElementById("cc53-app");if(!ROOT||ROOT.dataset.ready)return;ROOT.dataset.ready="1";
const CFG=window.CC53_CONFIG||{};const DATA=CFG.data||"https://baptistehsm.github.io/crosscorpo53/data/";
function cc53Top(){const y=ROOT.getBoundingClientRect().top+window.scrollY-(CFG.offset||90);if(window.scrollY>y)window.scrollTo({top:Math.max(0,y)})}
${js}
})();
`;
fs.writeFileSync(path.join(out, "app.js"), js);

// ---------- Version (le plugin s'en sert pour éviter le cache des navigateurs) ----------
const hash = crypto.createHash("sha1").update(fs.readFileSync(path.join(out, "app.css"))).update(js).digest("hex").slice(0, 10);
fs.writeFileSync(path.join(out, "version.json"), JSON.stringify({ v: hash, built: new Date().toISOString() }));

// ---------- Plugin zippé, prêt à téléverser dans WordPress ----------
const zip = path.join(out, "cc53-resultats.zip");
fs.rmSync(zip, { force: true });
execSync(`zip -qr "${zip}" cc53-resultats`, { cwd: path.join(root, "wordpress") });
console.log(`✓ module WordPress ${hash} → _site/wp/`);
