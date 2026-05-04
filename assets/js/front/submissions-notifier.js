(function(){

  var LS_KEY = 'ouinpo_res_unread_v1';

  var badge = null;



  var __OUINPO_INJECTED_THIS_VIEW = false;

  function maybeInjectNow(){

    if (__OUINPO_INJECTED_THIS_VIEW) return;

    __OUINPO_INJECTED_THIS_VIEW = true;

    injectFromMemory();

    getNew(false).then(function(d){ persistAndMaybeUpdate(d); });

  }



  var __OUINPO_LAST_INJECT_KEY = null;

  function _payloadKey(data){

    try{

      var s = '';

      ['cours','corriges','tp','projets','ressources'].forEach(function(k){

        (data.sections[k]||[]).forEach(function(it){

          s += (it.id||it.url||'') + '|';

        });

        s += '~';

      });

      return s || 'empty';

    }catch(_){ return 'empty'; }

  }



  function ensureBadge(){

    if (badge && document.body.contains(badge)) return badge;

    if (!document.body) return null;

    badge = document.createElement('div');

    badge.className = 'ouinpo-notif-float';

    badge.style.display = 'none';

    badge.addEventListener('click', function(){

      var t = document.getElementById('sf-toggle');

      if (t && typeof t.click==='function') t.click();

      setTimeout(function(){ getNew(false).then(function(d){ if(d) persistAndMaybeUpdate(d); }); }, 50);

    });

    document.body.appendChild(badge);

    return badge;

  }

  function setBadgeCount(n){

    var b = ensureBadge(); if (!b) return;

    if (n>0) { b.textContent = 'Nouvelles ressources ('+n+')'; b.style.display='block'; }

    else {

      b.style.display='none';

      if (b.parentNode) b.parentNode.removeChild(b);

      badge = null;

    }

  }



  function readMem(){

    try{

      var raw = localStorage.getItem(LS_KEY);

      if (!raw) return null;

      var obj = JSON.parse(raw);

      if (!obj || typeof obj.count!=='number') return null;

      return obj;

    }catch(_){ return null; }

  }

  function writeMem(data){

    try{

      localStorage.setItem(LS_KEY, JSON.stringify({

        count: data.count||0,

        payload: { count:data.count||0, sections:data.sections||{} },

        ts: Date.now()

      }));

    }catch(_){}

  }

  function clearMem(){

    try{ localStorage.removeItem(LS_KEY); }catch(_){}

    __OUINPO_LAST_INJECT_KEY = null;

    try{

      var b = document.querySelector('.ouinpo-notif-float');

      if (b && b.parentNode) b.parentNode.removeChild(b);

    }catch(_){}

  }



  function esc(s){ return String(s).replace(/[&<>\"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',\"'\":'&#039;'})[c]; }); }



  function injectMessage(data){

    if (!data || !data.sections) return;

    var total = data.count||0; if (total<=0) return;



    var key = _payloadKey(data);

    if (__OUINPO_LAST_INJECT_KEY === key) return;

    __OUINPO_LAST_INJECT_KEY = key;



    var order=['cours','corriges','tp','projets','ressources'];

    var labels={cours:'Cours',corriges:'Corrigés',tp:'TP',projets:'Projets',ressources:'Ressources'};



    var seg = (typeof window.OUINPO_SEGF_PHRASE==='string' && window.OUINPO_SEGF_PHRASE)

              ? ' <em>'+esc(window.OUINPO_SEGF_PHRASE)+'</em>' : '';

    var html=['<div><strong>🐾 Nouvelles ressources</strong> ('+total+') :'+seg+'</div>'];



    order.forEach(function(k){

      var arr = (data.sections[k]||[]);

      if (!arr.length) return;

      html.push('<div class=\"ouinpo-res-notif-section-title\">'+labels[k]+'</div><ul class=\"ouinpo-res-notif-list\">');

      arr.slice(0,5).forEach(function(it){

    var href = (typeof it.url === 'string' && it.url) ? esc(it.url) : '#';

    html.push('<li><a href=\"'+href+'\">'+esc(it.title)+'</a> <span class=\"ouinpo-res-notif-date\">('+esc(it.date)+')</span></li>');

      });

      if (arr.length>5) html.push('<li>… et '+(arr.length-5)+' de plus</li>');

      html.push('</ul>');

    });



    var tries=0, markup = html.join('');

    (function push(){

      if (window.SegFaultChat && typeof window.SegFaultChat.addBubble==='function'){

        window.SegFaultChat.addBubble(markup,'assistant');

      } else {

        var box = document.getElementById('sf-chat-floating') || document.getElementById('sf-chat-inline');

        var msgs = box && box.querySelector('.sf-messages');

        if (msgs){

          var d = document.createElement('div');

          d.className = 'sf-bubble assistant';

          d.innerHTML = markup;

          msgs.appendChild(d);

          msgs.scrollTop = msgs.scrollHeight;

          return;

        }

        if (tries++ < 30) { setTimeout(push, 150); return; }

      }

    })();

  }

  function injectFromMemory(){

    var m = readMem();

    if (m && m.count>0 && m.payload) injectMessage(m.payload);

  }



  function getNew(mark){

    if (!window.OuInPoRes || !OuInPoRes.endpoint) return Promise.resolve(null);

    var url = OuInPoRes.endpoint + (OuInPoRes.endpoint.indexOf('?')>-1?'&':'?') + 'limit=50' + (mark?'&mark=1':'');

    return fetch(url, {headers:{'X-WP-Nonce':OuInPoRes.nonce,'Accept':'application/json'}, credentials:'same-origin'})

      .then(function(r){ return r.json(); })

      .catch(function(){ return null; });

  }

  function persistAndMaybeUpdate(d){

    if (!d) return;

    if ((d.count||0) > 0){

      writeMem(d);

      setBadgeCount(d.count||0);

    } else {

      var m = readMem();

      if (m && m.count>0){

        setBadgeCount(m.count);

      } else {

        setBadgeCount(0);

      }

    }

  }



  function boot(){

    ensureBadge();



    if (document.getElementById('ouinpo-resources-view')) {

      clearMem();

      return;

    }



    var m = readMem();

    setBadgeCount(m && m.count>0 ? m.count : 0);



    if (window.OuInPoRes && OuInPoRes.endpoint){

      getNew(false).then(function(d){ persistAndMaybeUpdate(d); });

    } else {

      var elapsed=0, t=setInterval(function(){

        elapsed+=200;

        if (window.OuInPoRes && OuInPoRes.endpoint){

          clearInterval(t);

          getNew(false).then(function(d){ persistAndMaybeUpdate(d); });

        }

        if (elapsed>=20000) clearInterval(t);

      },200);

    }

  }



  if (document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', boot); } else { boot(); }



  document.addEventListener('click', function(e){

    var t = e.target && e.target.closest ? e.target.closest('#sf-toggle') : null;

    if (!t) return;

    setTimeout(maybeInjectNow, 80);

  });



  function chatIsVisible(){

    var box = document.getElementById('sf-chat-floating');

    if (!box) return false;

    return !box.classList.contains('sf-hidden');

  }

  function maybeInjectIfVisible(){

    if (chatIsVisible()) maybeInjectNow();

  }

  (function(){

    var box = document.getElementById('sf-chat-floating');

    if (!box) return;

    var mo = new MutationObserver(function(){

      if (chatIsVisible()) maybeInjectNow();

    });

    mo.observe(box, { attributes:true, attributeFilter:['class'] });

  })();

  setTimeout(maybeInjectIfVisible, 150);



  window.__OUINPO_CLEAR_RES_UNREAD = function(){ clearMem(); setBadgeCount(0); };

})();
