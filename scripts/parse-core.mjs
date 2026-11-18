// Lecture des PDF « Scratch » et des traces GPX. Code partagé entre le script Node
// (scripts/build-data.mjs) et l'extension WordPress (lecture dans le navigateur).
const norm=s=>String(s||"").normalize("NFD").replace(/[\u0300-\u036f]/g,"").toLowerCase();
const toSec=t=>{const p=t.split(":").map(Number);return p.length===3?p[0]*3600+p[1]*60+p[2]:p[0]*60+p[1]};
const isIND=c=>!c||/^IND$/i.test(c.trim());

async function pdfLines(pdfjsLib,buf){
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
// Ligne dont les colonnes sont décalées dans le PDF (année manquante, catégorie à la place de la nationalité).
const RE_ROW_BAD=/^(\d+)\s+(.+?)\s+([A-Z][A-Z0-9]{1,3})\s+n°\s?(\d+)\s+(\d+)\s+([A-Z][A-Z0-9]{1,3})\s+(\d+)\s+([MF])\s+(\d{1,2}:\d{2}:\d{2})\s+(\d+'\d{2})\s*(.*)$/;
const RE_CAT=/^(SE|ES|JU|CA|MI|BE|PO|M\d|V\d)[MF]$/;
const CAT_BAND={ES:"16-19",JU:"16-19",CA:"16-19",SE:"20-29",M0:"30-39",M1:"40-49",M2:"40-49",M3:"50-59",M4:"50-59",M5:"60-69",M6:"60-69",M7:"70+",M8:"70+",M9:"70+"};
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
    if(sec==="gen"){const m=l.match(RE_ROW);if(m)D.rows.push({clt:+m[1],nom:m[2],nat:m[3]||"",dos:+m[4],ne:m[5],cc:+m[6],cat:m[7],cs:+m[8],sx:m[9],t:toSec(m[10]),al:m[11],club:m[12].trim()});
      else{const b=l.match(RE_ROW_BAD);if(b){const cat=RE_CAT.test(b[3])?b[3]:b[6];const sx=/[MF]$/.test(cat)?cat.slice(-1):b[8];
        D.rows.push({clt:+b[1],nom:b[2],nat:"",dos:+b[4],ne:CAT_BAND[cat.slice(0,2)]||"",cc:+b[5],cat,cs:null,sx,t:toSec(b[9]),al:b[10],club:/^[A-Z]{3}$/.test(b[11].trim())?"":b[11].trim()});
        (res.warnings=res.warnings||[]).push(b[1]+" "+b[2])}}}
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

const hav=(a,b)=>{const R=6371000,r=Math.PI/180;const dLa=(b[0]-a[0])*r,dLo=(b[1]-a[1])*r;const x=Math.sin(dLa/2)**2+Math.cos(a[0]*r)*Math.cos(b[0]*r)*Math.sin(dLo/2)**2;return 2*R*Math.asin(Math.sqrt(x))};
function simplify(P,step=4){const out=[P[0]];let acc=0;for(let i=1;i<P.length;i++){acc+=hav(P[i-1],P[i]);if(acc>=step){out.push(P[i]);acc=0}}if(out[out.length-1]!==P[P.length-1])out.push(P[P.length-1]);return out.map(([a,b])=>[+a.toFixed(6),+b.toFixed(6)])}
function firstLap(P){let cum=0;for(let i=1;i<P.length;i++){cum+=hav(P[i-1],P[i]);if(cum>3000&&hav(P[i],P[0])<60)return P.slice(0,i+1)}return null}
function gpxPoints(s){return [...s.matchAll(/<trkpt[^>]*lat="([-\d.]+)"[^>]*lon="([-\d.]+)"/g)].map(m=>[+m[1],+m[2]])}
function trackLength(P){return P.slice(1).reduce((s,p,i)=>s+hav(P[i],p),0)}
const ageBand=ne=>{if(/[-+]/.test(String(ne)))return String(ne);const y=Number(ne);const a=2026-(y<=40?2000+y:1900+y);return a<20?"16-19":a>=70?"70+":Math.floor(a/10)*10+"-"+(Math.floor(a/10)*10+9)};

export{norm,toSec,isIND,pdfLines,parseLines,computeTeams,packRow,unpackRow,packTeam,unpackTeam,hav,simplify,firstLap,gpxPoints,trackLength,ageBand};
