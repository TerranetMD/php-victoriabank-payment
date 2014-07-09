<?php

namespace Service;

use Service\Victoriabank as VB;

class Victoriabank{

    const TRX_TYPE_AUTHORIZATION    = 0;
    const TRX_TYPE_COMPLETION       = 21;
    const TRX_TYPE_REVERSAL         = 24;

    /**
     * @var bool
     */
    private $_debugMode         = false;

    /**
     * @var array
     */
    private $_acceptedLangs     = array('en','ro','ru');

    /**
     * @var string
     */
    private $_timezoneName      = 'Europe/Chisinau';

    /**
     * @var string
     */
    private $_merchant          = null;

    /**
     * @var string
     */
    private $_terminal          = null;

    /**
     * @var string
     */
    private $_currency          = 'MDL';

    /**
     * @var null
     */
    private $_timezoneOffset    = '+2';

    /**
     * @var null
     */
    private $_language          = 'en';

    /**
     * @var string
     */
    private $_countryCode       = 'md';

    /**
     * @var null
     */
    private $_requestTimestamp  = null;

    /**
     * @var null
     */
    private $_request           = null;

    /**
     * @var string
     */
    private $_merchantName      = '';

    /**
     * @var string
     */
    private $_merchantUrl       = '';

    /**
     * @var string
     */
    private $_merchantAddress   = '';

    /**
     * @var string
     */
    private $_backRefUrl        = '';

    /**
     * Victoriabank has strict requirements for order ID.
     * If its length is small the prefix is applied
     * @var string
     */
    private $_orderPrefix       = '00000';

    /**
     * Construct
     */
    public function __construct($merchant, $terminal, $currency=null, $language=null)
    {
        if (!empty($merchant))
            $this->_merchant    = $merchant;

        if (!empty($terminal))
            $this->_terminal    = $terminal;

        if (!empty($currency))
            $this->_currency    = $currency;

        #Calcualte timezone offset
        $this->_updateTimezoneOffset();

        #Set language
        if (!is_null($language) && strlen($language) >= 2)

            if (in_array($language, $this->_acceptedLangs)){
                $this->_language    = $language;

            } elseif (in_array(substr($language, 0, 2), $this->_acceptedLangs)){
                $this->_language    = substr($language, 0, 2);
            }

        #Set timezone for dates construction
        date_default_timezone_set($this->_timezoneName);

        return $this;
    }

    /**
     * @return $this
     */
    protected function _updateTimezoneOffset()
    {
        $dateTimeZone           = new \DateTimeZone($this->_timezoneName);
        $this->_timezoneOffset  = (float)$dateTimeZone->getOffset(new \DateTime("now"))/60/60;

        if ($this->_timezoneOffset>0)
            $this->_timezoneOffset  = '+' . (string)$this->_timezoneOffset;

        return $this;
    }

    /**
     * VB accepts order ID not less than 6 characters long
     * @param $code
     * @return string
     */
    public function normalizeOrderId($code)
    {
        if (strlen($code) < 6)
            return $this->_orderPrefix . $code;

        return $code;
    }

    /**
     * VB accepts order ID not less than 6 characters long
     * @param $code
     * @return mixed
     */
    public function deNormalizeOrderId($code)
    {
        if (0 === strpos($code, $this->_orderPrefix))
            return  substr_replace($code, '', 0, strlen($this->_orderPrefix));

        return $code;
    }

    /**
     * Debug mode setter
     * @param boolean $debugMode
     * @return $this
     */
    public function setDebugMode($debugMode)
    {
        $this->_debugMode   = (boolean)$debugMode;

        return $this;
    }

    /**
     * Timezone name setter. Used to calculate the timezone offset sent to Victoriabank
     * @param $tzName
     * @return $this
     */
    public function setTimezoneName($tzName)
    {
        $this->_timezoneName    = $tzName;

        return $this->_updateTimezoneOffset();
    }

    /**
     * Language used by Victoriabank to display forms setter
     * @param $lang
     * @return $this
     * @throws Victoriabank\Exception
     */
    public function setLanguage($lang)
    {
        if (!in_array($lang,$this->_acceptedLangs))
            throw new VB\Exception("The language '{$lang}' is not accepted by Victoriabank");

        $this->_language    = $lang;

        return $this;
    }

    /**
     * Merchant shop country code setter
     * @param $countryCode - two letter country code
     * @return $this
     */
    public function setCountryCode($countryCode)
    {
        $this->_countryCode = $countryCode;

        return $this;
    }

    /**
     * Merchant name setter
     * @param $name
     * @return $this
     */
    public function setMerchantName($name)
    {
        $this->_merchantName    = $name;

        return $this;
    }

    /**
     * Merchant address setter
     * @param $address
     * @return $this
     */
    public function setMerchantAddress($address)
    {
        $this->_merchantAddress    = $address;

        return $this;
    }

    /**
     * Merchant URL setter
     * @param $url
     * @return $this
     */
    public function setMerchantUrl($url)
    {
        $this->_merchantUrl    = $url;

        return $this;
    }

    /**
     * BackRef URL setter
     * @param $url
     * @return $this
     */
    public function setBackRefUrl($url)
    {
        $this->_backRefUrl    = $url;

        return $this;
    }

    public function setSecurityOptions($signatureFirst, $signaturePrefix, $signaturePadding, $publicKeyPath, $privateKeyPath, $bankPublicKeyPath)
    {
        #Request security options
        VB\Request::$signatureFirst     = $signatureFirst;
        VB\Request::$signaturePrefix    = $signaturePrefix;
        VB\Request::$signaturePadding   = $signaturePadding;
        VB\Request::$publicKeyPath      = $publicKeyPath;
        VB\Request::$privateKeyPath     = $privateKeyPath;

        #Response security options
        VB\Response::$signaturePrefix   = $signaturePrefix;
        VB\Response::$bankPublicKeyPath = $bankPublicKeyPath;

        return $this;
    }

