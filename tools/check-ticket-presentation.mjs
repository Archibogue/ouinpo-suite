import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

class Element {
  constructor(tag) { this.tag=tag;this.children=[];this.dataset={};this.listeners={};this.isConnected=true;this.classList={add(){},remove(){}}; }
  append(...nodes){nodes.forEach(n=>n.parentNode=this);this.children.push(...nodes);}
  prepend(...nodes){this.children.unshift(...nodes);}
  replaceChildren(...nodes){this.children=nodes;}
  setAttribute(){}
  addEventListener(name,fn){this.listeners[name]=fn;}
  querySelectorAll(){return [];}
  remove(){if(this.parentNode)this.parentNode.children=this.parentNode.children.filter(n=>n!==this);this.isConnected=false;}
}
const window={OuinpoTicketing:{name:'PataDesk'}};
const server={state:'submitted',submissions:[{submitted_at:'2026-09-20T14:16:00Z',markdown:'# Copie',markdown_html:'<h1>Copie</h1>',scenario:{tickets:[]},rubric:[{id:'one',title:'Premier critère',max:4,instruction:'Justifier',private:'Réservé'},{id:'two',title:'Second critère',max:6,instruction:'Vérifier',private:'Réservé'}]}],history:[],draft:{criteria:{one:{points:0,comment:'Zéro explicite'},two:{points:null,comment:''}}}};
vm.runInNewContext(fs.readFileSync(new URL('../assets/js/front/ticket-simulator.js',import.meta.url),'utf8'),{window,document:{createElement:tag=>new Element(tag),querySelectorAll:()=>[]},fetch:async()=>({ok:true,json:async()=>server})});
const ui=window.OuinpoTicketUI;
const nodes=n=>[n,...n.children.flatMap(nodes)];
const text=n=>nodes(n).map(v=>v.textContent||'').join('\n');
const legacy='Traces déclarées par l’élève (à vérifier) : '+JSON.stringify({information:'À confirmer <img src=x onerror=alert(1)>',proof:'ligne 1\nligne 2'});
const trace=ui.traceContent(legacy);
assert.equal(trace.tag,'dl');assert.match(text(trace),/Informations recherchées/);assert.match(text(trace),/À confirmer <img/);assert.equal(nodes(trace).some(n=>n.innerHTML),false);
assert.equal(ui.traceContent('Traces déclarées par l’élève (à vérifier) : {oops').tag,'pre');
assert.equal(ui.dateLabel('2026-09-20 14:16:00'),ui.dateLabel('2026-09-20T14:16:00Z'));
assert.match(ui.dateLabel('2026-09-20T14:16:00Z'),/20\/09\/2026/);
assert.equal(ui.dateLabel('not a date'),'Date indisponible');
for(const mode of ['practice','graded']) {
  const root=new Element('div');root.dataset.showTitle='0';
  new ui.Desk(root,{id:1,student_id:11,events:[],read_only:true,completion_status:'qualified',path_completed:true,assessment:{mode,state:'working',attempts:1,rubric:[]},tickets:[{id:'T',title:'Qualification',status:'new',exercise_completed:true,fields:{},done:[],resources:[],tests:{},actions:[{id:'take',type:'take',label:'Prendre en charge'}]}]},()=>{});
  assert.match(text(root),/Objectif pédagogique atteint/);
  assert.doesNotMatch(text(root),/Prochaine étape proposée|Travail en cours|Étape actuelle/);
  assert.equal(nodes(root).some(n=>n.tag==='h2'&&n.textContent==='PataDesk'),false);
  if(mode==='graded')assert.match(text(root),/travail reste à remettre/);
  else assert.match(text(root),/Aucune remise notée attendue/);
}
const grading=new Element('div');
await ui.Desk.prototype.assessmentPanel.call({a:{id:1,teacher_view:true,assessment:{mode:'graded',state:'submitted',rubric:[]}},root:grading},grading);
const inputs=nodes(grading).filter(n=>n.type==='number');
assert.equal(inputs[0].value,0);assert.equal(inputs[1].value,'');
assert.match(text(grading),/1 \/ 2 critères renseignés · 0 \/ 10 points/);
inputs[1].value='3';inputs[1].listeners.input();
assert.match(text(grading),/2 \/ 2 critères renseignés · 3 \/ 10 points/);
assert.equal(nodes(grading).filter(n=>n.innerHTML).length,1);
for(const delayed of ['/scenarios','/attempts']) {
  let complete;
  const pending=new Promise(resolve=>complete=resolve);
  const root=new Element('div');root.dataset.canManage='1';
  vm.runInNewContext(fs.readFileSync(new URL('../assets/js/admin/ticket-simulator-admin.js',import.meta.url),'utf8'),{
    window:{OuinpoTicketUI:{...ui,api:async path=>path===delayed?pending:[]},OuinpoTicketing:{name:'PataDesk'}},
    document:{querySelectorAll:()=>[root]},URLSearchParams,
  });
  await Promise.resolve();await Promise.resolve();
  await nodes(root).find(n=>n.textContent==='Remises, correction et export CSV').listeners.click();
  complete([]);await Promise.resolve();await Promise.resolve();await Promise.resolve();
  assert.match(text(root),/Remises et correction/);
  assert.doesNotMatch(text(root),/Filtrer par étudiant|Tentatives et observation/);
}
console.log('Presentation: legacy traces as literal text, French UTC conversion, qualified completion, pending submission, hidden title, zero vs unmarked and correction summary passed.');
console.log('Async navigation: late scenario and attempt lists cannot contaminate the results view.');
