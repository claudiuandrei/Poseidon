<?php
/**
 * API client library, handles the API communication logic
 *
 * @package    Poken
 * @category   API
 * @author     Claudiu Andrei
 * @copyright  (c) 2011-2012 Poken
 */
class API {
	
	// Release details
	const VERSION = 'Poseidon/2.0';
	
	/**
	 * @var  Client  Main client instance
	 */
	static protected $instance;
	
	/**
	* @var  Token  Authorization offered to the client/user
	*/
	public $token;
	
	/**
	 * @var  string  Id, used for client authorization
	 */
	protected $id;

	/**
	 * @var  string  Secret, used for client authorization
	 */
	protected $secret;
		
	/**
	 * @var  string  Access point rest path
	 */
	protected $path;
	
	/**
	 * @var  string  API response format
	 */
	protected $format;
	
	/**
	 * Get singleton api client instance
	 *
	 *     $client = API::instance();
	 *
	 * @return  API
	 */
	public static function instance()
	{
		// Check first if we already have an instance
		API::$instance OR API::$instance = new API;
		
		// Return the instance
		return API::$instance;
	}
	
	/**
	 * Constructor: Set the token used
	 */
	public function __construct(array $options = array())
	{	
		// Load the configuration
		$options += (array) Kohana::$config->load('api');
				
		// Set the credentials
		$this->id($options['id'])->secret($options['secret']);
		
		// Set the path
		$this->path(trim($options['path'], '/') . '/');
		
		// Set the format
		$this->format($options['format']);
		
		// Setup the default token
		$this->authenticate(User::instance()->authenticated());
	}
	
	/**
	 * Initialize the api token
	 *
	 *     $client->authenticate(TRUE);
	 *
	 * @param  mixed  Token or bool for cache loading
	 *
	 * @return  API
	 */
	public function authenticate($token = FALSE, array $options = array())
	{
		// Get the request segments
		$segments = explode('/', trim($this->path(), '/'));
		
		// Set the path for the oauth request
		$options += array('path' => str_replace(end($segments), 'oauth2', $this->path()));
		
		// Initialize the token
		$this->token = Token::factory($token, $options);
	}
	
	/**
	 * Set up regular request and send it
	 *
	 *     $response = $client->open('object/query', pokenRequest::GET, array('displayHits' => 'true'));
	 *
	 * @param  string  End point path, appended to entry point URI
	 * @param  string  HTTP method (Request::GET, Request::POST, Request::PUT or Request::DELETE.
	 * @param  array   Parameters for GET/POST/PUT
	 *
	 * @return  API_Response
	 */
	public function open($path, $method = Request::GET, $parameters = array(), $body = NULL, $type = NULL)
	{
		// Check if we have a cached token
		$token = ($this->token->cached()) ?: $this->token->client();
		
		// This is used for the signed requests, so add a token to the request
		$response = $this->request($path, $method, $parameters, $body, $type, $token);
		
		// This might be an issue with an expired token
		if ($response->status() === 401)
		{
			$response = $this->request($path, $method, $parameters, $body, $type, $this->token->refresh());
		}
		
		// Get the content
		$content = $response->content();
		
		// Check for errors in the response
		if ($response->status() !== 200)
		{
			if (is_array(Arr::get($content, 'error')))
			{
				throw new Kohana_Exception($content['error'][0]['description']);
			}
			else
			{
				throw new Kohana_Exception("[API][Error] Unexpected HTTP status #:status: :path \n\n :content", array(':status' => $response->status(), ':path' => $path, ':content' => $content));
			}
		}
		
		// The token has been used successfully, cache the token only once every 6 hours
		$this->token->cache(24 * 60 * 40, 6 * 60 * 60);
		
		// Return the response content
		return $content;
	}
	
	/**
	 * This is the request base, it should be used only for requesting tokens, regular requests should be done with open
	 *
	 *     $response = $client->request('object/query', Request::GET, array('displayHits' => 'true'));
	 *
	 * @param  string  End point path, appended to entry point URI
	 * @param  string  HTTP method (Request::GET, Request::POST, Request::PUT or Request::DELETE.
	 * @param  array   Parameters for GET/POST/PUT
	 * @param  array   The token we should use
	 * @param  string  The request content type 
	 *
	 * @return  API_Response
	 */
	public function request($path, $method = Request::GET, array $parameters = array(), $body = NULL, $type = NULL, $token = NULL)
	{
		// Add the client identification to the request
		$passport = array(
			'_format' => $this->format,
		);
		
		// Add either the token or the client credentials
		$passport += ($token)
			? array('access_token' => $token['access'])
			: array('client_id' => $this->id(), 'client_secret' => $this->secret());
		
		// Add the access point if the request contains a relative path
		if (strpos($path, '://') === FALSE)
		{
			$path = trim($this->path(), '/') . '/' . trim($path, '/');
		}
		
		// Build the request
		$request = API_Request::factory($path, $method, $parameters);
		
		// Add a body content if needed
		if ($body !== NULL)
		{
			$request->content($body, $type);
		}
		
		// Return the API response
		return $request->send($passport, API::VERSION);
	}
	
	/**
	 * Set/get the access point for the api
	 *
	 *     $client->path('https://api.poken.com/rest081/');
	 *
	 * @param  string  Access point for the api
	 *
	 * @return  API
	 */
	public function path($path = NULL)
	{
		// Return the current path
		if ($path === NULL)
		{
			return $this->path;
		}
		
		// Set a new path
		$this->path = $path;
		
		// Return the modified API object
		return $this;
	}

	/**
	 * Set/get the application id
	 *
	 *     $client->id('999');
	 *
	 * @param  string  Client id
	 *
	 * @return  API
	 */
	public function id($id = NULL)
	{
		// Return the current app id
		if ($id === NULL)
		{
			return $this->id;
		}
		
		// Set a new id
		$this->id = $id;
		
		// Return the modified API object
		return $this;
	}
	
	/**
	 * Set/get the application secret
	 *
	 *     $client->secret('XxXXxX');
	 *
	 * @param  string  Client secret key
	 *
	 * @return  API
	 */
	public function secret($secret = NULL)
	{
		// Return the current app secret key
		if ($secret === NULL)
		{
			return $this->secret;
		}
		
		// Set a new secret key
		$this->secret = $secret;
		
		// Return the modified API object
		return $this;
	}
	
	/**
	 * Set/get the application secret
	 *
	 *     $client->format('json');
	 *
	 * @param  string  Response format
	 *
	 * @return  API
	 */
	public function format($format = NULL)
	{
		// Return the current app secret key
		if ($format === NULL)
		{
			return $this->format;
		}
		
		// Set a new secret key
		$this->format = $format;
		
		// Return the modified API object
		return $this;
	}
}