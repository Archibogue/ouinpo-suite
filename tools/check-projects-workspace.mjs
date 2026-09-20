import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const requests=[];
const document={body:{},activeElement:null,addEventListener(){},querySelectorAll:()=>[],createElement:()=>node()};
function node() {
  return {dataset:{},attributes:{},disabled:false,isConnected:true,children:[],handlers:{},
    setAttribute(key,value){this.attributes[key]=value;},
    appendChild(child){this.children.push(child);},
    insertBefore(child){this.children.push(child);},
    addEventListener(name,fn){this.handlers[name]=fn;},
    focus(){document.activeElement=this;}};
}
const window={OuinpoProjects:{nonce:'test',root:'/projects'},location:{reload(){throw new Error('Page reload forbidden');}}};
const source=fs.readFileSync(new URL('../assets/js/front/projects.js',import.meta.url),'utf8');
vm.runInNewContext(source.replace('  /* boot */','  window.testSaveItem = saveItem; window.testChoices = syncDeliverableChoices;\n  /* boot */'),{
  window,document,DOMParser:class {
    parseFromString(html) {return {querySelector:()=>html==='valid' ? {childNodes:[{saved:true}],querySelectorAll:()=>[]} : null};}
  },
  fetch:(path,options)=>new Promise((resolve,reject)=>requests.push({path,options,resolve:data=>resolve({ok:true,json:async()=>data}),reject}))
});
function fixture(id=1) {
  const input={...node(),value:'Work in progress'};
  const button=node();
  const list={replacements:0,replaceChildren(...children){this.replacements++;this.children=children;}};
  const root={...node(),dataset:{projectId:String(id)},querySelector(selector){
    if(selector==='[data-ouinpo-projects-list]')return list;
    return this.children.find(child=>child.dataset.projectsSaveStatus);
  },querySelectorAll(){return [input,button];}};
  const form={resets:0,reset(){this.resets++;input.value='';}};
  return {root,input,button,list,form,notice:()=>root.children[0]};
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
const a=fixture(), neighbour=fixture(2);
document.activeElement=a.button;
const saving=window.testSaveItem(a.root,'journal','/projects/1/logs',{method:'POST'},a.form);
await window.testSaveItem(a.root,'journal','/projects/1/logs',{method:'POST'},a.form);
assert.equal(requests.length,1,'Double submit sends only one write');
assert(a.input.disabled && a.button.disabled);
assert.equal(neighbour.input.disabled,false);
neighbour.input.value='Other form typed while saving';
requests.shift().resolve({id:1});
await tick();
assert.equal(a.form.resets,1);
assert.match(requests[0].path,/workspace\/journal$/);
requests.shift().resolve({html:'valid'});
await saving;
assert.equal(a.list.replacements,1);
assert.equal(neighbour.input.value,'Other form typed while saving');
assert.equal(a.input.disabled,false);
assert.equal(document.activeElement,a.button);

const b=fixture();
const failing=window.testSaveItem(b.root,'evidence','/projects/1/evidence/upload',{method:'POST'},b.form);
requests.shift().reject(new Error('Offline'));
await failing;
assert.equal(b.form.resets,0,'Upload failure preserves the form and its selected file');
assert.equal(b.input.value,'Work in progress');
assert.match(b.notice().textContent,/Vos saisies sont conservées/);

const c=fixture();
const saved=window.testSaveItem(c.root,'deliverables','/deliverables/1/status',{method:'PATCH'});
requests.shift().resolve({id:1});
await tick();
requests.shift().reject(new Error('Refresh offline'));
await saved;
assert.equal(c.input.value,'Work in progress','Changing a row never resets the creation form');
assert.match(c.notice().textContent,/Modification enregistrée/);
const retry=c.notice().children[0];
const refresh=retry.handlers.click();
assert.equal(requests.length,1);
assert.equal(requests[0].options.method,undefined,'Retry only reads, never repeats the write');
requests.shift().resolve({html:'valid'});
await refresh;
assert.equal(c.list.replacements,1);
assert.equal(c.input.value,'Work in progress');
console.log('Projects: double submit, neighbouring edits, failed upload, status change and read-only refresh retry passed.');
const choice = {value:'7',replaceChildren(...options){this.options=options;}};
const otherChoice = {value:'99',replaceChildren(){throw new Error('Other project changed');}};
document.querySelectorAll = () => [
  {dataset:{projectId:'1'},querySelector:()=>choice},
  {dataset:{projectId:'2'},querySelector:()=>otherChoice}
];
const incomingChoices = ids => ({querySelectorAll:()=>ids.map(id=>({dataset:{deliverableId:id},querySelector:()=>({textContent:'Livrable '+id})}))});
window.testChoices({dataset:{projectId:'1'}},incomingChoices(['7','8']));
assert.equal(choice.value,'7','Existing selection retained');
assert.equal(choice.options[2].value,'8','New deliverable available');
window.testChoices({dataset:{projectId:'1'}},incomingChoices(['8']));
assert.equal(choice.value,'','Deleted selection returns to Aucun');
assert.equal(otherChoice.value,'99');
console.log('Deliverable choices: additions, retained selection, deletion and project isolation passed.');
