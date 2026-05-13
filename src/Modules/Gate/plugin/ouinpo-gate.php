<?php
// Module interne OuInPo Suite : Gate.

if (!defined('ABSPATH')) {
    exit;
}

/* ================= Helpers ================= */
function ouinpo_gate_salt(){ return 'ouinpo_salt_2025_change_me'; } // ⚠️ à personnaliser

function ouinpo_norm($s){
  $s = trim((string)$s);
  // guillemets typographiques & espaces Unicode → ASCII
  $map = [
    "\xC2\xA0"=>' ', "\xE2\x80\x89"=>' ', "\xE2\x80\x8A"=>' ', "\xE2\x80\xAF"=>' ',
    "‘"=>"'", "’"=>"'", "‚"=>"'", "‛"=>"'", "“"=>'"', "”"=>'"', "„"=>'"', "‟"=>'"'
  ];
  $s = strtr($s, $map);
  // 🔧 enlève tout antislash (\' etc.)
  $s = str_replace('\\', '', $s);
  // unifier sur l'apostrophe simple
  $s = str_replace('"', "'", $s);
  // supprimer TOUS les espaces (Unicode compris)
  $s = preg_replace('~[\s\p{Z}]+~u', '', $s);
  // retirer ; final
  $s = rtrim($s, ';');
  // casse insensible
  return mb_strtolower($s, 'UTF-8');
}
function ouinpo_hash($s){ return hash('sha256', ouinpo_gate_salt().ouinpo_norm($s)); }

function ouinpo_gate_enqueue_assets(): void {
  $rel = 'assets/css/front/gate.css';
  $root_file = dirname(__DIR__, 4) . '/ouinpo-suite.php';
  $base_url = defined('OUINPO_SUITE_URL') ? OUINPO_SUITE_URL : plugin_dir_url($root_file);
  $base_dir = defined('OUINPO_SUITE_DIR') ? OUINPO_SUITE_DIR : dirname(__DIR__, 4) . '/';
  $file = $base_dir . $rel;
  $version = defined('OUINPO_SUITE_VERSION') ? OUINPO_SUITE_VERSION : '1.0.0';

  if (file_exists($file)) {
    $version = (string) filemtime($file);
  }

  wp_enqueue_style('ouinpo-gate-css', $base_url . $rel, [], $version);
}

function ouinpo_gate_enqueue_signpad_script(): void {
  $rel = 'assets/js/front/gate.js';
  $root_file = dirname(__DIR__, 4) . '/ouinpo-suite.php';
  $base_url = defined('OUINPO_SUITE_URL') ? OUINPO_SUITE_URL : plugin_dir_url($root_file);
  $base_dir = defined('OUINPO_SUITE_DIR') ? OUINPO_SUITE_DIR : dirname(__DIR__, 4) . '/';
  $file = $base_dir . $rel;
  $version = defined('OUINPO_SUITE_VERSION') ? OUINPO_SUITE_VERSION : '1.0.0';

  if (file_exists($file)) {
    $version = (string) filemtime($file);
  }

  wp_enqueue_script('ouinpo-gate-js', $base_url . $rel, [], $version, true);
}

