(function() {
  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  ready(function() {
    const config = window.OuinpoSfStudentParcours || {};
    const ajaxUrl = config.ajaxUrl || '';
    const nonce = config.nonce || {};
    const comps = config.comps || [];
    const prefill = config.prefill || {};

    const selDomain = document.getElementById("sf-gen-domain");
    const selComp = document.getElementById("sf-gen-comp");
    const inpLimit = document.getElementById("sf-gen-limit");
    const btn = document.getElementById("sf-gen-btn");
    const msg = document.getElementById("sf-gen-msg");
    const tplMsg = document.getElementById("sf-template-msg");
    const tplDomain = document.getElementById("sf-template-domain");
    const tplGoal = document.getElementById("sf-template-goal");
    const tplEmpty = document.getElementById("sf-template-empty");
    const tplFilterBtn = document.getElementById("sf-template-filter-btn");

    if (!selDomain && !tplFilterBtn && !document.querySelector(".sf-use-template") && !document.querySelector(".sf-delete-path")) {
      return;
    }

    function setMsg(target, text) {
      if (target) target.textContent = text || "";
    }

    function escHtml(s) {
      return (s || "").replace(/[&<>"']/g, function(m) {
        return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[m] || m;
      });
    }

    function renderCompsForDomain(domainVal) {
      if (!selComp) return;

      const d = (domainVal || "").trim();
      if (!d) {
        selComp.innerHTML = '<option value="0">— Toutes —</option>';
        selComp.disabled = true;
        return;
      }

      const list = comps.filter(function(c) {
        const slug = (c.domain_slug || "").trim();
        const dom = (c.domain || "").trim();
        return slug ? slug === d : dom === d;
      });

      let html = '<option value="0">— Toutes —</option>';
      let curTrack = "";

      list.forEach(function(c) {
        const track = (c.track || "").trim();
        const cid = parseInt(c.id, 10) || 0;
        const comp = (c.competency || "").trim();
        const label = comp.trim();
        if (!cid) return;

        if (track !== curTrack) {
          if (curTrack) html += '</optgroup>';
          curTrack = track;
          html += '<optgroup label="' + escHtml(curTrack || 'Compétences') + '">';
        }

        html += '<option value="' + cid + '">' + escHtml(label) + '</option>';
      });

      if (curTrack) html += '</optgroup>';
      selComp.innerHTML = html;
      selComp.disabled = false;
    }

    function applyPrefill() {
      if (selDomain && prefill.domain) {
        selDomain.value = prefill.domain;
        renderCompsForDomain(prefill.domain);
      } else if (selDomain) {
        renderCompsForDomain(selDomain.value);
      }

      if (selComp && prefill.compId) {
        selComp.value = String(prefill.compId);
      }
    }

    async function postAction(params) {
      const body = new URLSearchParams();
      Object.keys(params).forEach(function(k) {
        body.set(k, String(params[k]));
      });

      const res = await fetch(ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: {"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},
        body: body.toString()
      });

      return await res.json();
    }

    async function generate() {
      const domain = (selDomain && selDomain.value || "").trim();
      if (!domain) {
        setMsg(msg, "Choisis un domaine.");
        return;
      }

      const compId = (selComp && !selComp.disabled) ? (parseInt(selComp.value, 10) || 0) : 0;
      let limit = parseInt(inpLimit && inpLimit.value, 10) || 7;
      if (limit < 1) limit = 1;
      if (limit > 25) limit = 25;

      setMsg(msg, "Génération…");

      let data = null;
      try {
        data = await postAction({
          action: "ouinpo_sf_student_generate_path",
          nonce: nonce.gen,
          domain_value: domain,
          competency_id: compId,
          limit: limit
        });
      } catch(e) {
        setMsg(msg, "Erreur réseau (AJAX).");
        return;
      }

      if (!data || !data.success) {
        setMsg(msg, "Erreur lors de la génération.");
        return;
      }

      if (data.data && data.data.ok) {
        const pathId = data.data.path_id;
        setMsg(msg, "Parcours créé (" + (data.data.count || 0) + " exos). Ouverture…");
        const url = new URL(window.location.href);
        url.searchParams.set("sf_path", String(pathId));
        window.location.href = url.toString();
        return;
      }

      setMsg(msg, "Aucun exercice trouvé pour ce domaine (ou déjà tous réussis).");
    }

    async function useTemplate(templateId) {
      if (!templateId) return;

      setMsg(tplMsg, "Création du parcours depuis le modèle…");

      let data = null;
      try {
        data = await postAction({
          action: "ouinpo_sf_student_use_template",
          nonce: nonce.tpl,
          template_id: templateId
        });
      } catch(e) {
        setMsg(tplMsg, "Erreur réseau (AJAX).");
        return;
      }

      if (!data || !data.success || !data.data || !data.data.ok) {
        setMsg(tplMsg, "Impossible de créer ce parcours à partir du modèle.");
        return;
      }

      const url = new URL(window.location.href);
      url.searchParams.set("sf_path", String(data.data.path_id));
      window.location.href = url.toString();
    }

    async function deletePath(pathId) {
      if (!pathId) return;
      if (!window.confirm("Supprimer ce parcours de ton espace élève ?")) return;

      let data = null;
      try {
        data = await postAction({
          action: "ouinpo_sf_student_delete_path",
          nonce: nonce.del,
          path_id: pathId
        });
      } catch(e) {
        window.alert("Erreur réseau.");
        return;
      }

      if (!data || !data.success) {
        window.alert("Suppression impossible pour ce parcours.");
        return;
      }

      const url = new URL(window.location.href);
      if (url.searchParams.get("sf_path") === String(pathId)) {
        url.searchParams.delete("sf_path");
      }
      window.location.href = url.toString();
    }

    function filterTemplates() {
      const domainVal = (tplDomain && tplDomain.value || "").trim();
      const goalVal = (tplGoal && tplGoal.value || "").trim();
      const rows = Array.from(document.querySelectorAll(".sf-template-row"));

      let visibleCount = 0;

      rows.forEach(function(row) {
        const rowDomain = (row.getAttribute("data-domain") || "").trim();
        const rowGoal = (row.getAttribute("data-goal") || "").trim();

        const okDomain = !domainVal || rowDomain === domainVal;
        const okGoal = !goalVal || rowGoal === goalVal;
        const show = okDomain && okGoal;

        row.style.display = show ? "" : "none";
        if (show) visibleCount++;
      });

      if (tplEmpty) {
        tplEmpty.style.display = visibleCount === 0 ? "" : "none";
      }

      setMsg(tplMsg, "");
    }

    if (selDomain) {
      selDomain.addEventListener("change", function() {
        renderCompsForDomain(selDomain.value);
        setMsg(msg, "");
      });
    }

    applyPrefill();

    if (btn) btn.addEventListener("click", generate);

    if (tplFilterBtn) tplFilterBtn.addEventListener("click", filterTemplates);
    if (tplDomain) tplDomain.addEventListener("change", function() { setMsg(tplMsg, ""); });
    if (tplGoal) tplGoal.addEventListener("change", function() { setMsg(tplMsg, ""); });
    filterTemplates();

    document.addEventListener("click", function(ev) {
      const tplBtn = ev.target.closest(".sf-use-template");
      if (tplBtn) {
        ev.preventDefault();
        useTemplate(parseInt(tplBtn.getAttribute("data-template-id"), 10) || 0);
        return;
      }

      const delBtn = ev.target.closest(".sf-delete-path");
      if (delBtn) {
        ev.preventDefault();
        deletePath(parseInt(delBtn.getAttribute("data-path-id"), 10) || 0);
      }
    });
  });
})();
