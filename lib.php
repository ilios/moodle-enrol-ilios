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
 * Ilios enrolment plugin.
 *
 * @package    enrol_ilios
 * @copyright  2015 Carson Tam <carson.tam@ucsf.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir.'/filelib.php';

/**
 * Ilios enrolment plugin implementation.
 * @author    Carson Tam
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_ilios_plugin extends enrol_plugin {
    /** @var object Ilios client object */
    protected $iliosclient;

    /**
     * Constructor
     */
    public function __construct() {
        $this->iliosclient = new ilios_client($this->get_config('host_url'),
                                              $this->get_config('userid'),
                                              $this->get_config('secret'),
                                              $this->get_config('apikey'));
    }

    /**
     * Returns the Ilios Client for API access
     *
     * @return ilios_client object
     */
    public function get_http_client() {
        return $this->iliosclient;
    }


    /**
     * Returns localised name of enrol instance.
     *
     * @param stdClass $instance (null is accepted too)
     * @return string
     */
    public function get_instance_name($instance) {
        global $DB;

        if (empty($instance)) {
            $enrol = $this->get_name();
            return get_string('pluginshortname', 'enrol_'.$enrol);

        } else if (empty($instance->name)) {
            $enrol = $this->get_name();

            $syncfield = $instance->customchar1;
            $syncid = $instance->customint1;

            // $groups = $this->iliosclient->getbyids($syncfield.'s', $syncid);

            // if (!empty($groups)) {
            //     $group = $groups[0];
            //     $groupname = format_string($group->title, true, array('context'=>context::instance_by_id($instance->courseid)));
            // } else
            {
                $syncinfo = json_decode($instance->customtext1);
                if (!empty($syncinfo)) {
                    $schooltitle = $syncinfo->school->title;
                    $programtitle = $syncinfo->program->shorttitle;
                    $cohorttitle = $syncinfo->cohort->title;
                    $groupname = $schooltitle ."/".$programtitle."/".$cohorttitle;
                    if (isset($syncinfo->learnerGroup)) {
                        $grouptitle = $syncinfo->learnerGroup->title;
                        $groupname .= '/'.$grouptitle;
                    }
                } else {
                    $groupname = empty($instance->customchar2) ? get_string('pluginshortname', 'enrol_'.$enrol) : $instance->customchar2;
                }
            }

            if ($role = $DB->get_record('role', array('id'=>$instance->roleid))) {
                $role = role_get_name($role, context_course::instance($instance->courseid, IGNORE_MISSING));
                return $groupname . ' (' . $role .')';
            } else {
                return $groupname;
            }

        } else {
            return format_string($instance->name, true, array('context'=>context_course::instance($instance->courseid)));
        }
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        if (!$this->can_add_new_instances($courseid)) {
            return NULL;
        }
        // Multiple instances supported - multiple parent courses linked.
        return new moodle_url('/enrol/ilios/edit.php', array('courseid'=>$courseid));
    }

    /**
     * Given a courseid this function returns true if the user is able to enrol or configure cohorts.
     * AND there are cohorts that the user can view.
     *
     * @param int $courseid
     * @return bool
     */
    protected function can_add_new_instances($courseid) {
        global $DB;

        // TODO: Should our capabilities depends on moodle/cohort:view? Probably not, right?!
        $coursecontext = context_course::instance($courseid);
        if (!has_capability('moodle/course:enrolconfig', $coursecontext) or !has_capability('enrol/ilios:config', $coursecontext)) {
            return false;
        }
        // list($sqlparents, $params) = $DB->get_in_or_equal($coursecontext->get_parent_context_ids());
        // $sql = "SELECT id, contextid
        //           FROM {cohort}
        //          WHERE contextid $sqlparents
        //       ORDER BY name ASC";
        // $cohorts = $DB->get_records_sql($sql, $params);
        // foreach ($cohorts as $c) {
        //     $context = context::instance_by_id($c->contextid);
        //     if (has_capability('moodle/cohort:view', $context)) {
        //         return true;
        //     }
        // }
        // return false;
        return true;
    }

    /**
     * Returns edit icons for the page with list of instances.
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'ilios') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = array();

        if (has_capability('enrol/ilios:config', $context)) {
            $editlink = new moodle_url("/enrol/ilios/edit.php", array('courseid'=>$instance->courseid, 'id'=>$instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core',
                    array('class' => 'iconsmall')));
        }

        return $icons;
    }

    /**
     * Called for all enabled enrol plugins that returned true from is_cron_required().
     * @return void
     */
    public function cron($trace=null) {
        global $CFG;

        require_once("$CFG->dirroot/enrol/ilios/locallib.php");
        if ($trace === null) {
            $trace = new null_progress_trace();
        }
        enrol_ilios_sync($trace);
        $trace->finished();
    }

    /**
     * Called after updating/inserting course.
     *
     * @param bool $inserted true if course just inserted
     * @param stdClass $course
     * @param stdClass $data form data
     * @return void
     */
    public function course_updated($inserted, $course, $data) {
        // It turns out there is no need for cohorts to deal with this hook, see MDL-34870.
    }

    /**
     * Update instance status
     *
     * @param stdClass $instance
     * @param int $newstatus ENROL_INSTANCE_ENABLED, ENROL_INSTANCE_DISABLED
     * @return void
     */
    public function update_status($instance, $newstatus) {
        global $CFG;

        parent::update_status($instance, $newstatus);

        require_once("$CFG->dirroot/enrol/ilios/locallib.php");
        $trace = new null_progress_trace();
        enrol_ilios_sync($trace, $instance->courseid);
        $trace->finished();
    }

    /**
     * Does this plugin allow manual unenrolment of a specific user?
     * Yes, but only if user suspended...
     *
     * @param stdClass $instance course enrol instance
     * @param stdClass $ue record from user_enrolments table
     *
     * @return bool - true means user with 'enrol/xxx:unenrol' may unenrol this user, false means nobody may touch this user enrolment
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
        if ($ue->status == ENROL_USER_SUSPENDED) {
            return true;
        }

        return false;
    }

    /**
     * Gets an array of the user enrolment actions.
     *
     * @param course_enrolment_manager $manager
     * @param stdClass $ue A user enrolment object
     * @return array An array of user_enrolment_actions
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = array();
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol_user($instance, $ue) && has_capability('enrol/ilios:unenrol', $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(new pix_icon('t/delete', ''), get_string('unenrol', 'enrol'), $url, array('class'=>'unenrollink', 'rel'=>$ue->id));
        }
        return $actions;
    }

    /**
     * Returns a button to enrol a ilios or its users through the manual enrolment plugin.
     *
     * This function also adds a quickenrolment JS ui to the page so that users can be enrolled
     * via AJAX.
     *
     * @param course_enrolment_manager $manager
     * @return enrol_user_button
     */
    public function get_manual_enrol_button(course_enrolment_manager $manager) {
        $course = $manager->get_course();
        if (!$this->can_add_new_instances($course->id)) {
            return false;
        }

        $iliosurl = new moodle_url('/enrol/ilios/edit.php', array('courseid' => $course->id));
        $button = new enrol_user_button($iliosurl, get_string('enrol', 'enrol_ilios'), 'get');
        $button->class .= ' enrol_ilios_plugin';

        $button->strings_for_js(array(
            'enrol',
            'synced',
            ), 'enrol');
        $button->strings_for_js(array(
            'ajaxmore',
            'enrolilios',
            'enrolilioscohort',
            'enroliliosgroup',
            'enroliliosusers',
            'iliosgroups',
            'iliosgroupsearch',
            'school',
            'program',
            'cohort'
            ), 'enrol_ilios');
        $button->strings_for_js('assignroles', 'role');
        $button->strings_for_js('ilios', 'enrol_ilios');
        $button->strings_for_js('users', 'moodle');

        // No point showing this at all if the user cant manually enrol users.
        $hasmanualinstance = has_capability('enrol/manual:enrol', $manager->get_context()) && $manager->has_instance('manual');

        $modules = array('moodle-enrol_ilios-quickenrolment', 'moodle-enrol_ilios-quickenrolment-skin');
        $function = 'M.enrol_ilios.quickenrolment.init';
        $arguments = array(
            'courseid'        => $course->id,
            'ajaxurl'         => '/enrol/ilios/ajax.php',
            'url'             => $manager->get_moodlepage()->url->out(false),
            'manualEnrolment' => $hasmanualinstance);
        $button->require_yui_module($modules, $function, array($arguments));

        return $button;
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB, $CFG;

        if (!$step->get_task()->is_samesite()) {
            // No ilios restore from other sites.
            $step->set_mapping('enrol', $oldid, 0);
            return;
        }

        if (!empty($data->customint6)) {
            $data->customint6 = $step->get_mappingid('group', $data->customint6);
        }

        if ($data->roleid and $DB->record_exists('ilios', array('id'=>$data->customint1school))) {
            $instance = $DB->get_record('enrol', array('roleid'=>$data->roleid, 'customint1'=>$data->customint1school, 'courseid'=>$course->id, 'enrol'=>$this->get_name()));
            if ($instance) {
                $instanceid = $instance->id;
            } else {
                $instanceid = $this->add_instance($course, (array)$data);
            }
            $step->set_mapping('enrol', $oldid, $instanceid);

            require_once("$CFG->dirroot/enrol/ilios/locallib.php");
            $trace = new null_progress_trace();
            enrol_ilios_sync($trace, $course->id);
            $trace->finished();

        } else if ($this->get_config('unenrolaction') == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            $data->customint1school = 0;
            $instance = $DB->get_record('enrol', array('roleid'=>$data->roleid, 'customint1'=>$data->customint1school, 'courseid'=>$course->id, 'enrol'=>$this->get_name()));

            if ($instance) {
                $instanceid = $instance->id;
            } else {
                $data->status = ENROL_INSTANCE_DISABLED;
                $instanceid = $this->add_instance($course, (array)$data);
            }
            $step->set_mapping('enrol', $oldid, $instanceid);

            require_once("$CFG->dirroot/enrol/ilios/locallib.php");
            $trace = new null_progress_trace();
            enrol_ilios_sync($trace, $course->id);
            $trace->finished();

        } else {
            $step->set_mapping('enrol', $oldid, 0);
        }
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $oldinstancestatus
     * @param int $userid
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        global $DB;

        if ($this->get_config('unenrolaction') != ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            // Enrolments were already synchronised in restore_instance(), we do not want any suspended leftovers.
            return;
        }

        // ENROL_EXT_REMOVED_SUSPENDNOROLES means all previous enrolments are restored
        // but without roles and suspended.

        if (!$DB->record_exists('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$userid))) {
            $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, ENROL_USER_SUSPENDED);
        }
    }

    /**
     * Restore user group membership.
     * @param stdClass $instance
     * @param int $groupid
     * @param int $userid
     */
    public function restore_group_member($instance, $groupid, $userid) {
        // Nothing to do here, the group members are added in $this->restore_group_restored()
        return;
    }
}