/* ================= Corpus des 42 énigmes ================= */
function ouinpo_enigmes(){
  return [
    /* ==== NIVEAU SNT / INTRO PYTHON ==== */
    [ 'theme'=>'Bases numériques', 'prompt'=>"Combien vaut 0b1010 en base 10 ?", 'canon'=>"10", 'note'=>1 ],
    [ 'theme'=>'Booléens', 'prompt'=>"Python : True and False renvoie ?", 'canon'=>"false", 'note'=>2 ],
    [ 'theme'=>'Chaînes', 'prompt'=>"Python : len('OuInPo') renvoie ?", 'canon'=>"6", 'note'=>3 ],
    [ 'theme'=>'Variables', 'prompt'=>"Nom donné à une valeur mémorisée ?", 'canon'=>"variable", 'note'=>4 ],
    [ 'theme'=>'Entrée / Sortie', 'prompt'=>"Python : fonction pour afficher du texte ?", 'canon'=>"print", 'note'=>5 ],
    [ 'theme'=>'Listes', 'prompt'=>"Python : [1,2,3][0] renvoie ?", 'canon'=>"1", 'note'=>6 ],
    [ 'theme'=>'OuInPo', 'prompt'=>"Quel mot magique fait planter un programme tout en lui donnant du sens ? C'est aussi le nom d'un chat", 'canon'=>"segfault", 'note'=>7 ],

    /* ==== NIVEAU PREMIÈRE — STRUCTURES, CONDITIONS, BOUCLES ==== */
    [ 'theme'=>'Conditions', 'prompt'=>"Mot-clé Python pour une alternative ?", 'canon'=>"else", 'note'=>8 ],
    [ 'theme'=>'Boucles', 'prompt'=>"Mot-clé Python pour répéter un bloc tant qu’une condition est vraie ?", 'canon'=>"while", 'note'=>9 ],
    [ 'theme'=>'Listes', 'prompt'=>"Python : [2,3]*2 renvoie ?", 'canon'=>"[2, 3, 2, 3]", 'note'=>10 ],
    [ 'theme'=>'Dictionnaires', 'prompt'=>"Python : {'a':1}['a'] renvoie ?", 'canon'=>"1", 'note'=>11 ],
    [ 'theme'=>'Compréhensions', 'prompt'=>"[x*2 for x in [1,2,3]] renvoie ?", 'canon'=>"[2, 4, 6]", 'note'=>12 ],
    [ 'theme'=>'Fonctions', 'prompt'=>"Mot-clé Python pour définir une fonction ?", 'canon'=>"def", 'note'=>13 ],
    [ 'theme'=>'OuInPo', 'prompt'=>"Lorsque le code propose des solutions imaginaires, comment s’appelle cette science ?", 'canon'=>"'pataphysique", 'note'=>14 ],

    /* ==== ALGORITHMIQUE / STRUCTURES DE DONNÉES ==== */
    [ 'theme'=>'Algorithmes', 'prompt'=>"Nom de l’algorithme qui cherche un élément en parcourant la liste entière ?", 'canon'=>"recherche séquentielle", 'note'=>15 ],
    [ 'theme'=>'Tri', 'prompt'=>"Complexité du tri par insertion au pire ?", 'canon'=>"o(n^2)", 'note'=>16 ],
    [ 'theme'=>'Pile (LIFO)', 'prompt'=>"Après empiler 1, empiler 2, dépiler → renvoie ?", 'canon'=>"2", 'note'=>17 ],
    [ 'theme'=>'File (FIFO)', 'prompt'=>"Après enfiler A, enfiler B, défiler → renvoie ?", 'canon'=>"a", 'note'=>18 ],
    [ 'theme'=>'POO - base', 'prompt'=>"Python : mot-clé pour créer une classe ?", 'canon'=>"class", 'note'=>19 ],
    [ 'theme'=>'POO - init', 'prompt'=>"Nom de la méthode d’initialisation d’un objet Python ?", 'canon'=>"__init__", 'note'=>20 ],
    [ 'theme'=>'OuInPo', 'prompt'=>"Finir cette sentence : La logique n'empêche ...", 'canon'=>"ni la joie, ni le jeu", 'note'=>21 ],

    /* ==== BASES DE DONNÉES / SQL ==== */
    [ 'theme'=>'SQL', 'prompt'=>"Commande SQL pour extraire des données ?", 'canon'=>"select", 'note'=>22 ],
    [ 'theme'=>'SQL WHERE', 'prompt'=>"Mot-clé SQL pour filtrer ?", 'canon'=>"where", 'note'=>23 ],
    [ 'theme'=>'SQL COUNT', 'prompt'=>"SQL : compter les lignes ?", 'canon'=>"count", 'note'=>24 ],
    [ 'theme'=>'SQL tri', 'prompt'=>"Mot-clés SQL pour trier le résultat ?", 'canon'=>"order by", 'note'=>25 ],
    [ 'theme'=>'SQL clé primaire', 'prompt'=>"Propriété d’une clé primaire : unique et… ?", 'canon'=>"non nulle", 'note'=>26 ],
    [ 'theme'=>'SQL clé étrangère', 'prompt'=>"Elle relie une table à une autre, c’est la clé… ?", 'canon'=>"étrangère", 'note'=>27 ],
    [ 'theme'=>'OuInPo', 'prompt'=>"Quel mot Python provoque souvent la révélation du néant ?", 'canon'=>"None", 'note'=>28 ],

    /* ==== TERMINALE — STRUCTURES COMPLEXES / RÉSEAUX ==== */
    [ 'theme'=>'Graphes', 'prompt'=>"Nom du parcours en largeur ?", 'canon'=>"bfs", 'note'=>29 ],
    [ 'theme'=>'Arbres binaires', 'prompt'=>"Parcours infixe d’un ABR : ordre des clés ?", 'canon'=>"croissant", 'note'=>30 ],
    [ 'theme'=>'Réseaux', 'prompt'=>"Combien d’hôtes utilisables dans un /24 ?", 'canon'=>"254", 'note'=>31 ],
    [ 'theme'=>'Encodage', 'prompt'=>"ASCII : ord('A') vaut ?", 'canon'=>"65", 'note'=>32 ],
    [ 'theme'=>'Algorithmes', 'prompt'=>"Nom de l’algorithme de tri O(n log n) stable ?", 'canon'=>"fusion", 'note'=>33 ],
    [ 'theme'=>'Fichiers', 'prompt'=>"Python : mot-clé pour ouvrir un fichier ?", 'canon'=>"open", 'note'=>34 ],
    [ 'theme'=>'OuInPo', 'prompt'=>"Si ton code devient infini, dans quel univers entre-t-il ?", 'canon'=>"boucle divine", 'note'=>35 ],

    /* ==== TERMINALE AVANCÉE / FIN DE CURSUS ==== */
    [ 'theme'=>'POO - égalité', 'prompt'=>"Méthode spéciale Python pour == ?", 'canon'=>"__eq__", 'note'=>36 ],
    [ 'theme'=>'POO - représentation', 'prompt'=>"Méthode spéciale pour affichage lisible d’un objet ?", 'canon'=>"__repr__", 'note'=>37 ],
    [ 'theme'=>'SQL - jointure', 'prompt'=>"Mot-clé pour combiner plusieurs tables ?", 'canon'=>"join", 'note'=>38 ],
    [ 'theme'=>'Complexité', 'prompt'=>"Quel est le nom de l'algorithme qui permet de trouver le plus court chemin entre un noeud de départ et tous les autres noeuds ?", 'canon'=>"Dijkstra", 'note'=>39 ],
    [ 'theme'=>'Réseaux', 'prompt'=>"Protocole d’envoi de mail ?", 'canon'=>"smtp", 'note'=>40 ],
    [ 'theme'=>'POO - héritage', 'prompt'=>"Mot-clé Python pour hériter d’une classe ?", 'canon'=>"super", 'note'=>41 ],
    [ 'theme'=>'OuInPo', 'prompt'=>"Quel laboratoire virtuel se consacre à la Poétique du Code et à la Science de l'Inutile ?", 'canon'=>"ouinpo", 'note'=>42 ],
  ];
}