    /**
     * Perform an authorization request
     * @param $amount
     * @param $orderID
     * @param string $orderDescription
     * @param string $clientEmail
     * @return mixed|void
     * @throws Victoriabank\Exception
     */
    public function requestAuthorization($amount, $orderID, $orderDescription='', $clientEmail='')
    {
        try{
            $this->_requestTimestamp    = gmdate('YmdHis');

            $this->_request    = new VB\Authorization_Request(array(
                VB\Authorization_Request::AMOUNT        => str_replace(',','.',(string)$amount),
                VB\Authorization_Request::CURRENCY      => $this->_currency,
                VB\Authorization_Request::ORDER         => $this->normalizeOrderId($orderID),
                VB\Authorization_Request::DESC          => (!empty($orderDescription)?$orderDescription:"Order {$orderID} payment"),
                VB\Authorization_Request::MERCH_NAME    => $this->_merchantName,
                VB\Authorization_Request::MERCH_URL     => $this->_merchantUrl,
                VB\Authorization_Request::MERCHANT      => $this->_merchant,
                VB\Authorization_Request::TERMINAL      => $this->_terminal,
                VB\Authorization_Request::EMAIL         => $clientEmail,
                VB\Authorization_Request::MERCH_ADDRESS => $this->_merchantAddress,
                VB\Authorization_Request::COUNTRY       => $this->_countryCode,
                VB\Authorization_Request::MERCH_GMT     => $this->_timezoneOffset,
                VB\Authorization_Request::TIMESTAMP     => $this->_requestTimestamp,
                VB\Authorization_Request::NONCE         => md5(mt_rand()),
                VB\Authorization_Request::BACKREF       => $this->_backRefUrl,
                VB\Authorization_Request::LANG          => $this->_language,
            ), $this->_debugMode);

            return $this->_request->request();

        } catch(VB\Exception $e) {
            if ($this->_debugMode)
                throw $e;
            else
                throw new VB\Exception('Authorization request to the payment gateway failed. Please contact ' . $this->_merchantUrl . ' for further details');
        }
    }

    /**
     * @param $amount
     * @param $orderID
     * @param $rrn
     * @param $intRef
     * @return mixed|void
     * @throws Victoriabank\Exception
     */
    public function requestCompletion($amount, $orderID, $rrn, $intRef)
    {
        try {
            $this->_requestTimestamp    = gmdate('YmdHis');

            $this->_request    = new VB\Completion_Request(array(
                VB\Completion_Request::ORDER        => $orderID,
                VB\Completion_Request::AMOUNT       => str_replace(',','.',(string)$amount),
                VB\Completion_Request::CURRENCY     => $this->_currency,
                VB\Completion_Request::RRN          => $rrn,
                VB\Completion_Request::INT_REF      => $intRef,
                VB\Completion_Request::TERMINAL     => $this->_terminal,
                VB\Completion_Request::TIMESTAMP    => $this->_requestTimestamp,
                VB\Completion_Request::NONCE        => md5(mt_rand())
            ), $this->_debugMode);

            return $this->_request->request();

        } catch (VB\Exception $e) {
            if ($this->_debugMode)
                throw $e;
            else
                throw new VB\Exception('Completion request to the payment gateway failed. Please contact ' . $this->_merchantUrl . ' for further details.' . $e->getMessage());
        }
    }

    /**
     * @param $amount
     * @param $orderID
     * @param $rrn
     * @param $intRef
     * @return mixed|void
     * @throws Victoriabank\Exception
     */
    public function requestReversal($amount, $orderID, $rrn, $intRef)
    {
        try {
            $this->_requestTimestamp    = gmdate('YmdHis');

            $this->_request             = new VB\Reversal_Request(array(
                VB\Completion_Request::ORDER        => $orderID,
                VB\Completion_Request::AMOUNT       => str_replace(',','.',(string)$amount),
                VB\Completion_Request::CURRENCY     => $this->_currency,
                VB\Completion_Request::RRN          => $rrn,
                VB\Completion_Request::INT_REF      => $intRef,
                VB\Completion_Request::TERMINAL     => $this->_terminal,
                VB\Completion_Request::TIMESTAMP    => $this->_requestTimestamp,
                VB\Completion_Request::NONCE        => md5(mt_rand())
            ), $this->_debugMode);

            return $this->_request->request();

        } catch (VB\Exception $e) {
            if ($this->_debugMode)
                throw $e;
            else
                throw new VB\Exception('Completion request to the payment gateway failed. Please contact ' . $this->_merchantUrl . ' for further details.' . $e->getMessage());
        }
    }

    /**
     * Identifies the type of response object based on the received data over post from the bank
     * @param $post
     * @return VB\Authorization_Response|VB\Completion_Response|VB\Reversal_Response
     * @throws Victoriabank\Exception
     */
    public function getResponseObject($post)
    {
        if (!isset($post[VB\Response::TRTYPE]))
            throw new VB\Exception('Invalid response data');

        switch ($post[VB\Response::TRTYPE]) {
            case VB\Authorization_Response::TRX_TYPE:
                return new VB\Authorization_Response($post);
                break;
            case VB\Completion_Response::TRX_TYPE:
                return new VB\Completion_Response($post);
                break;
            case VB\Reversal_Response::TRX_TYPE:
                return new VB\Reversal_Response($post);
                break;
            default:
                throw new VB\Exception('No response object found for the provided data');
        }
    }
}

