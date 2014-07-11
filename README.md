#UPS PHP Api for Address Validation with suggestions

##Requirements
1. In order to use any of these apis you must first sign up for a UPS Developer account. (https://www.ups.com/upsdeveloperkit?loc=en_US)

## Installation
1. Use composer to install the project (PSR4-Autoloading)

##Usage

###Address Validation
Simply create a PHP file you can point to either CLI or Web, containing what is shown below.
!! Make sure you change the required autoloader based upon your path
```php
header ("Content-Type:application/json");
require('vendor/autoload.php');

use UpsApi\AddressValidation;
$upsValidator = new AddressValidation('YOUR_UPS_ACCESS_KEY','YOUR_UPS_USERNAME','YOUR_UPS_PASSWORD');

$upsValidator->set_cosignee('Tobias Hagenbeek');
//$upsValidator->set_address('1 Street'); // invalid
$upsValidator->set_address('799 E DRAGRAM'); // valid
// (optional) $upsValidator->set_suite('SUITE 5A');
$upsValidator->set_city('TUCSON');
$upsValidator->set_state('Arizona'); // can be AZ
$upsValidator->set_zip('38116');
// (optional) $upsValidator->set_country('US'); // NOT USA (two digits only)

echo $upsValidator->validate()->getResult();
exit;
```

##Minimum System Requirements
Use of this library requires PHP 5.3+, and PHP SOAP extension (http://php.net/manual/en/book.soap.php)
