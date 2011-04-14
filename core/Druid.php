<?php

class Druid
{
	const 
		VERSION = '0.5';
	
	const
		HTTP_100='Continue',
		HTTP_101='Switching Protocols',
		HTTP_200='OK',
		HTTP_201='Created',
		HTTP_202='Accepted',
		HTTP_203='Non-Authorative Information',
		HTTP_204='No Content',
		HTTP_205='Reset Content',
		HTTP_206='Partial Content',
		HTTP_300='Multiple Choices',
		HTTP_301='Moved Permanently',
		HTTP_302='Found',
		HTTP_303='See Other',
		HTTP_304='Not Modified',
		HTTP_305='Use Proxy',
		HTTP_306='Temporary Redirect',
		HTTP_400='Bad Request',
		HTTP_401='Unauthorized',
		HTTP_402='Payment Required',
		HTTP_403='Forbidden',
		HTTP_404='Not Found',
		HTTP_405='Method Not Allowed',
		HTTP_406='Not Acceptable',
		HTTP_407='Proxy Authentication Required',
		HTTP_408='Request Timeout',
		HTTP_409='Conflict',
		HTTP_410='Gone',
		HTTP_411='Length Required',
		HTTP_412='Precondition Failed',
		HTTP_413='Request Entity Too Large',
		HTTP_414='Request-URI Too Long',
		HTTP_415='Unsupported Media Type',
		HTTP_416='Requested Range Not Satisfiable',
		HTTP_417='Expectation Failed',
		HTTP_500='Internal Server Error',
		HTTP_501='Not Implemented',
		HTTP_502='Bad Gateway',
		HTTP_503='Service Unavailable',
		HTTP_504='Gateway Timeout',
		HTTP_505='HTTP Version Not Supported';
	
	private static $instance	 = NULL;
	private static $routing  	 = array();	
	private static $registry 	 = array();
	private static $request  	 = array();	
	public static  $headers  	 = array();
	private static $fatal_errors = array(
		E_PARSE, 
		E_ERROR, 
		E_USER_ERROR
	);
	public static $php_errors    = array(
		E_ERROR              => 'Fatal Error',
		E_USER_ERROR         => 'User Error',
		E_PARSE              => 'Parse Error',
		E_WARNING            => 'Warning',
		E_USER_WARNING       => 'User Warning',
		E_STRICT             => 'Strict',
		E_NOTICE             => 'Notice',
		E_RECOVERABLE_ERROR  => 'Recoverable Error',
	);
	
	/**
	 * Create and return the singleton instance of Druid
	 * 
	 * @access public
	 * @return Druid
	 */	
	public static function instance()
	{
		if(is_null(self::$instance))
		{
			$class = __CLASS__;
			
			self::$instance = new $class;
		}
		
		return self::$instance;
	}
	
	/**
	 * Set up the Druid instance
	 * 
	 * @access private
	 * @return void
	 */
	private function __construct()
	{	
		// Set the autoloader
		$this->set_auto_loader();
		
		// Handle all generic errors
		set_error_handler(array('Druid', 'druid_error_handler'));
		
		// Handle all generic exceptions
		set_exception_handler(array('Druid', 'druid_exception_handler'));
		
		// Handle all critical errors
		register_shutdown_function(array('Druid', 'druid_shutdown_handler'));
		
		self::$headers[] = 'Content-Type: text/html; charset=UTF-8';
		self::$headers[] = 'X-Powered-By: Druid v'. Druid::VERSION;
		
		if(ini_get('register_globals'))
		{
			$this->revert_register_globals();
		}
		
		self::set('magic_quotes', (bool)get_magic_quotes_gpc());

		$_GET    = Druid::sanitize($_GET);
		$_POST   = Druid::sanitize($_POST);
		$_COOKIE = Druid::sanitize($_COOKIE);
		
		$this->parse_request();
	}

