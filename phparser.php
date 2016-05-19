<?php

define('HOST_SSL', TRUE);

class PHG {

	/** @var This is passed as array part in construct */
	protected $network_api_key = NULL;
	/** @var This is passed as array part in construct */
	protected $user_api_key = NULL;
	/** @var This is the defaul status for curl */
	protected $curl_status = 200;

	/** @var This is in case they want to specify an API call */
	public $api_url = '';
	/** @var How we want to handle the creation of jobs */
	public $job_processing = 'instant';
	/** @var Do we want to output the API call URLs to the screen */
	public $show_api_call = FALSE;
	/** @var Do we want to parse the json string into an object */
	public $decode_json = FALSE;

	public function __construct($arr_auth = NULL)
	{
		if( ! ini_get('date.timezone'))
		{
			date_default_timezone_set('UTC');
		}

		if ( ! defined('HOST_SSL'))
		{
			throw new Exception('{"status":"error","message":"You must define HOST_SSL as TRUE|FALSE."}');
		}

		if ( ! defined('API_HOST'))
		{
			throw new Exception('{"status":"error","message":"You must define API_HOST."}');
		}

		if ( ! defined('LOGGING_ENABLED'))
		{
			$this->logging_enabled = FALSE;
		}
		else
		{
			$this->logging_enabled = LOGGING_ENABLED;
		}

		if ($this->logging_enabled)
		{
			if ( ! is_writable(OUTPUT_LOG))
			{
				throw new Exception('{"status":"error","message":"Logging is enabled but the path \''.OUTPUT_LOG.'\' is not writable."}');
			}

			if ( ! defined('FILE_STORE'))
			{
				$this->output('The save location for files was not defined by "FILE_STORE". All files will save to "'.sys_get_temp_dir().'/"');
			}
		}

		if ( ! defined('FILE_STORE'))
		{
			$save_path = sys_get_temp_dir().'/';
			define('FILE_STORE', $save_path);
		}

		if ( ! is_null($arr_auth))
		{
			$this->root_url_set($arr_auth);
		}
		$this->start_time = microtime(true);
	}

	public function __destruct()
	{
		if ($this->logging_enabled)
		{
			if (is_writable(OUTPUT_LOG))
			{
				$end = round((microtime(true) - $this->start_time) / 60, 4);

				if (($time = $_SERVER['REQUEST_TIME']) == '')
				{
					$time = time();
				}

				$remote_addr = $this->remote_addr_get();
				$request_uri = $this->request_uri_get();

				$date = "[".date("d/M/Y:H:i:s O", $time)."]";
				$line = "{$date} PHGAPI {$remote_addr} __destruct() {$request_uri} 200\n";
				$line .= "{$date} PHGAPI \tEND Total Execution Time: {$end} Minutes\n";

				$file = OUTPUT_LOG.date('j-n-Y').'.log';

				file_put_contents($file, $line, FILE_APPEND);
			}
		}
	}

	/**
	 * You can manually specify auth for a call
	 * @param Array $arr_data the api credentials
	 */
	public function set_api_auth($arr_data)
	{
		$this->root_url_set($arr_data);
	}

	/**
	 * Get a network level list of all campaigns
	 * @return String json formatted api results
	 */
	public function campaign_get_list()
	{
		$this->api_url = $this->base_url.'/campaign';
		$object = $this->curl_get();

		return $this->output($object);
	}

	/**
	 * Get details for a specific campaign
	 * @param  String $str_campaign_id the campaign to look up
	 * @return String                  json formatted api result
	 */
	public function campaign_get($str_campaign_id = NULL)
	{
		$this->api_url = $this->base_url.'/campaign';
		if ( ! is_null($str_campaign_id))
		{
			$this->api_url .= '/'.$str_campaign_id;
		}

		$object = $this->curl_get();

		return $this->output($object);
	}

