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
 * pair_manager.php
 *
 * @package   mod_pair
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pair;

use cm_info;
use context_module;
use moodle_exception;
use stdClass;

/**
 * Class pair_manager.
 */
class pair_manager {
    /**
     * Property pair.
     *
     * @var stdClass
     */
    private stdClass $pair;
    /**
     * Property cm.
     *
     * @var cm_info
     */
    private stdClass $cm;
    /**
     * Property context.
     *
     * @var context_module
     */
    private context_module $context;

    /**
     * Method __construct.
     *
     * @param stdClass $pair Parameter pair.
     * @param cm_info $cm Parameter cm.
     * @param context_module $context Parameter context.
     */
    public function __construct(stdClass $pair, stdClass $cm, context_module $context) {
        $this->pair = $pair;
        $this->cm = $cm;
        $this->context = $context;
    }

    /**
     * Method is_open.
     *
     * @return bool Return value.
     */
    public function is_open(): bool {
        $now = time();
        if (!empty($this->pair->timeopen) && $now < $this->pair->timeopen) {
            return false;
        }
        if (!empty($this->pair->timeclose) && $now > $this->pair->timeclose) {
            return false;
        }
        return true;
    }

    /**
     * Method get_member_record.
     *
     * @param int $userid Parameter userid.
     * @return ?stdClass Return value.
     */
    public function get_member_record(int $userid): ?stdClass {
        global $DB;
        return $DB->get_record("pair_members", ["pairid" => $this->pair->id, "userid" => $userid]) ?: null;
    }

    /**
     * Method get_partner.
     *
     * @param int $userid Parameter userid.
     * @return ?stdClass Return value.
     */
    public function get_partner(int $userid): ?stdClass {
        global $DB;

        $member = $this->get_member_record($userid);
        if (!$member) {
            return null;
        }

        $sql = "SELECT u.*
                  FROM {pair_members} pm
                  JOIN {user} u ON u.id = pm.userid
                 WHERE pm.groupid = :groupid
                   AND pm.userid <> :userid
                   AND u.deleted = 0";
        return $DB->get_record_sql($sql, ["groupid" => $member->groupid, "userid" => $userid]) ?: null;
    }

    /**
     * Method get_available_users.
     *
     * @param int $excludeuserid Parameter excludeuserid.
     * @return array Return value.
     */
    public function get_available_users(int $excludeuserid = 0): array {
        global $DB;

        $users = get_enrolled_users($this->context, "mod/pair:participate", 0,
            "u.id,u.firstname,u.lastname,u.email,u.picture,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename");
        if (!$users) {
            return [];
        }

        $paired = $DB->get_fieldset_select("pair_members", "userid", "pairid = :pairid", ["pairid" => $this->pair->id]);
        $paired = array_fill_keys(array_map("intval", $paired), true);

        foreach ($users as $id => $user) {
            if ((int) $id === $excludeuserid || isset($paired[(int) $id])) {
                unset($users[$id]);
            }
        }

        \core_collator::asort_objects_by_property($users, "lastname", \core_collator::SORT_NATURAL);
        return $users;
    }

    /**
     * Method create_manual_pair.
     *
     * @param int $userid Parameter userid.
     * @param int $partnerid Parameter partnerid.
     * @return int Return value.
     */
    public function create_manual_pair(int $userid, int $partnerid): int {
        global $DB;

        if (!$this->is_open()) {
            throw new moodle_exception("activityclosed", "pair");
        }
        if ($userid === $partnerid) {
            throw new moodle_exception("cannotpairself", "pair");
        }
        if (!has_capability("mod/pair:participate", $this->context, $userid) ||
                !has_capability("mod/pair:participate", $this->context, $partnerid)) {
            throw new moodle_exception("invalidparticipant", "pair");
        }

        $transaction = $DB->start_delegated_transaction();

        if ($this->get_member_record($userid)) {
            throw new moodle_exception("alreadypaired", "pair");
        }
        if ($this->get_member_record($partnerid)) {
            throw new moodle_exception("partnerunavailable", "pair");
        }

        $groupid = $this->create_group($userid);
        $this->add_member($groupid, $userid);
        $this->add_member($groupid, $partnerid);

        $transaction->allow_commit();
        return $groupid;
    }