/* ================= Aliases & Hashes ================= */
function ouinpo_aliases(){
  return [
    5 => [ // index 5 => 6e énigme
      "t1 = tache(1, 'init', 15)",
      "t1=tache(1,'init',15)",
      "t1 = tache(1, \"init\", 15)",
      "t1=tache(1,\"init\",15)",
      "t1 = Tache(1, 'Init', 15)",
      "t1=Tache(1,'Init',15)",
      "t1 = Tache(1, \"Init\", 15)",
      "t1=Tache(1,\"Init\",15)",
      "t1=Tache(1, 'Init', 15)",
      "t1 =Tache(1,'Init',15)",
      "t1= Tache(1,'Init',15)",
      "t1 = Tache(1, 'Init', 15);",
      "t1=Tache(1,'Init',15);",
    ],
  ];
}
function ouinpo_hashes(){
  $HS = [];
  $aliases = ouinpo_aliases();
  foreach(ouinpo_enigmes() as $i=>$E){
    $set = [$E['canon']];
    if(isset($aliases[$i])) $set = array_merge($set, $aliases[$i]);
    $HS[$i] = array_map('ouinpo_hash', $set);
  }
  return $HS;
}

/* ================= Shortcode principal : [ouinpo_gate] =================
   Usage : [ouinpo_gate page="sample-page" needed="42" reveal="embed|link|redirect"]
*/
add_shortcode('ouinpo_gate', function($atts){
  ouinpo_gate_enqueue_assets();
  ouinpo_gate_enqueue_signpad_script();

  if(!is_user_logged_in()){
    return '<p>🔒 Cette quête ouinpienne est réservée aux membres. <a href="'.esc_url(wp_login_url(get_permalink())).'">Connecte-toi</a> pour commencer.</a></p>';
  }
  $a = shortcode_atts(['page'=>'sample-page','needed'=>42,'reveal'=>'embed'], $atts);
  $page    = sanitize_title($a['page']);
  $needed  = max(1, intval($a['needed']));
  $reveal  = in_array($a['reveal'], ['embed','link','redirect'], true) ? $a['reveal'] : 'embed';
  $uid     = get_current_user_id();

  global $wpdb;
  $t_prog = $wpdb->prefix.'ouinpo_progress';
  $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t_prog WHERE user_id=%d AND page_slug=%s", $uid, $page));
  $progress = $row ? intval($row->progress) : 0;

  $post  = get_page_by_path($page);
  $plink = $post ? get_permalink($post) : home_url('/'.$page.'/');

  $enigmes = ouinpo_enigmes();
  $public  = array_map(fn($e)=>['theme'=>$e['theme'],'prompt'=>$e['prompt'],'note'=>$e['note']], $enigmes);
  $nonce = wp_create_nonce('ouinpo_nonce');

  ob_start(); ?>
  <div id="ouinpo-game" data-page="<?php echo esc_attr($page);?>" data-needed="<?php echo $needed;?>"
       data-reveal="<?php echo esc_attr($reveal);?>" data-plink="<?php echo esc_url($plink);?>"
       data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
       data-nonce="<?php echo esc_attr($nonce); ?>"
       data-progress="<?php echo (int)$progress; ?>"
       data-enigmes="<?php echo esc_attr(wp_json_encode($public, JSON_UNESCAPED_UNICODE)); ?>"
       data-redirect-url="<?php echo ($progress >= $needed && $reveal === 'redirect') ? esc_url($plink) : ''; ?>">
    <blockquote class="eldritch">« Quarante-deux verrous, un seul sourire : le tien. » — Prof. Archibald Bogue</blockquote>

    <div id="ouinpo-progress" class="ouinpo-gate-progress">
      Progression : <strong><span id="ouinpo-count"><?php echo $progress;?></span> / <span id="ouinpo-needed"><?php echo $needed;?></span></strong>
    </div>

    <div id="ouinpo-question" class="ouinpo-gate-question"></div>

    <div id="ouinpo-final" class="ouinpo-gate-final<?php echo ($progress >= $needed ? '' : ' ouinpo-gate-hidden'); ?>">
      <h2>🎉 Félicitations !</h2>
      <div id="ouinpo-secret-content">
        <?php if($progress >= $needed): ?>
          <?php if($reveal === 'embed'): ?>
            <?php echo $post ? apply_filters('the_content', $post->post_content) : '<em>Contenu introuvable.</em>'; ?>
          <?php elseif($reveal === 'link'): ?>
            <p><a class="button" href="<?php echo esc_url($plink); ?>">🚪 Accéder à la page secrète</a></p>
          <?php else: /* redirect */ ?>
            <p>Redirection en cours… Si rien ne se passe, <a href="<?php echo esc_url($plink); ?>">clique ici</a>.</p>
          <?php endif; ?>
        <?php else: ?>
          <em>Atteins le seuil pour révéler le contenu…</em>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php
  return ob_get_clean();
});

