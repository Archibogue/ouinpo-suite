<?php
// Lancement de l'indexation différentielle SegFault via cron HTTP ou CLI.
// Le secret OUINPO_SF_CRON_KEY doit être défini dans wp-config.php.

function ouinpo_sf_cron_status(int $code): void {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        http_response_code($code);
    }
}

function ouinpo_sf_find_wp_load(string $start_dir): string {
    $dir = realpath($start_dir);

    if (!$dir) {
        return '';
    }

    for ($i = 0; $i < 12; $i++) {
        $candidate = $dir . DIRECTORY_SEPARATOR . 'wp-load.php';

        if (is_file($candidate)) {
            return $candidate;
        }

        $parent = dirname($dir);

        if ($parent === $dir) {
            break;
        }

        $dir = $parent;
    }

    return '';
}

// 1) Charger WordPress de manière robuste, quel que soit le chemin du plugin.
$wp_load = ouinpo_sf_find_wp_load(__DIR__);

if ($wp_load === '') {
    ouinpo_sf_cron_status(500);
    echo "wp-load.php introuvable depuis " . __DIR__ . "\n";
    exit;
}

require_once $wp_load;

// 2) Vérifier que le secret existe côté configuration serveur.
if (!defined('OUINPO_SF_CRON_KEY') || OUINPO_SF_CRON_KEY === '') {
    ouinpo_sf_cron_status(500);
    echo "OUINPO_SF_CRON_KEY non défini dans wp-config.php\n";
    exit;
}

// 3) Récupérer la clé fournie en CLI ou HTTP.
$key = null;

if (PHP_SAPI === 'cli') {
    // Exemple CLI :
    // php cron-segfault.php MA_CLE
    $key = $argv[1] ?? null;
} else {
    // Exemple HTTP :
    // cron-segfault.php?key=MA_CLE
    $key = isset($_GET['key']) ? (string) $_GET['key'] : null;
}

// 4) Comparaison sécurisée.
if (!is_string($key) || !hash_equals((string) OUINPO_SF_CRON_KEY, $key)) {
    ouinpo_sf_cron_status(403);
    echo "Forbidden\n";
    exit;
}

// 5) Vérifier que les classes SegFault sont chargées.
if (!class_exists('\\OuInPo\\SegFault\\DB') || !class_exists('\\OuInPo\\SegFault\\RAG')) {
    ouinpo_sf_cron_status(500);
    echo "Classes SegFault manquantes\n";
    exit;
}

// 6) Lancer l'indexation différentielle.
try {
    \OuInPo\SegFault\DB::init();
    \OuInPo\SegFault\RAG::cron_reindex_nightly();

    echo "SegFault cron OK\n";
} catch (\Throwable $e) {
    error_log(
        '[SegFault cron] Exception: '
        . $e->getMessage()
        . ' in '
        . $e->getFile()
        . ':'
        . $e->getLine()
    );

    ouinpo_sf_cron_status(500);
    echo "SegFault cron ERROR: " . $e->getMessage() . "\n";
}