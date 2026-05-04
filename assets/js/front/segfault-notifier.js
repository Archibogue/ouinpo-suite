(function(){

  // util

  function esc(s){

    return String(s).replace(/[&<>"']/g, function(m){

      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m];

    });

  }



  function getLauncher(){

    return document.querySelector('#segfault-launcher') || document.querySelector('.sf-launcher');

  }



  function setBadge(n){

    var btn = getLauncher(); if(!btn) return;

    var b = btn.querySelector('.sf-badge');

    if(n > 0){

      if(!b){

        b = document.createElement('span');

        b.className = 'sf-badge';

        btn.appendChild(b);

      }

      b.textContent = ''+n;

    } else {

      if(b) b.remove();

    }

  }



  function addQuery(endpoint, params){

    var url = String(endpoint || '');

    var parts = [];

    Object.keys(params || {}).forEach(function(key){

      parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(params[key]));

    });

    if(!parts.length) return url;

    return url + (url.indexOf('?') === -1 ? '?' : '&') + parts.join('&');

  }



  // Affiche dans la chatbox si possible

  function pushBubble(data){

    if(!data || !data.count) return false;

    var labels = {

      cours:'Cours',

      corriges:'Corrigés',

      tp:'TP',

      projets:'Projets',

      ressources:'Ressources',

      badges:'Badges'

    };

    var html = "🐾 <em>SegFault</em> a reniflé des nouveautés :<br>";

    ['cours','corriges','tp','projets','ressources','badges'].forEach(function(key){

      var arr = (data.sections && data.sections[key]) || [];

      if(!arr.length) return;

      html += '<div class="sf-notifier-section"><strong>'+labels[key]+' :</strong><ul class="sf-notifier-list">';

      arr.slice(0,5).forEach(function(it){

        html += '<li><a href="'+it.url+'">'+esc(it.title)+'</a>';

        if (it.date){

          html += ' <span class="sf-notifier-date">('+esc(it.date)+')</span>';

        }

        html += '</li>';

      });

      if(arr.length>5) html += '<li>… et '+(arr.length-5)+' de plus</li>';

      html += '</ul></div>';

    });

    if(window.SegFaultChat && typeof window.SegFaultChat.addBubble === 'function'){

      window.SegFaultChat.addBubble(html, 'assistant');

      return true;

    }

    return false;

  }



  async function checkOnce(){

    try{

      if(!window.OuInPoRes) return;



      var url = addQuery(window.OuInPoRes.endpoint, {limit:50});

      var resp = await fetch(url, {

        credentials:'same-origin',

        headers:{'X-WP-Nonce': window.OuInPoRes.nonce}

      });

      if(!resp.ok) return;

      var data = await resp.json();



      // Si pas de données, on crée une structure vide

      if(!data){

        data = {count:0, sections:{}};

      }

      data.count = data.count || 0;

      data.sections = data.sections || {};



      // 🔗 On fusionne les NOUVEAUX BADGES comme une "section" supplémentaire

      if(window.OuInPoBadges && OuInPoBadges.count){

        var badges = OuInPoBadges.badges || [];

        var list = [];

        badges.forEach(function(b){

          list.push({

            url: (window.OuInPoBadgesUrl || '#'),

            title: '🎖 ' + (b.title || b.slug),

            date: b.awarded_at || ''

          });

        });

        if(list.length){

          data.sections.badges = (data.sections.badges || []).concat(list);

          data.count += list.length;

        }

      }



      if(!data.count){

        setBadge(0);

        return;

      }



      // Tente bulle immédiate, sinon badge rouge

      var bubbled = pushBubble(data);

      if(!bubbled) setBadge(data.count);



      // Marque les ressources vues (côté API)

      fetch(addQuery(window.OuInPoRes.endpoint, {mark:1}), {

        credentials:'same-origin',

        headers:{'X-WP-Nonce': window.OuInPoRes.nonce}

      }).catch(function(){});

    }catch(e){}

  }



  // Quand la chatbox est prête

  window.addEventListener('segfault:ready', function(){

    checkOnce().then(function(){ /* rien */ });

  });



  // Premier check au chargement

  document.addEventListener('DOMContentLoaded', function(){

    setTimeout(checkOnce, 600);

  });

})();
