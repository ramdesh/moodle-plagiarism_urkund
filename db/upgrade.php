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
 * @global moodle_database $DB
 * @param int $oldversion
 * @return bool
 */
function xmldb_plagiarism_urkund_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2011121200) {
        $table = new xmldb_table('urkund_files');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'plagiarism_urkund_files');
        }

        $table = new xmldb_table('urkund_config');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'plagiarism_urkund_config');
        }

        upgrade_plugin_savepoint(true, 2011121200, 'plagiarism', 'urkund');
    }
    if ($oldversion < 2013081900) {
        require_once($CFG->dirroot . '/plagiarism/urkund/lib.php');
        // We have changed the way files are identified to urkund - we need to check for files that have been
        // submitted using the old indentifier format but haven't had a report returned.
        $sql = "UPDATE {plagiarism_urkund_files}
                   SET statuscode = '".URKUND_STATUSCODE_ACCEPTED_OLD."'".
               " WHERE statuscode = '".URKUND_STATUSCODE_ACCEPTED."'";
        $DB->execute($sql);
        upgrade_plugin_savepoint(true, 2013081900, 'plagiarism', 'urkund');
    }

    if ($oldversion < 2015052100) {
        // Check for old API address and update if required.
        $apiaddress = get_config('plagiarism', 'urkund_api');
        if ($apiaddress == 'https://secure.urkund.com/ws/integration/1.0/rest/submissions' ||
            $apiaddress == 'https://secure.urkund.com/api/rest/submissions' ||
            $apiaddress == 'https://secure.urkund.com/api') {
            set_config('urkund_api', 'https://secure.urkund.com/api/submissions', 'plagiarism');
        }

        upgrade_plugin_savepoint(true, 2015052100, 'plagiarism', 'urkund');
    }

    if ($oldversion < 2015102800) {
        // Set new opt-out setting as true by default.
        set_config('urkund_optout', 1, 'plagiarism');

        upgrade_plugin_savepoint(true, 2015102800, 'plagiarism', 'urkund');
    }
    if ($oldversion < 2015112400) {
        global $OUTPUT;
        // Check to make sure no events are still in the queue as these will be deleted/ignored.
         $sql = "SELECT count(*) FROM {events_queue_handlers} qh
                   JOIN {events_handlers} eh ON qh.handlerid = eh.id
                  WHERE eh.component = 'plagiarism_urkund' AND
                        (eh.eventname = 'assessable_file_uploaded' OR
                         eh.eventname = 'assessable_content_uploaded' OR
                         eh.eventname = 'assessable_submitted')";
        $countevents = $DB->count_records_sql($sql);
        if (!empty($countevents)) {
            echo $OUTPUT->notification(get_string('cannotupgradeunprocesseddata', 'plagiarism_urkund'));
            return false;
        }

        upgrade_plugin_savepoint(true, 2015112400, 'plagiarism', 'urkund');
    }
    if ($oldversion < 2015121401) {
        if (!$DB->record_exists('plagiarism_urkund_config', array('name' => 'urkund_allowallfile', 'cm' => 0))) {
            // Set appropriate defaults for new setting.
            $newelement = new Stdclass();
            $newelement->cm = 0;
            $newelement->name = 'urkund_allowallfile';
            $newelement->value = 1;
            $DB->insert_record('plagiarism_urkund_config', $newelement);

            upgrade_plugin_savepoint(true, 2015121401, 'plagiarism', 'urkund');
        }

    }

    return true;
}