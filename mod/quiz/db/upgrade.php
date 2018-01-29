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
 * Upgrade script for the quiz module.
 *
 * @package    mod_quiz
 * @copyright  2006 Eloy Lafuente (stronk7)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Quiz module upgrade function.
 * @param string $oldversion the version we are upgrading from.
 */
function xmldb_quiz_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2016092000) {
        // Define new fields to be added to quiz.
        $table = new xmldb_table('quiz');

        $field = new xmldb_field('allowofflineattempts', XMLDB_TYPE_INTEGER, '1', null, null, null, 0, 'completionpass');
        // Conditionally launch add field allowofflineattempts.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2016092000, 'quiz');
    }

    if ($oldversion < 2016092001) {
        // New field for quiz_attemps.
        $table = new xmldb_table('quiz_attempts');

        $field = new xmldb_field('timemodifiedoffline', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 'timemodified');
        // Conditionally launch add field timemodifiedoffline.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2016092001, 'quiz');
    }

    if ($oldversion < 2016100300) {
        // Find quizzes with the combination of require passing grade and grade to pass 0.
        $gradeitems = $DB->get_records_sql("
            SELECT gi.id, gi.itemnumber, cm.id AS cmid
              FROM {quiz} q
        INNER JOIN {course_modules} cm ON q.id = cm.instance
        INNER JOIN {grade_items} gi ON q.id = gi.iteminstance
        INNER JOIN {modules} m ON m.id = cm.module
             WHERE q.completionpass = 1
               AND gi.gradepass = 0
               AND cm.completiongradeitemnumber IS NULL
               AND gi.itemmodule = m.name
               AND gi.itemtype = ?
               AND m.name = ?", array('mod', 'quiz'));

        foreach ($gradeitems as $gradeitem) {
            $DB->execute("UPDATE {course_modules}
                             SET completiongradeitemnumber = :itemnumber
                           WHERE id = :cmid",
                array('itemnumber' => $gradeitem->itemnumber, 'cmid' => $gradeitem->cmid));
        }
        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2016100300, 'quiz');
    }

    // Automatically generated Moodle v3.2.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.3.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.4.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2018012400) {

        // Define key questionid (foreign) to be dropped form quiz_slots.
        $table = new xmldb_table('quiz_slots');
        $key = new xmldb_key('questionid', XMLDB_KEY_FOREIGN, array('questionid'), 'question', array('id'));

        // Launch drop key questionid.
        $dbman->drop_key($table, $key);

        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2018012400, 'quiz');
    }

    if ($oldversion < 2018012401) {

        // Changing nullability of field questionid on table quiz_slots to null.
        // Also changing the default of field questionid on table quiz_slots to drop it.
        $table = new xmldb_table('quiz_slots');
        $field = new xmldb_field('questionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'requireprevious');

        // Launch change of nullability for field questionid.
        $dbman->change_field_notnull($table, $field);

        // Launch change of default for field questionid.
        $dbman->change_field_default($table, $field);

        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2018012401, 'quiz');
    }

    if ($oldversion < 2018012402) {

        // Define key questionid (foreign) to be added to quiz_slots.
        $table = new xmldb_table('quiz_slots');
        $key = new xmldb_key('questionid', XMLDB_KEY_FOREIGN, array('questionid'), 'question', array('id'));

        // Launch add key questionid.
        $dbman->add_key($table, $key);

        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2018012402, 'quiz');
    }

    if ($oldversion < 2018012403) {

        $table = new xmldb_table('quiz_slots');

        // Define field questioncategoryid to be added to quiz_slots.
        $field = new xmldb_field('questioncategoryid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'questionid');
        // Conditionally launch add field questioncategoryid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define key questioncategoryid (foreign) to be added to quiz_slots.
        $key = new xmldb_key('questioncategoryid', XMLDB_KEY_FOREIGN, array('questioncategoryid'), 'questioncategory', array('id'));
        // Launch add key questioncategoryid.
        $dbman->add_key($table, $key);

        // Define field includingsubcategories to be added to quiz_slots.
        $field = new xmldb_field('includingsubcategories', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'questioncategoryid');
        // Conditionally launch add field includingsubcategories.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field tagid to be added to quiz_slots.
        $field = new xmldb_field('tagid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'includingsubcategories');
        // Conditionally launch add field tagid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define key tagid (foreign) to be added to quiz_slots.
        $key = new xmldb_key('tagid', XMLDB_KEY_FOREIGN, array('tagid'), 'tag', array('id'));
        // Launch add key tagid.
        $dbman->add_key($table, $key);

        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2018012403, 'quiz');
    }

    if ($oldversion < 2018012404) {
        // This SQL fetches all "random" questions from the question bank.
        $fromclause = "FROM {quiz_slots} qs
                       JOIN {question} q ON q.id = qs.questionid
                      WHERE q.qtype = 'random'";

        // Get the total record count - used for the progress bar.
        $total = $DB->count_records_sql("SELECT count(qs.id) $fromclause");

        // Get the records themselves.
        $rs = $DB->get_recordset_sql("SELECT qs.id, q.category, q.questiontext $fromclause");

        $a = new stdClass();
        $a->total = $total;
        $a->done = 0;

        // For each question, move the configuration data to the quiz_slots table.
        $pbar = new progress_bar('updatequizslotswithrandom', 500, true);
        foreach ($rs as $record) {
            $data = new stdClass();
            $data->id = $record->id;
            $data->questionid = null;
            $data->questioncategoryid = $record->category;
            $data->includingsubcategories = empty($record->questiontext) ? 0 : 1;
            $DB->update_record('quiz_slots', $data);

            // Update progress.
            $a->done++;
            $pbar->update($a->done, $a->total, get_string('updatequizslotswithrandomxofy', 'quiz', $a));
        }
        $rs->close();

        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2018012404, 'quiz');

    }

    return true;
}