	/**
	 * Sanitize the input for better security and consistency
	 * 
	 * @static
	 * @access private
	 * @param  mixed $value
	 * @return mixed
	 */	
	private static function sanitize($value)
	{
		if(is_array($value) OR is_object($value))
		{
			foreach ($value as $key => $val)
			{
				$value[$key] = self::sanitize($val);
			}
		}
		elseif(is_string($value))
		{
			if(self::get('magic_quotes') === TRUE)
			{
				$value = stripslashes($value);
			}

			if(strpos($value, "\r") !== FALSE)
			{
				$value = str_replace(array("\r\n", "\r"), "\n", $value);
			}
		}

		return $value;
	}
	
	/**
	 * Revert the effects of 'register globals'
	 * 
	 * @access private
	 * @return void
	 */
	private function revert_register_globals()
	{
		if(isset($_REQUEST['GLOBALS']) OR isset($_FILES['GLOBALS']))
		{
			exit(1); // Malicious attack detected, exit
		}
		
		$global_variables = array_diff(array_keys($GLOBALS), array(
			'_COOKIE',
			'_ENV',
			'_GET',
			'_FILES',
			'_POST',
			'_REQUEST',
			'_SERVER',
			'_SESSION',
			'GLOBALS',
		));

		foreach ($global_variables as $name)
		{
			unset($GLOBALS[$name]);
		}
	}

	/**
	 * Retrieve an item from the global instance registry
	 * 
	 * @access public
	 * @param  string $key
	 * @param  mixed $default
	 * @return mixed
	 */
	public static function get($key, $default = FALSE)
	{
		if(isset(self::$registry[$key]))
		{
			return self::$registry[$key];
		}
		else
		{
			return $default;
		}
	}
	
	/**
	 * Set an item to the global object registry
	 * 
	 * @static
	 * @access public
	 * @param  string $key
	 * @param  mixed $value
	 * @return void
	 */
	public static function set($key, $value)
	{
		self::$registry[$key] = $value;
		
		return;
	}
	
	/**
	 * Map a route to a specified controller
	 * 
	 * @static
	 * @access public
	 * @param  string $route
	 * @param  mixed $conroller
	 * @return void
	 */
	public static function map($route, $controller)
	{
		$method = 'GET';
		$route  = trim($route);
		
		if(strpos($route, chr(32)) !== FALSE)
		{
			$route  = explode(chr(32), $route);			
			$method = $route[0];
			$route  = $route[1];
		}
		
		self::$routing[$method][$route] = $controller;
		
		return;
	}
	
	/**
	 * Execute and process the current request
	 * 
	 * @static
	 * @access public
	 * @return void
	 */
	public function run()
	{
		self::set('start_time', time());
		
		$this->output_headers();
		
		ob_start();
		
		foreach(self::$routing[self::request_method()] as $route => $handler)
		{
			if(strpos($route, '%') !== FALSE)
			{
				preg_match_all($route, self::request_path(), $matches);

				if(count($matches[0]) != 0)
				{					
					foreach($matches as $name => $match)
					{
						if(is_string($name))
						{
							self::set('request.'.$name, $match[0]);
						}
					}
					
					if(is_callable($handler))
					{
						call_user_func_array($handler, array());			
						
						self::$request['mapped'] = TRUE;
						
						break;
					}
				}
			}
			else
			{
				if($route == self::request_path())
				{
					if(is_callable($handler))
					{
						call_user_func_array($handler, array());
						
						self::$request['mapped'] = TRUE;
						
						break;
					}
				}
			}
		}
		
		$output = ob_get_contents();
		
		ob_end_clean();
		
		self::set('end_time', time());
		
		if(!isset(self::$request['mapped']))
		{
			if(!headers_sent())
			{
				header("HTTP/1.0 404 Not Found");				
			}
		}
		else
		{	
			echo $output;
		}
	}
	
	/**
	 * Parse the current request object
	 * 
	 * @access private
	 * @return void
	 */
	private function parse_request()
	{	
		$domain = strtolower(
			trim(
				mb_substr(
					$_SERVER['SERVER_NAME'], 0, strpos($_SERVER['SERVER_NAME'], '.')
				)
			)
		);
		
		self::$request['is_subdomain'] = FALSE;
		self::$request['path'] 		   = strtolower($_SERVER['REQUEST_URI']);
		self::$request['method']  	   = strtoupper($_SERVER['REQUEST_METHOD']);	
		self::$request['remote_addr']  = $_SERVER['REMOTE_ADDR'];
		self::$request['time']  	   = $_SERVER['REQUEST_TIME'];
		
		return;
	}
	
