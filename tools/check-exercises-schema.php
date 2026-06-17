<?php
/**
 * Static and optional WordPress checks for the Exercises assessment schema fix.
 *
 * Usage:
 *   php tools/check-exercises-schema.php
 *   php tools/check-exercises-schema.php --wp-load=C:\path\to\wp-load.php
 *   php tools/check-exercises-schema.php --wp-load=C:\path\to\wp-load.php --apply
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$installFile = $root . '/src/Modules/Exercises/plugin/inc/InstallV2.php';
$args = array_slice($argv, 1);
$wpLoad = null;
$apply = false;
$failures = [];

foreach ($args as $arg) {
    if (str_starts_with($arg, '--wp-load=')) {
        $wpLoad = substr($arg, strlen('--wp-load='));
        continue;
    }

    if ($arg === '--apply') {
        $apply = true;
        continue;
    }

    $failures[] = 'Unknown argument: ' . $arg;
}

function check_ok(string $message): void {
    echo '[OK] ' . $message . PHP_EOL;
}

function check_fail(array &$failures, string $message): void {
    $failures[] = $message;
    echo '[FAIL] ' . $message . PHP_EOL;
}

if (!is_file($installFile)) {
    check_fail($failures, 'InstallV2.php not found');
} else {
    $source = (string) file_get_contents($installFile);
    $required = [
        'guarded upgrade_schema result' => 'if (!self::upgrade_schema())',
        'assessment results controlled helper' => 'ensure_assessment_results_schema',
        'assessment attendance controlled helper' => 'ensure_assessment_attendance_schema',
        'conditional column helper' => 'add_column_if_missing',
        'conditional primary key helper' => 'add_primary_key_if_missing',
        'conditional index helper' => 'add_index_if_missing',
        'schema error buffer' => 'self::$schema_errors',
        'partial migration repair marker' => 'ASSESSMENT_SCHEMA_CHECK_OPTION',
        'partial migration repair helper' => 'maybe_repair_assessment_tables',
        'repair runs when DB version is current' => 'self::maybe_repair_assessment_tables();',
        'schema migration lock marker' => 'SCHEMA_LOCK_OPTION',
        'schema migration lock acquire helper' => 'acquire_schema_lock',
        'schema migration lock release helper' => 'release_schema_lock',
        'normalized dbDelta wrapper' => 'run_db_delta',
        'dbDelta SQL normalizer' => 'normalize_db_delta_sql',
    ];

    foreach ($required as $label => $needle) {
        if (str_contains($source, $needle)) {
            check_ok('Static check: ' . $label);
        } else {
            check_fail($failures, 'Missing static marker: ' . $label);
        }
    }

    $forbidden = [
        'dbDelta($sql_assessment_results)',
        'dbDelta($sql_assessment_attendance)',
        'ADD  `` (``)',
    ];

    foreach ($forbidden as $needle) {
        if (str_contains($source, $needle)) {
            check_fail($failures, 'Forbidden marker still present: ' . $needle);
        } else {
            check_ok('Static check: absent ' . $needle);
        }
    }
}

if ($wpLoad !== null) {
    if (!is_file($wpLoad)) {
        check_fail($failures, 'wp-load.php not found: ' . $wpLoad);
    } else {
        $GLOBALS['ouinpo_exercises_schema_wp_bootstrap_done'] = false;
        register_shutdown_function(static function (): void {
            if (!empty($GLOBALS['ouinpo_exercises_schema_wp_bootstrap_done'])) {
                return;
            }

            fwrite(STDERR, PHP_EOL . 'WordPress bootstrap stopped before schema checks completed.' . PHP_EOL);
            exit(1);
        });
        require_once $wpLoad;
        $GLOBALS['ouinpo_exercises_schema_wp_bootstrap_done'] = true;

        global $wpdb;
        if (!isset($wpdb)) {
            check_fail($failures, 'WordPress bootstrap did not expose $wpdb');
        } else {
            if ($apply) {
                if (!class_exists('\Ouinpo\Exercises\InstallV2')) {
                    check_fail($failures, 'Ouinpo\\Exercises\\InstallV2 is not loaded');
                } elseif (!\Ouinpo\Exercises\InstallV2::upgrade_schema()) {
                    check_fail($failures, 'InstallV2::upgrade_schema() returned false');
                } else {
                    check_ok('InstallV2::upgrade_schema() completed');
                }
            }

            $expected = [
                $wpdb->prefix . 'ouin_exo_assessment_results' => [
                    'columns' => ['assessment_id', 'user_id', 'competency_id', 'observed_status', 'note', 'updated_at', 'updated_by'],
                    'indexes' => ['PRIMARY', 'user_id', 'competency_id', 'observed_status'],
                ],
                $wpdb->prefix . 'ouin_exo_assessment_attendance' => [
                    'columns' => ['assessment_id', 'user_id', 'is_absent', 'note', 'updated_at', 'updated_by'],
                    'indexes' => ['PRIMARY', 'user_id', 'is_absent'],
                ],
            ];

            foreach ($expected as $table => $schema) {
                $exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
                if (!$exists) {
                    check_fail($failures, 'Missing table: ' . $table);
                    continue;
                }

                check_ok('Found table: ' . $table);
                foreach ($schema['columns'] as $column) {
                    $columnExists = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*)
                           FROM information_schema.columns
                          WHERE table_schema = DATABASE()
                            AND table_name = %s
                            AND column_name = %s",
                        $table,
                        $column
                    ));

                    if ($columnExists > 0) {
                        check_ok('Found column: ' . $table . '.' . $column);
                    } else {
                        check_fail($failures, 'Missing column: ' . $table . '.' . $column);
                    }
                }

                foreach ($schema['indexes'] as $index) {
                    $indexExists = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*)
                           FROM information_schema.statistics
                          WHERE table_schema = DATABASE()
                            AND table_name = %s
                            AND index_name = %s",
                        $table,
                        $index
                    ));

                    if ($indexExists > 0) {
                        check_ok('Found index: ' . $table . '.' . $index);
                    } else {
                        check_fail($failures, 'Missing index: ' . $table . '.' . $index);
                    }
                }
            }
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, PHP_EOL . 'Schema check failed:' . PHP_EOL . '- ' . implode(PHP_EOL . '- ', $failures) . PHP_EOL);
    exit(1);
}

echo 'Exercises assessment schema checks passed.' . PHP_EOL;
