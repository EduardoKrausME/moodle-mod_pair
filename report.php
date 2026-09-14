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
 * report.php
 *
 * @package   mod_pair
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . "/../../config.php");

$id = required_param("id", PARAM_INT);
$cm = get_coursemodule_from_id("pair", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$pair = $DB->get_record("pair", ["id" => $cm->instance], "*", MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability("mod/pair:viewreport", $context);

$PAGE->set_url("/mod/pair/report.php", ["id" => $cm->id]);
$PAGE->set_title(get_string("reporttitle", "pair", format_string($pair->name)));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$manager = new \mod_pair\pair_manager($pair, $cm, $context);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_sesskey();
    require_capability("mod/pair:managepairs", $context);
    $action = required_param("action", PARAM_ALPHA);
    if ($action === "remove") {
        $groupid = required_param("groupid", PARAM_INT);
        $manager->remove_group($groupid);
        redirect($PAGE->url, get_string("pairremoved", "pair"));
    }
}

$pairs = $manager->get_pairs();
$available = $manager->get_available_users();

$rows = [];
foreach ($pairs as $index => $group) {
    $names = [];
    foreach ($group["members"] as $member) {
        $url = new moodle_url("/user/view.php", ["id" => $member->userid, "course" => $course->id]);
        $names[] = html_writer::link($url, fullname($member));
    }

    $actions = "";
    if (has_capability("mod/pair:managepairs", $context)) {
        $form = html_writer::start_tag("form", ["method" => "post", "action" => $PAGE->url->out(false), "class" => "d-inline"]);
        $form .= html_writer::empty_tag("input", ["type" => "hidden", "name" => "sesskey", "value" => sesskey()]);
        $form .= html_writer::empty_tag("input", ["type" => "hidden", "name" => "action", "value" => "remove"]);
        $form .= html_writer::empty_tag("input", ["type" => "hidden", "name" => "groupid", "value" => $group["id"]]);
        $form .= html_writer::tag("button", get_string("remove", "pair"),
            ["type" => "submit", "class" => "btn btn-sm btn-outline-danger"]);
        $form .= html_writer::end_tag("form");
        $actions = $form;
    }

    $rows[] = [
        get_string("pairnumber", "pair", $index + 1),
        implode(" &amp; ", $names),
        userdate($group["timecreated"]),
        $actions,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string("reporttitle", "pair", format_string($pair->name)));

$table = new html_table();
$table->head = [get_string("pair", "pair"), get_string("members", "pair"), get_string("created", "pair"), get_string("actions")];
$table->data = $rows;
if ($rows) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string("nopairs", "pair"), "info");
}

echo $OUTPUT->heading(get_string("unpairedstudents", "pair"), 3);
if ($available) {
    $names = array_map(fn($user) => fullname($user), $available);
    echo html_writer::alist($names);
} else {
    echo html_writer::tag("p", get_string("nounpairedstudents", "pair"));
}

echo $OUTPUT->single_button(new moodle_url("/mod/pair/view.php", ["id" => $cm->id]), get_string("backtoactivity", "pair"), "get");
echo $OUTPUT->footer();
