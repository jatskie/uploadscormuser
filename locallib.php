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
 * Bulk user registration functions
 *
 * @package    tool
 * @subpackage uploadscormuser
 * @copyright  2004 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

define('US_USER_ADDNEW', 0);
define('US_USER_ADDINC', 1);
define('US_USER_ADD_UPDATE', 2);
define('US_USER_UPDATE', 3);

define('US_UPDATE_NOCHANGES', 0);
define('US_UPDATE_FILEOVERRIDE', 1);
define('US_UPDATE_ALLOVERRIDE', 2);
define('US_UPDATE_MISSING', 3);

define('US_BULK_NONE', 0);
define('US_BULK_NEW', 1);
define('US_BULK_UPDATED', 2);
define('US_BULK_ALL', 3);

define('US_PWRESET_NONE', 0);
define('US_PWRESET_WEAK', 1);
define('US_PWRESET_ALL', 2);

define('DEFAULT_PASSWORD', 'Password-1');

define('MDL_FLD_FIRSTNAME', 0);
define('MDL_FLD_LASTNAME', 1);
define('MDL_FLD_USERNAME', 2);
define('MDL_FLD_EMAIL', 3);
define('MDL_FLD_PASSWORD', 4);
define('MDL_FLD_IDNUMBER', 5);
define('MDL_FLD_COURSE1', 6);

/**
 * Tracking of processed users.
 *
 * This class prints user information into a html table.
 *
 * @package    core
 * @subpackage admin
 * @copyright  2007 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class us_progress_tracker 
{
    private $_row;
    public $columns = array('status', 'line', 'id', 'username', 'firstname', 'lastname', 'email', 'password', 'auth', 'enrolments', 'suspended', 'deleted');

    /**
     * Print table header.
     * @return void
     */
    public function start() 
    {
        $ci = 0;
        echo '<table id="usresults" class="generaltable boxaligncenter flexible-wrap" summary="'.get_string('uploadscormusersresult', 'tool_uploadscormuser').'">';
        echo '<tr class="heading r0">';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('status').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('uscsvline', 'tool_uploadscormuser').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">ID</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('username').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('firstname').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('lastname').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('email').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('password').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('authentication').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('enrolments', 'enrol').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('suspended', 'auth').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('delete').'</th>';
        echo '</tr>';
        $this->_row = null;
    }

    /**
     * Flush previous line and start a new one.
     * @return void
     */
    public function flush() 
    {
        if (empty($this->_row) or empty($this->_row['line']['normal'])) 
        {
            // Nothing to print - each line has to have at least number
            $this->_row = array();
            foreach ($this->columns as $col) {
                $this->_row[$col] = array('normal'=>'', 'info'=>'', 'warning'=>'', 'error'=>'');
            }
            return;
        }
        $ci = 0;
        $ri = 1;
        echo '<tr class="r'.$ri.'">';
        foreach ($this->_row as $key=>$field) 
        {
            foreach ($field as $type=>$content) 
            {
                if ($field[$type] !== '') 
                {
                    $field[$type] = '<span class="us'.$type.'">'.$field[$type].'</span>';
                } 
                else 
                {
                    unset($field[$type]);
                }
            }
            echo '<td class="cell c'.$ci++.'">';
            if (!empty($field)) 
            {
                echo implode('<br />', $field);
            } 
            else 
            {
                echo '&nbsp;';
            }
            echo '</td>';
        }
        echo '</tr>';
        foreach ($this->columns as $col) 
        {
            $this->_row[$col] = array('normal'=>'', 'info'=>'', 'warning'=>'', 'error'=>'');
        }
    }

    /**
     * Add tracking info
     * @param string $col name of column
     * @param string $msg message
     * @param string $level 'normal', 'warning' or 'error'
     * @param bool $merge true means add as new line, false means override all previous text of the same type
     * @return void
     */
    public function track($col, $msg, $level = 'normal', $merge = true) 
    {
        if (empty($this->_row)) 
        {
            $this->flush(); //init arrays
        }
        if (!in_array($col, $this->columns)) 
        {
            debugging('Incorrect column:'.$col);
            return;
        }
        if ($merge) 
        {
            if ($this->_row[$col][$level] != '') 
            {
                $this->_row[$col][$level] .='<br />';
            }
            $this->_row[$col][$level] .= $msg;
        } 
        else 
        {
            $this->_row[$col][$level] = $msg;
        }
    }

    /**
     * Print the table end
     * @return void
     */
    public function close() 
    {
        $this->flush();
        echo '</table>';
    }
}

