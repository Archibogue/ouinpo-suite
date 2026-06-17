<?php
namespace OuInPo\SegFault;

defined('ABSPATH') || exit;

use PDO;

class DB {
  static function pdo(): PDO {
    if (class_exists(Storage::class)) {
      Storage::ensure_dirs();
    }

    $path = (string) OUINPO_SF_DB;
    $dir = dirname($path);

    if (!is_dir($dir)) {
      throw new \RuntimeException('SegFault SQLite directory is not available.');
    }

    if (!is_writable($dir)) {
      throw new \RuntimeException('SegFault SQLite directory is not writable.');
    }

    try {
      $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    } catch (\PDOException $e) {
      throw new \RuntimeException('SegFault SQLite database cannot be opened.', 0, $e);
    }
    $pdo->exec("PRAGMA journal_mode=WAL;");
    return $pdo;
  }

  static function init(): void {
    $db = self::pdo();

    $db->exec("CREATE TABLE IF NOT EXISTS memory_sessions (
      id TEXT PRIMARY KEY, user_hash TEXT, consent INT, created_at INT, last_seen INT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS memory_turns (
      id INTEGER PRIMARY KEY AUTOINCREMENT, session_id TEXT, role TEXT, content TEXT, created_at INT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS documents (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      origin TEXT,
      url TEXT,
      title TEXT,
      chunk TEXT,
      embedding TEXT,
      tokens INT,
      ptype TEXT
    )");

    // Migrations légères : index RAG enrichi.
    // Ces colonnes sont recalculables : l’index peut être purgé/réindexé sans perte métier.
    try {
      $existing = [];
      foreach ($db->query("PRAGMA table_info(documents)") as $col) {
        if (isset($col['name'])) $existing[$col['name']] = true;
      }
    
      $wanted = [
        'ptype'              => 'TEXT',
        'embedding_provider' => 'TEXT',
        'embedding_model'    => 'TEXT',
        'content_hash'       => 'TEXT',
        'chunk_index'        => 'INTEGER',
        'section_title'      => 'TEXT',
        'visibility'         => 'TEXT',
      ];
    
      foreach ($wanted as $name => $type) {
        if (!isset($existing[$name])) {
          $db->exec("ALTER TABLE documents ADD COLUMN {$name} {$type}");
        }
      }
    } catch (\Throwable $e) {
      error_log('[SegFault DB] migration documents ignorée : '.$e->getMessage());
    }
    
    $db->exec("CREATE INDEX IF NOT EXISTS idx_docs_origin ON documents(origin)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_docs_url ON documents(url)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_docs_embedding_model ON documents(embedding_provider, embedding_model)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_docs_visibility ON documents(visibility)");
  }

  // =============================
  // Purges (admin tools)
  // =============================

  /** Purge uniquement l’index RAG (table documents). */
  static function purge_documents(bool $vacuum = true): void {
    $db = self::pdo();
    $db->exec("DELETE FROM documents");
    if ($vacuum) {
      // Réduit la taille du fichier SQLite (peut prendre un peu de temps)
      $db->exec("VACUUM");
    }
  }

  /** Purge la mémoire de conversation (sessions + turns). */
  static function purge_memory(bool $vacuum = true): void {
    $db = self::pdo();
    $db->exec("DELETE FROM memory_turns");
    $db->exec("DELETE FROM memory_sessions");
    if ($vacuum) {
      $db->exec("VACUUM");
    }
  }

  static function ensure_session(string $session='', bool $consent=false): string {
    $db = self::pdo();
    $now = time();
    if ($session !== '' && strlen($session) >= 16 && !self::session_belongs_to_current_client($session)) {
      $session = '';
    }

    if ($session === '' || strlen($session) < 16) {
      $session = wp_generate_uuid4();
      $st = $db->prepare("INSERT INTO memory_sessions(id,user_hash,consent,created_at,last_seen) VALUES(?,?,?,?,?)");
      $st->execute([$session, self::client_hash(), $consent?1:0, $now, $now]);
    } else {
      $st = $db->prepare("UPDATE memory_sessions SET last_seen=?, consent=? WHERE id=?");
      $st->execute([$now, $consent?1:0, $session]);
    }
    self::gc();
    return $session;
  }

  static function save_turn(string $session, string $role, string $content): void {
    $db = self::pdo();

    $st = $db->prepare("SELECT consent FROM memory_sessions WHERE id = ? LIMIT 1");
    $st->execute([$session]);
    $consent = (int)$st->fetchColumn();

    if ($consent !== 1) {
        return;
    }

    $st = $db->prepare("INSERT INTO memory_turns(session_id,role,content,created_at) VALUES(?,?,?,?)");
    $st->execute([$session,$role,$content,time()]);
  }

    static function delete_session(string $session): void {
        if ($session === '') return;
    
        $db = self::pdo();
        $db->prepare("DELETE FROM memory_turns WHERE session_id = ?")->execute([$session]);
        $db->prepare("DELETE FROM memory_sessions WHERE id = ?")->execute([$session]);
    }

  static function session_belongs_to_current_client(string $session): bool {

    if ($session === '' || strlen($session) < 16) return false;

    $db = self::pdo();

    $st = $db->prepare("SELECT user_hash FROM memory_sessions WHERE id = ? LIMIT 1");

    $st->execute([$session]);

    $stored = (string) $st->fetchColumn();

    return $stored !== '' && hash_equals($stored, self::client_hash());

  }



  static function delete_current_client_session(string $session): bool {

    if (!self::session_belongs_to_current_client($session)) return false;

    self::delete_session($session);

    return true;

  }



  static function client_hash(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return substr(hash('sha256',$ip.'|'.$ua.'|'.site_url()),0,16);
  }

  // Garbage collector mémoire (durée configurable)
  static function gc(): void {
    $days = (int)get_option('ouinpo_sf_memory_days', 30);
    if ($days <= 0) return;
    $limit = time() - $days*86400;
    $db = self::pdo();
    $db->prepare("DELETE FROM memory_turns WHERE session_id IN (SELECT id FROM memory_sessions WHERE last_seen<?)")->execute([$limit]);
    $db->prepare("DELETE FROM memory_sessions WHERE last_seen<?")->execute([$limit]);
  }

  static function last_turns(string $session, int $n = 6): array {
    $db = self::pdo();

    $sql = "SELECT role, content
            FROM memory_turns
            WHERE session_id = :session
            ORDER BY id DESC
            LIMIT :limit";

    $st = $db->prepare($sql);
    $st->bindValue(':session', $session, \PDO::PARAM_STR);
    $st->bindValue(':limit', $n, \PDO::PARAM_INT);

    $st->execute();
    $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

    return array_reverse($rows);
  }
}
