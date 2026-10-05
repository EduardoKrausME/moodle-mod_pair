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
 * Restore structure for mod_pair.
 *
 * @package   mod_pair
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores Pair activity data and remaps users and pair groups.
 */
class restore_pair_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the elements restored from pair.xml.
     *
     * @return restore_path_element[]
     */
    protected function define_structure(): array {
        $paths = [
            new restore_path_element("pair", "/activity/pair"),
        ];

        if ($this->get_setting_value("userinfo")) {
            $paths[] = new restore_path_element("pair_group", "/activity/pair/groups/group");
            $paths[] = new restore_path_element("pair_member", "/activity/pair/groups/group/members/member");
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the activity instance.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_pair(array $data): void {
        global $DB;

        $data = (object)$data;
        $oldid = (int)$data->id;
        $data->course = $this->get_courseid();

        if (!empty($data->timeopen)) {
            $data->timeopen = $this->apply_date_offset($data->timeopen);
        }
        if (!empty($data->timeclose)) {
            $data->timeclose = $this->apply_date_offset($data->timeclose);
        }
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newid = $DB->insert_record("pair", $data);
        $this->apply_activity_instance($newid);
        $this->set_mapping("pair", $oldid, $newid, true);
    }

    /**
     * Restores a generated pair group.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_pair_group(array $data): void {
        global $DB;

        $data = (object)$data;
        $oldid = (int)$data->id;
        $data->pairid = $this->get_new_parentid("pair");
        $data->createdby = $this->get_mappingid("user", $data->createdby, 0);
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        $newid = $DB->insert_record("pair_groups", $data);
        $this->set_mapping("pair_group", $oldid, $newid, true);
    }

    /**
     * Restores a user assigned to a pair.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_pair_member(array $data): void {
        global $DB;

        $data = (object)$data;
        $oldid = (int)$data->id;
        $data->pairid = $this->get_new_parentid("pair");
        $data->groupid = $this->get_new_parentid("pair_group");
        $data->userid = $this->get_mappingid("user", $data->userid, 0);

        if (!$data->userid || !$data->pairid || !$data->groupid) {
            return;
        }

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $newid = $DB->insert_record("pair_members", $data);
        $this->set_mapping("pair_member", $oldid, $newid);
    }

    /**
     * Restores files embedded in the activity description.
     *
     * @return void
     */
    protected function after_execute(): void {
        $this->add_related_files("mod_pair", "intro", null);
    }
}