	/**
	 * Get the response of a job
	 * @param  String $str_job_id job id to look up
	 * @return String             json formatted api results
	 */
	public function job_get($str_job_id)
	{
		$this->api_url = $this->base_url.'/job/'.$str_job_id.'.json';
		$data = json_decode($this->curl_get());
		if (@intval($data->job->percentage_complete) === 100)
		{
			if (is_null($data->job->hypermedia->response))
			{
				sleep(10);
				return $this->job_get($str_job_id);
			} else {
				$this->api_url = $this->base_url.$data->job->hypermedia->response;
				$object = $this->curl_get();
				return $object;
			}
		}
		else
		{
			sleep(10);
			return $this->job_get($str_job_id);
		}
	}

	/**
	 * Get all of the publishers on the network
	 * @return String json formatted api results
	 */
	public function network_publisher_get($int_offset = NULL)
	{
		$this->api_url = $this->base_url.'/user/publisher';

		if ( ! is_null($int_offset))
		{
			$this->api_url .= '?limit=100&offset='.$int_offset;
		}
		$object = $this->curl_get();

		return $this->output($object);
	}

	/**
	 * Get details about a specific publisher
	 * @param  String $str_publisher_id the publisher to look up
	 * @return String                   json formatted api result
	 */
	public function publisher_get($str_publisher_id = NULL)
	{
		$this->api_url = $this->base_url.'/user/publisher';

		if ( ! is_null($str_publisher_id))
		{
			$this->api_url .= '/'.$str_publisher_id;
		}
		$object = $this->curl_get();

		return $this->output($object);
	}

	/**
	 * Get details for provided user
	 * @param  String $str_user_id the user to look up
	 * @return String                   json formatted api result
	 */
	public function user_get($str_user_id = NULL)
	{
		$this->api_url = $this->base_url.'/user/';

		if ( ! is_null($str_user_id))
		{
			$this->api_url .= $str_user_id;
		}
		$object = $this->curl_get();

		return $this->output($object);
	}

	/**
	 * Add ability to create manual log messages
	 * @param  String $str_message the message to add to log
	 */
	public function log($str_message)
	{
		$this->curl_status = 400;
		$this->log_entry_create('MANUAL ENTRY', $str_message);
		$this->curl_status = 200;
	}

	/**
	 * Get the status of the last cURL
	 * @return Integer the last curl status
	 */
	public function get_curl_status()
	{
		return $this->curl_status;
	}

//
// Protected Functions //
//

	/**
	 * Returns the URL being called
	 * @return String the url that will be called
	 */
	public function api_url_get()
	{
		return $this->api_url;
	}