	/**
	 * Utility method to return the current request path
	 *
	 * @static
	 * @access public
	 * @return string
	 */
	public static function request_path()
	{
		return self::$request['path'];
	}
	
	/**
	 * Utility method to return the remote address
	 *
	 * @static
	 * @access public
	 * @return string
	 */
	public static function request_remote_addr()
	{
		return self::$request['remote_addr'];
	}
	
	/**
	 * Utility method to return the current request method
	 *
	 * @static
	 * @access public
	 * @return string
	 */
	public static function request_method()
	{
		return self::$request['method'];
	}
	
	/**
	 * Utility method to return the subdomain indicator
	 *
	 * @static
	 * @access public
	 * @return bool
	 */
	public static function request_is_subdomain()
	{
		return self::$request['is_subdomain'];
	}

	/**
	 * Set the output headers (default to UTF-8)
	 * 
	 * @access private
	 * @return boolean
	 */	
	private function output_headers()
	{
		if(!headers_sent())
		{
			foreach(self::$headers as $header)
			{
				header($header);
			}			
		}
	}
	
	/**
	 * Set the system autoloader (single autoloader)
	 * 
	 * @access private
	 * @return boolean
	 */
	private function set_auto_loader()
	{	
		spl_autoload_register(
			function($class)
			{
				$path = str_replace('_', '/', strtolower($class)).'.php';
				
				$locations = array(
					realpath('../').'/www/',
					realpath('../').'/modules/',
				);
				
				foreach($locations as $location)
				{
					if(file_exists($location.$path))
					{
						require $location.$path;
						
						return TRUE;
					}
				}
				
				return FALSE;
			}
		);
	}
	
	/**
	 * Set the druid error handler
	 * 
	 * @access private
	 * @return boolean
	 */
	public static function druid_error_handler($code, $error, $file = NULL, $line = NULL)
	{
		if (error_reporting() & $code)
		{
			throw new ErrorException($error, $code, 0, $file, $line);
		}

		return TRUE;	
	}
	
	/**
	 * Set the druid exception handler
	 * 
	 * @access private
	 * @return void
	 */
	public static function druid_exception_handler($e)
	{
		$type    = get_class($e);
		$code    = $e->getCode();
		$message = $e->getMessage();
		$file    = $e->getFile();
		$line    = $e->getLine();
		$trace   = $e->getTrace();
		
		if ($e instanceof ErrorException)
		{
			if (isset(self::$php_errors[$code]))
			{
				$code = self::$php_errors[$code];
			}

			if (version_compare(PHP_VERSION, '5.3', '<')) // @see http://bugs.php.net/bug.php?id=45895
			{
				for ($i = count($trace) - 1; $i > 0; --$i)
				{
					if (isset($trace[$i - 1]['args']))
					{
						$trace[$i]['args'] = $trace[$i - 1]['args'];

						unset($trace[$i - 1]['args']);
					}
				}
			}
		}
		
		$error = sprintf(
			'%s [ %s ]: %s ~ %s [ %d ]', get_class($e), $e->getCode(), strip_tags($e->getMessage()), $e->getFile(), $e->getLine()
		);
		
		error_log($error, 0); // Log to PHP error log
		
		echo $error;
		
		return TRUE;
	}
	
	/**
	 * Set the shutdown handler, handle critical errors
	 * 
	 * @access private
	 * @return void
	 */
	public static function druid_shutdown_handler()
	{
		$last_error = error_get_last();
			
	    if($last_error['type'] === E_ERROR || in_array($last_error['type'], self::$fatal_errors)) 
	    {
			self::druid_exception_handler(
				new ErrorException($last_error['message'], $last_error['type'], 0, $last_error['file'], $last_error['line'])
			);
			
			exit(1);
	    }
	}
	
	/**
	 * Prevent Druid instance from being cloned
	 * 
	 * @access private
	 * @return void
	 */
	public function __clone()
	{
		trigger_error('Cloning of Druid not permitted.', E_USER_ERROR);
	}
	
} // EOC