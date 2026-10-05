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
 * Restore task for mod_pair.
 *
 * @package   mod_pair
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->dirroot}/mod/pair/backup/moodle2/restore_pair_stepslib.php");

/**
 * Provides the settings and steps required to restore a Pair activity.
 */
class restore_pair_activity_task extends restore_activity_task {
    /**
     * Defines activity-specific restore settings.
     *
     * @return void
     */
    protected function define_my_settings(): void {
        return;
    }

    /**
     * Defines activity-specific restore steps.
     *
     * @return void
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_pair_activity_structure_step("pair_structure", "pair.xml"));
    }

    /**
     * Defines content fields processed by the link decoder.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents(): array {
        return [
            new restore_decode_content("pair", ["intro"], "pair"),
        ];
    }

    /**
     * Defines link decoding rules for the activity.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule("PAIRVIEWBYID", "/mod/pair/view.php?id=$1", "course_module"),
            new restore_decode_rule("PAIRINDEX", "/mod/pair/index.php?id=$1", "course"),
        ];
    }
}
