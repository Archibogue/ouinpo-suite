<?php
namespace Ouinpo\Exercises;

defined('ABSPATH') || exit;

final class PracticalFiles {

    private static function table(string $suffix): string {
        global $wpdb;
        return $wpdb->prefix . 'ouin_exo_' . $suffix;
    }

    private static function normalize_folder_name(string $name): string {
        $name = remove_accents($name);
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim((string) $name, '_');

        return $name !== '' ? $name : 'practical_subject';
    }

    public static function get_folder_name_for_exercise(int $exercise_id): string {
        global $wpdb;

        $tExam = self::table('exam_meta');

        $folder = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT subject_group
             FROM {$tExam}
             WHERE exercise_id = %d
             LIMIT 1",
            $exercise_id
        ));

        $folder = self::normalize_folder_name($folder);

        if ($folder === '' || $folder === 'practical_subject') {
            $folder = 'practical_' . $exercise_id;
        }

        return $folder;
    }

    public static function get_subject_dir(int $exercise_id): array {
        $uploads = wp_upload_dir();

        $folder = self::get_folder_name_for_exercise($exercise_id);
        $subdir = '/ouinpo/practical/' . $folder;
        $path   = $uploads['basedir'] . $subdir;
        $url    = $uploads['baseurl'] . $subdir;

        if (!file_exists($path)) {
            wp_mkdir_p($path);
        }

        return [
            'folder_name' => $folder,
            'path'        => $path,
            'url'         => $url,
            'subdir'      => $subdir,
        ];
    }

    public static function store_uploaded_file(array $file, int $exercise_id): array|\WP_Error {
        if (empty($file['tmp_name']) || empty($file['name'])) {
            return new \WP_Error('missing_file', 'Fichier manquant.');
        }

        $dir = self::get_subject_dir($exercise_id);

        $filename = wp_unique_filename($dir['path'], sanitize_file_name($file['name']));
        $target   = trailingslashit($dir['path']) . $filename;

        if (!@move_uploaded_file($file['tmp_name'], $target)) {
            return new \WP_Error('move_failed', 'Impossible de déplacer le fichier.');
        }

        $stat  = @stat(dirname($target));
        $perms = $stat ? ($stat['mode'] & 0000666) : 0644;
        @chmod($target, $perms);

        return [
            'folder_name' => $dir['folder_name'],
            'filename'    => $filename,
            'path'        => $target,
            'url'         => trailingslashit($dir['url']) . $filename,
        ];
    }
}