    /**
     * Method leave_pair.
     *
     * @param int $userid Parameter userid.
     * @return void Return value.
     */
    public function leave_pair(int $userid): void {
        global $DB;

        if (empty($this->pair->allowchange) && !has_capability("mod/pair:managepairs", $this->context)) {
            throw new moodle_exception("changepairdisabled", "pair");
        }

        $member = $this->get_member_record($userid);
        if (!$member) {
            return;
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records("pair_members", ["groupid" => $member->groupid]);
        $DB->delete_records("pair_groups", ["id" => $member->groupid]);
        $transaction->allow_commit();
    }

    /**
     * Method remove_group.
     *
     * @param int $groupid Parameter groupid.
     * @return void Return value.
     */
    public function remove_group(int $groupid): void {
        global $DB;

        $group = $DB->get_record("pair_groups", ["id" => $groupid, "pairid" => $this->pair->id], "*", MUST_EXIST);
        unset($group);

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records("pair_members", ["groupid" => $groupid]);
        $DB->delete_records("pair_groups", ["id" => $groupid]);
        $transaction->allow_commit();
    }

    /**
     * Method randomize_unpaired.
     *
     * @param int $createdby Parameter createdby.
     * @return array Return value.
     */
    public function randomize_unpaired(int $createdby): array {
        $users = array_values($this->get_available_users());
        if (count($users) < 2) {
            return ["created" => 0, "leftover" => count($users) === 1 ? $users[0] : null];
        }

        shuffle($users);
        $created = 0;
        while (count($users) >= 2) {
            $first = array_pop($users);
            $second = array_pop($users);
            $this->create_pair_for_users((int) $first->id, (int) $second->id, $createdby);
            $created++;
        }

        return ["created" => $created, "leftover" => $users ? array_pop($users) : null];
    }

    /**
     * Method get_pairs.
     *
     * @return array Return value.
     */
    public function get_pairs(): array {
        global $DB;

        $sql = "SELECT pm.id AS recordid, pg.id AS groupid, pg.timecreated, pm.userid,
                       u.firstname, u.lastname, u.email,
                       u.picture, u.imagealt, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename
                  FROM {pair_groups} pg
                  JOIN {pair_members} pm ON pm.groupid = pg.id
                  JOIN {user} u ON u.id = pm.userid
                 WHERE pg.pairid = :pairid
              ORDER BY pg.id, u.lastname, u.firstname";
        $records = $DB->get_records_sql($sql, ["pairid" => $this->pair->id]);

        $pairs = [];
        foreach ($records as $record) {
            if (!isset($pairs[$record->groupid])) {
                $pairs[$record->groupid] = [
                    "id" => (int) $record->groupid,
                    "timecreated" => (int) $record->timecreated,
                    "members" => [],
                ];
            }
            $pairs[$record->groupid]["members"][] = $record;
        }
        return array_values($pairs);
    }

    /**
     * Method create_pair_for_users.
     *
     * @param int $firstid Parameter firstid.
     * @param int $secondid Parameter secondid.
     * @param int $createdby Parameter createdby.
     * @return int Return value.
     */
    private function create_pair_for_users(int $firstid, int $secondid, int $createdby): int {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        if ($this->get_member_record($firstid) || $this->get_member_record($secondid)) {
            throw new moodle_exception("partnerunavailable", "pair");
        }

        $groupid = $this->create_group($createdby);
        $this->add_member($groupid, $firstid);
        $this->add_member($groupid, $secondid);
        $transaction->allow_commit();
        return $groupid;
    }

    /**
     * Method create_group.
     *
     * @param int $createdby Parameter createdby.
     * @return int Return value.
     */
    private function create_group(int $createdby): int {
        global $DB;

        return $DB->insert_record("pair_groups", (object) [
            "pairid" => $this->pair->id,
            "createdby" => $createdby,
            "timecreated" => time(),
        ]);
    }

    /**
     * Method add_member.
     *
     * @param int $groupid Parameter groupid.
     * @param int $userid Parameter userid.
     * @return void Return value.
     */
    private function add_member(int $groupid, int $userid): void {
        global $DB;

        $DB->insert_record("pair_members", (object) [
            "pairid" => $this->pair->id,
            "groupid" => $groupid,
            "userid" => $userid,
            "timecreated" => time(),
        ]);
    }
}
