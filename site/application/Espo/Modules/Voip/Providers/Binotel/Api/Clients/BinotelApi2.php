<?php

// CLIENT
class BinotelApi
{
    protected $key;
    protected $secret;

    protected $apiHost = 'https://my.binotel.ua/api/';
    protected $apiVersion = '2.0';
    protected $apiFormat = 'json';

    protected $disableSSLChecks = false;

    public $debug = false;

    public function __construct($key, $secret, $apiHost = null, $apiVersion = null, $apiFormat = null)
    {
        $this->key = $key;
        $this->secret = $secret;

        if (!is_null($apiHost)) $this->apiHost = $apiHost;
        if (!is_null($apiVersion)) $this->apiVersion = $apiVersion;
        if (!is_null($apiFormat)) $this->apiFormat = $apiFormat;
    }

    public function sendRequest($url, array $params)
    {
        if ($this->debug) printf("[CLIENT] Send request: %s\n", json_encode($params));

        $params['signature'] = $this->getSingnatureByRequest($params);
        $params['key'] = $this->key;

        $url = $this->apiHost . $this->apiVersion .'/'. $url .'.'. $this->apiFormat;

        // Send request
        $ch = curl_init();
        $postData = json_encode($params);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);

        if ($this->disableSSLChecks) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Length: ' . mb_strlen($postData),
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            if ($this->debug) printf("[CLIENT] curl_error: %s\n", curl_error($ch));
            return;
        }

        curl_close($ch);

        if ($this->debug) printf("[CLIENT] Server response code: %d\n", $code);

        if ($code !== 200) {
            // TODO: handle 50x 40x and log somewhere
            if ($this->debug) printf("[CLIENT] Server error: %s\n", $result);
            return;
        }

        $decodeResult = json_decode($result, true);

        if (is_null($decodeResult)) {
            if ($this->debug) printf("[CLIENT] Server sent invalid data: %s\n", $result);
            return;
        }

        $signature = $decodeResult['signature'];
        unset($decodeResult['signature']);

        $expectedSignature = $this->getSingnatureByRequest($decodeResult);
        if ($expectedSignature !== $signature) {
            if ($this->debug) printf("[CLIENT] Bad server signature: %s !== %s (expected)\n", $signature, $expectedSignature);
            return;
        }

        return $decodeResult;
    }

    public function handleBinotelCallback()
    {
        $inputContent = file_get_contents('php://input');
        $data = json_decode($inputContent, true); // read raw post data

        if (is_null($data)) {
            if ($this->debug) error_log(printf("[CLIENT] Server sent invalid data: %s\n", $inputContent));
            return;
        }

        $signature = $data['signature'];
        unset($data['signature']);

        $expectedSignature = $this->getSingnatureByRequest($data);
        if ($expectedSignature !== $signature) {
            if ($this->debug) error_log(sprintf("[CLIENT] Bad server signature: %s !== %s (expected)", $signature, $expectedSignature));
            return false;
        }

        return $data;
    }

    public function disableSSLChecks()
    {
        $this->disableSSLChecks = true;
    }

    protected function getSingnatureByRequest(array $params)
    {
        ksort($params);
        $signature = md5($this->secret . json_encode($params));
        return $signature;
    }
}
