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
 * provider.php
 *
 * @package   mod_pair
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pair\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Class provider.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Method get_metadata.
     *
     * @param collection $collection Parameter collection.
     * @return collection Return value.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table("pair_members", [
            "userid" => "privacy:metadata:pair_members:userid",
            "timecreated" => "privacy:metadata:pair_members:timecreated",
        ], "privacy:metadata:pair_members");

        $collection->add_database_table("pair_groups", [
            "createdby" => "privacy:metadata:pair_groups:createdby",
            "timecreated" => "privacy:metadata:pair_groups:timecreated",
        ], "privacy:metadata:pair_groups");

        return $collection;
    }

    /**
     * Method get_contexts_for_userid.
     *
     * @param int $userid Parameter userid.
     * @return contextlist Return value.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {pair} p ON p.id = cm.instance
             LEFT JOIN {pair_members} pm ON pm.pairid = p.id AND pm.userid = :memberuserid
             LEFT JOIN {pair_groups} pg ON pg.pairid = p.id AND pg.createdby = :creatoruserid
                 WHERE pm.id IS NOT NULL OR pg.id IS NOT NULL";
        $contextlist->add_from_sql($sql, [
            "contextlevel" => CONTEXT_MODULE,
            "modname" => "pair",
            "memberuserid" => $userid,
            "creatoruserid" => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Method get_users_in_context.
     *
     * @param userlist $userlist Parameter userlist.
     * @return void Return value.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id("pair", $context->instanceid);
        if (!$cm) {
            return;
        }

        $sql = "SELECT pm.userid
                  FROM {pair_members} pm
                 WHERE pm.pairid = :pairid";
        $userlist->add_from_sql("userid", $sql, ["pairid" => $cm->instance]);

        $sql = "SELECT pg.createdby AS userid
                  FROM {pair_groups} pg
                 WHERE pg.pairid = :pairid";
        $userlist->add_from_sql("userid", $sql, ["pairid" => $cm->instance]);
    }

    /**
     * Method export_user_data.
     *
     * @param approved_contextlist $contextlist Parameter contextlist.
     * @return void Return value.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id("pair", $context->instanceid);
            if (!$cm) {
                continue;
            }

            $member = $DB->get_record("pair_members", [
                "pairid" => $cm->instance,
                "userid" => $userid,
            ]);

            $data = [];
            if ($member) {
                $partnerid = $DB->get_field_select(
                    "pair_members",
                    "userid",
                    "groupid = :groupid AND userid <> :userid",
                    ["groupid" => $member->groupid, "userid" => $userid]
                );
                $data["pair_membership"] = (object) [
                    "partner_userid" => $partnerid ?: null,
                    "timecreated" => transform::datetime($member->timecreated),
                ];
            }

            $createdgroups = $DB->get_records("pair_groups", [
                "pairid" => $cm->instance,
                "createdby" => $userid,
            ], "timecreated ASC");
            if ($createdgroups) {
                $data["pairs_created"] = array_map(static function($group) {
                    return (object) [
                        "groupid" => $group->id,
                        "timecreated" => transform::datetime($group->timecreated),
                    ];
                }, array_values($createdgroups));
            }

            if ($data) {
                writer::with_context($context)->export_data([], (object) $data);
            }
        }
    }

    /**
     * Method delete_data_for_all_users_in_context.
     *
     * @param context $context Parameter context.
     * @return void Return value.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id("pair", $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records("pair_members", ["pairid" => $cm->instance]);
        $DB->delete_records("pair_groups", ["pairid" => $cm->instance]);
    }

    /**
     * Method delete_data_for_user.
     *
     * @param approved_contextlist $contextlist Parameter contextlist.
     * @return void Return value.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        self::delete_user_from_contexts($contextlist->get_user()->id, $contextlist->get_contexts());
    }

    /**
     * Method delete_data_for_users.
     *
     * @param approved_userlist $userlist Parameter userlist.
     * @return void Return value.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            self::delete_user_from_contexts((int) $userid, [$context]);
        }
    }

    /**
     * Method delete_user_from_contexts.
     *
     * @param int $userid Parameter userid.
     * @param array $contexts Parameter contexts.
     * @return void Return value.
     */
    private static function delete_user_from_contexts(int $userid, array $contexts): void {
        global $DB;

        foreach ($contexts as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id("pair", $context->instanceid);
            if (!$cm) {
                continue;
            }

            $member = $DB->get_record("pair_members", [
                "pairid" => $cm->instance,
                "userid" => $userid,
            ]);
            if ($member) {
                $DB->delete_records("pair_members", ["groupid" => $member->groupid]);
                $DB->delete_records("pair_groups", ["id" => $member->groupid]);
            }

            $DB->set_field("pair_groups", "createdby", 0, [
                "pairid" => $cm->instance,
                "createdby" => $userid,
            ]);
        }
    }
}
