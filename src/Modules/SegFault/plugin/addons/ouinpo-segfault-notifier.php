<?php

// Module interne OuInPo Suite : SegFault Notifier.



if (!defined('ABSPATH')) exit;



class OuInPo_Segfault_Notifier {

    const VERSION = '1.5.5';



    public function __construct() {

        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

    }



    public function enqueue_assets() {

            if (!is_user_logged_in()) {

                return;

            }



        // CSS badge

        $css_rel = 'assets/css/front/segfault-notifier.css';

        $css_path = defined('OUINPO_SUITE_DIR')
            ? OUINPO_SUITE_DIR . $css_rel
            : '';

        $css_url = defined('OUINPO_SUITE_URL')
            ? OUINPO_SUITE_URL . $css_rel
            : '';

        if ($css_url !== '') {
            $css_ver = ($css_path !== '' && file_exists($css_path))
                ? (string) filemtime($css_path)
                : self::VERSION;

            $deps = [];

            if (wp_style_is('ouinpo-core-css', 'registered')) {
                $deps[] = 'ouinpo-core-css';
            }

            wp_enqueue_style(
                'ouinpo-segfault-notifier',
                $css_url,
                $deps,
                $css_ver
            );
        }



        // JS

        wp_register_script('ouinpo-segfault-notify', false, array(), self::VERSION, true);

        wp_enqueue_script('ouinpo-segfault-notify');



        /**

         * 🔎 BADGES : on regarde dans {prefix}ouin_exo_user_badges ce qui existe

         * depuis la dernière fois où on a affiché quelque chose.

         */

        $badges_payload = array(

            'count'  => 0,

            'badges' => array(),

        );



        $user_id = get_current_user_id();

        if ($user_id) {

            global $wpdb;

            $t_user_badges = $wpdb->prefix . 'ouin_exo_user_badges';

            $t_badges      = $wpdb->prefix . 'ouin_exo_badges';



            $last_seen = get_user_meta($user_id, 'ouinpo_badges_seen_at', true);



            // Première fois : on remonte très loin dans le temps pour capter les badges déjà là

            if (empty($last_seen)) {

                $last_seen = '1970-01-01 00:00:00';

            }



            $rows = $wpdb->get_results(

                $wpdb->prepare(

                    "SELECT ub.badge_id, ub.awarded_at, b.slug, b.title, b.description

                       FROM $t_user_badges ub

                       JOIN $t_badges b ON b.id = ub.badge_id

                      WHERE ub.user_id = %d

                        AND ub.awarded_at > %s

                      ORDER BY ub.awarded_at DESC

                      LIMIT 10",

                    $user_id,

                    $last_seen

                ),

                ARRAY_A

            );



            if (!empty($rows)) {

                foreach ($rows as $r) {

                    $badges_payload['badges'][] = array(

                        'id'          => (int) $r['badge_id'],

                        'slug'        => $r['slug'],

                        'title'       => $r['title'],

                        'description' => $r['description'],

                        'awarded_at'  => $r['awarded_at'],

                    );

                }

                $badges_payload['count'] = count($badges_payload['badges']);



                // On n’avance le curseur QUE s’il y a eu de nouveaux badges

                update_user_meta($user_id, 'ouinpo_badges_seen_at', current_time('mysql'));

            }

        }



        // Exposer au JS

        wp_localize_script('ouinpo-segfault-notify', 'OuInPoBadges', $badges_payload);



        // JS inline : ressources + badges (les badges sont considérés comme une "section" de ressources)

        $js = <<<'JS'

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

JS;



        wp_add_inline_script('ouinpo-segfault-notify', $js);

    }

}



new OuInPo_Segfault_Notifier();

