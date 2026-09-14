<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Core/ClassSubgroups.php';

use Ouinpo\Suite\Core\ClassSubgroups;

$options = [];
function get_option($key, $default = false) { global $options; return $options[$key] ?? $default; }
function update_option($key, $value, $autoload = null) { global $options; $options[$key] = $value; }
function check(bool $condition, string $label): void {
    if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "OK: $label\n";
}

check(ClassSubgroups::all(1) === [], 'Existing classes need no subgroup');
ClassSubgroups::save(1, ['a' => ['label' => 'Groupe 1', 'members' => [10, 11]]]);
check(ClassSubgroups::allows(10, [1], ['1:a']), 'One subgroup can contain the whole class');
ClassSubgroups::save(1, [
    'a' => ['label' => 'Groupe 1', 'members' => [10]],
    'b' => ['label' => 'Groupe 2', 'members' => [11]],
]);
check(ClassSubgroups::allows(10, [1], ['1:a']), 'Selected subgroup allowed');
check(!ClassSubgroups::allows(11, [1], ['1:a']), 'Other subgroup denied');
check(ClassSubgroups::allows(11, [1], ['1:a', '1:b']), 'Multiple subgroups allowed');
check(!ClassSubgroups::allows(10, [2], ['1:a']), 'Former class member denied despite stale subgroup membership');
check(!ClassSubgroups::allows(10, [1], ['2:a']), 'Other class target denied');
check(!ClassSubgroups::allows(10, [1], []), 'Empty selection grants no subgroup access');
ClassSubgroups::save(1, []);
check(!ClassSubgroups::allows(10, [1], ['1:a']), 'Deleted subgroup never expands access');
