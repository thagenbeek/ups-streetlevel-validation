<?php
namespace UpsApi;
include("helpers/xml2array.php");

/**
 * Class for validating a mailing address
 *
 * @author Tobias Hagenbeek <thagenbeek@nabancard.com>
 */
class AddressValidation
{
    private $accessKey;
	private $userid;
	private $password;
	private $wsdl =  "/schemas/address_validation/XAV.wsdl";
	private $operation = "ProcessXAV";
	private $endpointurl = 'https://onlinetools.ups.com/webservices/XAV';
	private $outputFileName = "XOLTResult.xml";
	
	private $errors = array();
	
	//address information
	private $cosigneeName;
	private $address_line1;
	private $address_line2;
	private $city;
	private $zip;
	private $state;
	private $country = "US";
	
	private $isValid;
	private $response;
	private $request;
	private $responseArray;

	private $suggestions = array();
	private $extracted_data;
  		
  	/**
  	 * Construct using UPS credentials
  	 * @param string $accessKey
  	 * @param string $userid
  	 * @param string $password
  	 */
	public function __construct($accessKey, $userid, $password)
	{
		$this->accessKey = $accessKey;
		$this->userid = $userid;
		$this->password = $password;
	}
	/**
	 * Set the reciever name ("firstName lastName")
	 * @param string $cosigneeName
	 */
	public function set_cosignee($cosigneeName)
	{
		$this->cosigneeName = (string)$cosigneeName;
	}
	
	/**
	 * Set the street address for address line 1
	 * @param string $address_line1
	 */
	public function set_address($address_line1)
	{
		$this->address_line1 = (string)$address_line1;	
	}
	
	/**
	 * Set the street address for address line 2
	 * @param string $address_line2
	 */
	public function set_suite($address_line2)
	{
		$this->address_line2 = (string)$address_line2;
	}
	
	/**
	 * set the city
	 * @param string $city
	 */
	public function set_city($city)
	{
		$this->city = (string)$city;
	}
	
	/**
	 * Set the zipcode
	 * @param int $zip
	 */
	public function set_zip($zip)
	{
		$this->zip = (int)$zip;
	}
	
	/**
	 * Sets the state
	 * would be a two character (upper) representation or full proper cased version for US (REQUIRED US ONLY)
	 * can be considered optional when not US, but if used full name is required
	 * @param string $state
	 * @return void
	 */
	public function set_state($state)
	{
		$this->state = (string)$state;
	}
	
	/**
	 * sets the two character (upper) representation of a country (i.e. United States == US, the Netherlands == NL) 
	 * @param string $country
	 * @return void
	 */
	public function set_country($country)
	{
		$this->country = (string)$country;
	}

	/**
	 * set the suggestions based on a non valid address
	 * @return \UpsApi\AddressValidation
	 */
	public function setSuggestionsFromResponse()
	{
		$suggestions = $this->getResponseArray()['xav:XAVResponse']['xav:Candidate'];
		
		$i = 0;
		foreach($suggestions as $k => $data)
		{
			if (!is_int($key) && $i == 1)
				$this->suggestions[] = $data;
			elseif (is_int($key))
				$this->suggestions[] = $data['xav:AddressKeyFormat'];
			$i++;
		}
	
		return $this;
	}
	
	/**
	 * sets the xml response to a response Array
	 * @return \UpsApi\AddressValidation
	 */
	private function setResponseArray()
	{
		$data = xml2array($this->getRawResponse());
		$this->responseArray = $data['soapenv:Envelope']['soapenv:Body'];
	
		return $this;
	}
	
