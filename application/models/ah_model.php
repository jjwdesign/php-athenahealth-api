<?php
defined('BASEPATH') OR exit('No direct script access allowed');


// Include Httpful Class (Rest Client)
include_once(APPPATH . 'libraries/httpful.phar');

use Httpful\Request;

/*
 * Athena Health Model
 * @author Jeff Walters <jjwdesign@gmail.com>
 * See config/ah.php configuration options
 * 
 */

class Ah_model extends CI_Model
{

    private $version;
    private $key;
    private $secret;
    private $practiceid;
    private $baseurl;
    private $authurl;
    
    private $ah_options;
    private $options;
    private $option_name;
    
    private $token;
    private $token_type;
    private $token_expires_in = 3540; // Default ~ 3600
    private $token_cache_key;
    private $refresh_token;
    private $cache_prefix = 'ah';
    
    public $result = null;
    public $log_requests = false;
    public $log_requests_to_db = false;
    public $response = null;
    public $error = null;
    public $detailedmessage = null;

    public function __construct()
    {
        // CI_Model
        parent::__construct();

        // Cache Data Driver
        $this->load->driver('cache', array('adapter' => 'file', 'backup' => 'dummy'));

        // Load Athena Health config (ah.php)
        $this->config->load('ah');
        $this->ah_options = $this->config->item('ah_options');

        // Call init, if no token set and default config option set
        if (!isset($this->token) && isset($this->ah_options['default'])) {
            log_message('debug', __METHOD__ . ' - ' . __LINE__ . ': set default option');
            $this->init('default');
        } else {
            log_message('debug', __METHOD__ . ' - ' . __LINE__ . ': default option not set');
        }
    }

    public function setConfig($option_name = '')
    {
        if (!empty($option_name) && isset($this->ah_options[$option_name])) {
            $this->init($option_name);
        } else {
            log_message('error', __METHOD__ . ' - ' . __LINE__ .
                ': configuration option does not exist: ' . $option_name);
            die('Error: Athena Health model configuration option "' .
                $option_name . '" does not exist.');
        }
    }

    private function init($option_name)
    {
        $this->options = $this->ah_options[$option_name];
        $this->option_name = $option_name;

        $this->version = $this->options['version'];
        $this->key = $this->options['key'];
        $this->secret = $this->options['secret'];
        $this->practiceid = $this->options['practiceid'];
        $this->baseurl = 'https://api.athenahealth.com/' . $this->version;

        $auth_prefixes = array(
            'v1' => 'oauth/token',
            'preview1' => 'oauthpreview/token',
            'openpreview1' => 'ouathopenpreview/token'
        );
        $this->authurl = 'https://api.athenahealth.com/' . $auth_prefixes[$this->version];

        // Define API Token (or Authenticate)
        $this->token_cache_key = $this->cache_prefix . '_' . $this->version . '_' .
            $this->practiceid . '_' . $this->key . '_token';
        $token = $this->cache->get($this->token_cache_key);
        if (!empty($token)) {
            $this->token = $token;
        } else {
            // Authenticate, get new token and save it to cache
            $this->authenticate();
            $this->cache->save(
                $this->token_cache_key,
                $this->token,
                floor((int) $this->token_expires_in * 0.9)
            );
        }
    }

    private function authenticate($attempt_number = 1)
    {
        $this->response = null;
        $this->token = null;
        $this->token_type = null;
        $this->token_expires_in = null;
        $this->refresh_token = null;

        $this->result = \Httpful\Request::post($this->authurl)
            ->addHeaders(array('Content-type' => 'application/x-www-form-urlencoded'))
            ->body(http_build_query(array('grant_type' => 'client_credentials')))
            ->authenticateWith($this->key, $this->secret)
            ->send();
        $this->logResponseError($this->result);

        $this->response = $this->result->body;

        if (isset($this->result->body->access_token)) {
            $this->token = $this->result->body->access_token; // ex: qhvbkzkmdursrc1cs7zjmvjw
            $this->token_type = $this->result->body->token_type; // 'bearer'
            $this->token_expires_in = $this->result->body->expires_in; // See token cache above
            $this->refresh_token = $this->result->body->access_token; // Not used; 3-legged OAuth
        } elseif (!isset($this->result->body->access_token) && $attempt_number <= 3) {
            sleep(5);
            $token = $this->cache->get($this->token_cache_key);
            if (!empty($token)) {
                $this->token = $token;
            } else {
                $attempt_number++;
                $this->authenticate($attempt_number);
            }
        }
        if (empty($this->token)) {
            log_message('error', __METHOD__ . ' - ' . __LINE__ .
                ': Token not set: ' . print_r($this->result->body, true));
            die('Error: Athena Health API Athentication Failure. '
                . 'Athena Health model token not set. Response: ' .
                print_r($this->result->body, true));
        }
    }

