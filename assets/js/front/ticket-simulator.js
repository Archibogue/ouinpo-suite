/* PataDesk: vanilla JS, all teaching content is rendered as text. */
(() => {
  "use strict";
  const cfg = window.OuinpoTicketing;
  if (!cfg) return;
  const statuses = {
    new: "Nouveau",
    accepted: "Pris en charge",
    diagnosing: "En diagnostic",
    waiting_user: "En attente utilisateur",
    waiting_specialist: "En attente spécialiste",
    escalated: "Escaladé",
    resolving: "En cours de résolution",
    resolved: "Résolu",
    closed: "Clôturé",
    reopened: "Réouvert",
  };
  const types = {
    action: "Action",
    created: "Réception",
    status: "Statut",
    qualification: "Qualification",
    intake: "Fiche rédigée par l’élève",
    ai_question: "Question à un interlocuteur IA",
    ai_reply: "Réponse simulée par IA",
    ticket_reset: "Ticket remis à zéro",
    user_message: "Vous → demandeur",
    user_reply: "Demandeur",
    specialist_request: "Vous → spécialiste",
    specialist_reply: "Spécialiste",
    technical_note: "Note technique interne",
    test: "Test simulé",
    result: "Résultat",
    resource: "Consultation",
    code_edit: "Code modifié par l’élève",
    resolution: "Compte rendu",
    requester_validation: "Validation simulée du demandeur",
    archived: "Archivage",
  };
  function el(tag, text, cls) {
    const n = document.createElement(tag);
    if (text !== undefined) n.textContent = text;
    if (cls) n.className = cls;
    return n;
  }
  async function api(path, method = "GET", body) {
    const res = await fetch(cfg.root + path, {
      method,
      credentials: "same-origin",
      headers: { "X-WP-Nonce": cfg.nonce, "Content-Type": "application/json" },
      ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || "Action impossible.");
    return data;
  }
  function error(root, e) {
    let box = root.querySelector(".ouinpo-ticket-error");
    if (!box) {
      box = el("p", "", "ouinpo-ticket-error");
      box.setAttribute("role", "alert");
      root.prepend(box);
    }
    box.textContent = e.message || String(e);
    box.scrollIntoView({ block: "nearest" });
  }
  function button(text, fn, root, cls = "") {
    const b = el("button", text, cls);
    b.type = "button";
    b.addEventListener("click", async () => {
      b.disabled = true;
      try {
        await fn();
      } catch (e) {
        error(root || b.parentNode, e);
      } finally {
        b.disabled = false;
      }
    });
    return b;
  }
  function field(parent, label, value = "", area = false) {
    const wrap = el("label", label);
    const input = el(area ? "textarea" : "input");
    input.value = value;
    if (area) input.rows = 4;
    else input.type = "text";
    input.maxLength = 10000;
    wrap.append(input);
    parent.append(wrap);
    return input;
  }
  function select(parent, label, options, value, change) {
    const wrap = el("label", label);
    const input = el("select");
    options.forEach(([id, title]) => {
      const opt = el("option", title);
      opt.value = id;
      input.append(opt);
    });
    input.value = value;
    if (change) input.addEventListener("change", () => change(input.value));
    wrap.append(input);
    parent.append(wrap);
    return input;
  }
  // Page-memory drafts only: no student text is left in shared-browser storage.
  const drafts = new Map();
  const stateNames = {working:'À remettre',submitted:'Remis',correcting:'Correction en cours',published:'Résultat publié',active:'Parcours en cours',completed:'Parcours terminé',archived:'Archivée',draft:'Brouillon'};
  const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
  function dateLabel(value, empty = 'Non renseignée') {
    if (!value) return empty;
    const normalized = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value) ? value.replace(' ', 'T') + 'Z' : value;
    const date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? 'Date indisponible' : new Intl.DateTimeFormat('fr-FR', {dateStyle:'short',timeStyle:'short',timeZone}).format(date) + ' (' + timeZone + ')';
  }
  const traceLabels = {context:'Contexte et reformulation',information:'Informations recherchées / manquantes',initial_response:'Réponse initiale et délai justifié',orientation:'Orientation documentée',symptom:'Symptôme et reproduction',hypothesis:'Hypothèse et test',observed:'Résultat observé',correction:'Correction réalisée',verification:'Vérification après correction',proof:'Preuves textuelles / références de fichiers',reflection:'Bilan personnel'};
  function traceContent(text, type) {
    if(type==='status' && typeof text==='string')text=text.replace(/\b(new|accepted|diagnosing|waiting_user|waiting_specialist|escalated|resolving|resolved|closed|reopened)\b/g,k=>statuses[k]);
    if(type==='qualification' && typeof text==='string')text=text.replace(/^(nature|category|subcategory|impact|urgency|priority|priority_justification|it_service|assignee)(?= :)/,k=>({nature:'Nature',category:'Catégorie',subcategory:'Sous-catégorie',impact:'Impact',urgency:'Urgence',priority:'Priorité',priority_justification:'Justification de priorité',it_service:'Service informatique',assignee:'Affectation'})[k]);
    const prefix = 'Traces déclarées par l’élève (à vérifier) : ';
    if (typeof text === 'string' && text.startsWith(prefix)) {
      try {
        const values = JSON.parse(text.slice(prefix.length));
        if (values && typeof values === 'object' && !Array.isArray(values)) {
          const list = el('dl', undefined, 'ouinpo-ticket-traces');
          list.append(el('dt','Traces déclarées par l’élève — à vérifier'));
          Object.entries(traceLabels).forEach(([key,label]) => {
            if (typeof values[key] === 'string' && values[key].trim()) list.append(el('dt',label),el('dd',values[key]));
          });
          return list;
        }
      } catch (_) { /* Keep malformed legacy notes readable as literal text. */ }
    }
    return el('pre', text || '');
  }
  function moduleTitle(root) { return root.dataset.showTitle === '0' ? [] : [el('h2',cfg.name)]; }
  class Desk {
    evidencePanel(parent, t) {
      const box=el('details'); box.append(el('summary', `Traces pédagogiques — ${t.optional ? 'extension facultative' : 'ticket obligatoire'}`)); parent.append(box);
      box.append(el('p','Manipulations extérieures déclarées par l’élève, à vérifier par l’enseignant. Les références de fichiers restent du texte.'));
      const labels={context:'Contexte et reformulation',information:'Informations recherchées / manquantes',initial_response:'Réponse initiale et délai justifié',orientation:'Orientation : destinataire, motif et informations transmises',symptom:'Symptôme et reproduction',hypothesis:'Hypothèse et test réalisé',observed:'Résultat observé',correction:'Correction réalisée',verification:'Vérification après correction',proof:'Preuves textuelles / références de fichiers',reflection:'Bilan personnel court'};
      const inputs={};
      Object.entries(labels).forEach(([key,label])=>{
        if(this.a.read_only) { if(t.evidence?.[key]) box.append(el('strong',label),el('pre',t.evidence[key])); }
        else inputs[key]=this.remember(field(box,label,t.evidence?.[key]||'',true),'evidence:'+key);
      });
      if(!this.a.read_only) {
        box.append(button('Enregistrer les traces',async()=>{
          const values=Object.fromEntries(Object.entries(inputs).map(([k,v])=>[k,v.value]));
          const scope=this.draftKey('');
          await this.mutate('/evidence','PATCH',values);
          this.clearSubmitted('evidence:',values,scope);
        },this.root));
        if(['qualified','oriented'].includes(this.a.completion_status)) box.append(button('Terminer cet exercice',()=>this.mutate('/finish-exercise','POST',{}),this.root));
      }
      box.append(el('p',`Traces exigées manquantes : ${(t.trace_missing||[]).map(k=>traceLabels[k]||k).join(', ')||'aucune'}. Leur présence ne valide pas leur pertinence.`));
    }
    async assessmentPanel(parent) {
      const a=this.a.assessment;
      if(!a || !a.state) return;
      const box=el('section',undefined,'ouinpo-ticket-notice'); parent.append(box);
      box.append(el('h3',`${a.mode==='graded'?'Évaluation notée':'Entraînement'} — ${a.label||''}`),el('p',a.mode==='graded' ? `Remise et correction : ${stateNames[a.state]}.` : `Progression pédagogique : ${this.a.path_completed?'parcours terminé':'parcours en cours'}. Aucune remise notée attendue.`),el('p',`Ouverture : ${dateLabel(a.opens_at,'immédiate')} ; échéance : ${dateLabel(a.due_at,'aucune')} ; ${a.attempts} tentative(s). Retard : ${a.late_policy==='flag'?'remise signalée':'interdit'}.`),el('p',`Aides autorisées : indices ${a.hints?'oui':'non'}, conseils IA ${a.ai_advice?'oui':'non'}, dialogues IA ${a.ai_dialogue?'oui':'non'}.`));
      if(a.mode!=='graded') {
        if(!this.a.teacher_view && this.a.path_completed && a.attempts>1) box.append(button('Nouvelle tentative d’entraînement',async()=>{
          const next=await api(`/assignments/${this.a.assignment_id}/attempts`,'POST',{next:true});this.a=await api('/attempts/'+next.id);this.ticketId=this.a.tickets[0]?.id;this.render();
        },this.root));
        return;
      }
      const rubric=el('ul'); box.append(rubric);
      (a.rubric||[]).forEach(r=>rubric.append(el('li',`${r.title} — ${r.max} points — ${r.instruction} (${r.ticket||'ensemble du scénario'} ; ${r.competence||'sans repère'})`)));
      box.append(el('p',`Total : ${(a.rubric||[]).reduce((n,r)=>n+r.max,0)} points. Note /20 arrondie au centième, demi supérieur. Aucun zéro automatique sur un critère à corriger.`));
      if(a.result) {
        box.append(el('h4',`Note : ${a.result.grade}/20`),el('p',a.result.general),el('p','Preuves vérifiées par l’enseignant : '+a.result.verified_evidence));
        (a.rubric||[]).forEach(r=>box.append(el('p',`${r.title} : ${a.result.criteria[r.id].points}/${r.max} — ${a.result.criteria[r.id].comment}`)));
      }
      const change=async(op,body={})=>{await api(`/attempts/${this.a.id}/assessment/${op}`,'POST',{...body,revision:Number(this.a.revision)});await this.refresh();};
      if(!this.a.teacher_view) {
        if(a.state==='working' && !this.a.read_only) box.append(button('Remettre mon travail',async()=>{
          const missing=(this.a.missing||[]).join('\n')||'Aucun élément exigé manquant.';
          if(window.confirm('Remettre le travail enregistré et verrouiller la copie ? Les saisies non enregistrées ne seront pas incluses.\n\n'+missing+'\n\nUne copie incomplète reste évaluable.')) await change('submit',{confirm:true});
        },this.root));
        if(a.state!=='working' && a.attempts>1) box.append(button('Commencer une nouvelle tentative autorisée',async()=>{
          if(!window.confirm('Créer une nouvelle tentative indépendante ? La copie précédente sera conservée.')) return;
          const next=await api(`/assignments/${this.a.assignment_id}/attempts`,'POST',{next:true});
          this.a=await api('/attempts/'+next.id); this.ticketId=this.a.tickets[0]?.id; this.render();
        },this.root));
        return;
      }
      const data=await api(`/attempts/${this.a.id}/assessment`);
      if(!box.isConnected || !data.submissions) return;
      const submitted=data.submissions.at(-1);
      if(!submitted) {box.append(el('p','Travail non remis : aucune note.'));return;}
      box.append(el('p',`Dernière remise : ${dateLabel(submitted.submitted_at)}${submitted.late?' — En retard':''}`));
      const correction=el('div',undefined,'ouinpo-ticket-correction');
      const copies=el('section',undefined,'ouinpo-ticket-copies');
      const grading=el('section',undefined,'ouinpo-ticket-grading');
      copies.append(el('h4','Copies conservées'));
      grading.append(el('h4','Correction privée'));
      correction.append(copies,grading);box.append(correction);
      data.submissions.forEach((copy,i)=>{
        const details=el('details');details.open=i===data.submissions.length-1;
        details.append(el('summary',`Copie ${i+1} — ${dateLabel(copy.submitted_at)}`));
        const document=el('div',undefined,'ouinpo-ticket-markdown');
        // HTML is produced exclusively by the authenticated server's safe Markdown renderer.
        if(typeof copy.markdown_html==='string') {
          document.innerHTML=copy.markdown_html;
          document.querySelectorAll('time[datetime]').forEach(time=>time.textContent=dateLabel(time.getAttribute('datetime')));
        }
        else document.append(el('pre',copy.markdown));
        details.append(document);copies.append(details);
      });
      const expected=el('details');expected.append(el('summary','Attendus privés de la dernière copie — enseignant uniquement'));
      submitted.scenario.tickets.forEach(t=>{
        expected.append(el('h5',t.title||t.id));
        const values=el('dl');Object.entries(t.expected||{}).forEach(([key,value])=>values.append(el('dt',({nature:'Nature',category:'Catégorie',subcategory:'Sous-catégorie',impact:'Impact',urgency:'Urgence',priority:'Priorité',assignee:'Affectation',it_service:'Service informatique',priority_justification:'Justification de priorité'})[key]||key),el('dd',Array.isArray(value)?value.join(', '):String(value))));
        expected.append(values,el('p',t.expected_solution||'Aucun corrigé textuel.'));
      });grading.append(expected);
      const history=el('details');history.append(el('summary','Historique privé des corrections et réouvertures'));
      const entries=el('ol');history.append(entries);
      (data.history||[]).forEach(entry=>{
        const item=el('li');item.append(el('p',`${({submit:'Remise',grade:'Correction enregistrée en brouillon',publish:'Résultat publié',reopen:'Copie rouverte'})[entry.operation]||'Événement'} — ${dateLabel(entry.at)} — copie ${entry.submission} — compte #${entry.actor}`));
        if(entry.reason)item.append(el('p','Motif : '+entry.reason));
        if(entry.correction){
          const detail=el('details');detail.append(el('summary','Correction conservée'));
          const rubric=data.submissions[Number(entry.submission)-1]?.rubric||submitted.rubric;
          rubric.forEach(r=>{const c=entry.correction.criteria?.[r.id];detail.append(el('p',`${r.title} : ${c?.points==null?'À corriger':c.points+' / '+r.max}${c?.comment?' — '+c.comment:''}`));});
          detail.append(el('p','Appréciation : '+(entry.correction.general||'Non renseignée')),el('p','Preuves vérifiées : '+(entry.correction.verified_evidence||'Non renseignées')));item.append(detail);
        }entries.append(item);
      });
      if(!data.history?.length)history.append(el('p','Aucun événement de correction.'));
      box.append(history);
      if(data.state!=='working') {
        const summary=el('p',undefined,'ouinpo-ticket-grade-summary');summary.setAttribute('role','status');grading.append(summary);
        const inputs={};
        const summarize=()=>{
          const filled=Object.values(inputs).map(({p})=>p.value).filter(v=>v!=='');
          const points=filled.reduce((sum,v)=>sum+(Number(v)||0),0).toLocaleString('fr-FR');
          const total=submitted.rubric.reduce((sum,r)=>sum+r.max,0);
          summary.textContent=`${filled.length} / ${submitted.rubric.length} critères renseignés · ${points} / ${total} points attribués · ${data.state==='published'?'Résultat publié':'Résultat non publié'} (${stateNames[data.state]}). ${filled.length<submitted.rubric.length?'Les critères vides restent à corriger. ':''}Les modifications doivent être enregistrées en brouillon.`;
        };
        submitted.rubric.forEach(r=>{
          const row=el('fieldset');row.append(el('legend',r.title),el('p',r.instruction),el('p','Indications privées : '+r.private));grading.append(row);
          const p=field(row,`Points / ${r.max} (vide = à corriger)`,data.draft?.criteria[r.id]?.points??'');p.type='number';p.min='0';p.max=String(r.max);p.step='0.01';
          const c=field(row,'Commentaire',data.draft?.criteria[r.id]?.comment||'',true);inputs[r.id]={p,c};
          p.addEventListener('input',summarize);
        });
        summarize();
        const general=field(grading,'Appréciation générale',data.draft?.general||'',true);
        const verified=field(grading,'Preuves effectivement vérifiées (références et méthode)',data.draft?.verified_evidence||'',true);
        grading.append(button('Enregistrer la correction en brouillon',()=>change('grade',{criteria:Object.fromEntries(Object.entries(inputs).map(([id,{p,c}])=>[id,{points:p.value===''?null:Number(p.value),comment:c.value}])),general:general.value,verified_evidence:verified.value}),this.root));
        if(data.state==='correcting') grading.append(button('Publier explicitement le résultat',()=>change('publish'),this.root));
        const reason=field(grading,'Motif de réouverture');
        const due=field(grading,`Nouvelle échéance de réouverture (facultative, ${timeZone})`);due.type='datetime-local';
        grading.append(button('Rouvrir la copie',()=>change('reopen',{reason:reason.value,...(due.value?{due_at:new Date(due.value).toISOString().replace('.000Z','Z')}:{})}),this.root));
      }
    }
    draftKey(key) {
      return `${this.a.student_id}:${this.a.id}:${this.ticketId}:${key}`;
    }
    remember(input, key) {
      const scoped = this.draftKey(key);
      if (drafts.has(scoped)) input.value = drafts.get(scoped);
      const save = () => drafts.set(scoped, input.value);
      input.addEventListener("input", save);
      input.addEventListener("change", save);
      return input;
    }
    clearSubmitted(prefix, body, scope) {
      for (const [key, value] of Object.entries(body)) {
        const scoped = `${scope}${prefix}${key}`;
        if (drafts.get(scoped) === value) drafts.delete(scoped);
      }
    }
    constructor(root, attempt, back) {
      this.root = root;
      this.a = attempt;
      this.back = back;
      this.ticketId = attempt.tickets[0]?.id;
      this.tab = "Conversation";
      this.filters = {};
      this.eventsComplete = attempt.events.length < 500;
      this.render();
    }
    async refresh() {
      this.a = await api("/attempts/" + this.a.id);
      this.eventsComplete = this.a.events.length < 500;
      this.render();
    }
    async mutate(path, method, body) {
      const draftScope = this.draftKey("");
      const submittedTicket = this.ticketId;
      const previous = this.a.events.at(-1)?.id || 0;
      const loadedEvents = this.a.events;
      this.a = await api(
        `/attempts/${this.a.id}/tickets/${this.ticketId}${path}`,
        method,
        { ...body, revision: Number(this.a.revision) },
      );
      const prefix =
        path === "/intake"
          ? "intake:"
          : path === "/dialogue"
            ? "dialogue:"
            : path === ""
              ? "qualify:"
              : path === "/notes"
                ? "notes:"
                : path.startsWith("/actions/")
                  ? `action:${path.slice(9)}:`
                  : null;
      if (prefix !== null) this.clearSubmitted(prefix, body, draftScope);
      if (path === "/reset") {
        for (const key of drafts.keys())
          if (key.startsWith(draftScope)) drafts.delete(key);
      }
      if (loadedEvents.length >= 500) {
        const more = await api(
          `/attempts/${this.a.id}/events?after=${previous}`,
        );
        this.a.events = loadedEvents.concat(more);
        this.eventsComplete = more.length < 500;
      } else {
        this.eventsComplete = this.a.events.length < 500;
      }
      const results = this.a.events.filter(
        (e) =>
          Number(e.id) > Number(previous) &&
          e.ticket_key === submittedTicket &&
          [
            "result",
            "test",
            "user_reply",
            "specialist_reply",
            "ai_reply",
            "specialist_request",
          ].includes(e.event_type),
      );
      const replies = results.filter((e) =>
        ["user_reply", "specialist_reply", "ai_reply"].includes(e.event_type),
      );
      this.render();
      if (results.length && this.ticketId === submittedTicket) {
        const output = el("div", "", "ouinpo-ticket-notice");
        output.setAttribute("role", "status");
        if (replies.length)
          output.append(
            el(
              "p",
              "Nouvelle réponse reçue. Cet échange est conservé dans Conversation sur cette même page.",
            ),
          );
        else if (results.some((e) => e.event_type === "specialist_request"))
          output.append(
            el(
              "p",
              "Demande envoyée au spécialiste. Utilisez « Recevoir la réponse et reprendre » pour recevoir sa réponse prédéfinie ; elle apparaîtra dans Conversation.",
            ),
          );
        results.forEach((e) =>
          output.append(
            el("strong", types[e.event_type]),
            el("pre", e.payload.text),
          ),
        );
        this.root
          .querySelector(".ouinpo-ticket-panel:not([hidden])")
          .prepend(output);
        output.setAttribute("tabindex", "-1");
        output.focus();
      }
    }
    render() {
      const root = this.root;
      root.replaceChildren();
      const header = el("header", "", "ouinpo-ticket-header");
      header.append(
        ...moduleTitle(root),
        el(
          "span",
          this.a.teacher_view
            ? "Observation professeur"
            : "Centre de services — simulation",
        ),
      );
      header.append(
        button("Retour", this.back, root),
        button("Actualiser", () => this.refresh(), root),
        button(
          "Télécharger mon bilan (.md)",
          async () => {
            const progress = el(
              "p",
              "SegFault prépare les conseils du bilan…",
              "ouinpo-ticket-notice",
            );
            header.append(progress);
            let report;
            try {
              report = await api(`/attempts/${this.a.id}/summary`, "POST");
            } finally {
              progress.remove();
            }
            const url = URL.createObjectURL(
              new Blob([report.markdown], {
                type: "text/markdown;charset=utf-8",
              }),
            );
            const link = el("a");
            link.href = url;
            link.download = report.filename;
            document.body.append(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
          },
          root,
        ),
      );
      root.append(header);
      this.assessmentPanel(root).catch((e) => error(root, e));
      root.append(el('p', 'La console compare du texte à une référence sans exécuter le programme. Un écart ne démontre pas que votre programme est incorrect.'));
      if (this.a.priority_policy) root.append(el('h4', 'Grille de priorité du scénario'), el('pre', this.a.priority_policy));
      if (this.a.service_agreement) root.append(el('h4', 'Engagements de service'), el('pre', this.a.service_agreement));
      if (!this.a.read_only)
        root.append(
          el(
            "p",
            "Vos saisies en cours sont conservées pendant la navigation entre les onglets et tickets. Enregistrez-les avant de fermer ou recharger la page : les brouillons ne figurent pas dans le bilan.",
          ),
        );
      if (!this.a.teacher_view) root.append(studentGuide());
      root.append(el("h3", this.a.title), el("p", this.a.description));
      root.append(
        el(
          "p",
          `Objectif du parcours : ${{qualified:'qualifier',oriented:'orienter et documenter',closed:'clôturer',resolved:'résoudre'}[this.a.completion_status]} les tickets obligatoires. Parcours ${this.a.path_completed ? "terminé" : "en cours"} — cela ne vaut pas validation pédagogique.`,
        ),
      );
      if (this.a.read_only)
        root.append(
          el("p", "Consultation en lecture seule.", "ouinpo-ticket-notice"),
        );
      const stats = el("div", "", "ouinpo-ticket-stats");
      const counts = [
        ["Disponibles", (s) => s === "new"],
        [
          "En cours",
          (s) =>
            ["accepted", "diagnosing", "resolving", "reopened"].includes(s),
        ],
        ["En attente", (s) => s.startsWith("waiting")],
        ["Escaladés", (s) => s === "escalated"],
        ["Résolus", (s) => s === "resolved"],
        ["Clôturés", (s) => s === "closed"],
      ];
      counts.forEach(([label, test]) =>
        stats.append(
          el(
            "div",
            `${this.a.tickets.filter((t) => test(t.status)).length} · ${label}`,
          ),
        ),
      );
      root.append(stats);
      const layout = el("div", "", "ouinpo-ticket-layout");
      const queue = el("aside", "", "ouinpo-ticket-queue");
      const main = el("main", "", "ouinpo-ticket");
      layout.append(queue, main);
      root.append(layout);
      queue.append(el("h3", "File de tickets"));
      for (const [key, label] of [
        ["status", "Statut"],
        ["priority", "Priorité"],
        ["category", "Catégorie"],
        ["application", "Application / service"],
      ]) {
        const val = (t) =>
          key === "status"
            ? t.status
            : t.fields[key] ||
              (key === "application" ? t.fields.service : "") ||
              "";
        const options = [...new Set(this.a.tickets.map(val).filter(Boolean))];
        select(
          queue,
          label,
          [
            ["", "Tous"],
            ...options.map((v) => [v, key === "status" ? statuses[v] : v]),
          ],
          this.filters[key] || "",
          (v) => {
            this.filters[key] = v;
            this.render();
          },
        );
      }
      const tickets = this.a.tickets.filter((t) =>
        Object.entries(this.filters).every(
          ([k, v]) =>
            !v ||
            (k === "status"
              ? t.status
              : t.fields[k] ||
                (k === "application" ? t.fields.service : "") ||
                "") === v,
        ),
      );
      tickets.forEach((t) => {
        const b = button(
          `${t.id} · ${t.title}\n${statuses[t.status]} · ${t.fields.priority || "À qualifier"}${t.fields.sla ? " · SLA " + t.fields.sla : ""}`,
          () => {
            this.ticketId = t.id;
            this.render();
          },
          root,
          "ouinpo-ticket-card",
        );
        b.setAttribute("aria-pressed", String(t.id === this.ticketId));
        queue.append(b);
      });
      if (!tickets.length)
        queue.append(el("p", "Aucun ticket pour ces filtres."));
      const t = this.a.tickets.find((x) => x.id === this.ticketId);
      if (!t) return;
      this.evidencePanel(main, t);
      if (!this.a.read_only && this.a.assessment?.mode !== 'graded')
        main.append(
          button(
            "Remettre ce ticket à zéro",
            async () => {
              if (
                !window.confirm(
                  `Remettre le ticket « ${t.title} » à son état initial ? Sa qualification, son code, ses tests, son temps et sa résolution seront réinitialisés. Ses brouillons seront effacés. Les autres tickets ne changent pas. L’historique est conservé avec une marque de remise à zéro.`,
                )
              )
                return;
              await this.mutate("/reset", "POST", { confirm_reset: true });
            },
            root,
          ),
        );
      main.append(
        el("span", `${t.id} · ${statuses[t.status]}`, "ouinpo-ticket-status"),
        el("h3", t.title),
        el("p", `${t.requester} — ${t.fields.service || ""}`),
        el("p", t.description),
      );
      const facts = el("dl", "", "ouinpo-ticket-facts");
      if (t.intake_mode === "from_request") {
        main.append(
          el("h4", "Message utilisateur original"),
          el("pre", t.raw_request),
        );
        const intake = el("details");
        intake.open = !t.intake?.title;
        intake.append(el("summary", "Créer un ticket à partir de la demande"));
        intake.append(
          el(
            "p",
            "Reformulez la demande sans inventer les informations absentes. Indiquez « à confirmer » si nécessaire. Les questions sont préparées ici ; pour les poser, utilisez les actions d’échange ou le dialogue IA s’il est activé.",
          ),
        );
        const inputs = {};
        const editable =
          !this.a.read_only &&
          !["resolved", "closed"].includes(t.status) &&
          !t.actions.some((a) => a.id === "__reply");
        for (const [key, label] of [
          ["title", "Titre précis"],
          ["requester", "Demandeur identifié"],
          ["service", "Service concerné"],
          ["application", "Application concernée"],
          ["symptoms", "Symptômes ou besoin décrit"],
          ["missing_information", "Informations manquantes"],
          ["questions", "Questions complémentaires à poser"],
        ]) {
          inputs[key] = field(
            intake,
            label,
            t.intake?.[key] || "",
            ["symptoms", "missing_information", "questions"].includes(key),
          );
          inputs[key].disabled = !editable;
          this.remember(inputs[key], `intake:${key}`);
        }
        if (editable)
          intake.append(
            button(
              "Enregistrer la fiche du ticket",
              () =>
                this.mutate(
                  "/intake",
                  "PATCH",
                  Object.fromEntries(
                    Object.entries(inputs).map(([key, input]) => [
                      key,
                      input.value,
                    ]),
                  ),
                ),
              root,
            ),
          );
        main.append(intake);
      }
      Object.entries(t.fields).forEach(([k, v]) => {
        facts.append(
          el(
            "dt",
            {
              priority: "Priorité",
              category: "Catégorie",
              application: "Application",
              sla: "SLA fictif",
              assignee: "Assignation",
              impact: "Impact",
              nature: "Nature de la demande",
              priority_justification: "Justification de priorité",
              urgency: "Urgence",
              location: "Localisation",
              fictional_date: "Date fictive",
              it_service: "Service informatique",
              service: "Service",
              subcategory: "Sous-catégorie",
            }[k] || k,
          ),
          el(
            "dd",
            k === "nature"
              ? {
                  incident: "Incident",
                  service: "Assistance / service",
                  evolution: "Évolution",
                }[v] || v
              : v,
          ),
        );
      });
      main.append(facts);
      main.append(
        el(
          "p",
          `${t.done.length} action(s) distincte(s) · ${t.minutes} min fictives · Début : ${dateLabel(this.a.started_at)}${this.a.ended_at ? " · Fin : " + dateLabel(this.a.ended_at) : ""}`,
        ),
      );
      main.append(
        el(
          "p",
          `Contrôles automatiques : ${t.automatic_checks === true ? "satisfaits" : t.automatic_checks === false ? "non satisfaits" : "pas encore évalués"}. La pertinence des réponses reste à apprécier par le professeur.`,
        ),
      );
      if (t.requester_validation_required)
        main.append(
          el(
            "p",
            "Procédure de ce scénario : recevoir la validation simulée du demandeur après résolution, puis clôturer. Un retour négatif rouvre le ticket.",
          ),
        );
      if (t.requester_validation)
        main.append(
          el(
            "p",
            `Retour du demandeur : ${t.requester_validation.outcome === "confirmed" ? "confirmation" : "problème persistant"} — ${t.requester_validation.message}`,
          ),
        );
      if (!this.a.read_only && !["resolved", "closed"].includes(t.status)) {
        const details = el("details");
        details.append(el("summary", "Qualifier / affecter le ticket"));
        const form = el("form");
        const inputs = {};
        for (const [k, l] of [
          ["nature", "Nature de la demande"],
          ["category", "Catégorie"],
          ["subcategory", "Sous-catégorie"],
          ["impact", "Impact"],
          ["urgency", "Urgence"],
          ["priority", "Priorité"],
          ["priority_justification", "Justification de la priorité"],
          ["it_service", "Service informatique"],
          ["assignee", "Technicien / groupe fictif"],
        ]) {
          const choices = {
            nature: [
              ["incident", "Incident"],
              ["service", "Assistance / service"],
              ["evolution", "Évolution"],
            ],
            impact: [
              ["Faible", "Faible — une personne, gêne limitée"],
              ["Moyen", "Moyen — plusieurs personnes ou un service touché"],
              [
                "Élevé",
                "Élevé — activité critique ou nombreux utilisateurs touchés",
              ],
            ],
            urgency: [
              ["Faible", "Faible — peut attendre"],
              ["Moyenne", "Moyenne — à traiter rapidement"],
              ["Élevée", "Élevée — blocage immédiat ou échéance proche"],
            ],
            priority: [
              ["Basse", "Basse"],
              ["Normale", "Normale"],
              ["Haute", "Haute"],
              ["Critique", "Critique"],
            ],
          }[k];
          const value = t.fields[k] || "";
          if (choices) {
            const options = [
              ["", "Choisir…"],
              ["À qualifier", "À qualifier"],
              ...choices,
            ];
            if (!options.some(([id]) => id === value)) {
              options.push([value, `${value} (valeur existante)`]);
            }
            inputs[k] = select(form, l, options, value);
          } else {
            inputs[k] = field(form, l, value, k === "priority_justification");
          }
          if (t.guided && t.qualification_required?.includes(k)) {
            inputs[k].required = true;
            inputs[k].parentNode.append(
              el("small", "Obligatoire avant résolution"),
            );
          }
          this.remember(inputs[k], `qualify:${k}`);
        }
        form.append(
          el(
            "p",
            "La nature décrit la demande ; la catégorie décrit le domaine technique. Justifiez la priorité en croisant impact, urgence et SLA. Enregistrez votre qualification avant de résoudre.",
          ),
        );
        form.append(
          button(
            "Enregistrer la qualification",
            () =>
              this.mutate(
                "",
                "PATCH",
                Object.fromEntries(
                  Object.entries(inputs).map(([k, v]) => [k, v.value]),
                ),
              ),
            root,
          ),
        );
        details.append(form);
        if (t.guided && t.qualification_missing?.length) {
          details.open = true;
          form.prepend(
            el(
              "p",
              "Qualification incomplète : renseignez les champs obligatoires encore vides ou À qualifier.",
            ),
          );
        }
        main.append(details);
      }
      main.append(el("h3", "Traiter le ticket"));
      const stage =
        t.status === "closed"
          ? "Clôture"
          : t.status === "resolved" || t.status === "resolving"
            ? "Résolution"
            : t.status === "new"
              ? "Demande"
              : t.qualification_missing?.length
                ? "Qualification"
                : "Diagnostic";
      main.append(
        el(
          "p",
          `Statut technique : ${statuses[t.status] || t.status}. Phase technique : ${stage}.`,
        ),
      );
      const next =
        t.actions.find((a) => a.type === "reply") ||
        t.actions.find((a) => a.type === "take" || a.type === "close");
      main.append(
        el(
          "p",
          t.exercise_completed || (this.a.completion_status==='resolved' && ['resolved','closed'].includes(t.status)) || (this.a.completion_status==='closed' && t.status==='closed')
            ? `Objectif pédagogique atteint pour ce ticket. Aucune étape technique supplémentaire exigée.${this.a.assessment?.mode==='graded' && this.a.assessment.state==='working' ? ' Le travail reste à remettre dans la section Évaluation notée.' : ''}`
            : ['qualified','oriented'].includes(this.a.completion_status)
              ? 'Complétez la qualification et les traces demandées, puis utilisez « Terminer cet exercice ». La résolution technique n’est pas exigée pour cet objectif.'
            : next
            ? `Prochaine étape proposée : ${next.label}.`
            : t.qualification_missing?.length
              ? "Complétez et enregistrez la qualification ci-dessus."
              : "Choisissez une action ou un test disponible ci-dessous. Les étapes proposées dépendent du scénario.",
        ),
      );
      if (
        t.ai_recipients?.length &&
        !this.a.read_only &&
        t.status !== "closed"
      ) {
        const chat = el("div", "", "ouinpo-ticket-action");
        chat.append(
          el("h4", "Dialoguer avec un interlocuteur IA"),
          el(
            "p",
            "Simulation : ces échanges sont conservés dans Conversation et le bilan. Ils ne remplacent pas les actions, tests et validations du scénario. N’indiquez pas de données personnelles réelles.",
          ),
        );
        const recipient = select(
          chat,
          "Interlocuteur",
          t.ai_recipients.map((r) => [r.id, r.label]),
          t.ai_recipients[0].id,
        );
        const message = field(chat, "Votre question ou message", "", true);
        this.remember(recipient, "dialogue:recipient");
        this.remember(message, "dialogue:message");
        message.maxLength = 3000;
        chat.append(
          button(
            "Envoyer à l’interlocuteur IA",
            async () => {
              const progress = el("p", "L’interlocuteur prépare sa réponse…");
              chat.append(progress);
              try {
                await this.mutate("/dialogue", "POST", {
                  recipient: recipient.value,
                  message: message.value,
                });
              } finally {
                progress.remove();
              }
            },
            root,
          ),
        );
        main.append(chat);
      }
      const supportButtons = el("div", "", "ouinpo-ticket-tabs");
      const supportPanels = el("div");
      main.append(supportButtons, supportPanels);
      for (const tab of [
        "Actions",
        "Conversation",
        "Ressources",
        "Notes",
        "Historique",
      ]) {
        const support = ["Ressources", "Notes", "Historique"].includes(tab);
        const panel = el("section", "", "ouinpo-ticket-panel");
        panel.append(
          el("h4", tab === "Actions" ? "Actions et tests disponibles" : tab),
        );
        if (support) {
          panel.hidden = this.supportPanel !== tab;
          const toggle = button(
            tab,
            () => {
              const open = panel.hidden;
              panel.hidden = !open;
              toggle.setAttribute("aria-expanded", String(open));
              this.supportPanel = open ? tab : null;
              if (open) {
                panel.setAttribute("tabindex", "-1");
                panel.focus();
              }
            },
            root,
          );
          toggle.setAttribute("aria-expanded", String(!panel.hidden));
          supportButtons.append(toggle);
          supportPanels.append(panel);
        } else main.append(panel);
        if (["Conversation", "Historique", "Notes"].includes(tab)) {
          const allowed =
            tab === "Conversation"
              ? [
                  "user_message",
                  "user_reply",
                  "requester_validation",
                  "ai_question",
                  "ai_reply",
                  "specialist_request",
                  "specialist_reply",
                ]
              : tab === "Notes"
                ? ["technical_note"]
                : null;
          const events = this.a.events.filter(
            (e) =>
              e.ticket_key === t.id &&
              (!allowed || allowed.includes(e.event_type)),
          );
          if (!events.length)
            panel.append(
              el(
                "p",
                tab === "Conversation"
                  ? t.description
                  : "Aucune trace pour le moment.",
              ),
            );
          const list = el("ol", "", "ouinpo-ticket-timeline");
          events.forEach((e) => {
            const item = el("li");
            item.append(
              el(
                "strong",
                `${dateLabel(e.created_at)} · ${types[e.event_type] || e.event_type}`,
              ),
              traceContent(e.payload.text,e.event_type),
            );
            list.append(item);
            if (e.event_type === "code_edit")
              item.append(el("pre", e.payload.content));
          });
          panel.append(list);
          if (!this.eventsComplete)
            panel.append(
              button(
                "Charger la suite du journal",
                async () => {
                  const more = await api(
                    `/attempts/${this.a.id}/events?after=${this.a.events.at(-1).id}`,
                  );
                  this.a.events.push(...more);
                  this.eventsComplete = more.length < 500;
                  this.render();
                },
                root,
              ),
            );
          if (tab === "Notes" && !this.a.read_only) {
            const note = field(panel, "Note technique interne", "", true);
            this.remember(note, "notes:message");
            panel.append(
              button(
                "Ajouter la note",
                () => this.mutate("/notes", "POST", { message: note.value }),
                root,
              ),
            );
          }
        } else if (tab === "Ressources") {
          t.resources.forEach((r) =>
            panel.append(
              button(
                `${r.label} · ${r.filename || r.type}`,
                async () => {
                  const data = await api(
                    `/attempts/${this.a.id}/tickets/${t.id}/resources/${r.id}`,
                    "POST",
                    { revision: Number(this.a.revision) },
                  );
                  this.a = data.attempt;
                  this.render();
                  this.showResource(data.resource);
                },
                root,
              ),
            ),
          );
          if (!t.resources.length)
            panel.append(
              el(
                "p",
                "Aucune ressource révélée. Les investigations peuvent débloquer des éléments techniques.",
              ),
            );
        } else {
          t.actions.forEach((a) => {
            const card = el("div", "", "ouinpo-ticket-action");
            card.append(el("h4", a.label), el("p", a.description || ""));
            if (
              ["question", "specialist", "transfer", "escalate"].includes(
                a.type,
              )
            )
              card.append(
                el(
                  "p",
                  "Action du scénario : réponse prédéfinie. Pour un échange libre avec l’IA, utilisez « Dialoguer avec un interlocuteur IA » lorsqu’il est proposé.",
                ),
              );
            if (a.specialist)
              card.append(el("p", "Destinataire : " + a.specialist));
            if (a.cost) card.append(el("small", `Coût fictif : ${a.cost} min`));
            const inputs = {};
            if (a.type === "resolve") {
              for (const [k, l] of [
                ["cause", "Cause ou analyse de la demande"],
                ["solution", "Solution / actions réalisées"],
                ["tests", "Tests effectués"],
                ["result", "Résultat"],
                ["message", "Message final au demandeur"],
              ])
                inputs[k] = field(card, l, "", true);
            } else if (
              a.requires_message ||
              [
                "specialist",
                "transfer",
                "escalate",
                "reassign",
                "communication",
                "technical_note",
              ].includes(a.type)
            )
              inputs.message = field(card, "Votre message", "", true);
            for (const [key, input] of Object.entries(inputs))
              this.remember(input, `action:${a.id}:${key}`);
            if (!this.a.read_only)
              card.append(
                button(
                  a.type === "resolve"
                    ? "Envoyer le compte rendu et résoudre"
                    : "Effectuer cette action",
                  () =>
                    this.mutate(
                      "/actions/" + a.id,
                      "POST",
                      Object.fromEntries(
                        Object.entries(inputs).map(([k, v]) => [k, v.value]),
                      ),
                    ),
                  root,
                ),
              );
            panel.append(card);
          });
          if (
            Object.keys(t.tests).length ||
            t.actions.some((a) => a.type === "test")
          ) {
            panel.prepend(
              el(
                "p",
                "Tests simulés : la comparaison de l’extrait avec une correction attendue n’exécute pas le code. Toute modification invalide les tests liés à cet extrait.",
              ),
            );
            Object.entries(t.tests).forEach(([id, r]) => {
              panel.append(el("h4", id + " · " + r.outcome), el("pre", r.text));
            });
          }
        }
      }
      if (t.resolution) {
        const d = el("details");
        d.append(el("summary", "Dernier compte rendu"));
        Object.entries(t.resolution).forEach(([k, v]) =>
          d.append(el("h4", k), el("p", v)),
        );
        main.append(d);
      }
      if (t.teacher) {
        const d = el("details");
        d.append(
          el("summary", "Analyse professeur — informations privées"),
          el('h4','Qualification attendue'),
        );
        const expected=el('dl');
        Object.entries(t.teacher.expected||{}).forEach(([key,value])=>expected.append(el('dt',({nature:'Nature',category:'Catégorie',subcategory:'Sous-catégorie',priority:'Priorité',impact:'Impact',urgency:'Urgence',assignee:'Affectation',it_service:'Service informatique',priority_justification:'Justification de priorité'})[key]||key),el('dd',Array.isArray(value)?value.join(', '):String(value))));
        d.append(expected,el('h4','Corrigé privé'),el('p',t.teacher.expected_solution||'Aucun corrigé textuel.'));
        Object.entries(t.teacher.review||{}).forEach(([key,value])=>d.append(el('p',`${({required_actions_met:'Actions exigées réalisées',expected_fields_match:'Qualification conforme à la référence'})[key]||key} : ${value===true?'oui':value===false?'non':'non évalué'}.`)));
        d.append(el('p',`Points historiques d’actions : ${t.teacher.score ?? 0} — distincts de la note pédagogique.`),
          el(
            "p",
            "Les critères automatiques portent sur les traces. La qualité du texte reste à apprécier par le professeur.",
          ),
        );
        main.append(d);
      }
    }
    showResource(r) {
      const panel = this.root.querySelector(
        ".ouinpo-ticket-panel:not([hidden])",
      );
      const wrap = el("div", "", "ouinpo-ticket-resource");
      wrap.append(
        el("h4", `${r.filename || r.label} · ${r.language || "texte"}`),
      );
      wrap.append(
        button(
          "Copier le contenu",
          async () => {
            await navigator.clipboard.writeText(r.content || "");
            const msg = el("p", "Contenu copié.");
            msg.setAttribute("role", "status");
            wrap.append(msg);
          },
          this.root,
        ),
      );
      const lines = el("ol");
      (r.content || "").split("\n").forEach((line) => {
        const li = el("li");
        li.append(el("code", line || " "));
        lines.append(li);
      });
      wrap.append(lines);
      const ticket = this.a.tickets.find((t) => t.id === this.ticketId);
      if (
        r.editable &&
        !this.a.read_only &&
        !["new", "resolved", "closed"].includes(ticket.status) &&
        !ticket.actions.some((a) => a.id === "__reply")
      ) {
        const consolePanel = el("details", undefined, "ouinpo-ticket-console");
        consolePanel.append(el("summary", "Ouvrir la console de correction"));
        consolePanel.append(
          el(
            "p",
            "Modifiez votre copie de l’extrait, enregistrez, puis relancez le test dans Actions et tests disponibles.",
          ),
        );
        const editor = field(
          consolePanel,
          "Votre code — " + (r.filename || r.label),
          r.content || "",
          true,
        );
        const codeKey = this.draftKey(`code:${r.id}`);
        this.remember(editor, `code:${r.id}`);
        editor.rows = 18;
        editor.maxLength = 100000;
        editor.spellcheck = false;
        editor.wrap = "off";
        editor.setAttribute("autocapitalize", "off");
        const saved = el(
          "p",
          editor.value === (r.content || "")
            ? "Copie enregistrée."
            : "Brouillon restauré — modifications non enregistrées.",
        );
        if (drafts.has(codeKey)) consolePanel.open = true;
        saved.setAttribute("role", "status");
        editor.addEventListener("input", () => {
          saved.textContent = "Modifications non enregistrées.";
        });
        consolePanel.append(
          button(
            "Remettre à l’état initial",
            () => {
              if (typeof r.initial_content !== "string")
                throw new Error(
                  "Rechargez la ressource pour obtenir son contenu initial.",
                );
              if (
                !window.confirm(
                  "Remplacer le texte de l’éditeur par l’extrait initial du scénario ? Cliquez ensuite sur Enregistrer mon code pour valider.",
                )
              )
                return;
              editor.value = r.initial_content;
              drafts.set(codeKey, editor.value);
              saved.textContent =
                "Extrait initial restauré — cliquez sur Enregistrer mon code pour valider.";
            },
            this.root,
          ),
          button(
            "Enregistrer mon code",
            async () => {
              const content = editor.value;
              this.a = await api(
                `/attempts/${this.a.id}/tickets/${this.ticketId}/resources/${r.id}/code`,
                "PATCH",
                { content, revision: Number(this.a.revision) },
              );
              if (drafts.get(codeKey) === content) drafts.delete(codeKey);
              this.render();
              this.showResource({ ...r, content });
              const newConsole = this.root.querySelector(
                ".ouinpo-ticket-console",
              );
              if (newConsole) newConsole.open = true;
            },
            this.root,
          ),
          saved,
        );
        wrap.append(consolePanel);
      }
      panel.append(wrap);
    }
  }
  function studentGuide() {
    const guide = el("details", undefined, "ouinpo-ticket-guide");
    guide.append(
      el("summary", "Guide d’utilisation — comment traiter un ticket ?"),
    );
    guide.append(
      el(
        "p",
        "Important : utilisez PataDesk dans un seul onglet du navigateur. N’ouvrez pas la même tentative dans plusieurs onglets ou fenêtres : les versions peuvent se décaler et vos brouillons ne sont pas partagés. Actions, échanges et tests sont réunis dans Traiter le ticket ; Ressources, Notes et Historique se déplient sur la même page.",
        "ouinpo-ticket-notice",
      ),
    );
    guide.append(
      el(
        "p",
        "Vous jouez le rôle d’un technicien de support. Chaque scénario propose ses propres actions : les informations se découvrent au fil de vos investigations.",
      ),
    );
    const steps = el("ol");
    [
      [
        "Ouvrir un scénario",
        "Depuis les scénarios affectés, cliquez sur Ouvrir, puis choisissez un ticket dans la file. Vous pouvez retrouver votre travail dans Mes tentatives.",
      ],
      [
        "Prendre en charge et qualifier",
        "Lisez la demande puis ouvrez Qualifier / affecter le ticket. Choisissez sa nature (incident, assistance/service ou évolution), sa catégorie technique, l’impact, l’urgence et la priorité ; justifiez votre priorité et enregistrez. En mode guidé, les champs marqués obligatoires doivent être complétés avant résolution. Prenez en charge le ticket dans Actions pour poursuivre.",
      ],
      [
        "Interroger et investiguer",
        "Posez les questions proposées et consultez les éléments disponibles. Après une demande, utilisez Recevoir la réponse et reprendre. Certaines actions débloquent des ressources supplémentaires.",
      ],
      [
        "Lire et corriger le code",
        "Dans Ressources, ouvrez le fichier révélé. Si le professeur l’autorise, ouvrez la console de correction, modifiez l’extrait puis cliquez sur Enregistrer mon code. Enregistrez avant de changer d’onglet. Votre copie est personnelle.",
      ],
      [
        "Tester",
        "Dans Tests, lancez les vérifications proposées et lisez leur résultat. Après chaque modification du code, enregistrez puis relancez le test. Il s’agit de tests pédagogiques simulés.",
      ],
      [
        "Contacter un spécialiste",
        "Dans Actions et tests disponibles, choisissez une demande au spécialiste et rédigez un message précis : symptômes, investigations et question. Recevez ensuite sa réponse pour reprendre le traitement. Les échanges restent visibles dans Conversation.",
      ],
      [
        "Documenter",
        "Dans Notes, enregistrez vos observations techniques. Conversation rassemble les échanges ; Historique conserve les actions, tests et versions de code enregistrées pour vous et votre professeur.",
      ],
      [
        "Résoudre et clôturer",
        "Dans Actions, documentez la cause ou l’analyse de la demande, les actions réalisées, les tests, le résultat et le message utilisateur. Selon la procédure du scénario, recevez ensuite la validation simulée du demandeur : une confirmation permet la clôture ; un retour négatif rouvre le ticket et demande de nouvelles vérifications. L’objectif affiché précise si le parcours se termine à la résolution ou à la clôture.",
      ],
    ].forEach(([title, text]) => {
      const item = el("li");
      item.append(el("strong", title), el("p", text));
      steps.append(item);
    });
    guide.append(steps);
    guide.append(
      el(
        "p",
        "Pour garder une trace de votre travail, utilisez Télécharger mon bilan (.md), en haut de la tentative. Enregistrez votre code et vos notes avant le téléchargement. SegFault propose une appréciation de vos réponses libres et des conseils à partir des traces. Cet avis IA est distinct de l’évaluation finale du professeur. La préparation peut prendre quelques instants ; si l’IA est indisponible, le bilan reste téléchargeable.",
      ),
    );
    guide.append(
      el(
        "p",
        "Aucun scénario affiché ? Vérifiez votre compte et demandez au professeur de vérifier la publication et l’affectation. Une tentative archivée se consulte en lecture seule. Si un autre onglet a modifié la tentative, actualisez avant de réessayer.",
      ),
    );
    return guide;
  }
  async function home(root) {
    const loading=el('p','Chargement des activités et tentatives…');loading.setAttribute('role','status');
    root.replaceChildren(...moduleTitle(root), studentGuide(),loading);
    try {
      const [assignments, attempts] = await Promise.all([
        api("/assignments"),
        api("/attempts"),
      ]);
      root.replaceChildren(
        ...moduleTitle(root),
        el("p", "Recevoir · qualifier · investiguer · communiquer · résoudre"),
        studentGuide(),
      );
      const filter = field(root, cfg.canObserve ? "Rechercher un élève, un scénario ou une activité" : "Rechercher un scénario ou une activité");filter.type='search';
      const list = el("div");
      root.append(list);
      const draw = () => {
        list.replaceChildren(el("h3", "Scénarios affectés"));
        let found=0;
        assignments
          .filter((a) =>
            a.title.toLowerCase().includes(filter.value.toLowerCase()),
          )
          .forEach((a) => {
            found++;
            list.append(
              button(
                a.title + " — Ouvrir",
                async () => {
                  const attempt = await api(
                    `/assignments/${a.id}/attempts`,
                    "POST",
                    {},
                  );
                  new Desk(root, await api("/attempts/" + attempt.id), () =>
                    home(root),
                  );
                },
                root,
              ),
            );
          });
        if(!found)list.append(el('p','Aucune activité affectée pour cette recherche.'));
        const activityCount=found;
        list.append(el("h3", cfg.canObserve ? "Tentatives accessibles" : "Mes tentatives"));
        attempts.forEach((a) => {
          const assignment = assignments.find(
            (x) => Number(x.id) === Number(a.assignment_id),
          );
          const title = a.scenario_title ? `${a.scenario_title}${a.activity_name?' — '+a.activity_name:''}` : assignment?.title || `Scénario #${a.scenario_id}`;
          if (!`${title} ${cfg.canObserve?a.student_name:''}`.toLowerCase().includes(filter.value.toLowerCase())) return;
          found++;
          list.append(
            button(
              `${cfg.canObserve?a.student_name+' — ':''}${title} · ${stateNames[a.status]||a.status}${a.assessment_state?' · '+stateNames[a.assessment_state]:''} · tentative #${a.number ?? a.id}`,
              async () =>
                new Desk(root, await api("/attempts/" + a.id), () =>
                  home(root),
                ),
              root,
            ),
          );
        if(!found && (assignments.length || attempts.length))list.append(el('p','Aucune activité ni tentative pour cette recherche.'));
        });
        if(found===activityCount)list.append(el('p','Aucune tentative pour cette recherche.'));
        if (!assignments.length && !attempts.length)
          list.append(
            el(
              "p",
              "Votre professeur ne vous a pas encore affecté de scénario.",
            ),
          );
      };
      filter.addEventListener("input", draw);
      draw();
    } catch (e) {
      error(root, e);
    }
  }
  window.OuinpoTicketUI = {
    api,
    el,
    button,
    field,
    select,
    error,
    Desk,
    statuses,
    stateNames,
    dateLabel,
    timeZone,
    traceContent,
  };
  document.querySelectorAll("[data-ticket-student]").forEach(home);
})();