/* ================= AJAX : vérification ================= */
add_action('wp_ajax_ouinpo_check','ouinpo_check');

function ouinpo_check(){
  if(!is_user_logged_in()) wp_send_json(['ok'=>false,'msg'=>'login']);
  check_ajax_referer('ouinpo_nonce','nonce');

  $uid    = get_current_user_id();
  $index  = max(0, intval($_POST['index'] ?? -1));

  // 🔓 Accepte payload base64 ou answer clair (fallback)
  if (isset($_POST['payload'])) {
    $answer = base64_decode( wp_unslash($_POST['payload']) );
  } elseif (isset($_POST['answer'])) {
    $answer = wp_unslash($_POST['answer']);
  } else {
    $answer = '';
  }

  $page = sanitize_title( wp_unslash( $_POST['page'] ?? 'sample-page' ) );

  $enigmes = ouinpo_enigmes();
  if(!isset($enigmes[$index])) wp_send_json(['ok'=>false,'msg'=>'bad index']);

  $expected = ouinpo_hashes()[$index]; // tableau de hashes (canon + alias)
  $ok = in_array(ouinpo_hash($answer), $expected, true);

  global $wpdb;
  $t_prog = $wpdb->prefix.'ouinpo_progress';
  $t_logs = $wpdb->prefix.'ouinpo_logs';

  if($ok){
    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM $t_prog WHERE user_id=%d AND page_slug=%s",
      $uid, $page
    ));

    // ✅ progression avant la nouvelle énigme
    $prev_progress = $row ? intval($row->progress) : 0;

    $solved   = $row ? (json_decode($row->solved_json ?: '[]', true) ?: []) : [];
    $progress = $prev_progress;

    if(!in_array($index, $solved, true)){
      $solved[] = $index;
      sort($solved);
      $progress = count($solved);

      if($row){
        $wpdb->update($t_prog, [
          'solved_json'=>wp_json_encode($solved),
          'progress'=>$progress,
          'updated_at'=>current_time('mysql')
        ], ['id'=>$row->id]);
      } else {
        $wpdb->insert($t_prog, [
          'user_id'=>$uid,'page_slug'=>$page,
          'solved_json'=>wp_json_encode($solved),
          'progress'=>$progress,
          'updated_at'=>current_time('mysql')
        ]);
      }

      $wpdb->insert($t_logs, [
        'user_id'=>$uid,'page_slug'=>$page,
        'riddle_index'=>$index,'ok'=>1,
        'answer_norm'=>ouinpo_norm($answer)
      ]);

      // 🎖 Attribution auto du badge "Héraut des 42 Mystères" (id = 86)
      $needed = 42; // nombre d’énigmes du corpus
      if ($progress >= $needed && $prev_progress < $needed) {
        $t_user_badges = $wpdb->prefix.'ouin_exo_user_badges';
        $badge_id_42   = 86;

        $already = $wpdb->get_var($wpdb->prepare(
          "SELECT 1 FROM $t_user_badges WHERE user_id=%d AND badge_id=%d LIMIT 1",
          $uid, $badge_id_42
        ));

        if(!$already){
          $wpdb->insert($t_user_badges, [
            'user_id'    => $uid,
            'badge_id'   => $badge_id_42,
            'awarded_at' => current_time('mysql'),
            'source'     => 'auto',
          ]);
        }

        // On garde aussi une trace côté meta pour les shortcodes qui l’utilisent déjà
        update_user_meta($uid, 'ouinpo_pass_42', current_time('mysql'));
      }
    }

    wp_send_json(['ok'=>true,'progress'=>$progress]);
  } else {
    // Log échec pour diagnostic (réponse normalisée)
    $wpdb->insert($t_logs, [
      'user_id'=>$uid,'page_slug'=>$page,'riddle_index'=>$index,'ok'=>0,
      'answer_norm'=>ouinpo_norm($answer),'created_at'=>current_time('mysql')
    ]);
    wp_send_json(['ok'=>false]);
  }
}