	/**
	 * Makes a formatted HTTP curl get
	 * @return String          string of result data
	 */
	protected function curl_get()
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $this->api_url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_USERAGENT, 'PHGAPI Wrapper (+http://www.phgdeployment.com/)');
		$results = curl_exec($curl);
		$this->curl_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if ($this->show_api_call)
		{
			echo "\n{$this->api_url}\n";
		}

		return $results;
	}

	/**
	 * Makes a formatted HTTP curl post
	 * @param  Array $arr_arguments the arguments to post to the url
	 * @return String                string of result data
	 */
	protected function curl_post($arr_arguments)
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $this->api_url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_USERAGENT, 'PHGAPI Wrapper (+http://www.phgdeployment.com/)');
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($arr_arguments));
		$results = curl_exec($curl);
		$this->curl_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if ($this->show_api_call)
		{
			echo "\n{$this->api_url}\n";
		}

		return $results;
	}

	/**
	 * Will flilter data dynamically
	 * @param  String $arr_data      json formatted api results
	 * @param  String $str_reference the type of filter
	 * @param  String $str_key       the key to use for comparison
	 * @param  String $str_value     the data to compare against
	 * @return String                reformatted api results
	 */
	protected function filter_data($arr_data, $str_reference, $str_key, $str_value)
	{
		$array = json_decode($arr_data, TRUE);
		$filtered = array();
		foreach ($array[$str_reference.'s'] as $node)
		{
			if ($node[$str_reference][$str_key] == $str_value)
			{
				array_push($filtered, $node);
			}

		}
		$array['count'] = count($filtered);
		$array[$str_reference.'s'] = $filtered;

		return json_encode($array);
	}

	/**
	 * This will present the data that was generated
	 * @param  String $str_result the data that was generated
	 * @return Mixed             the data that was generated
	 */
	protected function output($str_result)
	{
		if ($this->logging_enabled)
		{
			$caller = $this->get_calling_function();
			$this->log_entry_create($caller, $str_result);
		}

		if ($this->decode_json)
		{
			return json_decode($str_result);
		}
		else
		{
			return $str_result;
		}
	}

	/**
	 * Will return the minimum fields required for a post event
	 * @param  Array $arr_post_data data to be posted if successful
	 * @param  String $str_check     the fields we will be looking for
	 * @return String                the JSON object of results
	 */
	protected function post_required($arr_post_data, $str_check)
	{
		$arr_keys = array_keys($arr_post_data);
		$arr_required = $this->get_required_fields ($str_check);
		$arr_diff = array_diff($arr_required, $arr_keys);

		if (count($arr_diff) > 0)
		{
			$error_output = array(
				'fields' => $arr_diff,
				'message' => 'Required fields missing.',
				'status' => 'error'
				);
			$object = json_encode($error_output);
		}
		else
		{
			$object = $this->curl_post($arr_post_data);
		}

		return $object;
	}

	/**
	 * Builds the neccesary base url for the chosen API
	 * @param  Array $arr_auth array of auth data
	 * @return String           the base url to use for API calls
	 */
	protected function root_url_set($arr_auth)
	{
		$this->base_url = (HOST_SSL ? 'https://' : 'http://');

		if (array_key_exists('network', $arr_auth))
		{
			$this->network_api_key = $arr_auth['network'];
			$this->base_url .= $arr_auth['network'];
			if (array_key_exists('user', $arr_auth))
			{
				$this->user_api_key = $arr_auth['user'];
				$this->base_url .= ':'.$arr_auth['user'];
			}
			$this->base_url .= '@'.API_HOST;
		}
		else
		{
			$this->base_url .= API_HOST;
		}
	}

	/**
	 * Will either save the csv output or output the JSON result
	 * @param  String $str_output json formatted api results
	 * @return String             json formatted api results
	 */
	protected function save_output($str_output, $str_filename = NULL)
	{
		if (substr($str_output, 0, 6) === 'job_id')
		{
			$rows = array_map('str_getcsv', explode("\n", $str_output));
			$job_id = $rows[1][0];

			if ($this->job_processing === 'instant')
			{
				$str_output = $this->job_get($job_id);
			}
			else
			{
				if ($this->job_processing === 'deferred')
				{
					return $this->output(json_encode(array("status" => "success", "job" => $job_id)));
				}
			}

		}

		if ($this->is_json($str_output))
		{
			return $this->output($str_output);
		}
		else
		{
			if (is_null($str_filename))
			{
				$filename = uniqid().'_report.csv';
			}
			else
			{
				$filename = $str_filename.'_'.uniqid().'_report.csv';
			}
			file_put_contents(FILE_STORE.$filename, $str_output);
			return $this->output(json_encode(array("status" => "success", "file" => FILE_STORE.$filename)));
		}

	}

	/**
	 * This will convert the argument arrau for PHG API
	 * @param  Array $arr_arguments the array of arguments
	 */
	protected function handle_argument_array($arr_arguments)
	{
		if (count($arr_arguments) > 0)
		{
			$i = 0;
			foreach($arr_arguments as $key => $value)
			{
				if (is_array($value))
				{
					foreach ($value as $val)
					{
						$i++;
						$this->api_url .= ($i == 1 ? '?' : '&');
						$this->api_url .= $key.'%5B%5D='.$val;
					}
				}
				else
				{
					$i++;
					$this->api_url .= ($i == 1 ? '?' : '&');
					$this->api_url .= $key.'='.$value;
				}
			}
		}
		else
		{
			$this->api_url .= '.'.$str_format;
		}
	}

