<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Advanced Pack
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/advanced-pack-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2021 Letrium Ltd.
 *
 * License ID: 4bc1026aa50a71b8840665043d28bcbc
 ***********************************************************************************/

namespace Espo\Modules\Advanced\Core\Workflow\Actions;

use Espo\ORM\Entity;

use Espo\Core\Exceptions\Error;

use Espo\Modules\Advanced\Core\Workflow\Exceptions\SendRequestError;

class SendRequest extends Base
{
    protected function run(Entity $entity, $actionData)
    {
        $requestType = $actionData->requestType ?? null;
        $contentType = $actionData->contentType ?? null;
        $requestUrl = $actionData->requestUrl ?? null;
        $content = $actionData->content ?? null;
        $additionalHeaders = $actionData->headers ?? [];

        if (!$requestUrl) {
            throw new Error("Empty request URL.");
        }

        if (!$requestType) {
            throw new Error("Empty request type.");
        }

        if (!in_array($requestType, ['POST', 'PUT', 'PATCH', 'DELETE', 'GET'])) {
            throw new Error("Not supported request type.");
        }

        $isGet = $requestType === 'GET';

        $requestUrl = $this->applyVariables($requestUrl);

        $contentTypeList = [
            null,
            'application/json',
            'application/x-www-form-urlencoded',
        ];

        if (!in_array($contentType, $contentTypeList)) {
            throw new Error();
        }

        $isNotJsonPayload = !$contentType || $contentType === 'application/x-www-form-urlencoded';

        if (is_string($content)) {
            $content = $this->applyVariables($content, true);
        }

        $timeout = $this->getConfig()->get('workflowSendRequestTimeout', 7);

        $ch = curl_init();

        curl_setopt($ch, \CURLOPT_URL, $requestUrl);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, \CURLOPT_HEADER, true);
        curl_setopt($ch, \CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, \CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, $requestType);

        $post = null;

        if ($isNotJsonPayload) {
            if ($content) {
                $post = json_decode($content, true);

                foreach ($post as $k => $v) {
                    if (is_array($v)) {
                        $post[$k] = '"' . implode(', ', $v) . '"';
                    }
                }
            }

            //curl_setopt($ch, \CURLOPT_NOBODY, true);
        }
        else if ($contentType === 'application/json') {
            if ($content) {
                $post = $content;
            }
        }

        if (!$isGet) {
            curl_setopt($ch, \CURLOPT_POSTFIELDS, $post);
        }

        $headers = [];

        if ($contentType) {
            $headers[] = 'Content-Type: ' . $contentType;
        }

        foreach ($additionalHeaders as $header) {
            $headers[] = $this->applyVariables($header);
        }

        if (!empty($headers)) {
            curl_setopt($ch, \CURLOPT_HTTPHEADER, $headers);
        }

        $GLOBALS['log']->debug("Workflow: Send request: payload:" . $content);

        $response = curl_exec($ch);

        $code = curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $error = curl_errno($ch);

        $headerSize = curl_getinfo($ch, \CURLINFO_HEADER_SIZE);

        $header = mb_substr($response, 0, $headerSize);
        $body = mb_substr($response, $headerSize);

        curl_close($ch);

        if ($code && $code >= 400 && $code <= 500) {
            throw new SendRequestError(
                "Workflow: Send Request action: {$requestType} {$requestUrl}; Error {$code} response.",
                $code
            );
        }

        if ($error) {
            if (in_array($error, [\CURLE_OPERATION_TIMEDOUT, \CURLE_OPERATION_TIMEOUTED])) {
                throw new Error("Workflow: Send Request action: {$requestUrl}; Timeout.");
            }
        }

        if (!($code >= 200 && $code < 300)) {
            throw new SendRequestError("Workflow: Send Request action: {$code} response.", $code);
        }

        $this->setResponseVariables($body, $code);

        return true;
    }

    protected function setResponseVariables($body, $code)
    {
        if (!$this->hasVariables()) {
            return;
        }

        $this->updateVariables(
            (object) [
                '_lastHttpResponseBody' => $body,
                '_lastHttpResponseCode' => $code,
            ]
        );

        //$this->variables->_lastHttpResponseBody = $body;
    }

    protected function applyVariables(string $content, bool $isJson = false) : string
    {
        $target = $this->getEntity();

        if ($target) {
            foreach ($target->getAttributeList() as $a) {
                $value = $target->get($a) ?? '';

                if (
                    $isJson &&
                    $target->getAttributeParam($a, 'isLinkMultipleIdList') &&
                    $target->get($a) === null
                ) {
                    $value = $target->getLinkMultipleIdList(
                        $target->getAttributeParam($a, 'relation')
                    );
                }

                if (!$isJson && is_array($value)) {
                    $arr = [];

                    foreach ($value as $item) {
                        if (is_string($item)) {
                            $arr[] = str_replace(',', '_COMMA_', $item);
                        }
                    }

                    $value = implode(',', $arr);
                }

                if (is_string($value)) {
                    $value = $isJson ?
                        $this->escapeStringForJson($value) :
                        str_replace(["\r\n", "\r", "\n"], "\\n", $value);
                }
                else if (!is_string($value) && is_numeric($value)) {
                    $value = strval($value);
                }
                else if (is_array($value)) {
                    $value = json_encode($value);
                }

                if (is_string($value)) {
                    $content = str_replace('{$' . $a . '}', $value, $content);
                }
            }
        }

        $variables = $this->getVariables() ?? (object) [];

        foreach (get_object_vars($variables) as $key => $value) {
            if (
                !is_string($value) &&
                !is_int($value) &&
                !is_float($value) &&
                !is_array($value)
            ) {
                continue;
            }

            if (is_int($value) || is_float($value)) {
                $value = strval($value);
            }
            else if (is_array($value)) {
                if (!$isJson) {
                    continue;
                }

                $value = json_encode($value);
            }
            else if (is_string($value)) {
                $value = $isJson ?
                    $this->escapeStringForJson($value) :
                    str_replace(["\r\n", "\r", "\n"], "\\n", $value);
            }
            else {
                continue;
            }

            $content = str_replace('{$$' . $key . '}', $value, $content);
        }

        return $content;
    }

    private function escapeStringForJson(string $string): string
    {
        return substr(json_encode($string, \JSON_UNESCAPED_UNICODE), 1, -1);
    }
}
