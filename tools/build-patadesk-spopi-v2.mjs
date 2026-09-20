// Builds new versions only. The original import and course files are read-only inputs.
import fs from 'node:fs';
const dir=new URL('./templates/patadesk/',import.meta.url);
const original=JSON.parse(fs.readFileSync(new URL('PataDesk_AP_Portail_SPOPI-corrige.json',dir),'utf8'));
const base=structuredClone(original);
base.priority_policy='Grille pédagogique propre à ce dossier SPOPI (aucune équivalence universelle) :\nP1 / Haute : fonction utile bloquée pour plusieurs utilisateurs ou information trompeuse risquant de perdre une demande ; expliquer impact et urgence.\nP2 / Normale : gêne limitée, contournement possible ou impact de présentation.\nBasse : évolution de confort planifiable. Critique : à justifier par un arrêt majeur non décrit ici.\nUne autre priorité argumentée peut être pertinente : comparaison indicative, décision de l’enseignant.';
base.service_agreement='Extrait fictif de contrat pour cet exercice : périmètre = portail interne SPOPI sur environnement pédagogique fourni. Prise en charge P1 sous 30 minutes ouvrées ; P2 sous 4 heures ouvrées ; évolutions à planifier après analyse. Aucun délai de résolution garanti dans ce dossier. Escalade au référent applicatif si hors périmètre, droits insuffisants ou diagnostic non abouti. Informer le demandeur du prochain point de suivi. La fin de séance est une échéance pédagogique, pas un SLA.';
for(const t of base.tickets) {
  t.fields.nature=t.id.startsWith('E')?'evolution':t.id==='P03'?'service':'incident';
  t.expected.nature=t.fields.nature;
  if(t.fields.category.startsWith('Demande')) t.fields.category=t.id==='P03'?'Identité et accès':'Applicatif / Web';
  t.expected.category=t.fields.category;
  t.fields.sla='Voir l’extrait de contrat du scénario : prise en charge distincte de la résolution.';
  for(const a of t.actions) {
    if(a.label==='Ajouter une note technique') a.type='technical_note';
  }
}
function save(name,s) {fs.writeFileSync(new URL(name,dir),JSON.stringify(s,null,2)+'\n');}
const practical=structuredClone(base);
practical.title='SPOPI v2 — Diagnostic et intervention extérieure, parcours essentiel';
practical.description='Semaines 5 à 7 : trois tickets préqualifiés (T01, T03, T05). Reproduire sur la copie locale du portail fournie par l’enseignant, tester une hypothèse, corriger et vérifier. PataDesk conserve les traces ; sa console ne fait que comparer un texte à une référence. Les preuves déclarées et toute correction différente sont évaluées par l’enseignant. T02/T04, les évolutions et les passerelles SISR sont des activités séparées. Aucun code n’est exécuté sur WordPress.';
practical.completion_status='resolved';
practical.tickets=practical.tickets.filter(t=>['T01','T03','T05'].includes(t.id));
for(const t of practical.tickets) {
  t.guided=false; t.bad_resolution='accept'; t.resolution_tests=[];
  t.trace_required=['symptom','hypothesis','observed','correction','verification','proof'];
  // Text comparison is advisory, never a gate rejecting a valid external correction.
  for(const a of t.actions.filter(a=>a.type==='resolve')) a.requires=[];
}
save('SPOPI-diagnostic-essentiel-v2.json',practical);
const extension=structuredClone(base);
extension.title='SPOPI v2 — Approfondissements facultatifs séparés';
extension.description='Activité indépendante : T02/T04, évolutions et passerelles support/réseau. À proposer après le parcours essentiel ; ne retire aucun point à ce dernier. Manipulations extérieures déclarées et appréciation humaine. Les comparaisons de code sont indicatives.';
extension.tickets=extension.tickets.filter(t=>!['T01','T03','T05'].includes(t.id));
for(const t of extension.tickets) {t.guided=false;t.bad_resolution='accept';t.resolution_tests=[];for(const a of t.actions.filter(a=>a.type==='resolve')) a.requires=[];}
save('SPOPI-approfondissements-v2.json',extension);
const qualification=structuredClone(base);
qualification.title='SPOPI v2 — Collecter et qualifier une demande brute';
qualification.description='Semaines 2 à 4 : parcours court sur une demande brute, collecte, nature distincte de la catégorie technique, impact/urgence/priorité argumentée et réponse initiale selon l’extrait contractuel. Terminer l’exercice sans résoudre le ticket. Utilisable en entraînement ou dans une activité notée distincte. Ne constitue pas le sujet écrit du 25 septembre 2026.';
qualification.completion_status='qualified';
qualification.tickets=qualification.tickets.filter(t=>t.id==='T01');
const q=qualification.tickets[0];
q.intake_mode='from_request';
q.raw_request='Bonjour, je suis Gérard, du centre de services SPOPI. Depuis ce matin, quand je clique sur Centre de services sur la page d’accueil du portail, ça ne s’ouvre pas. Ma collègue a la même chose. Nous pouvons encore utiliser le numéro habituel mais c’est gênant. Pouvez-vous regarder ?';
q.description='Construisez un ticket à partir du message original. Aucune correction technique n’est attendue dans ce parcours.';
q.fields={nature:'',category:'À qualifier',impact:'À qualifier',urgency:'À qualifier',priority:'À qualifier',priority_justification:''};
q.qualification_required=['nature','category','impact','urgency','priority','priority_justification'];
q.trace_required=['context','information','initial_response'];
q.guided=false;q.resources=[];q.visible_resources=[];q.resolution_requires=[];q.resolution_tests=[];delete q.requester_validation;
q.actions=[{id:'take',type:'take',label:'Prendre en charge',states:['new']},{id:'note',type:'technical_note',label:'Ajouter une note technique',requires_message:true,repeatable:true},{id:'initial',type:'communication',label:'Envoyer une réponse initiale',requires_message:true,repeatable:true}];
qualification.resources=[];
save('SPOPI-qualification-v2.json',qualification);
const orientation=structuredClone(qualification);
orientation.title='SPOPI v2 — Qualification et orientation documentée';
orientation.completion_status='oriented';
orientation.description+=' Approfondissement court : expliquer le destinataire, le périmètre et les informations transmises dans la trace d’orientation.';
orientation.tickets[0].trace_required.push('orientation');
save('SPOPI-orientation-v2.json',orientation);
