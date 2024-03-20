<?php

include_once 'vendor/autoload.php';

use FuelSdk\ET_Client;
use FuelSdk\ET_DataExtension;
use FuelSdk\ET_Email;
use FuelSdk\ET_Get;
use FuelSdk\ET_List;
use FuelSdk\ET_Organization;

class NGL_Salesforce_API {

    private $clientID;
    private $clientSecret;
    private $apiUrl;
    private $client;

    /**
     * constructor.
     */
    public function __construct( $api_key, $api_secret, $api_url ) {        
        $this->clientID = $api_key;
        $this->clientSecret = $api_secret;
        $this->apiUrl = $api_url;
        $this->client = new ET_Client(
			true,
			false, 
			array(
				'clientid' => $this->clientID,
				'clientsecret' => $this->clientSecret,
				'baseAuthUrl' => $this->apiUrl,
				'useOAuth2Authentication' => true,
                'xmlloc' => __DIR__ . '/ExactTargetWSDL.xml',
			)
		);
    }

    /**
     * Get client
     */
    public function getClient() {
        return $this->client;
    }

    /**
     * Get organization info
     */
    public function getOrganization() {
        $getOrganization = new ET_Organization();
		$getOrganization->authStub = $this->client;
		$getOrganization->props = array( 'ID', 'Name', 'Address', 'BusinessName', 'City', 'Country', 'Email', 'Fax', 'FromName', 'Phone',  'State', 'Zip', 'CustomerKey' );
		$organizationResponse = $getOrganization->get();

		$response = json_decode( json_encode( $organizationResponse->results ) , true );

        return $response;
    }

    /**
     * Get sender profiles
     */
    public function getSenderProfiles() {
        $getProfiles = new ET_Get(
            $this->client,
            'SenderProfile',
            array( 'ObjectID', 'CustomerKey', 'FromName', 'FromAddress' ),
            null,
        );

		$response = json_decode( json_encode( $getProfiles->results ) , true );

        return $response;
    }

    /**
     * Get send classifications
     */
    public function getSendClassifications() {
        $getSendClassifications = new ET_Get(
            $this->client,
            'SendClassification',
            array(
                'Name',
                'CustomerKey'
            ),
            null,
        );

		$response = json_decode( json_encode( $getSendClassifications->results ) , true );

        return $response;
    }

    /**
     * Get lists
     */
    public function getLists() {
        $getList = new ET_List();
		$getList->authStub = $this->client;
		$getList->props = array( 'ID', 'ListName' );
		$listResponse = $getList->get();

		$response = json_decode( json_encode( $listResponse->results ) , true );

        return $response;
    }

    /**
     * Get data extension (only sendable)
     */
    public function getDataExtension() {
        $getDE = new ET_DataExtension();
        $getDE->authStub = $this->client;
        $getDE->props = array( 'CustomerKey', 'Name' );
        $getDE->filter = array( 'Property' => 'IsSendable', 'SimpleOperator' => 'equals', 'Value' => '1');
        $getResult = $getDE->get();

		$response = json_decode( json_encode( $getResult->results ) , true );

        return $response;
    }

    /**
     * add a subscriber to a list
     */
    public function addSubscriberToList( $emailAddress, $listID ) {
        $addSubscriber = $this->client->AddSubscriberToList( $emailAddress, array( $listID ), $emailAddress );
        
        $response = json_decode( json_encode( $addSubscriber->results ) , true );
        
        return $response;
    }

    /**
     * Create an email template
     */
    public function postEmail( $name, $subject, $htmlBody, $emailType = 'HTML', $isHTMLPaste = 'true' ) {
        $postEmail = new ET_Email();
		$postEmail->authStub = $this->client;
		$postEmail->props = array(
			'CustomerKey' => uniqid(),
			'Name'        => $name,
			'Subject'     => $subject,
			'HTMLBody'    => $htmlBody,
			'EmailType'   => $emailType,
			'IsHTMLPaste' => $isHTMLPaste,
		);

        $postEmailResponse = $postEmail->post();
		$response = json_decode( json_encode( $postEmailResponse->results ) , true );

        return $response;
    }

}