<?php

use WHMCS\Billing\Invoice;

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
App::load_function('gateway');
App::load_function('invoice');

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$post = file_get_contents('php://input');
logTransaction($gatewayParams['name'], $post, 'Request received from Nanswap NanShop.');

// Verify HMAC signature
function verifySignature($post, $secret)
{
    if (empty($secret)) {
        return true; // no secret configured, skip verification
    }
    $receivedSig = isset($_SERVER['HTTP_X_NANSWAP_SIG']) ? $_SERVER['HTTP_X_NANSWAP_SIG'] : '';
    if (empty($receivedSig)) {
        return false;
    }
    $data = json_decode($post, true);
    if ($data === null) {
        return false;
    }
    ksort($data);
    $sorted = json_encode($data);
    $expected = hash_hmac('sha512', $sorted, $secret);
    return hash_equals($expected, $receivedSig);
}

if (!verifySignature($post, $gatewayParams['callbackSecret'])) {
    logTransaction($gatewayParams['name'], $post, 'HMAC signature verification failed');
    die('Signature verification failed');
}

$requestData = json_decode($post, true);
if ($requestData === null) {
    logTransaction($gatewayParams['name'], $post, 'Invalid JSON payload');
    die('Invalid payload');
}

$orderId = isset($requestData['order_id']) ? $requestData['order_id'] : '';
$invoiceId = str_replace('WHMCS-', '', $orderId);
$transactionId = isset($requestData['transaction_id']) ? $requestData['transaction_id'] : '';
$status = isset($requestData['status']) ? $requestData['status'] : '';
$payoutAmount = isset($requestData['payout_amount']) ? $requestData['payout_amount'] : 0;
$payoutCurrency = isset($requestData['payout_currency']) ? $requestData['payout_currency'] : '';

$payoutAmountFiat = isset($requestData['price_amount']) ? $requestData['price_amount'] : 0;
$payoutCurrencyFiat = isset($requestData['price_currency']) ? $requestData['price_currency'] : '';
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

$invoice = Invoice::find($invoiceId);
if (is_null($invoice)) {
    logTransaction($gatewayParams['name'], $post, 'No invoice found for ID: ' . $invoiceId);
    die('Invoice not found');
}

// don't process if payout method is dynamic, as funds could be sent to a different address than the merchant's 
if (isset($requestData['payout_method']) && $requestData['payout_method'] === 'dynamic') {
    logTransaction($gatewayParams['name'], $post, 'Dynamic payout method not supported');
    die('Unsupported payout method');
}

// if payout currency fiat doesn't match invoice currency, log and exit
$invoiceCurrencyCode = $invoice->currencyCode;
if ($payoutCurrencyFiat && strcasecmp($payoutCurrencyFiat, $invoiceCurrencyCode) !== 0) {
    logTransaction($gatewayParams['name'], $post, "Fiat currency mismatch. Expected: {$invoiceCurrencyCode}, Received: {$payoutCurrencyFiat}");
    die("Fiat currency mismatch. Expected: {$invoiceCurrencyCode}");
}

// Map NanShop statuses to WHMCS actions
// NanShop statuses: waiting, processing, completed, underpaid, error, processing-error, expired
switch ($status) {
    case 'completed':
        $upper = mb_strtoupper($payoutCurrency);
        $message = "Invoice {$invoiceId} paid. Amount: {$payoutAmount} {$upper} (${$payoutAmountFiat} {$payoutCurrencyFiat}). Transaction: {$transactionId}";
        $invoice->addPaymentIfNotExists($payoutAmountFiat, $transactionId, 0, $gatewayModuleName);
        logTransaction($gatewayParams['name'], $post, $message);
        break;

    case 'processing':
        if ($invoice->getBalanceAttribute()) {
            $invoice->status = 'Payment Pending';
            $invoice->save();
        }
        logTransaction($gatewayParams['name'], $post, 'Order is processing.');
        break;

    case 'underpaid':
        $amountReceived = isset($requestData['amount_received']) ? $requestData['amount_received'] : 0;
        $message = "Invoice {$invoiceId} underpaid. Received: {$amountReceived}. Transaction: {$transactionId}";
        logTransaction($gatewayParams['name'], $post, $message);
        if ($invoice->getBalanceAttribute()) {
            $invoice->status = 'Unpaid';
            $invoice->save();
        }
        break;

    case 'expired':
        logTransaction($gatewayParams['name'], $post, "Invoice {$invoiceId} expired.");
        break;

    case 'error':
    case 'processing-error':
        logTransaction($gatewayParams['name'], $post, "Invoice {$invoiceId} payment error. Status: {$status}");
        break;

    case 'waiting':
        logTransaction($gatewayParams['name'], $post, 'Waiting for payment.');
        break;

    default:
        logTransaction($gatewayParams['name'], $post, "Unknown status: {$status}");
        break;
}

http_response_code(200);
echo 'OK';
