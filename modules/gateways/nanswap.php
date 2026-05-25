<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}


function nanswap_MetaData()
{
    return array(
        'DisplayName' => 'Nanswap Pay',
        'APIVersion' => '1.2', // Use API Version 1.1
    );
}


function nanswap_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Nanswap Pay',
        ),
        'apiKey' => array(
            'FriendlyName' => 'Nanswap Pay API Public Key',
            'Type' => 'text',
            'Default' => '',
            'Description' => 'Enter your Nanswap Pay API Public Key here',
        ),
        'callbackSecret' => array(
            'FriendlyName' => 'Nanswap Pay Callback Secret',
            'Type' => 'text',
            'Default' => '',
            'Description' => 'Enter your Nanswap Pay Callback Secret here',
        )
    );
}


function nanswap_link($params)
{
    $origin = $_SERVER['HTTP_ORIGIN'];
    $path = array_filter(explode('/', parse_url($_SERVER['REQUEST_URI'])['path']));
    $logoUrl = "https://images.nanswap.com/logo/pay-in-crypto-white.svg";
    $ipnUrl = $params['systemurl'] . 'modules/gateways/callback/nanswap.php';
    if(empty($params['systemurl'])) {
        if(count($path) > 1) {
            array_pop($path);
            $prefix = implode('/', $path);
            $ipnUrl = $origin . '/' . $prefix . '/modules/gateways/callback/nanswap.php';
        } else {
            $ipnUrl = $origin . '/modules/gateways/callback/nanswap.php';
        }
    }
    $orderId = 'WHMCS-' . $params['invoiceid'];
    $nanswapArgs = [
        'callbackUrl' => $ipnUrl,
        'successURL' => $params['systemurl'].'viewinvoice.php?id='.$params['invoiceid'].'&paymentsuccess=true',
        'cancelURL' => $params['systemurl'].'viewinvoice.php?id='.$params['invoiceid'].'&paymentfailed=true',
        'dataSource' => 'whmcs',
        'priceCurrency' => mb_strtoupper($params['currency']),
        'publicKey' => $params['apiKey'],
        'customerName' => $params['clientdetails']['firstname'],
        'customerEmail' => $params['clientdetails']['email'],
        'priceAmount' => $params['amount'],
        'invoiceId' => $orderId,
    ];
    if (isset($params['companyname']) && !empty($params['companyname'])) {
        $nanswapArgs['shopName'] = $params['companyname'];
    }

    $url = 'https://nanswap.com/pay/invoice?data=';
    $nanswap_adr = $url . urlencode(json_encode($nanswapArgs));
    $htmlOutput = '<a href="' . $nanswap_adr . '" target="_blank">';
    $htmlOutput .= '<img  src="'.$logoUrl.'" alt="Nanswap Pay" style="width:200px" />';
    $htmlOutput .= ' </a>';

    return $htmlOutput;
}
