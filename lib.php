<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * lib.php
 *
 * @package   mod_pair
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns supported Moodle features.
 *
 * @param string $feature Feature name.
 * @return mixed
 */
function pair_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO => true,
        FEATURE_SHOW_DESCRIPTION => true,
        default => null,
    };
}

/**
 * Creates a pair activity.
 *
 * @param stdClass $data Activity data.
 * @param mod_pair_mod_form|null $mform Activity form.
 * @return int
 */
function pair_add_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    return $DB->insert_record("pair", $data);
}

/**
 * Updates a pair activity.
 *
 * @param stdClass $data Activity data.
 * @param mod_pair_mod_form|null $mform Activity form.
 * @return bool
 */
function pair_update_instance($data, $mform = null) {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    return $DB->update_record("pair", $data);
}

/**
 * Deletes a pair activity.
 *
 * @param int $id Pair instance id.
 * @return bool
 */
function pair_delete_instance($id) {
    global $DB;

    if (!$DB->record_exists("pair", ["id" => $id])) {
        return false;
    }

    $DB->delete_records("pair_members", ["pairid" => $id]);
    $DB->delete_records("pair_groups", ["pairid" => $id]);
    $DB->delete_records("pair", ["id" => $id]);
    return true;
}
