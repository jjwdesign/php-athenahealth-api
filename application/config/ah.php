<?php
/*
| -------------------------------------------------------------------
|  Athena Health - API Options
| -------------------------------------------------------------------
| 
| See models/ah_model.php for configuration parameters
| Base URL: https://api.athenahealth.com/
| Visit https://developer.athenahealth.com/io-docs for more information.
|
*/

$config['ah_options'] = array();

// Testing (Dev Environment)
$config['ah_options']['testing'] = array(
    'name' => 'testing',
    'version' => 'preview1',
    'username' => 'jjwdesign',
    'application' => 'Testing',
    'key' => 'bktkqusz64jxmzdjp3aagx8g',
    'secret' => 'tj9zxtEpBjrgynA',
    'practiceid' => '195900' // AH Sandbox
);

/*
 * Set Default AH Options
 */
if (!isset($config['ah_options']['default'])) {
    $config['ah_options']['default'] = $config['ah_options']['testing'];
}