/* ================= AJAX : contenu secret ================= */
add_action('wp_ajax_ouinpo_secret','ouinpo_secret');
function ouinpo_secret(){
  if(!is_user_logged_in()) { wp_die('login'); }
  check_ajax_referer('ouinpo_nonce','nonce');

  $uid  = get_current_user_id();
  $page = sanitize_title( wp_unslash( $_POST['page'] ?? 'sample-page' ) );
  $needed = 42;

  global $wpdb; $t_prog = $wpdb->prefix.'ouinpo_progress';
  $p = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT progress FROM $t_prog WHERE user_id=%d AND page_slug=%s", $uid, $page));
  if($p >= $needed){
    $post = get_page_by_path($page);
    echo $post ? apply_filters('the_content', $post->post_content) : '<em>Contenu introuvable.</em>';
  } else {
    echo '<em>Pas encore…</em>';
  }
  wp_die();
}

/* ================= Shortcode signature : [ouinpo_signpad] =================
   Usage: [ouinpo_signpad page="sample-page" needed="42" show_list="1"]
*/
add_shortcode('ouinpo_signpad', function($atts){
  ouinpo_gate_enqueue_assets();

  if(!is_user_logged_in()){
    return '<p>🔒 Réservé aux membres connectés.</p>';
  }
  $a = shortcode_atts(['page'=>'sample-page','needed'=>42,'show_list'=>'1'], $atts);
  $page      = sanitize_title($a['page']);
  $needed    = max(1, intval($a['needed']));
  $show_list = $a['show_list'] === '1';
  $uid       = get_current_user_id();

  global $wpdb;
  $t_prog = $wpdb->prefix.'ouinpo_progress';
  $progress = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT progress FROM $t_prog WHERE user_id=%d AND page_slug=%s", $uid, $page
  ));
  
  if($progress >= $needed){ update_user_meta($uid, 'ouinpo_pass_42', current_time('mysql')); }
