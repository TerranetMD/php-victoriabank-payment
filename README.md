## Victoriabank payment gateway

#### Requirements
* PHP > 5.4

#### Usage

######Request authorization
    use Service\Victoriabank;
    $bankPaymentGateway = new Victoriabank();

    //Set basic info
    $bankPaymentGateway
                ->setMerchantName('Your company name')
                ->setMerchantAddress('Your company address')
                ->setMerchantUrl('http://' . $_SERVER['HTTP_HOST'])
                ->setTimezoneName('Europe/Chisinau')
                ->setLanguage('ro')
                ->setCountryCode('md')
                ->setBackRefUrl('https://' . $_SERVER['HTTP_HOST'] . '/after-payment')
    ;

    //Set security options - provided by the bank
    $bankPaymentGateway
                ->setSecurityOptions($signatureFirst, $signaturePrefix, $signaturePadding, $publicKeyPath, $privateKeyPath, $bankPublicKeyPath)
    ;

    //Request payment authorization - redirects to the banks page
    $bankPaymentGateway
                ->requestAuthorization($webServiceReservation->getReservationAmount(), $code, $paymentDescription, $webServiceReservation->getEmail())
    ;

    //Request payment reversal
    $bankPaymentGateway
                ->requestReversal($amount,$orderId,$rrn,$intRef)
    ;

######Receive bank responses - all bank responses are server to server and are handled by same URI
    use Service\Victoriabank;
    $bankPaymentGateway = new Victoriabank();

    //Set basic info
    $bankPaymentGateway
                ->setMerchantName('Your company name')
                ->setMerchantAddress('Your company address')
                ->setMerchantUrl('http://' . $_SERVER['HTTP_HOST'])
                ->setTimezoneName('Europe/Chisinau')
                ->setLanguage('ro')
                ->setCountryCode('md')
                ->setBackRefUrl('https://' . $_SERVER['HTTP_HOST'] . '/after-payment')
    ;

    //Set security options - provided by the bank
    $bankPaymentGateway
                ->setSecurityOptions($signatureFirst, $signaturePrefix, $signaturePadding, $publicKeyPath, $privateKeyPath, $bankPublicKeyPath)
    ;

    $bankResponse       = $bankPaymentGateway->getResponseObject($_POST);
    $bankOrderCode      = $bankResponse->{Victoriabank\Response::ORDER};
    $systemOrderCode    = $paymentGateway->deNormalizeOrderId($bankOrderCode);
    $verificationCode   = $bankResponse->{Victoriabank\Response::RRN};

    if (!$bankResponse->isValid())
        throw new Victoriabank\Exception('Invalid bank Auth response');

    switch ($bankResponse::TRX_TYPE) {

        case Victoriabank::TRX_TYPE_AUTHORIZATION:

            $amount         = $bankResponse->{Victoriabank\Authorization_Response::AMOUNT};
            $bankOrderCode  = $bankResponse->{Victoriabank\Response::ORDER};
            $rrn            = $bankResponse->{Victoriabank\Response::RRN};
            $intRef         = $bankResponse->{Victoriabank\Response::INT_REF};

            #Funds locked on bank side - transfer the product/service to the customer and request completion
            $bankPaymentGateway->requestCompletion($amount, $bankOrderCode, $rrn, $intRef);

            break;

        case Victoriabank::TRX_TYPE_COMPLETION:
            #Funds successfully transferred on bank side
            break;

        case Victoriabank::TRX_TYPE_REVERSAL:
            #Reversal successfully applied on bank size
            break;

        default:
            throw new Exception('Unknown bank response transaction type');
    }


#### Installation

###### Via Composer
add a following line (root-only) into your composer.json

    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/TerranetMD/paginator.git"
        }
    ]

run

    composer update

###### Via GitHub

    git clone https://github.com/TerranetMD/paginator.git
