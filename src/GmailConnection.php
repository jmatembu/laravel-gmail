<?php

namespace Dacastro4\LaravelGmail;

use Dacastro4\LaravelGmail\Traits\Configurable;
use Google_Client;
use Google_Service_Gmail;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;

class GmailConnection extends Google_Client
{

	use Configurable {
		__construct as configConstruct;
	}

	protected $emailAddress;
	protected $refreshToken;
	protected $app;
	protected $accessToken;
	protected $token;
	private $configuration;

	public function __construct( $config = null )
	{
		$this->app = Container::getInstance();

		$this->configConstruct($config);

		$this->configuration = $config;

		parent::__construct( $this->getConfigs() );

		$this->configApi();

		if ( $this->check() ) {
			$this->refreshTokenIfNeeded();
		}
	}

	public function getAccessToken()
	{
		$token = parent::getAccessToken() ?: $this->getAccessTokenFromFile();

		return $token;
	}

	/**
	 * @return array|string
	 * @throws \Exception
	 */
	public function makeToken()
	{
		$request = Request::capture();
		$code = (string) $request->input( 'code', null );

		if ( ! is_null( $code ) && ! empty( $code ) ) {
			$accessToken = $this->fetchAccessTokenWithAuthCode( $code );
			$me = $this->getProfile();

			if ( property_exists( $me, 'emailAddress' ) ) {
				$this->emailAddress = $me->emailAddress;
			}

			$this->setBothAccessToken( $accessToken );

			return $accessToken;

		} else {
			throw new \Exception( 'No access token' );
		}

	}

	public function setToken( $token )
	{
		$this->setAccessToken( $token );
	}

	/**
	 * Check
	 *
	 * @return bool
	 */
	public function check()
	{
		return ! $this->isAccessTokenExpired();
	}

	/**
	 * Check if token exists and is expired
	 * Throws an AuthException when the auth file its empty or with the wrong token
	 *
	 *
	 * @return bool
	 */
	public function isAccessTokenExpired()
	{
		$token = $this->getToken();

		if ( $token ) {
			$this->setAccessToken( $token );
		}

		return parent::isAccessTokenExpired();
	}

	/**
	 * Revokes user's permission and logs them out
	 */
	public function logout()
	{
		$token = $this->getAccessTokenFromFile();
		
		$this->revokeToken($token);
	}

	public function getToken()
	{
		return $this->config() ?: parent::getAccessToken(); 
	}

	/**
	 * Refresh the auth token if needed
	 */
	private function refreshTokenIfNeeded()
	{
		if ( $this->isAccessTokenExpired() ) {
			$this->fetchAccessTokenWithRefreshToken( $this->getRefreshToken() );
			$token = $this->getAccessToken();
			$this->setAccessToken( $token );
		}
	}

	/**
	 * Gets user profile from Gmail
	 *
	 * @return \Google_Service_Gmail_Profile
	 */
	public function getProfile()
	{
		$service = new Google_Service_Gmail( $this );

		return $service->users->getProfile( 'me' );
	}

	/**
	 * @param array|string $token
	 */
	public function setAccessToken( $token )
	{
		parent::setAccessToken( $token );
	}

	/**
	 * @param $token
	 */
	public function setBothAccessToken( $token )
	{
		parent::setAccessToken( $token );
		$this->saveAccessToken( $token );
	}

	/**
	 * Save the credentials in a file
	 *
	 * @param array $config
	 */
	public function saveAccessToken( array $config)
	{
		$file = $this->getTokenFilePath();

		if ( Storage::disk( 'local' )->exists( $file ) ) {
			Storage::disk( 'local' )->delete( $file );
		}

		$config[ 'email' ] = $this->emailAddress;

		Storage::disk( 'local' )->put( $file, json_encode( $config ) );
	}

	/**
	 * Delete the credentials in a file
	 */
	public function deleteAccessToken()
	{
		$file = $this->getTokenFilePath();

		if ( Storage::disk( 'local' )->exists( $file ) ) {
			Storage::disk( 'local' )->delete( $file );
		}

		Storage::disk( 'local' )->put( $file, json_encode( [] ) );
	}

	public function getAccessTokenFromFile()
	{
		$filename = $this->getTokenFilePath();

		if (Storage::disk('local')->exists($filename)) {
			return json_decode( Storage::disk('local')->get($filename), true);
		}

		return null;
	}

}