/**
 * Validation callback function - verified the column line of csv file.
 * Converts standard column names to lowercase.
 * @param csv_import_reader $cir
 * @param array $stdfields standard user fields
 * @param array $profilefields custom profile fields
 * @param moodle_url $returnurl return url in case of any error
 * @return array list of fields
 */
function us_validate_user_upload_columns(csv_import_reader $cir, $stdfields, $profilefields, moodle_url $returnurl) 
{
    $columns = $cir->get_columns();
    
    if (empty($columns)) 
    {
        $cir->close();
        $cir->cleanup();
        print_error('cannotreadtmpfile', 'error', $returnurl);
    }
    if ( count($columns) < 2 ) 
    {
        $cir->close();
        $cir->cleanup();
        print_error('csvfewcolumns', 'error', $returnurl);
    }

    // test columns
    $processed = array();
    foreach ($columns as $key => $unused) 
    {
        $field = $columns[$key];
        $lcfield = core_text::strtolower($field);
        
        if (in_array($field, $stdfields) or in_array($lcfield, $stdfields)) 
        {
            // standard fields are only lowercase
            $newfield = $lcfield;

        } 
        else if (in_array($field, $profilefields)) 
        {
            // exact profile field name match - these are case sensitive
            $newfield = $field;

        } 
        else if (in_array($lcfield, $profilefields)) 
        {
            // hack: somebody wrote uppercase in csv file, but the system knows only lowercase profile field
            $newfield = $lcfield;

        } 
        else if (preg_match('/^(cohort|course|group|type|role|enrolperiod|enrolstatus)\d+$/', $lcfield)) 
        {
            // special fields for enrolments
            $newfield = $lcfield;

        } 
        else 
        {
            $cir->close();
            $cir->cleanup();
            print_error('invalidfieldname', 'error', $returnurl, $field);
        }
        
        if (in_array($newfield, $processed)) 
        {
            $cir->close();
            $cir->cleanup();
            print_error('duplicatefieldname', 'error', $returnurl, $newfield);
        }
        $processed[$key] = $newfield;
    }

    return $processed;
}

/**
 * Increments username - increments trailing number or adds it if not present.
 * Varifies that the new username does not exist yet
 * @param string $username
 * @return incremented username which does not exist yet
 */
function us_increment_username($username) 
{
    global $DB, $CFG;

    if (!preg_match_all('/(.*?)([0-9]+)$/', $username, $matches)) 
    {
        $username = $username.'2';
    } 
    else 
    {
        $username = $matches[1][0].($matches[2][0]+1);
    }

    if ($DB->record_exists('user', array('username'=>$username, 'mnethostid'=>$CFG->mnet_localhost_id))) 
    {
        return us_increment_username($username);
    } 
    else 
    {
        return $username;
    }
}

/**
 * Check if default field contains templates and apply them.
 * @param string template - potential tempalte string
 * @param object user object- we need username, firstname and lastname
 * @return string field value
 */
function us_process_template($template, $user) 
{
    if (is_array($template)) 
    {
        // hack for for support of text editors with format
        $t = $template['text'];
    } 
    else 
    {
        $t = $template;
    }
    if (strpos($t, '%') === false) 
    {
        return $template;
    }

    $username  = isset($user->username)  ? $user->username  : '';
    $firstname = isset($user->firstname) ? $user->firstname : '';
    $lastname  = isset($user->lastname)  ? $user->lastname  : '';

    $callback = partial('us_process_template_callback', $username, $firstname, $lastname);

    $result = preg_replace_callback('/(?<!%)%([+-~])?(\d)*([flu])/', $callback, $t);

    if (is_null($result)) 
    {
        return $template; //error during regex processing??
    }

    if (is_array($template)) 
    {
        $template['text'] = $result;
        return $t;
    } 
    else 
    {
        return $result;
    }
}

/**
 * Internal callback function.
 */
function us_process_template_callback($username, $firstname, $lastname, $block) 
{
    switch ($block[3]) 
    {
        case 'u':
            $repl = $username;
            break;
        case 'f':
            $repl = $firstname;
            break;
        case 'l':
            $repl = $lastname;
            break;
        default:
            return $block[0];
    }

    switch ($block[1]) 
    {
        case '+':
            $repl = core_text::strtoupper($repl);
            break;
        case '-':
            $repl = core_text::strtolower($repl);
            break;
        case '~':
            $repl = core_text::strtotitle($repl);
            break;
    }

    if (!empty($block[2])) 
    {
        $repl = core_text::substr($repl, 0 , $block[2]);
    }

    return $repl;
}