    /**
     * Perform an HTTP GET request and return response object.
     *
     * @param string $url the path (URI) of the resource
     * @param array $parameters the request parameters
     * @param array $headers the request headers
     */
    public function get($url, $parameters = array(), $headers = array())
    {
        # Join up a URL and add the parameters
        $new_url = $this->urlJoin($this->baseurl, $this->practiceid, $url);
        if (!empty($parameters)) {
            $new_url .= '?' . http_build_query($parameters);
        }

        $this->result = \Httpful\Request::get($new_url)
            ->addHeaders($headers)
            ->addHeaders(array('Authorization' => 'Bearer ' . $this->token))
            ->send();
        $this->logRequest('get', $url, $parameters, $headers);
        $this->logResponseError($this->result);
        $this->response = $this->result->body;

        return $this->result->body;
    }

    /**
     * Perform an HTTP POST request and return response object.
     *
     * @param string $url the path (URI) of the resource
     * @param array $parameters the request parameters
     * @param array $headers the request headers
     */
    public function post($url, $parameters = array(), $headers = array())
    {
        # Join up a URL
        $new_url = $this->urlJoin($this->baseurl, $this->practiceid, $url);

        $this->result = \Httpful\Request::post($new_url)
            ->addHeaders($headers)
            ->addHeaders(array('Authorization' => 'Bearer ' . $this->token))
            ->sendsType(\Httpful\Mime::FORM)
            ->body(http_build_query($parameters))
            ->send();
        $this->logRequest('post', $url, $parameters, $headers);
        $this->logResponseError($this->result);
        $this->response = $this->result->body;

        return $this->result->body;
    }

    /**
     * Perform an HTTP PUT request and return response object.
     *
     * @param string $url the path (URI) of the resource
     * @param array $parameters the request parameters
     * @param array $headers the request headers
     */
    public function put($url, $parameters = array(), $headers = array())
    {
        # Join up a URL
        $new_url = $this->urlJoin($this->baseurl, $this->practiceid, $url);

        $this->result = \Httpful\Request::put($new_url)
            ->addHeaders($headers)
            ->addHeaders(array('Authorization' => 'Bearer ' . $this->token))
            ->sendsType(\Httpful\Mime::FORM)
            ->body(http_build_query($parameters))
            ->send();
        $this->logRequest('put', $url, $parameters, $headers);
        $this->logResponseError($this->result);
        $this->response = $this->result->body;

        return $this->result->body;
    }

    /**
     * Perform an HTTP DELETE request and return response object.
     *
     * @param string $url the path (URI) of the resource
     * @param array $parameters the request parameters
     * @param array $headers the request headers
     */
    public function delete($url, $parameters = array(), $headers = array())
    {
        # Join up a URL and add the parameters
        $new_url = $this->urlJoin($this->baseurl, $this->practiceid, $url);
        if (!empty($parameters)) {
            $new_url .= '?' . http_build_query($parameters);
        }

        $this->result = \Httpful\Request::delete($new_url)
            ->addHeaders($headers)
            ->addHeaders(array('Authorization' => 'Bearer ' . $this->token))
            ->send();
        $this->logRequest('delete', $url, $parameters, $headers);
        $this->logResponseError($this->result);
        $this->response = $this->result->body;

        return $this->result->body;
    }

    /**
     * Perform an HTTP DELETE request and return response object.
     *
     * @param string $url the path (URI) of the resource
     * @param array $parameters the request parameters
     * @param array $headers the request headers
     */
    private function logRequest($type, $url, $parameters = array(), $headers = array())
    {
        if ($this->log_requests) {
            log_message('error', __METHOD__ . ' - ' . __LINE__ . ': ' . $type . ': ' . $url);
            if (!empty($parameters)) {
                log_message('error', __METHOD__ . ' - ' . __LINE__ .
                    ': $parameters: ' . print_r($parameters, true));
            }
            if (!empty($headers)) {
                log_message('error', __METHOD__ . ' - ' . __LINE__ .
                    ': $headers: ' . print_r($headers, true));
            }
        }
        
        if ($this->log_requests_to_db) {
            
            $this->load->model('ah_request_log_model');
            
            $data = (isset($this->result->meta_data))
                ? $data = $this->result->meta_data
                : array();
            
            $data['ah_option_name'] = $this->getOptionName();
            $data['practicid'] = $this->getPracticeid();
            $data['type'] = $type;
            $data['uri'] = $url;
            $data['parameters'] = json_encode($parameters);
            $data['headers'] = json_encode($headers);
            if (isset($data['certinfo']) && is_array($data['certinfo'])) {
                $data['certinfo'] = json_encode($data['certinfo']);
            }
            $data['code'] = isset($this->result->code)
                ? $this->result->code
                : null;
            $data['charset'] = isset($this->result->charset)
                ? $this->result->charset
                : null;
            
            $headers = $this->result->headers->toArray();
            $data['x_mashery_message_id'] = (isset($headers['x-mashery-message-id']))
                ? $headers['x-mashery-message-id']
                : null;
            $data['x_mashery_responder'] = (isset($headers['x-mashery-responder']))
                ? $headers['x-mashery-responder']
                : null;
            $data['x_mashery_error_code'] = (isset($headers['x-mashery-error-code']))
                ? $headers['x-mashery-error-code']
                : null;
            $data['x_error_detail_header'] = (isset($headers['x-error-code-detail_header'])) 
                ? $headers['x-error-code-detail_header']
                : null;
            
            $data['error'] = (isset($this->result->error))
                ? $this->result->error
                : null;
            $data['body_error'] = (isset($this->result->body->error))
                ? $this->result->body->error
                : null;
            $data['body_detailmessage'] = (isset($this->result->body->detailedmessage))
                ? $this->result->body->detailedmessage
                : null;
            
            // TODO
            // Insert $data into your database
        }
    }

