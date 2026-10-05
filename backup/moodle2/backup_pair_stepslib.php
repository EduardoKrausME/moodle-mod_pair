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
 * Backup structure for mod_pair.
 *
 * @package   mod_pair
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the data stored in a Pair activity backup.
 */
class backup_pair_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the activity backup structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $pair = new backup_nested_element("pair", ["id"], [
            "name",
            "intro",
            "introformat",
            "pairingmode",
            "allowchange",
            "timeopen",
            "timeclose",
            "timemodified",
        ]);

        $groups = new backup_nested_element("groups");
        $group = new backup_nested_element("group", ["id"], [
            "createdby",
            "timecreated",
        ]);

        $members = new backup_nested_element("members");
        $member = new backup_nested_element("member", ["id"], [
            "userid",
            "timecreated",
        ]);

        $pair->add_child($groups);
        $groups->add_child($group);
        $group->add_child($members);
        $members->add_child($member);

        $pair->set_source_table("pair", ["id" => backup::VAR_ACTIVITYID]);

        if ($this->get_setting_value("userinfo")) {
            $group->set_source_table("pair_groups", ["pairid" => backup::VAR_PARENTID], "id");
            $member->set_source_table("pair_members", ["groupid" => backup::VAR_PARENTID], "id");
        }

        $group->annotate_ids("user", "createdby");
        $member->annotate_ids("user", "userid");
        $pair->annotate_files("mod_pair", "intro", null);

        return $this->prepare_activity_structure($pair);
    }
}