if($progress < $needed){
    return '<p class="lab-note">⏳ Pas encore… Termine la quête pour signer cette page.</p>';
  }

  // déjà signé ?
  $t_sign = $wpdb->prefix.'ouinpo_signatures';
  $already = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $t_sign WHERE user_id=%d AND page_slug=%s ORDER BY date_time DESC, id DESC LIMIT 1",
    $uid, $page
  ));

  $nonce = wp_create_nonce('ouinpo_nonce');
  $certURL = esc_url( admin_url('admin-ajax.php?action=ouinpo_certificate&page='.$page) );

  ob_start(); ?>
  <div class="eldritch" id="ouinpo-signpad">
    <h3>📜 Registre des Trouveurs</h3>

    <?php if($already): ?>
      <p class="lab-note">✅ Vous avez déjà signé le <time><?php echo esc_html($already->date_time);?></time>.
      Merci <strong><?php echo esc_html($already->nom);?></strong><?php echo $already->pseudo? ' (alias <em>'.esc_html($already->pseudo).'</em>)':''; ?>.</p>
      <p><a class="button" target="_blank" rel="noopener" href="<?php echo $certURL; ?>">📜 Télécharger mon certificat de réussite</a></p>
    <?php else: ?>
      <?php ouinpo_gate_enqueue_signpad_script(); ?>
      <form id="ouinpo-sign-form" class="ouinpo-sign-form" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-certificate-url="<?php echo $certURL; ?>">
        <label>Nom (réel) : <input name="nom" required></label><br>
        <label>Pseudo : <input name="pseudo"></label><br>
        <label>Message : <textarea name="message" rows="3"></textarea></label><br>
        <input type="hidden" name="page" value="<?php echo esc_attr($page);?>">
        <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce);?>">
        <button type="submit">Signer</button>
      </form>
      <div id="ouinpo-sign-result" class="ouinpo-gate-sign-result"></div>
    <?php endif; ?>

    <?php if($show_list):
      $signs  = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $t_sign WHERE page_slug=%s ORDER BY date_time DESC LIMIT 100",
        $page
      ));
      if($signs): ?>
        <h4 class="ouinpo-gate-sign-title">Signatures récentes</h4>
        <ul>
          <?php foreach($signs as $S): ?>
            <li>
              <strong><?php echo esc_html($S->nom); ?></strong>
              <?php if($S->pseudo) echo ' (<em>'.esc_html($S->pseudo).'</em>)'; ?>
              — <time><?php echo esc_html($S->date_time); ?></time><br>
              <?php if($S->message) echo '<span>'.nl2br(esc_html($S->message)).'</span>'; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p><em>Aucune signature pour le moment. Serez-vous le premier ?</em></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>


  <?php
  return ob_get_clean();
});

/* ================= AJAX: signature ================= */
add_action('wp_ajax_ouinpo_sign','ouinpo_sign');
add_action('wp_ajax_nopriv_ouinpo_sign','ouinpo_sign'); // retour JSON clair si déconnecté
function ouinpo_sign(){
  if(!is_user_logged_in()) wp_send_json(['ok'=>false,'msg'=>'login']);
  check_ajax_referer('ouinpo_nonce','nonce');
  global $wpdb;
  $t_sign=$wpdb->prefix.'ouinpo_signatures';
  $uid=get_current_user_id();
  $nom = sanitize_text_field( wp_unslash( $_POST['nom'] ?? '' ) );
  $pseudo = sanitize_text_field( wp_unslash( $_POST['pseudo'] ?? '' ) );
  $message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
  $page = sanitize_title( wp_unslash( $_POST['page'] ?? 'sample-page' ) );
  if(empty($nom)) wp_send_json(['ok'=>false,'msg'=>'Nom requis']);
  $wpdb->insert($t_sign,[
    'user_id'=>$uid,'page_slug'=>$page,'nom'=>$nom,'pseudo'=>$pseudo,'message'=>$message,
    'ip'=>($_SERVER['REMOTE_ADDR']??''),'ua'=>($_SERVER['HTTP_USER_AGENT']??''),'date_time'=>current_time('mysql')
  ]);
  wp_send_json(['ok'=>true]);
}

