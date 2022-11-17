<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM VoIP Integration
 * Extension Agreement ("License") which can be viewed at
 * https://www.espocrm.com/voip-extension-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 * 
 * Copyright (C) 2015-2021 Letrium Ltd.
 * 
 * License ID: e36042ded1ed7ba87a149ac5079bd238
 ***********************************************************************************/

namespace Espo\Modules\Voip\Core\Utils;

class Voip
{
    /**
     * Format phone number. Ex. (goip-324/40995465357, 10) = 0995465357
     *
     * @param  string  $phoneNumber
     * @param  integer $length
     * @param  array  $replacement
     *
     * @return string
     */
    public static function formatNumber($phoneNumber, $length = null, array $replacement = null)
    {
        if (isset($replacement)) {
            foreach ($replacement as $pattern => $replace) {
                $phoneNumber = preg_replace('/'.$pattern.'/', $replace, $phoneNumber);
            }
        }

        $formattedNumber = preg_replace('/[^0-9\+]/', '', $phoneNumber);
        $formattedNumber = substr($formattedNumber, 0, 1) . preg_replace('/\+/', '', substr($formattedNumber, 1));

        if (isset($length)) {
            $phoneLength = '-' . $length;
            $formattedNumber = substr($formattedNumber, $phoneLength);
        }

        return $formattedNumber;
    }

    /**
     * Format the line. Ex. (goip-323/40995465357, 10) = 4
     *
     * @param  string  $phoneNumber
     * @param  integer $phoneLength
     *
     * @return string
     */
    public static function formatLine($phoneNumber, $phoneLength = 10)
    {
        $line = substr($phoneNumber, 0, '-' .$phoneLength);

        if (strstr($line, '/')) {
            $lineParts = explode('/', $line);
            $line = end($lineParts);
        }

        return $line;
    }

    /**
     * Normalize line. E.g. (Asterisk:1), the result "1". E.g. (Asterisk:1, CONNECTOR), the result "Asterisk".
     *
     * @param  string $line
     * @param  string $return
     *
     * @return string
     */
    public static function normalizeLine($line, $return = 'LINE')
    {
        $lineOpt = explode(':', $line);

        switch ($return) {
            case 'LINE':
                if (isset($lineOpt[1])) {
                    $line = $lineOpt[1];
                }
                break;

           case 'CONNECTOR':
                $line = $lineOpt[0];
                break;
        }

        return $line;
    }

    /**
     * Combine the field value with the connector
     *
     * @param  string $fieldValue
     * @param  string $connector
     *
     * @return string
     */
    public static function combineFieldValue($fieldValue, $connector = null)
    {
        if (isset($connector)) {
            return $connector . ':' . $fieldValue;
        }

        return $fieldValue;
    }

    /**
     * Check if a phone number exists in the array
     *
     * @param  string  $phoneNumber
     * @param  array   $numberList
     *
     * @return boolean
     */
    public static function isNumberExists($phoneNumber, array $numberList)
    {
        $withoutZero = ltrim($phoneNumber, '0');

        foreach ($numberList as $number) {
            $numbers = array($number, ltrim($number, '0'));
            if (in_array($phoneNumber, $numbers) || in_array($withoutZero, $numbers)) {
                return true;
            }
        }

        return false;
    }

    public static function isSipPhoneNumber($phoneNumber)
    {
        if (preg_match('/.*@.*/', $phoneNumber)) {
            return true;
        }

        return false;
    }

    /**
     * Get first value from array based on $keyList
     * @param  array  $data
     * @param  array  $keyList
     * @return mixed
     */
    public static function getFirstValueByKeys(array $data, array $keyList, $default = null)
    {
        foreach ($keyList as $keyName) {
            if (isset($data[$keyName])) {
                return $data[$keyName];
            }
        }

        return $default;
    }

    public static function getFileDataByUrl($url, array $permittedFileTypeList = null)
    {
        $type = null;

        if (function_exists('curl_init')) {
            $opts = [];
            $httpHeaders = [];
            $httpHeaders[] = 'Expect:';
            $opts[\CURLOPT_URL]  = $url;
            $opts[\CURLOPT_HTTPHEADER] = $httpHeaders;
            $opts[\CURLOPT_CONNECTTIMEOUT] = 10;
            $opts[\CURLOPT_TIMEOUT] = 10;
            $opts[\CURLOPT_HEADER] = true;
            $opts[\CURLOPT_BINARYTRANSFER] = true;
            $opts[\CURLOPT_VERBOSE] = true;
            $opts[\CURLOPT_SSL_VERIFYPEER] = false;
            $opts[\CURLOPT_SSL_VERIFYHOST] = 2;
            $opts[\CURLOPT_RETURNTRANSFER] = true;
            $opts[\CURLOPT_FOLLOWLOCATION] = true;
            $opts[\CURLOPT_MAXREDIRS] = 2;
            $opts[\CURLOPT_IPRESOLVE] = \CURL_IPRESOLVE_V4;

            $ch = curl_init();
            curl_setopt_array($ch, $opts);
            $response = curl_exec($ch);

            $headerSize = curl_getinfo($ch, \CURLINFO_HEADER_SIZE);

            $header = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);

            $headLineList = explode("\n", $header);
            foreach ($headLineList as $i => $line) {
                if ($i === 0) continue;
                if (strpos(strtolower($line), strtolower('Content-Type:')) === 0) {
                    $part = trim(substr($line, 13));
                    if ($part) {
                        $type = trim(explode(";", $part)[0]);
                    }
                }
            }

            if (!$type) return;

            if (!empty($permittedFileTypeList) && !in_array($type, $permittedFileTypeList)) {
                return;
            }

            return [
                'type' => $type,
                'contents' => $body
            ];

            curl_close($ch);
        }
    }

    public static function sendRemoteRequest($method, $url, array $data = null)
    {
        $method = strtoupper($method);

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($method != 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        $headerList = [];

        if (isset($data)) {
            if ($method == 'GET') {
                curl_setopt($ch, CURLOPT_URL, $url. '?' . http_build_query($data));
            } else {
                $payload = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                $headerList[] = 'Content-Type: application/json';
                $headerList[] = 'Content-Length: ' . strlen($payload);
            }
        }

        if (!empty($headerList)) {
            curl_setopt($ch, \CURLOPT_HTTPHEADER, $headerList);
        }

        $response = curl_exec($ch);

        $headerSize = curl_getinfo($ch, \CURLINFO_HEADER_SIZE);
        $parsedResponse = [
            'header' => trim( substr($response, 0, $headerSize) ),
            'body' => substr($response, $headerSize),
        ];

        $responseCode = curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $responseContentType = curl_getinfo($ch, \CURLINFO_CONTENT_TYPE);

        if ($responseCode == 200 && !empty($parsedResponse['body'])) {
            curl_close($ch);

            if (preg_match('/^application\/json/i', $responseContentType)) {
                return json_decode($parsedResponse['body'], true);
            }

            return $parsedResponse['body'];
        }

        curl_close($ch);

        return null;
    }

    public static function normalizerUrl($url)
    {
        $url = trim($url);
        $url = preg_replace('/\/$/', '', $url);
        $url = preg_replace('/\/\?$/', '', $url);

        return $url;
    }
}
