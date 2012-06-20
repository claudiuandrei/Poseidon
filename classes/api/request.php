<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Wrapper for the API request, sends the request and returns a API response object
 *
 * @package    Poken
 * @category   API
 * @author     Claudiu Andrei
 */
class API_Request {

	/**
	 * @var  int  Timeout in sec
	 */
	public $timeout = 120;
	
	/**
	 * @var  string  Request headers
	 */
	protected $headers = array();
	
	/**
	 * @var  string  API access path
	 */
	protected $path;
	
	/**
	 * @var  string  HTTP method: Request::GET, Request::POST, Request::PUT, Request::DELETE
	 */
	protected $method = Request::GET;
	
	/**
	 * @var  array  Request paramters
	 */
	protected $parameters = array();
	
	/**
	 * @var  string  Request body
	 */
	protected $body = NULL;
	
	/**
	 * Get a request instance
	 *
	 *     $request = API_Request::factory('object/query', Request::GET, array('displayHits' => 'true'));
	 *
	 * @param  string  Request path
	 * @param  string  HTTP method (Request::GET, Request::POST, Request::PUT or Request::DELETE.
	 *
	 * @return  API_Request
	 */
	public static function factory($path, $method = Request::GET, array $parameters = array())
	{
		return new API_Request($path, $method, $parameters);
	}
	
	/**
	 * Constructor: Create a new request
	 *
	 * @param  string  Request path
	 * @param  string  HTTP method (Request::GET, Request::POST, Request::PUT or Request::DELETE
	 * @param  array   Parameters for GET/POST/PUT
	 *
	 * @return  API_Request
	 */
	public function __construct($path, $method = Request::GET, array $parameters = array())
	{
		// Set the path
		$this->path = trim($path, '/');
		
		// Check if the method is correct
		if ( ! in_array($method, array(Request::GET, Request::POST, Request::PUT, Request::DELETE)))
		{
			throw new Kohana_Exception('[API\Request][Error] Unsupported method :method', array(':method' => $method));
		}
		
		// Set the method
		$this->method = $method;
		
		// Set the parameters
		$this->parameters = $parameters;
		
		// Return the modified object
		return $this;
	}
	
	/**
	 * Add body content to the PUT request
	 *
	 *     $request->content($xml, 'application/xml');
	 *
	 * @param  string  Body content to be attached to the PUT requests
	 * @param  string  Content type
	 *
	 * @return  API_Request
	 */
	public function content($body, $type = NULL)
	{
		// Set the body
		$this->body = $body;
		
		// Set the content type
		if ($type !== NULL) {
			$this->headers[] = 'Content-Type: ' . $type;
		}
		
		// Return the modified object
		return $this;
	}

	/**
	 * Send the request
	 *
	 *     $response = $request->send($passport);
	 *
	 * @return  API_Response
	 */
	public function send($passport, $useragent = NULL)
	{
		// Set the request path
		$options[CURLOPT_URL] = Arr::get(explode('?', $this->path), 0);
		
		// Set the request path
		$options[CURLOPT_URL] = $this->path;
		
		// Set the options
		$options[CURLOPT_RETURNTRANSFER] = 1;
		$options[CURLOPT_HEADER] = 0;
		$options[CURLOPT_FOLLOWLOCATION] = 1;
		
		// If an useragent is set, send the user agent
		if ($useragent)
		{
			$options[CURLOPT_USERAGENT] = $useragent;
		}
		
		// Set the headers
		$options[CURLOPT_HTTPHEADER] = $this->headers;

		// Set the timeout
		$options[CURLOPT_CONNECTTIMEOUT] = $options[CURLOPT_TIMEOUT] = $this->timeout;

		// http://ademar.name/blog/2006/04/curl-ssl-certificate-problem-v.html
		$options[CURLOPT_SSL_VERIFYHOST] = 0;
		$options[CURLOPT_SSL_VERIFYPEER] = 0;
		
		// Add the authentication data to the query
		$query = (array) $passport;
		
		// GET & PUT requests will send the parameters as query
		if ($this->method === Request::GET || $this->method === Request::PUT)
		{
			$query += $this->parameters;
		}
		
		// Do the custom method behaviour
		switch ($this->method)
		{
			case Request::POST:
				// Set the body to form parameters
				$this->body = http_build_query($this->parameters);
			
			case Request::PUT:
				// Set the request body. This is perfectly legal in CURL even
				// if using a request other than POST. PUT does support this method
				// and DOES NOT require writing data to disk before putting it, if
				// reading the PHP docs you may have got that impression.
				$options[CURLOPT_POSTFIELDS] = $this->body;
			
			case Request::DELETE;
				// Set the request method
				$options[CURLOPT_CUSTOMREQUEST] = $this->method;
				
			case Request::GET:
				$options[CURLOPT_URL] .= '?' . http_build_query($query);
		}
		
		// Open a new remote connection
		$curl = curl_init();

		// Set connection options
		if ( ! curl_setopt_array($curl, $options))
		{
			throw new Kohana_Exception('[API\Request][Error] Failed to set CURL options');
		}
		
		// Get the response content
		if ( ! $content = curl_exec($curl)) {
			$error = curl_error($curl);
		}
		
		// Get the response information
		$info = curl_getinfo($curl);
		
		// Close the connection
		curl_close($curl);
		
		// Log the request
		Log::instance()->add(Log::INFO, '[API\Request] :method :path :time #:code' , array(
			':method' => $this->method,
			':path' => $this->path . (($this->parameters) ? '?' . http_build_query($this->parameters) : ''),
			':time' => '(' . round($info['size_download'] / 1024, 2) . 'kB' . ' in ' . round($info['total_time'] * 1000, 2) .'ms' . ')',
			':code' => $info['http_code'],
		));
		
		// Cannot find the remote url
		if (isset($error))
		{
			throw new Kohana_Exception('[API\Request][Error] Failed fetching remote url');
		}
	
		// Return the response
		return API_Response::factory($info['http_code'], $content);
	}
}