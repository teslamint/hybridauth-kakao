<?php

/* !
 * HybridAuth
 * http://hybridauth.sourceforge.net | http://github.com/hybridauth/hybridauth
 * (c) 2009-2015, HybridAuth authors | http://hybridauth.sourceforge.net/licenses.html
 */

/**
 * Hybrid_Providers_Kakao provider adapter based on OAuth2 protocol
 *
 * http://hybridauth.sourceforge.net/userguide/IDProvider_info_Kakao.html
 */

/**
 * Howto define profile photo size:
 * - add params key into hybridauth config
 * ...
 *    "Kakao" => array (
 *       "enabled" => true,
 *       "keys"    => ...,
 *       "params" => array( "photo_size" => "small" )
 *   	),
 * ...
 * - list of valid photo_size values is described here: https://developers.kakao.com/docs/restapi#카카오톡
 * - default photo_size is 640x640
 */
class Hybrid_Providers_Kakao extends Hybrid_Provider_Model_OAuth2 {

	private static $defPhotoSize = "640x640";

	/**
	 * {@inheritdoc}
	 */
	function initialize() {
		parent::initialize();

		// Provider apis end-points
		$this->api->api_base_url = "https://kapi.kakao.com/v1/";
		$this->api->authorize_url = "https://kauth.kakao.com/oauth/authorize";
		$this->api->token_url = "https://kauth.kakao/oauth/token";

		$this->api->sign_token_name = "access_token";
	}

	/**
	 * {@inheritdoc}
	 */
	function getUserProfile() {
		$data = $this->api->api("talk/profile", "GET");

		if (!isset($data->response->user->id)) {
			throw new Exception("User profile request failed! {$this->providerId} returned an invalid response:" . Hybrid_Logger::dumpData( $data ), 6);
		}

		$data = $data->response->user;

		$this->user->profile->displayName = $data->nickName;
		$this->user->profile->profileImageURL = $data->pictureUrl;
        $this->user->profile->thumbnailURL = $data->thumbnailUrl;
		$this->user->profile->countryCode = $data->countryISO;

		return $this->user->profile;
	}

	/**
	 * {@inheritdoc}
	 */
	function getUserContacts() {
		// refresh tokens if needed
		$this->refreshToken();

		//
		$response = array();
		$contacts = array();
		try {
			$response = $this->api->api("users/self/friends", "GET");
		} catch (LinkedInException $e) {
			throw new Exception("User contacts request failed! {$this->providerId} returned an error: {$e->getMessage()}", 0, $e);
		}

		if (isset($response) && $response->meta->code == 200) {
			foreach ($response->response->friends->items as $contact) {
				$uc = new Hybrid_User_Contact();
				//
				$uc->identifier = $contact->id;
				//$uc->profileURL		= ;
				//$uc->webSiteURL		= ;
				$uc->photoURL = $this->buildPhotoURL($contact->photo->prefix, $contact->photo->suffix);
				$uc->displayName = $this->buildDisplayName((isset($contact->firstName) ? ($contact->firstName) : ("")), (isset($contact->lastName) ? ($contact->lastName) : ("")));
				//$uc->description	= ;
				$uc->email = (isset($contact->contact->email) ? ($contact->contact->email) : (""));
				//
				$contacts[] = $uc;
			}
		}
		return $contacts;
	}

	/**
	 * {@inheritdoc}
	 */
	private function buildDisplayName($firstName, $lastName) {
		return trim($firstName . " " . $lastName);
	}

	private function buildPhotoURL($prefix, $suffix) {
		if (isset($prefix) && isset($suffix)) {
			return $prefix . ((isset($this->config["params"]["photo_size"])) ? ($this->config["params"]["photo_size"]) : (Hybrid_Providers_Kakao::$defPhotoSize)) . $suffix;
		}
		return ("");
	}

}
