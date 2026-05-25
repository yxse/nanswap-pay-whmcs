# WHMCS Nanswap Pay Gateway Module #

Accept Nano and +1,000 cryptocurrencies via [Nanswap Pay](https://nanswap.com/pay) in WHMCS.

## Installation ##

1. In your [Nanswap Pay account](https://nanswap.com/account?tab=nanswap-pay) generate your `webhook secret` and `public key` in `Account->Nanswap Pay->API & Webhook` 
2. Copy the `modules/gateways/` contents into your WHMCS `modules/gateways/` directory
3. Activate plugin and configure `public key` and `webhook secret` with the values from step 1


## Path ##
```
modules/gateways/
  |- nanswap/whmcs.json
  |- nanswap/logo.png
  |- callback/nanswap.php
  |  nanswap.php
```