    /**
     * Determine if there is a response error and log message
     */
    private function logResponseError()
    {
        if (isset($this->result->error)) {
            log_message('error', __METHOD__ . ' - ' . __LINE__ .
                ': Athena Health Response error: ' .
                print_r($this->result->error, true));
            $this->error = $this->result->error;
        }
        if (isset($this->result->body->error)) {
            log_message('error', __METHOD__ . ' - ' . __LINE__ .
                ': Athena Health Response Body error: ' .
                print_r($this->result->body->error, true));
            $this->error = $this->result->body->error;
        }
        if (isset($this->result->body->detailedmessage)) {
            log_message('error', __METHOD__ . ' - ' . __LINE__ .
                ': Athena Health Response Body detailedmessage: ' .
                print_r($this->result->body->detailedmessage, true));
            $this->detailedmessage = $this->result->body->detailedmessage;
        }
        if (isset($this->result->error) || isset($this->result->body->error)) {
            log_message('error', __METHOD__ . ' - ' . __LINE__ . ': Full Response: ' .
                print_r($this->response, true));
            //print_r($this->result);
        }
    }

    /**
     * This method joins together parts of a URL to make a valid one.
     * Trims existing slashes from arguments and re-joins them with slashes.
     * @param string $arg,... any number of strings to join
     */
    private function urlJoin()
    {
        return join('/', array_map(function ($p) {
                return trim($p, '/');
        }, func_get_args()));
    }

    /**
     * Returns the current Athena Health API option name.
     */
    public function getOptionName()
    {
        return $this->option_name;
    }

    /**
     * Returns the current Athena Health API key.
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Returns the current Athena Health API access_token.
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Returns the current Athena Health API version.
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Returns the current Athena Health API practiceid.
     */
    public function getPracticeid()
    {
        return $this->practiceid;
    }

    /**
     * cast object variables
     *
     * (int), (integer) - cast to integer.
     * (bool), (boolean) - cast to boolean.
     * (float), (double), (real) - cast to float.
     * (string) - cast to string.
     * (array) - cast to array.
     * (object) - cast to object.
     *
     * @param object $obj
     * @param array $variable_types as var => type
     * @return object $obj
     */
    public function cast_object_vars($obj, $variable_types)
    {

        if (is_object($obj) && !empty($variable_types)) {
            foreach ($variable_types as $var => $type) {
                if (isset($obj->{$var})) {
                    $type = strtolower($type);
                    if ($type === 'int') {
                        $obj->{$var} = (int) $obj->{$var};
                    } elseif ($type === 'bool') {
                        $val = $obj->{$var};
                        if (is_string($val) && strtoupper($val) === 'TRUE') {
                            $obj->{$var} = true;
                        } elseif (is_string($val) && strtoupper($val) === 'FALSE') {
                            $obj->{$var} = false;
                        } else {
                            $obj->{$var} = (bool) $obj->{$var};
                        }
                    } elseif ($type === 'float') {
                        $obj->{$var} = (float) $obj->{$var};
                    } elseif ($type === 'string') {
                        $obj->{$var} = (string) $obj->{$var};
                    } elseif ($type === 'array') {
                        $obj->{$var} = (array) $obj->{$var};
                    } elseif ($type === 'object') {
                        $obj->{$var} = (object) $obj->{$var};
                    } elseif ($type === 'unset') {
                        $obj->{$var} = (unset) $obj->{$var};
                    }
                }
            }
        }
        return $obj;
    }
}
