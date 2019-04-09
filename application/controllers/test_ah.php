<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

ini_set("display_startup_errors", 1);
ini_set("display_errors", 1);
error_reporting(E_ALL);

/**
 * Test AthenaHealth API Controller
 * @author Jeff Walters <jjwdesign@gmail.com>
 * See config/ah.php configuration options
 * 
 */
class Test_ah extends CI_Controller {
    
    function __construct() {
        
        parent::__construct();
        
        $this->controller = $this->router->class;
        $this->method = $this->router->method;
        
        $this->load->helper('url');
        
        $this->load->model('ah_model');
        $this->ah_model->log_requests = true;
        
        // Default Settings for Tests
        // Adjust these as you work thru the steps below!
        $this->practiceid = $this->ah_model->getPracticeid(); // 195900
        $this->departmentid = 1; // Rome Office
        $this->providerid = 86; // Terry Ahmad, MD
        $this->patientid = 35749; // James-* Testoftenson
        $this->appointmenttypeid = 82; // Any 15
        $this->appointmentid = 999163;
        
        if (!in_array($this->method, array('test', 'hmac'))) {
            echo '<!doctype html><html lang="en-US"><head><meta charset="utf-8"><title>Tests for Athena Health API</title>'
                . '<meta name="viewport" content="width=device-width, initial-scale=1.0"></head><body><pre>';
        }
    }
    
    public function index() {
        
        echo '</pre>';
        $methods = get_class_methods('Test_ah');
        foreach ($methods as $method) {
            if (substr($method, 0, 1) != '_' && $method != 'get_instance') {
                echo '<a href="' . site_url('test_ah/' . $method) .'">' . $method . '</a><br>';
            }
        }
        echo '<br><br><br><br><br><br><br><br><br><br><br><br><br>';
    }

    /**************************************************************************/
    
    public function quickstart_step_1_available_practices($practiceid = '1') {
        
        if (empty($practiceid)) $practiceid = $this->practiceid;
        $res = $this->ah_model->get('/practiceinfo'); // The practice ID is added by the ah_model class.
        print_r($res);
        echo "\n\nDone...\n\n";
    }
    
    public function quickstart_step_2_get_departments() {
        
        $res = $this->ah_model->get('/departments');
        print_r($res);
        echo "\n\n";
        foreach ($res->departments as $department) {
            echo $department->departmentid.',';
        }
        echo "\n\nDone...\n\n";
    }
    
    public function quickstart_step_2a_find_providers_at_department($departmentid = '') {

        if (empty($departmentid)) $departmentid = $this->departmentid;
        $params = array(
            'departmentid' => $departmentid,
            'providertype' => 'MD',
            'showallproviderids' => 'true',
            'showusualdepartmentguessthreshold' => '.7' // see 'usualdepartmentid'
        );
        $res = $this->ah_model->get('/providers', $params);
        print_r($res);
        foreach ($res->providers as $provider) {
            echo $provider->providerid.',';
        }
        echo "\n\nDone...\n\n";
    }
    
    public function quickstart_step_3_create_a_patient() {
        
        if (empty($departmentid)) $departmentid = $this->departmentid;
        if (empty($providerid)) $providerid = $this->providerid;
        
        $params = array(
            'departmentid' => $departmentid,
            'primaryproviderid' => $providerid,
            'address1' => '1234 Happy Street',
            'address2' => 'Suite '.date('U'),
            'city' => 'Orlando',
            'state' => 'FL',
            'zip' => '32829',
            'dob' => date('m/d').'/1970',
            'email' => 'test@test.com',
            'firstname' => 'James-'.substr(uniqid(rand()),0,5), // Done to make sure it's unique.
            'lastname' => 'Testoftenson',
            'homephone' => '4078901234',
            'mobilephone' => 'declined',
            'sex' => 'M',
            'race' => '2106-3',
            'ethnicitycode' => '2186-5',
            'language6392code' => 'eng',
            'status' => 'active'
        );
        print_r($params);
        $res = $this->ah_model->post('/patients', $params);
        print_r($res); // patientid = "35749"
        echo "\n\nDone...\n\n";
    }
    
    public function quickstart_step_3_modify_patient_demog() {
        
        if (empty($departmentid)) $departmentid = $this->departmentid;
        if (empty($providerid)) $providerid = $this->providerid;
        if (empty($patientid)) $patientid = $this->patientid;
        
        $params = array(
            'address1' => '12345 Happy Street',
            'address2' => 'Suite '.date('U'),
            'middlename' => 'Arnold',
            'patientid' => $patientid,
            'sex' => 'M',
            'ssn' => '595112222',
            'mobilephone' => '4078901235'
        );
        print_r($params);
        $res = $this->ah_model->put('/patients/' . $patientid, $params);
        print_r($res);
        echo "\n\nDone...\n\n";
    }
    
    public function quickstart_step_4_get_patient_just_created($patientid = '') {
        
        if (empty($patientid)) $patientid = $this->patientid;
        $res = $this->ah_model->get('/patients/'.$patientid);
        print_r($res);
        echo "\n\nDone...\n\n";
    }

    public function quickstart_step_5_record_patient_has_carpal_tunnel_syndrome($patientid = '') {
        
        if (empty($departmentid)) $departmentid = $this->departmentid;
        if (empty($patientid)) $patientid = $this->patientid;
        $params = array(
            'departmentid' => $departmentid,
            'laterality' => 'LEFT',
            'note' => 'Carpal Tunnel Syndrome',
            'snomedcode' => '57406009',
            'startdate' => date('m/d/Y'),
            'status' => 'CHRONIC'
        );
        $res = $this->ah_model->post('/chart/'.$patientid.'/problems', $params);
        print_r($res);
        echo "\n\nDone...\n\n";
    }
    