/* ================= AJAX: Certificat PDF (A4 paysage) ================= */
add_action('wp_ajax_ouinpo_certificate','ouinpo_certificate');
function ouinpo_certificate(){
  if(!is_user_logged_in()) wp_die('login');
  global $wpdb;
  $uid  = get_current_user_id();
  $page = sanitize_title($_GET['page'] ?? 'sample-page');
  $t_prog = $wpdb->prefix.'ouinpo_progress';
  $t_sign = $wpdb->prefix.'ouinpo_signatures';

  $progress = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT progress FROM $t_prog WHERE user_id=%d AND page_slug=%s", $uid, $page
  ));
  if($progress < 42){ wp_die('not-complete'); }

  $row = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $t_sign WHERE user_id=%d AND page_slug=%s ORDER BY date_time DESC, id DESC LIMIT 1",
    $uid, $page
  ));
  if(!$row){ wp_die('no-sign'); }

  $base = plugin_dir_path(__FILE__);
  $fpdf = $base.'fpdf/fpdf.php';
  if(!file_exists($fpdf)){ wp_die('fpdf-missing'); }
  require_once $fpdf;

  class OuinpoPDF_L extends FPDF{
    protected $angle=0;
    function Rotate($angle,$x=-1,$y=-1){
      if($x==-1)$x=$this->x; if($y==-1)$y=$this->y;
      if($this->angle!=0)$this->_out('Q'); $this->angle=$angle;
      if($angle!=0){ $angle*=M_PI/180; $c=cos($angle); $s=sin($angle);
        $cx=$x*$this->k; $cy=($this->h-$y)*$this->k;
        $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.5F %.5F cm 1 0 0 1 %.5F %.5F cm',
          $c,$s,-$s,$c,$cx,$cy,-$cx,-$cy));
      }
    }
    function RotatedText($x,$y,$txt,$angle){ $this->Rotate($angle,$x,$y); $this->Text($x,$y,$txt); $this->Rotate(0); }
  }

  $pdf=new OuinpoPDF_L('L','mm','A4');
  $pdf->SetTitle('Certificat OuInPo');
  $pdf->AddPage();

  $bg   = $base.'assets/cert/parchment_bg.jpg';
  $logo = $base.'assets/cert/logo_ouinpo.png';
  if(file_exists($bg))   $pdf->Image($bg, 0, 0, 297, 210);
  if(file_exists($logo)) $pdf->Image($logo, 20, 20, 64, 64);

  // décor
  // $pdf->SetDrawColor(60,100,60); $pdf->SetLineWidth(0.8); $pdf->Rect(10,10,277,190);
  // $pdf->SetDrawColor(120,160,120); $pdf->Rect(13,13,271,184);
  // $pdf->SetTextColor(120,180,120); $pdf->SetFont('Times','B',60);
  // $pdf->RotatedText(80,160,utf8_decode('OUINPO'),20);
  // $pdf->SetTextColor(0,0,0);

  $post = get_page_by_path($page);
  $titre_page = $post ? $post->post_title : 'OuInPo';
  $dateStr = date_i18n('d F Y', strtotime($row->date_time));
  $certnum = 'OUINPO-'.date('Ymd', strtotime($row->date_time)).'-'.$row->id;

  $pdf->SetY(24);
  $pdf->SetFont('Times','B',24);
  $pdf->Cell(0,12,utf8_decode("CERTIFICAT DE RÉUSSITE DE L'OuInPo"),0,1,'C');
  $pdf->SetFont('Times','',14);
  $pdf->Cell(0,8,utf8_decode("Récompense : ".$titre_page),0,1,'C');
  $pdf->Ln(6);

  $pdf->SetFont('Times','',14);
  $pdf->Cell(0,8,utf8_decode("Ce document atteste que :"),0,1,'C');
  $pdf->Ln(2);
  $pdf->SetFont('Times','B',30);
  $pdf->SetTextColor(40,100,60);
  $pdf->Cell(0,14,utf8_decode($row->nom),0,1,'C');
  $pdf->SetTextColor(0,0,0);
  if(!empty($row->pseudo)){ $pdf->SetFont('Times','I',13); $pdf->Cell(0,8,utf8_decode('alias : '.$row->pseudo),0,1,'C'); }

  $pdf->SetFont('Courier','',11); $pdf->SetTextColor(90,90,90);
  $pdf->Cell(0,6,utf8_decode('Certificat n° '.$certnum),0,1,'C');
  $pdf->SetTextColor(0,0,0);

  $pdf->Ln(6);
  $pdf->SetFont('Times','',14);
  $pdf->MultiCell(0,8,utf8_decode("a brillamment franchi les 42 épreuves de l'OuInPo, sous la bienveillance farfelue du Professeur Archibald Bogue.\n\nPar ce certificat, l'intéressé(e) est déclaré(e) membre honoraire du Cercle des Débogueurs Métaphysiques et gardien(ne) des variables instables."),0,'C');

  $pdf->Ln(6);
  $pdf->SetDrawColor(120,160,120); $pdf->SetLineWidth(0.4);
  $x = 60; $w = 177; $y = $pdf->GetY();
  $pdf->Rect($x, $y, $w, 20);
  $pdf->SetFont('Times','I',12);
  $pdf->SetXY($x+4, $y+4);
  $pdf->MultiCell($w-8,6,utf8_decode("« L'erreur est humaine, mais le segfault est divin. » - Pr. Archi Bogue"),0,'C');

  $pdf->Ln(12);
  $pdf->SetFont('Times','B',13);
  $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
  $pdf->Cell(0,8,utf8_decode("Fait à ".$site_name.", le ".$dateStr),0,1,'C');
  $pdf->SetFont('Times','',12);
  $pdf->Cell(0,8,utf8_decode("Pr. Archi Bogue, Grand 'Pataphysicien du Code"),0,1,'C');
  $pdf->SetDrawColor(60,100,60); $pdf->SetLineWidth(0.8);
  $pdf->Line(110, 180, 187, 180);

  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="Certificat_OuInPo.pdf"');
  $pdf->Output();
  exit;
}