/**
 * Returns list of auth plugins that are enabled and known to work.
 *
 * If ppl want to use some other auth type they have to include it
 * in the CSV file next on each line.
 *
 * @return array type=>name
 */
function us_supported_auths() 
{
    // Get all the enabled plugins.
    $plugins = get_enabled_auth_plugins();
    $choices = array();
    foreach ($plugins as $plugin) 
    {
        $objplugin = get_auth_plugin($plugin);
        // If the plugin can not be manually set skip it.
        if (!$objplugin->can_be_manually_set()) 
        {
            continue;
        }
        $choices[$plugin] = get_string('pluginname', "auth_{$plugin}");
    }

    return $choices;
}

/**
 * Returns list of roles that are assignable in courses
 * @return array
 */
function us_allowed_roles() 
{
    // let's cheat a bit, frontpage is guaranteed to exist and has the same list of roles ;-)
    $roles = get_assignable_roles(context_course::instance(SITEID), ROLENAME_ORIGINALANDSHORT);
    return array_reverse($roles, true);
}

/**
 * Returns mapping of all roles using short role name as index.
 * @return array
 */
function us_allowed_roles_cache() 
{
    $allowedroles = get_assignable_roles(context_course::instance(SITEID), ROLENAME_SHORT);
    foreach ($allowedroles as $rid=>$rname) 
    {
        $rolecache[$rid] = new stdClass();
        $rolecache[$rid]->id   = $rid;
        $rolecache[$rid]->name = $rname;
        if (!is_numeric($rname)) 
        { // only non-numeric shortnames are supported!!!
            $rolecache[$rname] = new stdClass();
            $rolecache[$rname]->id   = $rid;
            $rolecache[$rname]->name = $rname;
        }
    }
    return $rolecache;
}

/**
 * Pre process custom profile data, and update it with corrected value
 *
 * @param stdClass $data user profile data
 * @return stdClass pre-processed custom profile data
 */
function us_pre_process_custom_profile_data($data) 
{
    global $CFG, $DB;
    // find custom profile fields and check if data needs to converted.
    foreach ($data as $key => $value) 
    {
        if (preg_match('/^profile_field_/', $key)) 
        {
            $shortname = str_replace('profile_field_', '', $key);
            if ($fields = $DB->get_records('user_info_field', array('shortname' => $shortname))) 
            {
                foreach ($fields as $field) 
                {
                    require_once($CFG->dirroot.'/user/profile/field/'.$field->datatype.'/field.class.php');
                    $newfield = 'profile_field_'.$field->datatype;
                    $formfield = new $newfield($field->id, $data->id);
                    if (method_exists($formfield, 'convert_external_data')) 
                    {
                        $data->$key = $formfield->convert_external_data($value);
                    }
                }
            }
        }
    }
    return $data;
}

/**
 * Checks if data provided for custom fields is correct
 * Currently checking for custom profile field or type menu
 *
 * @param array $data user profile data
 * @return bool true if no error else false
 */
function us_check_custom_profile_data(&$data)
{
    global $CFG, $DB;
    $noerror = true;

    // find custom profile fields and check if data needs to converted.
    foreach ($data as $key => $value) 
    {
        if (preg_match('/^profile_field_/', $key)) 
        {
            $shortname = str_replace('profile_field_', '', $key);
            if ($fields = $DB->get_records('user_info_field', array('shortname' => $shortname))) 
            {
                foreach ($fields as $field) 
                {
                    require_once($CFG->dirroot.'/user/profile/field/'.$field->datatype.'/field.class.php');
                    $newfield = 'profile_field_'.$field->datatype;
                    $formfield = new $newfield($field->id, 0);
                    if (method_exists($formfield, 'convert_external_data') &&
                            is_null($formfield->convert_external_data($value))) 
                    {
                        $data['status'][] = get_string('invaliduserfield', 'error', $shortname);
                        $noerror = false;
                    }
                }
            }
        }
    }
    return $noerror;
}

/**
 * Convert the the uploaded csv data to a moodle acceptable format
 */

