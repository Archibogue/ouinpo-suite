<?php
// Usage: php -d mysqli.default_port=10009 tools/check-exercise-level-sync.php /path/to/wp-load.php
// Uses temporary tables only; existing exercises are never modified.
if (empty($argv[1]) || !is_file($argv[1])) {
    fwrite(STDERR, "Provide a local WordPress wp-load.php path.\n");
    exit(1);
}
require $argv[1];
global $wpdb;
$wpdb->query('CREATE TEMPORARY TABLE qa_level_exercises (id INT PRIMARY KEY, level_id INT NULL)');
$wpdb->query('CREATE TEMPORARY TABLE qa_level_links (exercise_id INT, school_level_id INT)');
try {
    foreach (['screen-exercises.php', 'screen-import-exercises.php'] as $file) {
        $source = file_get_contents(dirname(__DIR__) . '/src/Modules/Exercises/plugin/admin/screens/' . $file);
        if (!preg_match('/"(UPDATE \{\$[^}]+\} SET level_id = \(SELECT MIN\(school_level_id\).*?WHERE id = %d)"/', $source, $matches)) {
            throw new RuntimeException("Missing synchronization in $file");
        }
        $sql = strtr($matches[1], [
            '{$p_exo}' => 'qa_level_exercises', '{$p_lv}' => 'qa_level_links',
            '{$table_exercises}' => 'qa_level_exercises', '{$table_exo_level}' => 'qa_level_links',
        ]);
        foreach ([[6], [6, 9], []] as $levels) {
            $wpdb->query('DELETE FROM qa_level_exercises');
            $wpdb->query('DELETE FROM qa_level_links');
            $wpdb->query('INSERT INTO qa_level_exercises VALUES (1,8),(2,8)');
            foreach ($levels as $level) {
                $wpdb->query("INSERT INTO qa_level_links VALUES (1,$level)");
            }
            if ($wpdb->query($wpdb->prepare($sql, 1, 1)) === false) {
                throw new RuntimeException($wpdb->last_error);
            }
            $actual = $wpdb->get_var('SELECT level_id FROM qa_level_exercises WHERE id=1');
            $expected = $levels ? (string) min($levels) : null;
            if ($actual !== $expected || $wpdb->get_var('SELECT level_id FROM qa_level_exercises WHERE id=2') !== '8') {
                throw new RuntimeException("Incorrect synchronization in $file");
            }
            echo "OK $file " . json_encode($levels) . "\n";
        }
    }
    $source = file_get_contents(dirname(__DIR__) . '/src/Modules/Exercises/plugin/admin/screens/screen-levels.php');
    $start = strpos($source, '$usage_for_level =');
    $end = strpos($source, '$sync_level_competencies =', $start);
    $tbl_exercises = 'qa_level_exercises';
    $tbl_exo_levels = 'qa_level_links';
    $tbl_groups = $tbl_members = $tbl_comp_levels = 'unused';
    $table_exists = static fn(string $table): bool => $table !== 'unused';
    eval(substr($source, $start, $end - $start));
    $wpdb->query('DELETE FROM qa_level_exercises');
    $wpdb->query('DELETE FROM qa_level_links');
    // One dual association, one legacy-only, one link-only and one unrelated exercise.
    $wpdb->query('INSERT INTO qa_level_exercises VALUES (1,6),(2,6),(3,NULL),(4,9)');
    $wpdb->query('INSERT INTO qa_level_links VALUES (1,6),(3,6),(4,9)');
    foreach ([6 => 3, 9 => 1, 8 => 0] as $level => $expected) {
        $usage = $usage_for_level($level);
        if ($usage['exercises_legacy'] + $usage['exercises_links'] !== $expected) {
            throw new RuntimeException("Incorrect exercise count for level $level");
        }
        echo "OK unique exercise count for level $level: $expected\n";
    }
} finally {
    $wpdb->query('DROP TEMPORARY TABLE qa_level_links');
    $wpdb->query('DROP TEMPORARY TABLE qa_level_exercises');
}
