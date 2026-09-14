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
 * view.php
 *
 * @package   mod_pair
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_pair\event\course_module_viewed;
use mod_pair\pair_manager;

require_once(__DIR__ . "/../../config.php");

$id = required_param("id", PARAM_INT);
$cm = get_coursemodule_from_id("pair", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$pair = $DB->get_record("pair", ["id" => $cm->instance], "*", MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$PAGE->set_url("/mod/pair/view.php", ["id" => $cm->id]);
$PAGE->set_title(format_string($pair->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$event = course_module_viewed::create([
    "objectid" => $pair->id,
    "context" => $context,
]);
$event->add_record_snapshot("pair", $pair);
$event->trigger();

$manager = new pair_manager($pair, $cm, $context);
$canparticipate = has_capability("mod/pair:participate", $context);
$canmanage = has_capability("mod/pair:managepairs", $context);
$canreport = has_capability("mod/pair:viewreport", $context);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_sesskey();
    $action = required_param("action", PARAM_ALPHA);

    if ($action === "choose") {
        require_capability("mod/pair:participate", $context);
        if ($pair->pairingmode === "random") {
            throw new moodle_exception("manualdisabled", "pair");
        }
        $partnerid = required_param("partnerid", PARAM_INT);
        $manager->create_manual_pair($USER->id, $partnerid);
        redirect($PAGE->url, get_string("pairsuccess", "pair"));
    }

    if ($action === "leave") {
        require_capability("mod/pair:participate", $context);
        $manager->leave_pair($USER->id);
        redirect($PAGE->url, get_string("pairleft", "pair"));
    }

    if ($action === "randomize") {
        require_capability("mod/pair:managepairs", $context);
        if ($pair->pairingmode === "manual") {
            throw new moodle_exception("randomdisabled", "pair");
        }
        $result = $manager->randomize_unpaired($USER->id);
        $message = get_string("randomizedcount", "pair", $result["created"]);
        if ($result["leftover"]) {
            $message .= " " . get_string("leftoveruser", "pair", fullname($result["leftover"]));
        }
        redirect($PAGE->url, $message);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($pair->name));

if (!empty($pair->intro)) {
    echo $OUTPUT->box(format_module_intro("pair", $pair, $cm->id), "generalbox mod_introbox");
}

if (!$manager->is_open()) {
    if (!empty($pair->timeopen) && time() < $pair->timeopen) {
        echo $OUTPUT->notification(get_string("openson", "pair", userdate($pair->timeopen)), "info");
    } else if (!empty($pair->timeclose) && time() > $pair->timeclose) {
        echo $OUTPUT->notification(get_string("closedon", "pair", userdate($pair->timeclose)), "info");
    }
}

$partner = $canparticipate ? $manager->get_partner($USER->id) : null;
$member = $canparticipate ? $manager->get_member_record($USER->id) : null;

if ($member && $partner) {
    $data = (object) [
        "partnername" => fullname($partner),
        "partnerprofileurl" => new moodle_url("/user/view.php", ["id" => $partner->id, "course" => $course->id]),
        "partnerpicture" => $OUTPUT->user_picture($partner, ["courseid" => $course->id, "size" => 100]),
        "canleave" => (bool) $pair->allowchange,
        "cmid" => $cm->id,
        "sesskey" => sesskey(),
    ];
    echo $OUTPUT->render_from_template("mod_pair/my_pair", $data);
} else if ($canparticipate && $manager->is_open() && $pair->pairingmode !== "random") {
    $available = $manager->get_available_users($USER->id);
    $users = [];
    foreach ($available as $user) {
        $users[] = [
            "id" => (int) $user->id,
            "name" => fullname($user),
            "picture" => $OUTPUT->user_picture($user, ["courseid" => $course->id, "size" => 48]),
        ];
    }
    echo $OUTPUT->render_from_template("mod_pair/choose_partner", [
        "users" => $users,
        "hasusers" => !empty($users),
        "cmid" => $cm->id,
        "sesskey" => sesskey(),
    ]);
} else if ($canparticipate && !$member && $pair->pairingmode === "random") {
    echo $OUTPUT->notification(get_string("waitingrandom", "pair"), "info");
}

if ($canmanage && $manager->is_open() && $pair->pairingmode !== "manual") {
    echo html_writer::start_div("mt-4");
    echo html_writer::tag("h3", get_string("randompairing", "pair"));
    echo html_writer::tag("p", get_string("randompairingdesc", "pair"));
    echo html_writer::start_tag("form", ["method" => "post", "action" => $PAGE->url->out(false)]);
    echo html_writer::empty_tag("input", ["type" => "hidden", "name" => "sesskey", "value" => sesskey()]);
    echo html_writer::empty_tag("input", ["type" => "hidden", "name" => "action", "value" => "randomize"]);
    echo html_writer::tag("button", get_string("randomizenow", "pair"), ["type" => "submit", "class" => "btn btn-primary"]);
    echo html_writer::end_tag("form");
    echo html_writer::end_div();
}

if ($canreport) {
    echo html_writer::div(
        $OUTPUT->single_button(new moodle_url("/mod/pair/report.php", ["id" => $cm->id]), get_string("viewreport", "pair"), "get"),
        "mt-4"
    );
}

echo $OUTPUT->footer();