    public function quickstart_step_5a_record_patient_has_low_back_pain($patientid = '') {
        
        if (empty($departmentid)) $departmentid = $this->departmentid;
        if (empty($patientid)) $patientid = $this->patientid;
        $params = array(
            'departmentid' => $departmentid,
            'laterality' => 'LEFT',
            'note' => 'Low back pain',
            'snomedcode' => '279039007',
            'startdate' => date('m/d/Y')
        );
        $res = $this->ah_model->post('/chart/'.$patientid.'/problems', $params);
        print_r($res);
        echo "\n\nDone...\n\n";
    }
    
    public function quickstart_step_6_verify_patient_problem_was_created($patientid = '') {
        
        if (empty($departmentid)) $departmentid = $this->departmentid;
        if (empty($patientid)) $patientid = $this->patientid;
        $params = array(
            'departmentid' => $departmentid,
        );
        $res = $this->ah_model->get('/chart/'.$patientid.'/problems', $params);
        print_r($res);
        echo "\n\nDone...\n\n";
    }
    
    public function quickstart_step_7a_patient_appointment_reasons($providerid = '') {
        
        if (empty($departmentid)) $departmentid = $this->departmentid;
        if (empty($providerid)) $providerid = $this->providerid;
        $params = array(
            'departmentid' => $departmentid,
            'providerid' => $providerid
        );
        $res = $this->ah_model->get('/patientappointmentreasons', $params); // /newpatient 
        print_r($res);
        echo "\n\nDone...\n\n";
        
    }
    
    public function quickstart_step_7b_find_booked_appointments() {
        
        if (empty($departmentid)) $departmentid = $this->departmentid;
        if (empty($patientid)) $patientid = $this->patientid;
        if (empty($providerid)) $providerid = $this->providerid;
        
        $dt_start = new DateTime();
        $dt_end = clone($dt_start);
        //$dt_start->modify('-1 day');
        $dt_end->modify('+1 day');
        $start_format = $dt_start->format('m/d/Y');
        $end_format = $dt_end->format('m/d/Y');
        
        echo "\n\nFrom: ".$start_format." to ".$end_format.".\n\n";
        
        $params = array(
            'departmentid' => $departmentid,
            'appointmenttypeid' => '', // See Athena Health API for "appointment reason ID"
            'startdate' => $start_format,
            'enddate' => $end_format,
            'providerid' => $providerid,
            'ignorerestrictions' => 'true',
            'showclaimdetail' => 'true',
            'showcopay' => 'true',
            'showinsurance' => 'true',
            'showpatientdetail' => 'true',
            'showremindercalldetail' => 'true',
        );
        print_r($params);
        echo "\n\n";
        $res = $this->ah_model->get('/appointments/booked', $params);
        print_r($res);
        echo "\n\nDone...\n\n";
    }
    
    public function quickstart_step_7c_appointment_types_for_practice() {
        
        if (empty($departmentid)) $departmentid = $this->departmentid;
        $params = array(
            'departmentid' => $departmentid,
        );
        $res = $this->ah_model->get('/appointmenttypes', $params);
        print_r($res);
    }
    
    public function quickstart_step_7_find_open_appointment() {
        
        if (empty($departmentid)) $departmentid = $this->departmentid;
        if (empty($patientid)) $patientid = $this->patientid;
        if (empty($providerid)) $providerid = $this->providerid;
        
        $dt_start = new DateTime();
        $dt_end = clone($dt_start);
        //$dt_start->modify('-1 day');
        //$dt_end->modify('+1 day');
        $start_format = $dt_start->format('m/d/Y');
        $end_format = $dt_end->format('m/d/Y');
        
        echo "\n\nFrom: ".$start_format." to ".$end_format.".\n\n";
        
        $params = array(
            'departmentid' => $departmentid,
            'startdate' => $start_format,
            'enddate' => $end_format,
            'providerid' => $providerid,
            'reasonid' => '-1',
            'appointmenttypeid' => '', // See Athena Health API for "appointment reason ID"
            'ignoreschedulablepermission' => 'true',
            'bypassscheduletimechecks' => 'true',
            'showfrozenslots' => 'true'
        );
        print_r($params);
        $res = $this->ah_model->get('/appointments/open', $params);
        print_r($res);
        echo "\n\nDone...\n\n";
    }
    
    public function quickstart_step_8_book_appointment($patientid = '', $appointmentid = '') {
        
        if (empty($departmentid)) $departmentid = $this->departmentid;
        if (empty($patientid)) $patientid = $this->patientid;
        if (empty($providerid)) $providerid = $this->providerid;
        if (empty($appointmentid)) $appointmentid = $this->appointmentid;
        
        $params = array(
            'departmentid' => $departmentid,
            'appointmenttypeid' => $this->appointmenttypeid,
            'patientid' => $patientid,
            'providerid' => $providerid,
            'bookingnote' => 'JJW',
            'donotsendconfirmationemail' => 'true',
            'ignoreschedulablepermission' => 'true'
        );
        print_r($params);
        
        $res = $this->ah_model->put('/appointments/'.$appointmentid, $params);
        print_r($res);
    }
    
    public function quickstart_step_9_verify_appointment($appointmentid = '') {
        
        if (empty($appointmentid)) $appointmentid = $this->appointmentid;
        
        $res = $this->ah_model->get('/appointments/'.$appointmentid);
        print_r($res);
    }

}

