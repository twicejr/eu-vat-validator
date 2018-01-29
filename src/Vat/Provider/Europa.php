<?php
/**
* @author         Pierre-Henry Soria <pierrehenrysoria@gmail.com>
* @copyright      (c) 2017, Pierre-Henry Soria. All Rights Reserved.
* @license        GNU General Public License; <https://www.gnu.org/licenses/gpl-3.0.en.html>
 */

declare(strict_types=1);

namespace PH7\Eu\Vat\Provider;

use PH7\Eu\Vat\Exception;
use stdClass;
use SoapClient;
use SoapFault;

class Europa implements Providable
{
    const EU_VAT_API = 'http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

    private $oClient;

    protected $timeout = "15";
    protected $max_attempts = 3;
    private $orig_sock_timeout;
    
    /**
     * @throws SoapFault
     */
    public function __construct()
    {
        try {
            $this->orig_sock_timeout = ini_get('default_socket_timeout');
            ini_set('default_socket_timeout', $this->timeout);
            $this->oClient = new SoapClient($this->getApiUrl());
        } catch(SoapFault $oExcept) {
            throw new Exception('Impossible to connect to the europa SOAP  : ' . $oExcept->faultstring);
        }
    }
    
    public function __destruct()
    {
        ini_set('default_socket_timeout', $this->orig_sock_timeout);
    }

    public function getApiUrl(): string
    {
        return static::EU_VAT_API;
    }

    /**
     * Send the VAT number and country code to europa.eu API and get the data.
     *
     * @param  int|string $sVatNumber The VAT number
     * @param  string $sCountryCode The country code
     * @return stdClass The VAT number's details.
     * @throws SoapFault
     * @throws Exception
     */
    public function getResource($sVatNumber, string $sCountryCode): stdClass
    {
        //ignore exceptions for x times.
        for($i = 1; $i <= $this->max_attempts; $i++)
        {
            try
            {
                $result = $this->_getResource($sVatNumber, $sCountryCode);
                return $result;
            }
            catch(Exception $e)
            {
                if($i === $this->max_attempts)
                {
                    throw $e;
                }
                usleep(100000); //1/10th
            }
        }
    }
    
    protected function _getResource($sVatNumber, string $sCountryCode): stdClass
    {
        try {
            $aDetails = [
                'countryCode' => strtoupper($sCountryCode),
                'vatNumber' => $sVatNumber
            ];
            return $this->oClient->checkVat($aDetails);
        } catch(SoapFault $oExcept) {
            //trigger_error('Impossible to retrieve the VAT details: ' . $oExcept->faultstring);
            throw new Exception('Impossible to retrieve the VAT details: ' . $oExcept->faultstring);
            return new stdClass;
        }
    }
}
