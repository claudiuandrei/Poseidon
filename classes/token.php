<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Wrapper for the API authentication token
 *
 * @package    Poken
 * @category   API
 * @author     Claudiu Andrei
 */
class Token {

	/**
	 * @const  string  Authentication grant types
	 */
	const CODE = 'authorization_code';
	const PASSWORD = 'password';
	const CLIENT = 'client_credentials';
	const EXTERNAL = 'poken_external';
	const REFRESH = 'refresh_token';
	
	/**
	 * @var  array  Token containing expires, access, refresh, authentication
	 */
	protected $token = NULL;
	
	/**
	 * @var  array  Options containing the token authentication mode and access point for the authentication
	 */
	protected $options;

	/**
	 * Build an instance
	 *
	 *     $access = Token::factory();
	 *
	 * @return  Token
	 */
	public static function factory($token = FALSE, array $options = array())
	{
		return new Token($token, $options);
	}
	
	/**
	 * Constructor: Load the token
	 */
	public function __construct($token = FALSE, array $options = array())
	{
		// Load the default configuration
		$config = (array) Kohana::$config->load('api');
		
		// Get the request segments
		$segments = explode('/', trim($config['path'], '/'));
		
		// Add the predefined options
		$options += array(
			'authenticated' => FALSE,
			'path' => str_replace(end($segments), 'oauth2', trim($config['path'], '/') . '/'),
		);
		
		// Set up the authentication path
		$this->path = $options['path'];
		
		// Load the saved token if no valid token is provided
		if ( ! is_array($token))
		{
			// Setup the token
			$token = ($token)
				? User::instance()->session->get('user.token')
				: Cache::instance()->get('api.token');
		}
		
		// Initialize the token
		$this->token = $token;
	}
	
	/**
	 * Get the cached token
	 *
	 *     $access->cached();
	 *
	 * @return  mixed  Access token or NULL when not found
	 */
	public function cached()
	{
		// Return the cached token
		if ($this->token)
		{
			// Check for expired token
			return (time() < $this->token['expires'])
				? $this->token
				: $this->refresh();
		}
		
		// Return false if no cached token
		return NULL;
	}
	
	/**
	 * Get a valid token
	 *
	 *     $access->get(Token::PASSWORD, array('username' => 'John', 'password' => 'pass'));
	 *
	 * @param   string  Access type method
	 * @param   array   Access request parameters
	 *
	 * @return  string  Access token
	 */
	public function get($method, array $parameters = array())
	{	
		return $this->request($method, $parameters)->token;
	}
	
	/**
	 * Get a valid user token with username and password
	 *
	 *     $token = $access->user('John Doe', 'password');
	 *
	 * @param   string  Username
	 * @param   string  Password
	 *
	 * @return  string  User access token
	 */
	public function user($username, $password)
	{
		return $this->get(Token::PASSWORD, array('username' => $username, 'password' => $password));
	}
	
	/**
	 * Get a valid user token using an external service
	 *
	 *     $token = $access->service('facebook', 'token');
	 *
	 * @param   string  Service
	 * @param   string  Service secret code
	 *
	 * @return  string  User access token
	 */
	public function service($service, $secret, $redirect = '')
	{
		return $this->get(Token::EXTERNAL, array('service' => $service, 'service_secret' => $secret, 'redirect_uri' => $redirect));
	}
	
	/**
	 * Exchange a code to a valid user token
	 *
	 *     $token = $access->code('code');
	 *
	 * @param   string  API response code
	 * @return  string  User access token
	 */
	public function code($code, $redirect = '')
	{
		return $this->get(Token::CODE, array('code' => $code, 'redirect_uri' => $redirect));
	}
	
	/**
	 * Get a valid client token, used for unauthenticated user requests
	 *
	 *     $token = $access->client();
	 *
	 * @return  string  Client access token
	 */
	public function client()
	{
		return $this->get(Token::CLIENT);
	}
	
	/**
	 * Refresh an expired token with a request to the server
	 *
	 *     $access->refresh();
	 *
	 * @return  Token
	 */
	protected function refresh()
	{
		return ($this->token['authenticated'])
			
			// The authenticated token can be refreshed
			? $this->get(Token::REFRESH, array('refresh_token' => $this->token['refreh']))
			
			// Unauthenticated token can only be requested again
			: $this->client();
	}
	
	/**
	 * Make a request to the server to ge the access token
	 *
	 *     $access->request(Token::PASSWORD, array('username' => 'John', 'password' => 'pass'));
	 *
	 * @param   string  Access type method
	 * @param   array   Access request parameters
	 *
	 * @return  Token
	 */
	protected function request($method, array $parameters = array())
	{
		// Verify that the access method is correct
		if ( ! $this->is_accepted_method($method))
		{
			throw new Kohana_Exception('[API\Token][Error] Unsupported method - :method', array(':method' => $method));
		}
		
		// Add the method to the authorization request
		$parameters['grant_type'] = $method;
		
		// Add the response type parameter
		$parameters['response_type'] = 'token';
		
		// Get the response containing the token
		$response = API::instance()->request($this->path . 'access_token', Request::POST, $parameters);
		
		// Get the response content
		$content = $response->content();
		
		// Was the response successful
		if (($status = $response->status()) !== 200 AND isset($content['error_description']))
		{
			throw new Kohana_Exception('[API\Token][Error] #:status - :description', array(':status' => $status, ':description' => $content['error_description']));
		}
		
		// Reset the token
		$this->token = array();
		
		// Set the access token
		$this->token['access'] = $content['access_token'];
		
		// This is an user autheticated token, save it to the user session
		if ($this->token['authenticated'] = ($method !== Token::CLIENT))
		{		
			// Set the refresh token
			$this->token['refresh'] = $content['refresh_token'];
		}
		
		// Set lifetime and save token
		$this->cache($content['expires_in']);

		// Return the modified token object
		return $this;
	}
	
	/**
	 * Set the expires time for this token to using the current timestamp
	 *
	 *     $access->cache(3600);
	 *
	 * @return  Token
	 */
	public function cache($expires_in = 86400, $postpone = NULL)
	{
		// Check if we need to postpone the caching
		if ($postpone === NULL OR $postpone > $expires_in) {
			$postpone = $expires_in;
		}
		
		// Save the token lifetime
		if ( ! isset($this->token['expires']) OR $this->token['expires'] < time() + $postpone)
		{
			// Set the expires time to the current time + token expires_in
			$this->token['expires'] = time() + $expires_in;
		
			// Cache the changed token
			($this->token['authenticated'])
				
				// Use the session for the user token
				? User::instance()->session->set('user.token', $this->token)
				
				// Use long term cache for unauthenticated token
				: Cache::instance()->set('api.token', $this->token);
		}
		
		// Return the modified token object
		return $this;
	}
	
	/**
	 * Check if the method used is accepted for OAuth2
	 *
	 *     $access->is_accepted_method(Token::CLIENT);
	 *
	 * @return  bool
	 */
	public function is_accepted_method($method)
	{
		// The accepted methods
		$accepted = array(Token::CODE, Token::PASSWORD, Token::CLIENT, Token::EXTERNAL, Token::REFRESH);
		
		// Verify that the access method is correct
		return in_array($method, $accepted);
	}
}