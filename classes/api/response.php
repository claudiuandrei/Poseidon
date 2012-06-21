<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Wrapper for the API response, prepares the response for easy use and returns it to the API handler
 *
 * @package    Poseidon
 * @category   API
 * @author     Claudiu Andrei
 */
class API_Response {
	
	/**
	 * @var  int  Response HTTP status code
	 */
	private $code;
	
	/**
	 * @var  string  Response content
	 */
	private $content;
	
	/**
	 * Get a response instance
	 *
	 *     $request = API_Response::factory($code, $content);
	 *
	 * @param   array  options
	 *
	 * @return  API_Response
	 */
	public static function factory($code, $content)
	{
		return new API_Response($code, $content);
	}
	
	/**
	 * Constructor: Set the response code and content
	 */
	public function __construct($code, $content)
	{
		// Set the code and the content from the request
		$this->code = $code;
		$this->content = $content;
	}
	
	/**
	 * Get the http status of the response
	 *
	 *     $response->status();
	 *
	 * @return  int
	 */
	public function status()
	{
		return $this->code;
	}
	
	/**
	 * Get content of the response
	 *
	 *     $response->content();
	 *
	 * @return  string
	 */
	public function content()
	{
		return json_decode($this->content, TRUE);
	}
}