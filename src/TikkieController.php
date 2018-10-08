<?php
/**
 * Painstakingly made by: Nina Barkat
 * Date: 02/10/2018
 */

namespace src;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Jose\Factory\JWKFactory;
use Jose\Factory\JWSFactory;

class TikkieController extends Controller
{

    const CONSUMER_KEY = "";
    const CONSUMER_SECRET = "";


    /**
     * This function generates a JSON WEB TOKEN based on the
     * private key.
     */
    private function generateJSONToken()
    {

        $url = "https://api-sandbox.abnamro.com/v1/oauth/token";

        $client = new Client();

        $headers = [
            'typ' => 'JWT',
            'alg' => 'RS256',
        ];

        $epochNow = time();
        $epochExp = time() + 1000;

        $payload = [
            'nbf' => $epochNow,
            'exp' => $epochExp,
            'iss' => 'me',
            'sub' => SELF::CONSUMER_KEY,
            'aud' => 'https://auth-sandbox.abnamro.com/oauth/token',
        ];

        //sign with RS256
        $privateKeyPath = "/var/www/spar_bags/app/keys/private_rsa.pem";

        $key = JWKFactory::createFromKeyFile($privateKeyPath);

        $jws = JWSFactory::createJWSToCompactJSON(
            $payload, // The payload or claims to sign
            $key, // The key used to sign
            $headers // Protected headers
        );

        return $jws;

    }

    /**
     * This function is used to authenticate in order to start
     * using the platform.
     */
    public function authenticate()
    {

        $url = "https://api-sandbox.abnamro.com/v1/oauth/token";

        $client = new Client();

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'API-Key' => SELF::CONSUMER_KEY,
        ];

        $JSONtoken = $this->generateJSONToken();

        $body = [
            'form_params' => [
                'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
                'client_assertion' => $JSONtoken,
                'grant_type' => 'client_credentials',
                'scope' => 'tikkie',
            ],
        ];

        $request = new Request('POST', $url, $headers);

        try {
            $response = $client->send($request, $body);
        } catch (Exception $e) {
            return $e->getResponse();
        }

        $token = json_decode($response->getBody());

        return $token->access_token;
    }

    /**
     * This function enrolls a new payment request platform and returns
     *  a platform token, which can be used to register new users and create
     * new payment requests.
     */

    public function createPlatform($name,$phoneNumber,$email)
    {

        $url = "https://api-sandbox.abnamro.com/v1/tikkie/platforms";

        $client = new Client();

        $headers = [
            'Authorization' => 'Bearer ' . $this->authenticate(),
            'API-Key' => SELF::CONSUMER_KEY,
            'Content-Type' => 'application/json',
        ];

        $body = [
            'json' => [
                'name' => $name,
                'phoneNumber' => $phoneNumber,
                'email' => $email,
                'platformUsage' => 'PAYMENT_REQUEST_FOR_MYSELF',
            ],
        ];

        $request = new Request('POST', $url, $headers);

        try {
            $response = $client->send($request, $body);
        } catch (Exception $e) {
            return $e->getResponse();
        }

    }

    /**
     * This operation fetches the list of all platsforms
     *  for a client.
     */
    public function getPlatforms($authToken)
    {
        $url = "https://api-sandbox.abnamro.com/v1/tikkie/platforms";

        $client = new Client();

        $headers = [
            'Authorization' => 'Bearer ' . $authToken,
            'API-Key' => SELF::CONSUMER_KEY,
        ];

        $request = new Request('GET', $url, $headers);

        try {
            $response = $client->send($request);
        } catch (Exception $e) {
            return $e->getResponse();
        }

        return json_decode($response->getBody());

    }

    /**
     * This operation enrolls a new user on a platform and
     * returns a user token. Make sure to use a VALID EXISTING IBAN number.
     */
    public function createUser($name,$phoneNumber,$iban,$bankAccountLabel)
    {
        $authToken = $this->authenticate();
        $platformToken = $this->getPlatforms($authToken)[0]->platformToken;

        $url = "https://api-sandbox.abnamro.com/v1/tikkie/platforms/" . $platformToken . "/users";

        $client = new Client();

        $headers = [
            'Authorization' => 'Bearer ' . $authToken,
            'API-Key' => SELF::CONSUMER_KEY,
            'Content-Type' => 'application/json',
        ];

        $body = [
            'json' => [
                'name' => $name,
                'phoneNumber' => $phoneNumber,
                'iban' => $iban,
                'bankAccountLabel' => $bankAccountLabel
            ],
        ];

        $request = new Request('POST', $url, $headers);

        try {
            $response = $client->send($request, $body);
        } catch (Exception $e) {
            return $e->getResponse();
        }

        return json_decode($response->getBody());
    }

    /**
     * This operation fetches the list of all users
     *  on a platform for a client.
     */
    public function getUsers($authToken)
    {
        $platformToken = $this->getPlatforms($authToken)[0]->platformToken;

        $url = "https://api-sandbox.abnamro.com/v1/tikkie/platforms/" . $platformToken . "/users";

        $client = new Client();

        $headers = [
            'Authorization' => 'Bearer ' . $authToken,
            'API-Key' => SELF::CONSUMER_KEY,
        ];

        $request = new Request('GET', $url, $headers);

        try {
            $response = $client->send($request);
        } catch (Exception $e) {
            return $e->getResponse();
        }

        return json_decode($response->getBody());
    }

    /**
     * This operation creates a new payment request for
     *  a given bankaccount.
     */
    public function createPayment($amountInCents, $description)
    {
        $authToken = $this->authenticate();
        $platformToken = $this->getPlatforms($authToken)[0]->platformToken;
        $userToken = $this->getUsers($authToken)[0]->userToken;
        $bankAccountToken = $this->getUsers($authToken)[0]->bankAccounts[0]->bankAccountToken;


        $url = "https://api-sandbox.abnamro.com/v1/tikkie/platforms/$platformToken/users/$userToken/bankaccounts/$bankAccountToken/paymentrequests";

        $client = new Client();

        $headers = [
            'Authorization' => 'Bearer ' . $authToken,
            'API-Key' => SELF::CONSUMER_KEY,
            'Content-Type' => 'application/json',
        ];

        $body = [
            'json' => [
                'amountInCents' => $amountInCents,
                'currency' => 'EUR',
                'description' => $description
            ],
        ];

        $request = new Request('POST', $url, $headers);

        try {
            $response = $client->send($request, $body);
        } catch (Exception $e) {
            return $e->getResponse();
        }

        return json_decode($response->getBody());
    }

    /**
     * This operation returns a specific payment request
     *  with the given payment request token.
     *  For the given payment request, only the payments that
     *  are actually paid are returned.
     */
    public function getPaymentByRequestToken($paymentRequestToken)
    {
        $authToken = $this->authenticate();
        $platformToken = $this->getPlatforms($authToken)[0]->platformToken;
        $userToken = $this->getUsers($authToken)[0]->userToken;
        $url = "https://api-sandbox.abnamro.com//v1/tikkie/platforms/$platformToken/users/$userToken/paymentrequests/$paymentRequestToken";
        $client = new Client();

        $headers = [
            'Authorization' => 'Bearer ' . $authToken,
            'API-Key' => SELF::CONSUMER_KEY,
        ];

        $request = new Request('GET', $url, $headers);

        try {
            $response = $client->send($request);
        } catch (Exception $e) {
            return $e->getResponse();
        }

        return json_decode($response->getBody());
    }
}