/**
 * Prevent removal of enrol roles.
 * @param int $itemid
 * @param int $groupid
 * @param int $userid
 * @return bool
 */
function enrol_ilios_allow_group_member_remove($itemid, $groupid, $userid) {
    return false;
}


/**
 * Ilios API 1.0 Client for using JWT access tokens.
 *
 * @package   enrol_ilios
 * @copyright Carson Tam <carson.tam@ucsf.edu>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ilios_client extends curl {
    /** @const API URL */
    const API_URL = '/api/v1';
    const AUTH_URL = '/auth/login';

    /** var string ilios hostname */
    private $hostname = '';
    /** var string API base URL */
    private $apibaseurl = '';
    /** var string The client ID. */
    private $clientid = '';
    /** var string The client secret. */
    private $clientsecret = '';
    /** var string JWT token */
    private $accesstoken = null;

    /**
     * Constructor.
     *
     * @param string $hostname
     * @param string $clientid
     * @param string $clientsecret
     */
    public function __construct($hostname, $clientid = '', $clientsecret = '', $clienttoken = '') {
        parent::__construct();
        $this->hostname = $hostname;
        $this->apibaseurl = $this->hostname . self::API_URL;
        $this->clientid = $clientid;
        $this->clientsecret = $clientsecret;
        if (empty($clienttoken)) {
            $this->accesstoken = $this->get_new_token();
        } else {
            $atoken = new stdClass;
            $atoken->token = $clienttoken;
            $atoken->expires = false;
            $this->accesstoken = $atoken;
        }
    }



    /**
     * Get Ilios json object and return PHP object
     *
     * @param string $object API object name (camel case)
     * @param array  $filters   e.g. array('id' => 3)
     * @param array  $sortorder e.g. array('title' => "ASC")
     */
    public function get($object, $filters='', $sortorder='') {

        if (empty($this->accesstoken)) {
            throw new moodle_exception( 'Error: client token is not set.' );
        }

        if ($this->accesstoken->expires && (time() > $this->accesstoken->expires)) {
            $this->accesstoken = $this->get_new_token();

            if (empty($this->accesstoken)) {
                throw new moodle_exception( 'Error: unable to renew access token.' );
            }
        }

        $token = $this->accesstoken->token;
        $this->resetHeader();
        $this->setHeader( 'X-JWT-Authorization: Token ' . $token );
        $url = $this->apibaseurl . '/' . strtolower($object);
        $filterstring = '';
        if (is_array($filters)) {
            foreach ($filters as $param => $value) {
                if (is_array( $value )) {
                    foreach ($value as $val) {
                        $filterstring .= "&filters[$param][]=$val";
                    }
                } else {
                    $filterstring .= "&filters[$param]=$value";
                }
            }
        }

        if (is_array($sortorder)) {
            foreach ($sortorder as $param => $value) {
                $filterstring .="&order_by[$param]=$value";
            }
        }

        $limit = 50;
        $offset = 0;
        $retobj = array();
        $obj = null;

        do {
            $url .= "?limit=$limit&offset=$offset".$filterstring;
            $results = parent::get($url);
            $obj = json_decode($results);

            if ($obj !== null && isset($obj->$object) && !empty($obj->$object)) {
                $retobj = array_merge($retobj, $obj->$object);
                if (count($obj->$object) < $limit) {
                    $obj = null;
                } else {
                    $offset += $limit;
                }
            } else {
                $obj = null;
            }

        } while ($obj !== null);

        return $retobj;
    }


    /**
     * Get Ilios json object by IDs and return PHP object
     *
     * @param string $object API object name (camel case)
     * @param string or array  $ids   e.g. array(1,2,3)
     */
    public function getbyids($object, $ids='') {

        if (empty($this->accesstoken)) {
            throw new moodle_exception( 'Error' );
        }

        if ($this->accesstoken->expires && (time() > $this->accesstoken->expires)) {
            $this->accesstoken = $this->get_new_token();

            if (empty($this->accesstoken)) {
                throw new moodle_exception( 'Error' );
            }
        }

        $token = $this->accesstoken->token;
        $this->resetHeader();
        $this->setHeader( 'X-JWT-Authorization: Token ' . $token );
        $url = $this->apibaseurl . '/' . strtolower($object);

        $filterstrings = array();
        if (is_numeric($ids)) {
            $filterstrings[] = "?filters[id]=$ids";
        } elseif (is_array($ids)) {
            // fetch 10 at a time
            $offset  = 0;
            $length  = 10;
            $remains = count($ids);
            do {
                $slicedids = array_slice($ids, $offset, $length);
                $offset += $length;
                $remains -= count($slicedids);

                $filterstr = "?limit=$length";
                foreach ($slicedids as $id) {
                    $filterstr .= "&filters[id][]=$id";
                }
                $filterstrings[] = $filterstr;
            } while ($remains > 0);
        }

        $retobj = array();
        foreach ($filterstrings as $filterstr) {
            $results = parent::get($url.$filterstr);
            $obj = json_decode($results);

            if ($obj !== null && isset($obj->$object) && !empty($obj->$object)) {
                $retobj = array_merge($retobj, $obj->$object);
            }
        }
        return $retobj;
    }

    /**
     * Get new token
     */
    protected function get_new_token() {
        $atoken = new stdClass;

        if (empty($this->clientid) || empty($this->clientsecret)) {
            return $this->accesstoken;
        } else {
            $params = array('password' => $this->clientsecret, 'username' => $this->clientid);
            // echo "$result = parent::post($this->hostname . self::AUTH_URL, $params);";
            // print_r($params);
            $result = parent::post($this->hostname . self::AUTH_URL, $params);
            $parsed_result = $this->parse_result($result);
            $atoken->token = $parsed_result->jwt;
            $atoken->expires = (time() + 3600);  // make it expires in an hour
            return $atoken;
        }
    }

    /**
     * A method to parse oauth response to get oauth_token and oauth_token_secret
     * @param string $str
     * @return array
     */
    public function parse_result($str) {
        if (empty($str)) {
            throw new moodle_exception('error');
        }
        $result = json_decode($str);

        if (empty($result)) {
            throw new moodle_exception('error');
        }

        if (isset($result->errors)) {
            throw new moodle_exception(print_r($result->errors[0]));
        }

        return $result;
    }

}
