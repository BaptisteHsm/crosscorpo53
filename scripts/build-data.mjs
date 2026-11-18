// Lit chaque PDF « Scratch » du dossier pdfs/ et produit data/cross-N.json + data/index.json.
// Lancé automatiquement par .github/workflows/build.yml à chaque dépôt de PDF.
import fs from "node:fs";
import path from "node:path";
import pdfjsLib from "pdfjs-dist/legacy/build/pdf.js";

const ROOT=path.resolve(path.dirname(new URL(import.meta.url).pathname),"..");
import{norm,toSec,isIND,pdfLines,parseLines,computeTeams,packRow,unpackRow,packTeam,unpackTeam,hav,simplify,firstLap,gpxPoints,trackLength,ageBand}from"./parse-core.mjs";
const pdfDir=path.join(ROOT,"pdfs"),dataDir=path.join(ROOT,"data");
fs.mkdirSync(dataDir,{recursive:true});
let failed=false;
for(const f of fs.readdirSync(pdfDir).filter(f=>/\.pdf$/i.test(f)).sort()){
  const buf=new Uint8Array(fs.readFileSync(path.join(pdfDir,f)));
  const p=parseLines(await pdfLines(pdfjsLib,buf));(p.warnings||[]).forEach(w=>console.warn("  ⚠ ligne mal formée dans le PDF, récupérée au mieux :",w));
  if(!p.n||!Object.keys(p.dists).length){console.error(`✗ ${f} : aucun classement général reconnu`);failed=true;continue}
  const out={n:p.n,date:p.date,dists:{}};
  for(const [d,D] of Object.entries(p.dists)){
    const teams=D.teams.length?D.teams:computeTeams(D.rows,d);
    out.dists[d]={km:D.km,rows:D.rows.map(packRow),teams:teams.map(packTeam)};
    console.log(`✓ ${f} → cross N°${p.n} ${d} : ${D.rows.length} arrivants, ${teams.length} équipes`);
  }
  fs.writeFileSync(path.join(dataDir,`cross-${p.n}.json`),JSON.stringify(out));
  fs.unlinkSync(path.join(pdfDir,f)); // le PDF n'est pas conservé dans le dépôt public
}
// Tracés GPX : gpx/*.gpx (nom contenant le numéro du cross, ex. cross-1.gpx ou "Cross corpo #1").
// On ne garde que les coordonnées (pas les horaires ni l'altitude), puis le GPX est supprimé.
// Le tracé du cross long sert aussi au cross court : premier tour détecté (retour au départ après 3 km).
const gpxDir=path.join(ROOT,"gpx");
if(fs.existsSync(gpxDir))for(const f of fs.readdirSync(gpxDir).filter(f=>/\.gpx$/i.test(f))){
  const s=fs.readFileSync(path.join(gpxDir,f),"utf8");
  const name=(s.match(/<name>([^<]*)<\/name>/)||[])[1]||"";
  const num=(f.match(/(\d+)/)||name.match(/#\s*(\d+)/)||name.match(/(\d+)/)||[])[1];
  const P=gpxPoints(s);
  if(!num||P.length<10){console.error(`✗ ${f} : numéro de cross ou tracé introuvable`);failed=true;continue}
  const len=trackLength(P);
  const tp=path.join(dataDir,`track-${num}.json`);let tr={};try{tr=JSON.parse(fs.readFileSync(tp))}catch{}
  const isCourt=/court/i.test(f+name)||len<6000;
  if(isCourt)tr.court=simplify(P);else{tr.long=simplify(P);const lap=firstLap(P);if(lap&&!tr.courtFromFile)tr.court=simplify(lap)}
  if(isCourt)tr.courtFromFile=true;
  fs.writeFileSync(tp,JSON.stringify(tr));fs.unlinkSync(path.join(gpxDir,f));
  console.log(`✓ ${f} → tracé du cross N°${num} (${Math.round(len)} m${isCourt?", cross court":""})`);
}

// Minimisation et droit d'opposition, appliqués à tous les cross publiés.
// - l'année de naissance est remplacée par une tranche d'âge ;
// - les dossards listés dans le secret GitHub EXCLUSIONS (ex. "1234,5678") sont masqués.
const EXCL=new Set(String(process.env.EXCLUSIONS||"").split(/[\s,;]+/).filter(Boolean).map(Number));
let maskedCount=0;
for(const f of fs.readdirSync(dataDir).filter(f=>/^cross-\d+\.json$/.test(f))){
  const fp=path.join(dataDir,f);const c=JSON.parse(fs.readFileSync(fp));
  for(const D of Object.values(c.dists)){
    const remap={};
    for(const r of D.rows){
      r[4]=ageBand(r[4]);
      if(r[3]>0&&EXCL.has(r[3])){remap[r[3]]=-r[0];r[3]=-r[0];r[1]="Coureur masqué";r[2]="";maskedCount++}
    }
    for(const tm of D.teams)tm[7]=tm[7].map(x=>remap[x]??x);
  }
  delete c.source;
  fs.writeFileSync(fp,JSON.stringify(c));
}
if(maskedCount)console.log(`${maskedCount} résultat(s) masqué(s)`);

const races=fs.readdirSync(dataDir).filter(f=>/^cross-\d+\.json$/.test(f)).map(f=>JSON.parse(fs.readFileSync(path.join(dataDir,f)))).sort((a,b)=>a.n-b.n)
  .map(r=>({n:r.n,date:r.date,file:`cross-${r.n}.json`,track:fs.existsSync(path.join(dataDir,`track-${r.n}.json`))?`track-${r.n}.json`:undefined,dists:Object.fromEntries(Object.entries(r.dists).map(([d,D])=>[d,{km:D.km,count:D.rows.length}]))}));
const idxPath=path.join(dataDir,"index.json");
let prev=null;try{prev=JSON.parse(fs.readFileSync(idxPath))}catch{}
const same=prev&&JSON.stringify(prev.races)===JSON.stringify(races);
// Tracés disponibles, y compris pour les cross pas encore courus (vue « Analyse » du parcours).
const tracks=fs.readdirSync(dataDir).map(f=>(f.match(/^track-(\d+)\.json$/)||[])[1]).filter(Boolean).map(Number).sort((a,b)=>a-b);
fs.writeFileSync(idxPath,JSON.stringify({updated:same?prev.updated:new Date().toISOString(),races,tracks},null,1));
console.log(`index.json : ${races.length} cross`);
if(failed)process.exit(1);
