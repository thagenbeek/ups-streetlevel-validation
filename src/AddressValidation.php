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
	/**
	 * UPS access key, provided after API access is granted
	 * @var string $accessKey
	 */
	private $accessKey;

	/**
     * UPS UserID
     * @var string $userid
     */
	private $userid;
	
	/**
	 * UPS Password
	 * @var string $password
	 */
	private $password;

	/** 
	 * local path-location of UPS wsdl
	 * @var string $wsdl
	 */
	private $wsdl =  "/schemas/address_validation/XAV.wsdl";
	
	/**
	 * Name of the operation performing the soap request
	 * @var string $operation
	 */
	private $operation = "ProcessXAV";
	
	/**
	 * UPS's endpoint (same for dev as live)
	 * @var string $enpointurl
	 */
	private $endpointurl = 'https://onlinetools.ups.com/webservices/XAV';
	
	/**
	 * UPS's XML schema url
	 * @var string $upsXmlSchemaUrl
	 */
	private $upsXmlSchemaUrl = 'http://www.ups.com/XMLSchema/XOLTWS/UPSS/v1.0';
	
	/**
	 * Error array holds any refular or soap exceptions returned with the response when not valid
	 * @var array $errors 
	 */
	private $errors = array();
	
	//address information
	/**
	 * @var string $addressee
	 */
	private $addressee;
	
	/**
	 * Primary Address line, line1 and line2 will be concated
	 * @var string $address_line1
	 */ 
	private $address_line1;

	/**
	 * Secondary (ie. suite #, apt # Address line, line1 and line2 will be concated
	 * @var string $address_line2
	 */
	private $address_line2;
	
	/** 
	 * @var string $city
	 */
	private $city;
	
	/**
	 * Can be int only for US, else can be string
	 * @var mixed (int|string) $zip
	 */
	private $zip;
	
	/**
	 * @var string $state
	 */
	private $state;
	
	/**
	 * @var string $country
	 */
	private $country = "US";
	
	/**
	 * isValid is set by the result, and will be read through $this->isValid
	 * @var bool $isValid
	 */
	private $isValid;
	
	/**
	 * The raw response from UPS
	 * @var mixed (xml|string)
	 */
	private $response;
	
	/**
	 * The raw request send to UPS
	 * @var mixed (xml|string)
	 */
	private $request;
	
	/** 
	 * The xml2array parsed response
	 * @var array $responseArray
	 */
	private $responseArray;

	/**
	 * Any suggestions we might recieve on a invalid address
	 * @var array $suggestions
	 */
	private $suggestions = array();
  		
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
	 * @param string $addressee
	 * @return \UpsApi\AddressValidation
	 */
	public function set_addressee($addressee)
	{
		$this->addressee = (string)$addressee;
		return $this;
	}
	
	/**
	 * Set the street address for address line 1
	 * @param string $address_line1
	 * @return \UpsApi\AddressValidation
	 */
	public function set_address($address_line1)
	{
		$this->address_line1 = (string)$address_line1;	
		return $this;
	}
	
	/**
	 * Set the street address for address line 2
	 * @param string $address_line2
	 * @return \UpsApi\AddressValidation
	 */
	public function set_suite($address_line2)
	{
		$this->address_line2 = (string)$address_line2;
		return $this;
	}
	
	/**
	 * set the city
	 * @param string $city
	 * @return \UpsApi\AddressValidation
	 */
	public function set_city($city)
	{
		$this->city = (string)$city;
		return $this;
	}
	
	/**
	 * Set the zipcode
	 * @param int $zip
	 * @return \UpsApi\AddressValidation
	 */
	public function set_zip($zip)
	{
		$this->zip = (int)$zip;
		return $this;
	}
	
	/**
	 * Sets the state
	 * would be a two character (upper) representation or full proper cased version for US (REQUIRED US ONLY)
	 * can be considered optional when not US, but if used full name is required
	 * @param string $state
	 * @return \UpsApi\AddressValidation
	 */
	public function set_state($state)
	{
		$this->state = (string)$state;
		return $this;
	}
	
	/**
	 * sets the two character (upper) representation of a country (i.e. United States == US, the Netherlands == NL) 
	 * @param string $country
	 * @return \UpsApi\AddressValidation
	 */
	public function set_country($country)
	{
		$this->country = (string)$country;
		return $this;
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
		// convert the raw response xml to an array
		$data = xml2array($this->getRawResponse());
		// set the response array to be the response body
		$this->responseArray = $data['soapenv:Envelope']['soapenv:Body'];
	
		return $this;
	}
	
	/**
	 * validate the set mailing address | catches errors from soap or exceptions
	 * @return \UpsApi\AddressValidation
	 */
	public function validate()
	{
		try
		{
			// set the general soap mode.
			$mode = array(
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
			}
			else
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
		//set the ups service data
		$ups_service_data = array(
			'UsernameToken' => array(
				'Username' => $this->userid,
				'Password' => $this->password
			),
			'ServiceAccessToken' => array(
				'AccessLicenseNumber' => $this->accessKey
			)
		);
		// build the headr from the data
		$header = new \SoapHeader($this->upsXmlSchemaUrl,'UPSSecurity',$ups_service_data);
	
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
		$addrkeyfrmt['ConsigneeName'] = $this->addressee;
		$addrkeyfrmt['AddressLine'] = implode(' ',array($this->address_line1,$this->address_line2));
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
	
	/**
	 * Returns the json encoded results of the query
	 * @return mixed $result
	 */
	public function getResult(){
		$result = array(
			'status' => 200,
			'success' => $this->isValid(),
			'suggestions' => $this->isValid() ? false : $this->setSuggestionsFromResponse()->getSuggestions(),
			'results' => $this->getResponseArray(),
			'errors' => $this->isValid() ? false : $this->errors,
			'raw' => array(
				'request' => $this->getRawRequest(),
				'response' => $this->getRawResponse()
			)
		);
		
		return json_encode($result);
	}
}

?>
