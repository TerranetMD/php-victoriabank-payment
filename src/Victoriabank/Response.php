<?php

namespace Service\Victoriabank;

/**
 * Class Response
 * @package Service\Victoriabank
 */
abstract class Response{

    const TRX_TYPE      = 0;

    const TERMINAL      = 'TERMINAL';   #Size: 8, Echo from the request
    const TRTYPE        = 'TRTYPE';     #Size: 2, Echo from the request
    const ORDER         = 'ORDER';      #Size: 6-32, Echo from the request
    const AMOUNT        = 'AMOUNT';     #Size: 12, Echo from the request
    const CURRENCY      = 'CURRENCY';   #Size: 3, Echo from the request
    const ACTION        = 'ACTION';     #Size: 1, E-Gateway action code: 0 – Transaction successfully completed;1 – Duplicate transaction detected;2 – Transaction declined;3 – Transaction processing fault.
    const RC            = 'RC';         #Size: 02, Transaction response code (ISO-8583 Field 39)
    const APPROVAL      = 'APPROVAL';   #Size: 06, Client bank’s approval code (ISO-8583 Field 38). Can be empty if not provided by card management system.
    const RRN           = 'RRN';        #Size: 12, Merchant bank’s retrieval reference number (ISO-8583 Field 37).
    const INT_REF       = 'INT_REF';    #Size: 1-32, E-Commerce gateway internal reference number
    const TIMESTAMP     = 'TIMESTAMP';  #Size: 14, E-Commerce gateway timestamp in GMT: YYYYMMDDHHMMSS
    const NONCE         = 'NONCE';      #Size: 1-64, E-Commerce gateway nonce value. Will be filled with 8-32 unpredictable random bytes in hexadecimal format. Will be present if MAC is used.
    const P_SIGN        = 'P_SIGN';     #Size: 1-256, E-Commerce gateway MAC (Message Authentication Code) in hexadecimal form. Will be present if MAC is used.
    const ECI           = 'ECI';        #Size: 0-02, Electronic Commerce Indicator (ECI): ECI=empty – Technical fault;ECI=05 - Secure electronic commerce transaction (fully 3-D Secure authenticated);ECI=06 - Non-authenticated security transaction at a 3-D Secure-capable merchant, and merchant attempted to authenticate the cardholder using 3-D Secure but was unable to complete the authentication because the issuer or cardholder does not participate in the 3-D Secure program;ECI=07 - Non-authenticated Security Transaction

    const STATUS_SUCCESS        = 0;        #Transaction successfully completed
    const STATUS_DUPLICATED     = 1;        #Duplicate transaction detected
    const STATUS_DECLINED       = 2;        #Transaction declined
    const STATUS_FAULT          = 3;        #Transaction processing fault


    /**
     * Public key is provided by Victoriabank
     * @var string
     */
    static public $bankPublicKeyPath    = null;

    /**
     * Provided by Victoriabank
     * @var string
     */
    static public $signaturePrefix  = null;

    /**
     * @var array
     */
    protected $_responseFields   = array(
        self::TERMINAL  => null,
        self::TRTYPE    => null,
        self::ORDER     => null,
        self::AMOUNT    => null,
        self::CURRENCY  => null,
        self::ACTION    => null,
        self::RC        => null,
        self::APPROVAL  => null,
        self::RRN       => null,
        self::INT_REF   => null,
        self::TIMESTAMP => null,
        self::NONCE     => null,
        self::P_SIGN    => null,
        self::ECI       => null,
    );

    /**
     * @var array
     */
    protected $_errors  = array();



    /**
     * Construct
     */
    public function __construct(array $responseData)
    {
        #Make sure to set these static params prior to calling the request
        if(is_null(self::$bankPublicKeyPath))
            throw new Exception('Could not instantiate the bank request - missing parameter bankPublicKeyPath');

        if(is_null(self::$signaturePrefix))
            throw new Exception('Could not instantiate the bank request - missing parameter signaturePrefix');


        if (empty($responseData))
            throw new Exception('Bank response error: Empty data received');

        if (! is_array($responseData))
            $responseData   = (array)$responseData;

        #Set the response fields
        foreach ($this->_responseFields as $k=>&$v) {
            if (isset($responseData[$k]))
                $v  = $responseData[$k];
        }

        return $this;
    }

    /**
     * @return bool
     * @throws Exception
     */
    protected function _validateSignature()
    {
        $action         = $this->_responseFields[self::ACTION];
        $rc             = $this->_responseFields[self::RC];
        $rrn            = $this->_responseFields[self::RRN];
        $order          = $this->_responseFields[self::ORDER];
        $amount         = $this->_responseFields[self::AMOUNT];
        $pSign          = $this->_responseFields[self::P_SIGN];


        $mac            = strlen($action) . $action . strlen($rc) . $rc . strlen($rrn) . $rrn . strlen($order) . $order . strlen($amount) . $amount;
        $macHash        = strtoupper(md5($mac));

        $encryptedBin   = hex2bin($pSign);
        $decryptedBin   = null;

        if (!file_exists(self::$bankPublicKeyPath) || !$rsaKey = file_get_contents(self::$bankPublicKeyPath))
            throw new Exception('Failed to generate response signature: Bank key not accessible');

        if (!$rsaKeyResource = openssl_get_publickey($rsaKey))
            throw new Exception('Failed to generate response signature: Failed to init bank key');


        if (!openssl_public_decrypt ($encryptedBin,$decryptedBin,$rsaKey)) {
            $errorMsg   = '';
            while ($msg = openssl_error_string())
                $errorMsg .= $msg . "<br />\n";

            throw new Exception('Failed to generate response signature: ' . $errorMsg);
        }

        $decrypted      = strtoupper(bin2hex($decryptedBin));

        $decryptedHash  = str_replace(self::$signaturePrefix,'',$decrypted);

        if ($decryptedHash==$macHash)
            return true;

        return false;
    }



    /**
     * Validates the response
     */
    protected function _validateResponse()
    {
        if (!isset($this->_responseFields[self::ACTION]))
            throw new Exception('Bank response: Invalid data received');

        switch ($this->_responseFields[self::ACTION]){
            case self::STATUS_DUPLICATED:
                throw new Exception('Bank response: Duplicate transaction');

            case self::STATUS_DECLINED:
                throw new Exception('Bank response: Transaction declined');

            case self::STATUS_FAULT:
                throw new Exception('Bank response: Processing fault');

            default:
                $this->_validateSignature();
                return true;
        }

        return true;
    }

    /**
     * Validates response
     */
    public function isValid()
    {
        try{

            $isValid = $this->_validateResponse();

        } catch (Exception $e) {

            $isValid            = false;
            $this->_errors[]    = $e->getMessage();
        }

        return $isValid;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * @return mixed
     */
    public function getLastError()
    {
        return end($this->_errors);
    }

    /**
     * Magic method to get response fields
     * @param $fieldName
     * @return null
     */
    public function __get($fieldName)
    {
        if (!isset($this->_responseFields[$fieldName]))
            return null;

        return $this->_responseFields[$fieldName];
    }
}

#PHP < 5.4
if ( !function_exists( 'hex2bin' ) ) {
    function hex2bin( $str ) {
        $sbin = "";
        $len = strlen( $str );
        for ( $i = 0; $i < $len; $i += 2 ) {
            $sbin .= pack( "H*", substr( $str, $i, 2 ) );
        }

        return $sbin;
    }
}