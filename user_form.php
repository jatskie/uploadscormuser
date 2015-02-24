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
 * Bulk user upload forms
 *
 * @package    tool
 * @subpackage uploadscormuser
 * @copyright  2007 Dan Poltawski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir.'/formslib.php';
require_once($CFG->dirroot . '/user/editlib.php');

/**
 * Upload a file CVS file with user information.
 *
 * @copyright  2007 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_uploadscormuser_form1 extends moodleform 
{
    function definition () 
    {
        $mform = $this->_form;

        $mform->addElement('header', 'settingsheader', get_string('upload'));

        $mform->addElement('filepicker', 'userfile', get_string('file'));
        $mform->addRule('userfile', null, 'required');

        $choices = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter_name', get_string('csvdelimiter', 'tool_uploadscormuser'), $choices);
        if (array_key_exists('cfg', $choices)) 
        {
            $mform->setDefault('delimiter_name', 'cfg');
        } 
        else if (get_string('listsep', 'langconfig') == ';') 
        {
            $mform->setDefault('delimiter_name', 'semicolon');
        }
        else 
        {
            $mform->setDefault('delimiter_name', 'comma');
        }

        $choices = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'tool_uploadscormuser'), $choices);
        $mform->setDefault('encoding', 'UTF-8');

        $choices = array('10'=>10, '20'=>20, '100'=>100, '1000'=>1000, '100000'=>100000);
        $mform->addElement('select', 'previewrows', get_string('rowpreviewnum', 'tool_uploadscormuser'), $choices);
        $mform->setType('previewrows', PARAM_INT);

        $this->add_action_buttons(false, get_string('uploadscormusers', 'tool_uploadscormuser'));
    }
}


/**
 * Specify user upload details
 *
 * @copyright  2007 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_uploadscormuser_form2 extends moodleform 
{
    function definition () 
    {
        global $CFG, $USER;

        $mform   = $this->_form;
        $columns = $this->_customdata['columns'];
        $data    = $this->_customdata['data'];

        // I am the template user, why should it be the administrator? we have roles now, other ppl may use this script ;-)
        $templateuser = $USER;

        // upload settings and file
        $mform->addElement('header', 'settingsheader', get_string('settings'));

        $choices = array(US_USER_ADDNEW     => get_string('usoptype_addnew', 'tool_uploadscormuser'),
                         US_USER_ADDINC     => get_string('usoptype_addinc', 'tool_uploadscormuser'),
                         US_USER_ADD_UPDATE => get_string('usoptype_addupdate', 'tool_uploadscormuser'),
                         US_USER_UPDATE     => get_string('usoptype_update', 'tool_uploadscormuser'));
        $mform->addElement('select', 'ustype', get_string('usoptype', 'tool_uploadscormuser'), $choices);

        $choices = array(0 => get_string('infilefield', 'auth'), 1 => get_string('createpasswordifneeded', 'auth'));
        $mform->addElement('select', 'uspasswordnew', get_string('uspasswordnew', 'tool_uploadscormuser'), $choices);
        $mform->setDefault('uspasswordnew', 1);
        $mform->disabledIf('uspasswordnew', 'ustype', 'eq', US_USER_UPDATE);

        $choices = array(US_UPDATE_NOCHANGES    => get_string('nochanges', 'tool_uploadscormuser'),
                         US_UPDATE_FILEOVERRIDE => get_string('usupdatefromfile', 'tool_uploadscormuser'),
                         US_UPDATE_ALLOVERRIDE  => get_string('usupdateall', 'tool_uploadscormuser'),
                         US_UPDATE_MISSING      => get_string('usupdatemissing', 'tool_uploadscormuser'));
        $mform->addElement('select', 'usupdatetype', get_string('usupdatetype', 'tool_uploadscormuser'), $choices);
        $mform->setDefault('usupdatetype', US_UPDATE_NOCHANGES);
        $mform->disabledIf('usupdatetype', 'ustype', 'eq', US_USER_ADDNEW);
        $mform->disabledIf('usupdatetype', 'ustype', 'eq', US_USER_ADDINC);

        $choices = array(0 => get_string('nochanges', 'tool_uploadscormuser'), 1 => get_string('update'));
        $mform->addElement('select', 'uspasswordold', get_string('uspasswordold', 'tool_uploadscormuser'), $choices);
        $mform->setDefault('uspasswordold', 0);
        $mform->disabledIf('uspasswordold', 'ustype', 'eq', US_USER_ADDNEW);
        $mform->disabledIf('uspasswordold', 'ustype', 'eq', US_USER_ADDINC);
        $mform->disabledIf('uspasswordold', 'usupdatetype', 'eq', 0);
        $mform->disabledIf('uspasswordold', 'usupdatetype', 'eq', 3);

        $choices = array(US_PWRESET_WEAK => get_string('usersweakpassword', 'tool_uploadscormuser'),
                         US_PWRESET_NONE => get_string('none'),
                         US_PWRESET_ALL  => get_string('all'));
        if (empty($CFG->passwordpolicy)) 
        {
            unset($choices[US_PWRESET_WEAK]);
        }
        $mform->addElement('select', 'usforcepasswordchange', get_string('forcepasswordchange', 'core'), $choices);


        $mform->addElement('selectyesno', 'usallowrenames', get_string('allowrenames', 'tool_uploadscormuser'));
        $mform->setDefault('usallowrenames', 0);
        $mform->disabledIf('usallowrenames', 'ustype', 'eq', US_USER_ADDNEW);
        $mform->disabledIf('usallowrenames', 'ustype', 'eq', US_USER_ADDINC);

        $mform->addElement('selectyesno', 'usallowdeletes', get_string('allowdeletes', 'tool_uploadscormuser'));
        $mform->setDefault('usallowdeletes', 0);
        $mform->disabledIf('usallowdeletes', 'ustype', 'eq', US_USER_ADDNEW);
        $mform->disabledIf('usallowdeletes', 'ustype', 'eq', US_USER_ADDINC);

        $mform->addElement('selectyesno', 'usallowsuspends', get_string('allowsuspends', 'tool_uploadscormuser'));
        $mform->setDefault('usallowsuspends', 1);
        $mform->disabledIf('usallowsuspends', 'ustype', 'eq', US_USER_ADDNEW);
        $mform->disabledIf('usallowsuspends', 'ustype', 'eq', US_USER_ADDINC);

        $mform->addElement('selectyesno', 'usnoemailduplicates', get_string('usnoemailduplicates', 'tool_uploadscormuser'));
        $mform->setDefault('usnoemailduplicates', 1);

        $mform->addElement('selectyesno', 'usstandardusernames', get_string('usstandardusernames', 'tool_uploadscormuser'));
        $mform->setDefault('usstandardusernames', 1);

        $choices = array(US_BULK_NONE    => get_string('no'),
                         US_BULK_NEW     => get_string('usbulknew', 'tool_uploadscormuser'),
                         US_BULK_UPDATED => get_string('usbulkupdated', 'tool_uploadscormuser'),
                         US_BULK_ALL     => get_string('usbulkall', 'tool_uploadscormuser'));
        $mform->addElement('select', 'usbulk', get_string('usbulk', 'tool_uploadscormuser'), $choices);
        $mform->setDefault('usbulk', 0);

        // roles selection
        $showroles = false;
        foreach ($columns as $column) 
        {
            if (preg_match('/^type\d+$/', $column)) 
            {
                $showroles = true;
                break;
            }
        }
        if ($showroles) 
        {
            $mform->addElement('header', 'rolesheader', get_string('roles'));

            $choices = us_allowed_roles(true);

            $mform->addElement('select', 'uslegacy1', get_string('uslegacy1role', 'tool_uploadscormuser'), $choices);
            if ($studentroles = get_archetype_roles('student')) 
            {
                foreach ($studentroles as $role) 
                {
                    if (isset($choices[$role->id])) 
                    {
                        $mform->setDefault('uslegacy1', $role->id);
                        break;
                    }
                }
                unset($studentroles);
            }

            $mform->addElement('select', 'uslegacy2', get_string('uslegacy2role', 'tool_uploadscormuser'), $choices);
            if ($editteacherroles = get_archetype_roles('editingteacher')) 
            {
                foreach ($editteacherroles as $role) 
                {
                    if (isset($choices[$role->id])) 
                    {
                        $mform->setDefault('uslegacy2', $role->id);
                        break;
                    }
                }
                unset($editteacherroles);
            }

            $mform->addElement('select', 'uslegacy3', get_string('uslegacy3role', 'tool_uploadscormuser'), $choices);
            if ($teacherroles = get_archetype_roles('teacher')) 
            {
                foreach ($teacherroles as $role) 
                {
                    if (isset($choices[$role->id])) 
                    {
                        $mform->setDefault('uslegacy3', $role->id);
                        break;
                    }
                }
                unset($teacherroles);
            }
        }

        // default values
        $mform->addElement('header', 'defaultheader', get_string('defaultvalues', 'tool_uploadscormuser'));

        $mform->addElement('text', 'username', get_string('ususernametemplate', 'tool_uploadscormuser'), 'size="20"');
        $mform->setType('username', PARAM_RAW); // No cleaning here. The process verifies it later.
        $mform->addRule('username', get_string('requiredtemplate', 'tool_uploadscormuser'), 'required', null, 'client');
        $mform->disabledIf('username', 'ustype', 'eq', US_USER_ADD_UPDATE);
        $mform->disabledIf('username', 'ustype', 'eq', US_USER_UPDATE);

        $mform->addElement('text', 'email', get_string('email'), 'maxlength="100" size="30"');
        $mform->setType('email', PARAM_RAW); // No cleaning here. The process verifies it later.
        $mform->disabledIf('email', 'ustype', 'eq', US_USER_ADD_UPDATE);
        $mform->disabledIf('email', 'ustype', 'eq', US_USER_UPDATE);

        // only enabled and known to work plugins
        $choices = us_supported_auths();
        $mform->addElement('select', 'auth', get_string('chooseauthmethod','auth'), $choices);
        $mform->setDefault('auth', 'manual'); // manual is a sensible backwards compatible default
        $mform->addHelpButton('auth', 'chooseauthmethod', 'auth');
        $mform->setAdvanced('auth');

        $choices = array(0 => get_string('emaildisplayno'), 1 => get_string('emaildisplayyes'), 2 => get_string('emaildisplaycourse'));
        $mform->addElement('select', 'maildisplay', get_string('emaildisplay'), $choices);
        $mform->setDefault('maildisplay', 2);

        $choices = array(0 => get_string('textformat'), 1 => get_string('htmlformat'));
        $mform->addElement('select', 'mailformat', get_string('emailformat'), $choices);
        $mform->setDefault('mailformat', 1);
        $mform->setAdvanced('mailformat');

        $choices = array(0 => get_string('emaildigestoff'), 1 => get_string('emaildigestcomplete'), 2 => get_string('emaildigestsubjects'));
        $mform->addElement('select', 'maildigest', get_string('emaildigest'), $choices);
        $mform->setDefault('maildigest', 0);
        $mform->setAdvanced('maildigest');

        $choices = array(1 => get_string('autosubscribeyes'), 0 => get_string('autosubscribeno'));
        $mform->addElement('select', 'autosubscribe', get_string('autosubscribe'), $choices);
        $mform->setDefault('autosubscribe', 1);

        $mform->addElement('text', 'city', get_string('city'), 'maxlength="120" size="25"');
        $mform->setType('city', PARAM_TEXT);
        if (empty($CFG->defaultcity)) 
        {
            $mform->setDefault('city', $templateuser->city);
        } 
        else 
        {
            $mform->setDefault('city', $CFG->defaultcity);
        }

        $choices = get_string_manager()->get_list_of_countries();
        $choices = array(''=>get_string('selectacountry').'...') + $choices;
        $mform->addElement('select', 'country', get_string('selectacountry'), $choices);
        if (empty($CFG->country)) 
        {
            $mform->setDefault('country', $templateuser->country);
        } 
        else 
        {
            $mform->setDefault('country', $CFG->country);
        }
        $mform->setAdvanced('country');

        $choices = get_list_of_timezones();
        $choices['99'] = get_string('serverlocaltime');
        $mform->addElement('select', 'timezone', get_string('timezone'), $choices);
        $mform->setDefault('timezone', $templateuser->timezone);
        $mform->setAdvanced('timezone');

        $mform->addElement('select', 'lang', get_string('preferredlanguage'), get_string_manager()->get_list_of_translations());
        $mform->setDefault('lang', $templateuser->lang);
        $mform->setAdvanced('lang');

        $editoroptions = array('maxfiles'=>0, 'maxbytes'=>0, 'trusttext'=>false, 'forcehttps'=>false);
        $mform->addElement('editor', 'description', get_string('userdescription'), null, $editoroptions);
        $mform->setType('description', PARAM_CLEANHTML);
        $mform->addHelpButton('description', 'userdescription');
        $mform->setAdvanced('description');

        $mform->addElement('text', 'url', get_string('webpage'), 'maxlength="255" size="50"');
        $mform->setType('url', PARAM_URL);
        $mform->setAdvanced('url');

        $mform->addElement('text', 'idnumber', get_string('idnumber'), 'maxlength="255" size="25"');
        $mform->setType('idnumber', PARAM_NOTAGS);

        $mform->addElement('text', 'institution', get_string('institution'), 'maxlength="255" size="25"');
        $mform->setType('institution', PARAM_TEXT);
        $mform->setDefault('institution', $templateuser->institution);

        $mform->addElement('text', 'department', get_string('department'), 'maxlength="255" size="25"');
        $mform->setType('department', PARAM_TEXT);
        $mform->setDefault('department', $templateuser->department);

        $mform->addElement('text', 'phone1', get_string('phone'), 'maxlength="20" size="25"');
        $mform->setType('phone1', PARAM_NOTAGS);
        $mform->setAdvanced('phone1');

        $mform->addElement('text', 'phone2', get_string('phone2'), 'maxlength="20" size="25"');
        $mform->setType('phone2', PARAM_NOTAGS);
        $mform->setAdvanced('phone2');

        $mform->addElement('text', 'address', get_string('address'), 'maxlength="255" size="25"');
        $mform->setType('address', PARAM_TEXT);
        $mform->setAdvanced('address');

        // Next the profile defaults
        profile_definition($mform);

        // hidden fields
        $mform->addElement('hidden', 'iid');
        $mform->setType('iid', PARAM_INT);

        $mform->addElement('hidden', 'previewrows');
        $mform->setType('previewrows', PARAM_INT);

        $this->add_action_buttons(true, get_string('uploadscormusers', 'tool_uploadscormuser'));

        $this->set_data($data);
    }

    /**
     * Form tweaks that depend on current data.
     */
    function definition_after_data() 
    {
        $mform   = $this->_form;
        $columns = $this->_customdata['columns'];

        foreach ($columns as $column) 
        {
            if ($mform->elementExists($column)) 
            {
                $mform->removeElement($column);
            }
        }

        if (!in_array('password', $columns)) 
        {
            // password resetting makes sense only if password specified in csv file
            if ($mform->elementExists('usforcepasswordchange')) 
            {
                $mform->removeElement('usforcepasswordchange');
            }
        }
    }

    /**
     * Server side validation.
     */
    function validation($data, $files) 
    {
        $errors = parent::validation($data, $files);
        $columns = $this->_customdata['columns'];
        $optype  = $data['ustype'];

        // detect if password column needed in file
        if (!in_array('password', $columns)) 
        {
            switch ($optype) {
                case US_USER_UPDATE:
                    if (!empty($data['uspasswordold'])) 
                    {
                        $errors['uspasswordold'] = get_string('missingfield', 'error', 'password');
                    }
                    break;

                case US_USER_ADD_UPDATE:
                    if (empty($data['uspasswordnew'])) 
                    {
                        $errors['uspasswordnew'] = get_string('missingfield', 'error', 'password');
                    }
                    
                    if  (!empty($data['uspasswordold'])) 
                    {
                        $errors['uspasswordold'] = get_string('missingfield', 'error', 'password');
                    }
                    break;

                case US_USER_ADDNEW:
                    if (empty($data['uspasswordnew'])) 
                    {
                        $errors['uspasswordnew'] = get_string('missingfield', 'error', 'password');
                    }
                    break;
                case US_USER_ADDINC:
                    if (empty($data['uspasswordnew'])) 
                    {
                        $errors['uspasswordnew'] = get_string('missingfield', 'error', 'password');
                    }
                    break;
             }
        }

        // look for other required data
        if ($optype != US_USER_UPDATE) 
        {
            $missing = array();

            if ($missing) 
            {
                $errors['ustype'] = implode('<br />',  $missing);
            }
            if (!in_array('email', $columns) and empty($data['email'])) 
            {
                $errors['email'] = get_string('requiredtemplate', 'tool_uploadscormuser');
            }
        }
        return $errors;
    }

    /**
     * Used to reformat the data from the editor component
     *
     * @return stdClass
     */
    function get_data() 
    {
        $data = parent::get_data();

        if ($data !== null and isset($data->description)) 
        {
            $data->descriptionformat = $data->description['format'];
            $data->description = $data->description['text'];
        }

        return $data;
    }
}