	/**
	 * validate the set mailing address
	 * @return \UpsApi\AddressValidation | catches errors from soap or exceptions
	 */
	public function validate()
	{
		try
		{
			$mode = array
			(
					'soap_version' => 'SOAP_1_1',  // use soap 1.1 client
					'trace' => 1
			);
	
			// initialize soap client
			$wsdl_path = __DIR__ . $this->wsdl;
			$client = new \SoapClient($wsdl_path , $mode);
				
			//set endpoint url
			$client->__setLocation($this->endpointurl);
			// set headers
			$client->__setSoapHeaders($this->create_header());
			// make the soap call
			$resp = $client->__soapCall($this->operation ,array($this->processXAV()));
			// set the raw data
			$this->response = $client->__getLastResponse();
			$this->request = $client->__getLastRequest();
			// set the response (xml to array)
			$this->setResponseArray();
			// check validity
			if(isset($resp->ValidAddressIndicator))
			{
				$this->isValid = true;
			}else
			{
				$this->isValid = false;
			}
			
			return $this;
		}
		catch(\Exception $ex)
		{
			$this->errors['exception'] = $ex;
		}
		catch(\SoapFault $e)
		{
			$this->errors['soap'] = $e;
		}
		
		return $this;
	}	
	
	/**
	 * @return bool return the validity status
	 */
	public function isValid()
	{
		return $this->isValid;
	}
	
	/**
	 * Create the header for the soap request
	 * @return \SoapHeader
	 */
	private function create_header()
	{
		//create soap header
		$usernameToken['Username'] = $this->userid;
		$usernameToken['Password'] = $this->password;
		$serviceAccessLicense['AccessLicenseNumber'] = $this->accessKey;
		$upss['UsernameToken'] = $usernameToken;
		$upss['ServiceAccessToken'] = $serviceAccessLicense;
	
		$header = new \SoapHeader('http://www.ups.com/XMLSchema/XOLTWS/UPSS/v1.0','UPSSecurity',$upss);
	
		return $header;
	}
	
	/**
	 * process the request information to add to complete the request
	 * @return array $request ($addrkeyfrmt)
	 */
	private function processXAV()
	{
		//create soap request
		$option['RequestOption'] = '3';
		$request['Request'] = $option;
	
		//$request['RegionalRequestIndicator'] = '1';
		$addrkeyfrmt['ConsigneeName'] = $this->cosigneeName;
		$addrkeyfrmt['AddressLine'] = $this->address_line1.' '.$this->address_line2;
		//$addrkeyfrmt['Region'] = $this->city.','.$this->state.','.$this->zip;
		$addrkeyfrmt['PoliticalDivision2'] = $this->city;
		$addrkeyfrmt['PoliticalDivision1'] = $this->state;
		$addrkeyfrmt['PostcodePrimaryLow'] = $this->zip;
		$addrkeyfrmt['CountryCode'] = $this->country;
		$request['AddressKeyFormat'] = $addrkeyfrmt;
	
		return $request;
	}	
	
	/**
	 * returns raw soap envelope (xml) from the request
	 * @return \UpsApi\AddressValidation->responseArray
	 */
	public function getRawResponse()
	{
		return $this->response;
	}
	
	/**
	 * returns the response array set by setResponseArray
	 * @return \UpsApi\AddressValidation->responseArray
	 */
	public function getResponseArray()
	{
		return $this->responseArray;
	}
	
	/**
	 * return (raw) request
	 * @return \UpsApi\AddressValidation->request
	 */
	public function getRawRequest()
	{
		return $this->request;
	}
	
	
	/**
	 * return the suggestions set from the response
	 * @return \UpsApi\AddressValidation->suggestions
	 */
	private function getSuggestions()
	{
		return $this->suggestions;
	}
		
	public function getResult(){
		if($this->isValid())
		{
			$result = array(
				'status' => 200,
				'success' => $this->isValid(),
				'results' => $this->getResponseArray(),
				'raw' => array(
						'request' => $this->getRawRequest(),
						'response' => $this->getRawResponse()
				)
			);
		}
		else
		{
			$result = array(
				'status' => 200,
				'success' => $this->isValid(),
				'suggestions' => $this->setSuggestionsFromResponse()->getSuggestions(),
				'results' => $this->getResponseArray(),
				'errors' => $this->errors,
				'raw' => array(
						'request' => $this->getRawRequest(),
						'response' => $this->getRawResponse()
				)
			);
		}
		
		return json_encode($result);
	}
}

?>
