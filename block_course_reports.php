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
 * Form for editing HTML block instances.
 *
 * @package   block_course_reports
 * @author    Manuel Quistial based in https://moodle.org/plugins/block_analytics_graphs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
class block_course_reports extends block_base {

    function init() {
        $this->title = get_string('pluginname', 'block_course_reports');
    }

    function get_content() {

        global $DB, $USER, $CFG, $COURSE;
        //$course = $this->page->course;

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = "";
        $courses = enrol_get_my_courses();
        foreach ($courses as $course) {
            $this->content->text .= "<li> <a href= {$CFG->wwwroot}/blocks/course_reports/view.php?courseid={$course->id}&legacy=0>" . $course->shortname . "</a>";
        }
        $this->content->footer = '';

        return $this->content;
    }
}
