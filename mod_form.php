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
 * mod_form.php
 *
 * @package   mod_pair
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->dirroot}/course/moodleform_mod.php");

/**
 * Class mod_pair_mod_form.
 */
class mod_pair_mod_form extends moodleform_mod {
    /**
     * Method definition.
     *
     * @return mixed Return value.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement("header", "general", get_string("general", "form"));
        $mform->addElement("text", "name", get_string("pairname", "pair"), ["size" => 64]);
        $mform->setType("name", PARAM_TEXT);
        $mform->addRule("name", null, "required", null, "client");

        $this->standard_intro_elements();

        $mform->addElement("select", "pairingmode", get_string("pairingmode", "pair"), [
            "manual" => get_string("pairingmode_manual", "pair"),
            "random" => get_string("pairingmode_random", "pair"),
            "both" => get_string("pairingmode_both", "pair"),
        ]);
        $mform->setDefault("pairingmode", "manual");
        $mform->addHelpButton("pairingmode", "pairingmode", "pair");

        $mform->addElement("advcheckbox", "allowchange", get_string("allowchange", "pair"));
        $mform->addHelpButton("allowchange", "allowchange", "pair");

        $mform->addElement("header", "availability", get_string("availability"));
        $mform->addElement("date_time_selector", "timeopen", get_string("timeopen", "pair"), ["optional" => true]);
        $mform->addElement("date_time_selector", "timeclose", get_string("timeclose", "pair"), ["optional" => true]);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Method validation.
     *
     * @param mixed $data Parameter data.
     * @param mixed $files Parameter files.
     * @return mixed Return value.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data["timeopen"]) && !empty($data["timeclose"]) && $data["timeclose"] <= $data["timeopen"]) {
            $errors["timeclose"] = get_string("closebeforeopen", "pair");
        }
        return $errors;
    }
}
