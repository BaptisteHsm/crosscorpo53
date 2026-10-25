// Lit chaque PDF « Scratch » du dossier pdfs/ et produit data/cross-N.json + data/index.json.
// Lancé automatiquement par .github/workflows/build.yml à chaque dépôt de PDF.
import fs from "node:fs";
import path from "node:path";
import pdfjsLib from "pdfjs-dist/legacy/build/pdf.js";

const ROOT=path.resolve(path.dirname(new URL(import.meta.url).pathname),"..");
const norm=s=>String(s||"").normalize("NFD").replace(/[\u0300-\u036f]/g,"").toLowerCase();
const toSec=t=>{const p=t.split(":").map(Number);return p.length===3?p[0]*3600+p[1]*60+p[2]:p[0]*60+p[1]};
const isIND=c=>!c||/^IND$/i.test(c.trim());

async function pdfLines(buf){
  const pdf=await pdfjsLib.getDocument({data:buf}).promise;
  const out=[];
  for(let p=1;p<=pdf.numPages;p++){
    const page=await pdf.getPage(p);const tc=await page.getTextContent();
    const items=tc.items.filter(i=>i.str&&i.str.trim()!=="").map(i=>({s:i.str,x:i.transform[4],y:i.transform[5],w:i.width||0}));
    items.sort((a,b)=>b.y-a.y||a.x-b.x);
    let cur=[],cy=null;const flush=()=>{if(!cur.length)return;cur.sort((a,b)=>a.x-b.x);let s="",end=null;for(const it of cur){if(end!==null)s+=(it.x-end>0.8)?" ":"";s+=it.s;end=it.x+it.w}out.push(s.replace(/\s+/g," ").trim());cur=[]};
    for(const it of items){if(cy===null||Math.abs(it.y-cy)>2.5){flush();cy=it.y}cur.push(it)}
    flush();
  }
  return out;
}
const MONTHS={janvier:1,fevrier:2,mars:3,avril:4,mai:5,juin:6,juillet:7,aout:8,septembre:9,octobre:10,novembre:11,decembre:12};
const RE_ROW=/^(\d+)\s+(.+?)\s+(?:([A-Z]{3})\s+)?n°\s?(\d+)\s+(\d{2})\s+(\d+)\s+([A-Z][A-Z0-9]{1,3})\s+(\d+)\s+([MF])\s+(\d{1,2}:\d{2}:\d{2})\s+(\d+'\d{2})\s*(.*)$/;
const RE_TEAM=/^(\d+)\s*(?:er|ère|ere|ème|eme|e|re)\s+(?:(FEMININ|FÉMININ|MASCULIN|MIXTE|FEMMES?|HOMMES?)\s+)?(.+?)\s+Temps\s+(\d{1,2}:\d{2}:\d{2})\s+Moy\.?\s+(\S+)\s+Points\s+(\d+)(?:\s*\((\d+)\))?/i;
const RE_MEMBER=/^(\d+)\s+(.+?)\s+n°\s?(\d+)\s+(\d{1,2}:\d{2}:\d{2})/;
function parseLines(lines){
  const res={n:null,date:null,dists:{}};let sec=null,d=null,team=null;
  for(const raw of lines){
    const l=raw.trim();if(!l)continue;
    const flat=l.replace(/\s+/g,"").toUpperCase();
    const hd=l.match(/Cross\s+N°\s*(\d+).*?Cross\s+([A-Za-zÀ-ÿ]+)\s*\(([\d,\.]+)\s*km\)/i);
    if(hd){res.n=res.n||Number(hd[1]);d=norm(hd[2]);const km=parseFloat(hd[3].replace(",","."));
      res.dists[d]=res.dists[d]||{km,rows:[],teams:[]};
      const dm=[...l.matchAll(/(\d{1,2})\s+([A-Za-zéèûÉÛ]+)\s+(\d{4})/g)].find(x=>MONTHS[norm(x[2])]);
      if(dm&&!res.date)res.date=`${dm[3]}-${String(MONTHS[norm(dm[2])]).padStart(2,"0")}-${dm[1].padStart(2,"0")}`;
      continue}
    if(flat.startsWith("CLASSEMENTGENERAL")){sec="gen";continue}
    if(flat.startsWith("CLASSEMENTPAREQUIPES")){sec="team";team=null;continue}
    if(flat.startsWith("CLASSEMENTPARCATEGORIE")){sec="cat";continue}
    if(!d)continue;const D=res.dists[d];
    if(sec==="gen"){const m=l.match(RE_ROW);if(m)D.rows.push({clt:+m[1],nom:m[2],nat:m[3]||"",dos:+m[4],ne:m[5],cc:+m[6],cat:m[7],cs:+m[8],sx:m[9],t:toSec(m[10]),al:m[11],club:m[12].trim()})}
    else if(sec==="team"){
      const tm=l.match(RE_TEAM);
      if(tm){const nm=tm[3].match(/^(.*?)\s*-\s*(\d+)$/);team={rank:+tm[1],label:(tm[2]||(d==="court"?"FEMININ":"MIXTE")).toUpperCase(),club:nm?nm[1].trim():tm[3].trim(),num:nm?+nm[2]:1,pts:+tm[6],scr:tm[7]?+tm[7]:null,t:toSec(tm[4]),dos:[]};D.teams.push(team);continue}
      const mm=l.match(RE_MEMBER);if(mm&&team)team.dos.push(+mm[3]);
    }
  }
  for(const k of Object.keys(res.dists)){const D=res.dists[k];
    // dédoublonnage (pages répétées) et ordre
    const seen=new Set();D.rows=D.rows.filter(r=>{const key=r.clt+"|"+r.dos;if(seen.has(key))return false;seen.add(key);return true}).sort((a,b)=>a.clt-b.clt);
    if(!D.rows.length)delete res.dists[k];
  }
  return res;
}
/* Classement équipes de secours si le PDF n'en contient pas : par ordre d'arrivée, 3 femmes (court) ou 4 coureurs (long). */
function computeTeams(rows,d){
  const size=d==="court"?3:4;const pool=rows.filter(r=>!isIND(r.club)&&(d!=="court"||r.sx==="F"));
  const by={};for(const r of pool)(by[r.club]=by[r.club]||[]).push(r);
  const teams=[];
  for(const [club,list] of Object.entries(by)){list.sort((a,b)=>a.clt-b.clt);for(let i=0;i+size<=list.length;i+=size){const g=list.slice(i,i+size);
    teams.push({club,num:i/size+1,label:d==="court"?"FEMININ":"MIXTE",pts:g.reduce((s,r)=>s+(d==="court"?r.cs:r.clt),0),scr:g.reduce((s,r)=>s+r.clt,0),t:g.reduce((s,r)=>s+r.t,0),dos:g.map(r=>r.dos),computed:true})}}
  teams.sort((a,b)=>a.pts-b.pts||a.scr-b.scr);teams.forEach((t,i)=>t.rank=i+1);return teams;
}

/* ---------- Stockage partagé ---------- */
const packRow=r=>[r.clt,r.nom,r.nat,r.dos,r.ne,r.cc,r.cat,r.cs,r.sx,r.t,r.al,r.club];
const unpackRow=a=>({clt:a[0],nom:a[1],nat:a[2],dos:a[3],ne:a[4],cc:a[5],cat:a[6],cs:a[7],sx:a[8],t:a[9],al:a[10],club:a[11]});
const packTeam=t=>[t.rank,t.label,t.club,t.num,t.pts,t.scr,t.t,t.dos,t.computed?1:0];
const unpackTeam=a=>({rank:a[0],label:a[1],club:a[2],num:a[3],pts:a[4],scr:a[5],t:a[6],dos:a[7],computed:!!a[8]});

const pdfDir=path.join(ROOT,"pdfs"),dataDir=path.join(ROOT,"data");
fs.mkdirSync(dataDir,{recursive:true});
let failed=false;
for(const f of fs.readdirSync(pdfDir).filter(f=>/\.pdf$/i.test(f)).sort()){
  const buf=new Uint8Array(fs.readFileSync(path.join(pdfDir,f)));
  const p=parseLines(await pdfLines(buf));
  if(!p.n||!Object.keys(p.dists).length){console.error(`✗ ${f} : aucun classement général reconnu`);failed=true;continue}
  const out={n:p.n,date:p.date,source:f,dists:{}};
  for(const [d,D] of Object.entries(p.dists)){
    const teams=D.teams.length?D.teams:computeTeams(D.rows,d);
    out.dists[d]={km:D.km,rows:D.rows.map(packRow),teams:teams.map(packTeam)};
    console.log(`✓ ${f} → cross N°${p.n} ${d} : ${D.rows.length} arrivants, ${teams.length} équipes`);
  }
  fs.writeFileSync(path.join(dataDir,`cross-${p.n}.json`),JSON.stringify(out));
}
const races=fs.readdirSync(dataDir).filter(f=>/^cross-\d+\.json$/.test(f)).map(f=>JSON.parse(fs.readFileSync(path.join(dataDir,f)))).sort((a,b)=>a.n-b.n)
  .map(r=>({n:r.n,date:r.date,file:`cross-${r.n}.json`,dists:Object.fromEntries(Object.entries(r.dists).map(([d,D])=>[d,{km:D.km,count:D.rows.length}]))}));
const idxPath=path.join(dataDir,"index.json");
let prev=null;try{prev=JSON.parse(fs.readFileSync(idxPath))}catch{}
const same=prev&&JSON.stringify(prev.races)===JSON.stringify(races);
fs.writeFileSync(idxPath,JSON.stringify({updated:same?prev.updated:new Date().toISOString(),races},null,1));
console.log(`index.json : ${races.length} cross`);
if(failed)process.exit(1);