function us_convert_scorm_data_to_moodle(&$content, $encoding = 'utf-8', $delimiter_name, $enclosure = '"' )
{
	global $CFG, $DB;
	
	$content = core_text::convert($content, $encoding, 'utf-8');
    // remove Unicode BOM from first line
    $content = core_text::trim_utf8_bom($content);
    // Fix mac/dos newlines
    $content = preg_replace('!\r\n?!', "\n", $content);
    // Remove any spaces or new lines at the end of the file.
    if ($delimiter_name == 'tab') 
    {
        // trim() by default removes tabs from the end of content which is undesirable in a tab separated file.
        $content = trim($content, chr(0x20) . chr(0x0A) . chr(0x0D) . chr(0x00) . chr(0x0B));
    }
    else 
    {
        $content = trim($content);
    }

    $csv_delimiter = csv_import_reader::get_delimiter($delimiter_name);
    $tempfile = tempnam(make_temp_directory('/csvimport'), 'tmp');
    if (!$fp = fopen($tempfile, 'w+b')) 
    {
    	$this->_error = get_string('cannotsavedata', 'error');
    	@unlink($tempfile);
    	return false;
    }
    
    fwrite($fp, $content);
    fseek($fp, 0);
    // Create an array to store the imported data for error checking.
    $columns = array();
    // str_getcsv doesn't iterate through the csv data properly. It has
    // problems with line returns.
    while ($fgetdata = fgetcsv($fp, 0, $csv_delimiter, $enclosure)) 
    {
    	// Check to see if we have an empty line.
    	if (count($fgetdata) == 1) 
    	{
    		if ($fgetdata[0] !== null) 
    		{
    			// The element has data. Add it to the array.
    			$columns[] = $fgetdata;
    		}
    	} 
    	else 
    	{
    		$columns[] = array(
    				$fgetdata[4],	// firstname
    				$fgetdata[5], 	// lastname
    				$fgetdata[3], 	// username
    				$fgetdata[3], 	// email
    				$fgetdata[3], 	// password
    				$fgetdata[19], 	// userid
    				$fgetdata[0], 	// avtivity name
    				/*
    				7 => array( // course data
    						$fgetdata[20],	// attempt date
    						$fgetdata[21],	// score
    						$fgetdata[22],	// status
    						$fgetdata[23],	// slides viewed
    						$fgetdata[25],	// duration
    				)
    				*/
    				
    		);
    	}
    }
    
    // reprocess column label
    $intColCount = count($columns[0]);
    $intRowCount = count($columns);
    
    for($intCtr = 0; $intCtr <= $intColCount; ++$intCtr )
    {
    	switch ($intCtr)
    	{
    		case MDL_FLD_FIRSTNAME:
    			$columns[0][$intCtr] = 'firstname';
    			break;
    		case MDL_FLD_LASTNAME:
    			$columns[0][$intCtr] = 'lastname';
    			break;
    		case MDL_FLD_USERNAME:
    			$columns[0][$intCtr] = 'username';
    			break;
    		case MDL_FLD_EMAIL:
    			$columns[0][$intCtr] = 'email';
    			break;
    		case MDL_FLD_PASSWORD:
    			$columns[0][$intCtr] = 'password';
    			break;
    		case MDL_FLD_IDNUMBER:
    			$columns[0][$intCtr] = 'idnumber';
    			break;
    		case MDL_FLD_COURSE1:
    			$columns[0][$intCtr] = 'course1';
    			break;
    		default:
    			break;
    	}
    }
    
   // reprocess data
   $intPointer = 0;
   foreach ($columns as $arrData)
   {
   		// we skip the labels
   		if($intPointer == 0)
   		{
   			++$intPointer;
   			continue;
   		}
   		// Check if user exists
   		$result = $DB->get_record('user', array('email' => $arrData[MDL_FLD_EMAIL]));
   		if( empty($result) )
   		{
   			// if not let's set a default password
   			$columns[$intPointer][MDL_FLD_PASSWORD] = DEFAULT_PASSWORD;
   		}
   		
   		// Get the courseid by scorm title
   		$objScorm = $DB->get_record('scorm', array('name' => $arrData[MDL_FLD_COURSE1]));
   		$objCourse = $DB->get_record('course', array('id' => $objScorm->course));
   		
   		$columns[$intPointer][MDL_FLD_COURSE1] = $objCourse->shortname;
   		
   		++$intPointer;   		
   }
   
   // reconstruct the csv
   $strCsvFile = '';
   foreach ($columns as $arrData)
   {
   		$strCsvFile .= '"' . $arrData[MDL_FLD_FIRSTNAME] . '","' . 
   							 $arrData[MDL_FLD_LASTNAME] . '","' . 
   		                     $arrData[MDL_FLD_USERNAME] . '","' . 
   		                     $arrData[MDL_FLD_EMAIL] . '","' . 
   		                     $arrData[MDL_FLD_PASSWORD] . '","' . 
   		                     $arrData[MDL_FLD_IDNUMBER] . '","' . 
   		                     $arrData[MDL_FLD_COURSE1] . '" \\r\\n';
   }
   
   // cleanup
   unlink($tempfile);
   return $strCsvFile;
}