//
// Private Functions //
//

	/**
	 * Allows tracing of calling function for logging
	 * @return String a verbose output containing calling function
	 */
	private function get_calling_function()
	{
		$caller = debug_backtrace();
		$caller = $caller[2];
		$r = '"'.$caller['function'].'()';
		if (isset($caller['class']))
		{
			$r .= ' in ' . $caller['class'];
		}

		if (isset($caller['object']))
		{
			$r .= ' (' . get_class($caller['object']) . ')';
		}

		$r .= '"';

		return $r;
	}

	/**
	 * Evaluates whether or not string is json
	 * @param  String  $str_data string to test
	 * @return Boolean           result of test
	 */
	private function is_json($str_data)
	{
		@json_decode($str_data);
		return (json_last_error() == JSON_ERROR_NONE);
	}

	/**
	 * This will add data to a file for logging
	 * @param  String $str_function the calling function
	 * @param  String $str_result   the data that is ready for output
	 * @return VOID
	 */
	private function log_entry_create($str_function, $str_result)
	{
		if (($time = $_SERVER['REQUEST_TIME']) == '')
		{
			$time = time();
		}

		$i = 0;

		$remote_addr = $this->remote_addr_get();
		$request_uri = $this->request_uri_get();

		$date = "[".date("d/M/Y:H:i:s O", $time)."]";
		$size = (strlen($str_result)*8);
		$line = "{$date} PHGAPI {$remote_addr} {$str_function} {$request_uri} {$this->curl_status} {$size}\n";

		if (substr($str_function, 0, 11) != "__construct" && $str_function != "MANUAL ENTRY")
		{
			$i++;
			$line .= "{$date} PHGAPI \t{$i}. {$this->api_url}\n";
		}

		if ($this->curl_status != 200)
		{
			$i++;
			$line .= "{$date} PHGAPI \t{$i}. {$str_result}\n";
		}

		if ($this->logging_enabled)
		{
			$file = OUTPUT_LOG.date('j-n-Y').'.log';
			file_put_contents($file, $line, FILE_APPEND);
		}
	}

	/**
	 * Will get or create a variable to add to log
	 * @return String the remote ip or cli name to add to log
	 */
	private function remote_addr_get()
	{
		if (array_key_exists('REMOTE_ADDR', $_SERVER))
		{
			if(($remote_addr = $_SERVER['REMOTE_ADDR']) == '')
			{
				$remote_addr = "-";
				if (php_sapi_name() === 'cli')
				{
					$remote_addr = "CLI";
				}

			}
		}
		else
		{
			$remote_addr = "CLI";
		}

		return $remote_addr;
	}

	/**
	 * Will get or create a variable to add to log
	 * @return String the uri or cwd of executing script
	 */
	private function request_uri_get()
	{
		if (array_key_exists('REQUEST_URI', $_SERVER))
		{
			if(($request_uri = $_SERVER['REQUEST_URI']) == '')
			{
				$request_uri = "-";
				if (php_sapi_name() === 'cli')
				{
					$argv = $GLOBALS['argv'];
					$request_uri = getcwd().'/'.$argv[0];
				}
			}
		}
		else
		{
			$argv = $GLOBALS['argv'];
			$request_uri = getcwd().'/'.$argv[0];
		}

		return $request_uri;
	}

}

class Reporting extends PHG {

	public function __construct($arr_auth = NULL)
	{
		parent::__construct($arr_auth);
	}

	/**
	 * Will pull a CSV export from the API
	 * @param  String $str_report_type the report type to pull
	 * @param  Array $arr_arguments   the url arguments
	 *                                click|conversion|commission|impression|ordervalue
	 * @return String                  csv formatted api results
	 */
	public function export($str_report_type, $arr_arguments)
	{
		$this->api_url = $this->base_url.'/reporting/export/export/'.$str_report_type.'.csv';
		$this->handle_argument_array($arr_arguments);
		$object = $this->curl_get();

		return $this->save_output($object);
	}

}