/* ================= Admin : suivi & export ================= */
add_action('admin_menu', function(){
  $parent = defined('OUINPO_SUITE_ADMIN_SLUG')
    ? OUINPO_SUITE_ADMIN_SLUG
    : 'ouinpo';

  if (!defined('OUINPO_SUITE_ADMIN_SLUG')) {
    add_menu_page(
      'Ouinpo',
      'Ouinpo',
      \Ouinpo\Suite\Core\Capabilities::MANAGE_AI,
      'ouinpo',
      'ouinpo_admin_progress',
      'dashicons-shield',
      65
    );
  }

  add_submenu_page(
    $parent,
    'Ouinpo / Gate',
    'Gate',
    \Ouinpo\Suite\Core\Capabilities::MANAGE_AI,
    'ouinpo',
    'ouinpo_admin_progress'
  );
});

function ouinpo_admin_progress(){
  if(!\Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::MANAGE_AI)) { wp_die('Nope'); }
  global $wpdb; $t_prog=$wpdb->prefix.'ouinpo_progress'; $t_logs=$wpdb->prefix.'ouinpo_logs'; $t_sign=$wpdb->prefix.'ouinpo_signatures';
  $page = sanitize_title($_GET['page_slug'] ?? 'sample-page');
  if(isset($_GET['export']) && $_GET['export']==='csv'){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ouinpo_progress_'.$page.'.csv');
    $out = fopen('php://output','w');
    fputcsv($out, ['user_id','login','progress','solved','updated_at']);
    $rows = $wpdb->get_results($wpdb->prepare("SELECT p.*, u.user_login FROM $t_prog p JOIN {$wpdb->users} u ON u.ID=p.user_id WHERE page_slug=%s ORDER BY updated_at DESC",$page), ARRAY_A);
    foreach($rows as $r){ fputcsv($out, [$r['user_id'],$r['user_login'],$r['progress'],$r['solved_json'],$r['updated_at']]); }
    fclose($out); exit;
  }
  echo '<div class="wrap"><h1>Ouinpo — Progression (page: '.esc_html($page).')</h1>';
  echo '<p><a class="button" href="'.esc_url(admin_url('admin.php?page=ouinpo&page_slug='.$page.'&export=csv')).'">Exporter CSV</a></p>';
  $rows = $wpdb->get_results($wpdb->prepare("SELECT p.*, u.user_login FROM $t_prog p JOIN {$wpdb->users} u ON u.ID=p.user_id WHERE page_slug=%s ORDER BY updated_at DESC",$page));
  echo '<table class="widefat"><thead><tr><th>Utilisateur</th><th>Progression</th><th>Indices résolus</th><th>Dernière MAJ</th></tr></thead><tbody>';
  foreach($rows as $r){
    echo '<tr><td>'.esc_html($r->user_login).' (#'.intval($r->user_id).')</td>'.
         '<td>'.intval($r->progress).'/42</td>'.
         '<td><code>'.esc_html($r->solved_json).'</code></td>'.
         '<td>'.esc_html($r->updated_at).'</td></tr>';
  }
  echo '</tbody></table>';

  $logs = $wpdb->get_results($wpdb->prepare("SELECT l.*, u.user_login FROM $t_logs l JOIN {$wpdb->users} u ON u.ID=l.user_id WHERE page_slug=%s ORDER BY created_at DESC LIMIT 50",$page));
  echo '<h2>Dernières résolutions</h2><table class="widefat"><thead><tr><th>Date</th><th>Utilisateur</th><th>Énigme</th><th>Réponse (normalisée)</th></tr></thead><tbody>';
  foreach($logs as $L){
    echo '<tr><td>'.esc_html($L->created_at).'</td><td>'.esc_html($L->user_login).'</td><td>#'.intval($L->riddle_index+1).'</td><td><code>'.esc_html($L->answer_norm).'</code></td></tr>';
  }
  echo '</tbody></table>';

  $signs = $wpdb->get_results($wpdb->prepare("SELECT s.*, u.user_login FROM $t_sign s JOIN {$wpdb->users} u ON u.ID=s.user_id WHERE page_slug=%s ORDER BY date_time DESC",$page));
  echo '<h2>Signatures</h2><table class="widefat"><thead><tr><th>Date</th><th>Utilisateur</th><th>Nom</th><th>Pseudo</th><th>Message</th></tr></thead><tbody>';
  foreach($signs as $S){
    echo '<tr><td>'.esc_html($S->date_time).'</td><td>'.esc_html($S->user_login).'</td><td>'.esc_html($S->nom).'</td><td>'.esc_html($S->pseudo).'</td><td>'.esc_html($S->message).'</td></tr>';
  }
  echo '</tbody></table></div>';
}

/* ================= Shortcode: [ouinpo_hint] ... [/ouinpo_hint] ================= */
add_shortcode('ouinpo_hint', function($atts, $content = ''){
  if( is_user_logged_in() ){
    $done = get_user_meta( get_current_user_id(), 'ouinpo_pass_42', true );
    if( ! empty($done) ){
      return '';
    }
  }
  return do_shortcode($content);
});
