<?php
/**
 * Uninstall policy for OuInPo Suite.
 *
 * By default, deleting the plugin files from WordPress does not remove pedagogical
 * data, student progress, exercises, flashcards, badges, assessments, logs or settings.
 *
 * This conservative behavior prevents accidental loss of school data.
 *
 * Future versions may provide an explicit admin setting such as:
 * "Delete all OuInPo data on uninstall".
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

// Intentionally empty.
// OuInPo Suite does not delete its database tables or options automatically on uninstall.