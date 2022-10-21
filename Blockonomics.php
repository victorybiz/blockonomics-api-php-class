<?php

namespace App\Classes;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use App\Models\Setting;

class Blockonomics {

    /**
     * Base URL for the Blockonomics API
     * @var string
     */
    protected $baseUrl = 'https://www.blockonomics.co/api';

    /**
     * Callback secret
     * @var string
     */
    protected $callbackSecret = '';

    /**
     * API Key
     * @var string
     */
    protected $apiKey = '';

    /**
     * Xpub
     * @var string
     */
    protected $xpub = '';

    /**
     * Constructor
     */
    public function __construct()
    { 
        try {
            $blockonomicsCallbackSecret = Setting::getValue('blockonomics_callback_secret');
            if ($blockonomicsCallbackSecret) {
                $this->callbackSecret = Crypt::decryptString($blockonomicsCallbackSecret);
            }            

            $blockonomicsApiKey = Setting::getValue('blockonomics_api_key');
            if ($blockonomicsApiKey) {
                $this->apiKey = Crypt::decryptString($blockonomicsApiKey);
            }
            
            $blockonomicsWalletXpub = Setting::getValue('blockonomics_wallet_xpub');
            if ($blockonomicsWalletXpub) {
                $xpub = Crypt::decryptString($blockonomicsWalletXpub);
                $this->xpub = substr($xpub, 4, 6); 
            }
        } catch (DecryptException $e) {
            if (config('app.debug')){
                echo 'Blockonomics Error: ' . $e->getMessage();
            }
        }
    }

    /**
     * Get btc price
     */
    public function getBtcPrice($currency = 'USD')
    {
        $url = $this->baseUrl . '/price';
        try {
			$client = new \GuzzleHttp\Client(); // Guzzle HTTP Client
			$response = $client->request('GET', $url, [
                'query' => [
                    'currency' => $currency,
                ],
			]);        
			if ($response->getStatusCode() == 200) {
				$responseContent = $response->getBody()->getContents();
                $responseData = json_decode($responseContent);
				return $responseData->price;			   
			} else {
				return false;
			}    
		} catch(\Exception $e) {
            if (config('app.debug')){
                echo 'Blockonomics Error: ' . $e->getMessage();
            }
			return false;
		}
    }

    /**
     * Generate a new address
     */
    public function generateAddress($resetAddress = false)
    {
        try {
            $url = $this->baseUrl . '/new_address';
			$client = new \GuzzleHttp\Client(); // Guzzle HTTP Client
			$response = $client->request('POST', $url, [
                'query' => [
                    'match_account' => $this->xpub,
                    'reset' => $resetAddress ? 1 : 0,
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey
                ]
			]); 
			if ($response->getStatusCode() == 200) {
				$responseContent = $response->getBody()->getContents();
				$responseData = json_decode($responseContent);
				return $responseData->address;		   
			} else {
				return false;
			}    
		} catch(\Exception $e) {
            if (config('app.debug')){
                echo 'Blockonomics Error: ' . $e->getMessage();
            }
			return false;
		}
    }

    /**
     * Handle callback from Blockonomics
     */
    public function handleCallback(\Illuminate\Http\Request $request)
    {
        $status = $request->query('status'); // status is the status of tx. 0-Unconfirmed, 1-Partially Confirmed, 2-Confirmed
        $txid = $request->query('txid'); // txid is the id of the paying transaction
        $addr = $request->query('addr'); // addr is the receiving address
        $value = $request->query('value'); // value is the recevied payment amount in satoshis
        $rbf = $request->query('rbf'); // For unconfirmed transactions an rbf attribute may be returned
        $secret = $request->query('secret'); // secret is the callback secret

        // Match secret for security
        if ($secret != $this->callbackSecret) {
            return false;
        }

        if (!$addr) {
            return false;
        }

        return [
            'status' => $status,            
            'txid' => $txid,
            'addr' => $addr,
            'value' => $value,
            'rbf' => $rbf,
        ];
    }
}
