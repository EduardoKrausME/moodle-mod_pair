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
 * Backup task for mod_pair.
 *
 * @package   mod_pair
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->dirroot}/mod/pair/backup/moodle2/backup_pair_stepslib.php");

/**
 * Provides the settings and steps required to back up a Pair activity.
 */
class backup_pair_activity_task extends backup_activity_task {
    /**
     * Defines activity-specific backup settings.
     *
     * @return void
     */
    protected function define_my_settings(): void {
        return;
    }

    /**
     * Defines activity-specific backup steps.
     *
     * @return void
     */
    protected function define_my_steps(): void {
        $this->add_step(new backup_pair_activity_structure_step("pair_structure", "pair.xml"));
    }

    /**
     * Encodes links so they remain portable between sites and courses.
     *
     * @param string $content Content to encode.
     * @return string Encoded content.
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, "/");

        $search = "/(" . $base . "\/mod\/pair\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@PAIRINDEX*$2@$', $content);

        $search = "/(" . $base . "\/mod\/pair\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@PAIRVIEWBYID*$2@$', $content);

        return $content;
    }
}
