<?php
/**
 * User class, we keep the basic user information here
 *
 * @package    Poseidon
 * @category   User
 * @author     Claudiu Andrei
 */
class User {
	
	/**
	 * @var  User  Main user instance
	 */
	static protected $instance;
	
	/**
	 * @var  Session  Main session
	 */
	public $session;
	
	/**
	 * Get singleton user instance
	 *
	 *     $user = User::instance();
	 *
	 * @return  User
	 */
	public static function instance()
	{
		// Check first if we already have an instance
		User::$instance OR User::$instance = new User;

		// Return the User instance
		return User::$instance;
	}
	
	/**
	 * Constructor: Load the session and the other things that the user needs
	 */
	public function __construct()
	{
		// Set up the session
		$this->session = Session::instance('native', 'poken');
	}
	
	/**
	 * Set/Get the authentication status
	 *
	 *     $user->authenticated(FALSE);
	 *
	 * @param  bool  Status
	 *
	 * @return  User
	 */
	public function authenticated($status = NULL)
	{
		// Simple check if the user is authenticated
		if ($status === NULL)
		{
			return $this->session->get('user.status', FALSE);
		}
		
		// Store the status
		$this->session->set('user.status', $status);

		// Return the User object
		return $this;
	}
	
	/**
	 * Log the user in
	 *
	 *     $user->connect(Token::PASSWORD, array('username' => 'John', 'password' => 'pass'));
	 *
	 * @param   string  Access type method
	 * @param   array   Access request parameters
	 *
	 * @return  User
	 */
	public function connect($method, array $parameters = array())
	{
		// Refresh the authentication
		$this->authenticated(FALSE);
	
		// Check if we can can authenticate the api
		if (API::instance()->token->get($method, $parameters))
		{	
			// Authenticate the session
			$this->authenticated(TRUE);
		}
		
		// Return the User object
		return $this;
	}
}