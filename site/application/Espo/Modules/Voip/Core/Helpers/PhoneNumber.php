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

namespace Espo\Modules\Voip\Core\Helpers;

use Espo\Modules\Voip\Entities\VoipEvent as VoipEventEntity;
use Espo\Modules\Voip\Core\Utils\Voip as VoipUtils;
use Espo\Modules\Voip\Core\Utils\PhoneNumber as PhoneNumberUtil;

class PhoneNumber
{
    private $container;

    private $phoneNumberUtil;

    private $connectors = [];

    protected $options = [];

    public function __construct(\Espo\Core\Container $container)
    {
        $this->container = $container;
        $this->phoneNumberUtil = new \Espo\Modules\Voip\Core\Utils\PhoneNumber();
    }

    protected function getContainer()
    {
        return $this->container;
    }

    protected function getPhoneNumberUtil()
    {
        return $this->phoneNumberUtil;
    }

    protected function getIntegrationEntity($connector)
    {
        if (!isset($this->connectors[$connector])) {
            $this->connectors[$connector] = $this->getContainer()->get('entityManager')->getEntity('Integration', strtolower($connector));
        }

        return $this->connectors[$connector];
    }

    protected function getConnectorData($connector)
    {
        $integrationEntity = $this->getIntegrationEntity($connector);

        if ($integrationEntity) {
            return $integrationEntity->get('data');
        }
    }

    protected function getPhoneNumberObj($connector, $phoneNumber, $returns = null)
    {
        $connectorData = $this->getConnectorData($connector);

        try {
            $phoneNumberObj = $this->getPhoneNumberUtil()->parse($phoneNumber, $connectorData->defaultCountry);
        } catch (\Exception $e) {
            return $returns;
        }

        return $phoneNumberObj;
    }

    /**
     * Get rules for a phone replacement
     *
     * @param  string $connector
     *
     * @return array|null
     */
    protected function getPhoneNumberReplacement($connector)
    {
        if (!array_key_exists('phoneNumberReplacement', $this->options) || !array_key_exists($connector, $this->options['phoneNumberReplacement'])) {
            $metadata = $this->getContainer()->get('metadata');
            $this->options['phoneNumberReplacement'][$connector] = $metadata->get('app.voip.phoneNumberReplacement.' . $connector);
            if (!isset($this->options['phoneNumberReplacement'][$connector])) {
                $this->options['phoneNumberReplacement'][$connector] = $metadata->get('app.voip.phoneNumberReplacement.default');
            }
        }

        return $this->options['phoneNumberReplacement'][$connector];
    }

    /**
     * Do phone number replacement
     *
     * @param  string $connector
     * @param  string $phoneNumber
     *
     * @return string
     */
    public function doPhoneNumberReplacement($connector, $phoneNumber)
    {
        $replacement = $this->getPhoneNumberReplacement($connector);

        if ($replacement) {
            foreach ($replacement as $pattern => $replace) {
                $phoneNumber = preg_replace('/'.$pattern.'/', $replace, $phoneNumber);
            }
        }

        return $phoneNumber;
    }

    /**
     * Format a phone number
     *
     * @param  string $connector
     * @param  string $phoneNumber
     * @param  string $format
     * @param  string $originalPhoneNumber
     *
     * @return string
     */
    public function formatPhoneNumber($connector, $phoneNumber, $format, $originalPhoneNumber = null)
    {
        $connectorData = $this->getConnectorData($connector);
        $phoneNumberUtil = $this->getPhoneNumberUtil();

        $phoneNumber = trim($phoneNumber);
        $phoneNumber = $this->doPhoneNumberReplacement($connector, $phoneNumber);

        if (!isset($originalPhoneNumber)) {
            $originalPhoneNumber = $phoneNumber;
        }

        if ($this->isSipPhoneNumber($phoneNumber)) {
            return $this->formatSipPhoneNumber($connector, $phoneNumber, $format, $originalPhoneNumber);
        }

        switch ($format) {
            case PhoneNumberUtil::SEARCH_FORMAT:
            case PhoneNumberUtil::NATIONAL_SEARCH_FORMAT:
            case PhoneNumberUtil::DIAL_FORMAT:
            case PhoneNumberUtil::DISPLAY_FORMAT:
            case PhoneNumberUtil::E164_FORMAT:
            case PhoneNumberUtil::INTERNATIONAL_FORMAT:
                $phoneNumber = $this->formatPhoneNumber($connector, $phoneNumber, PhoneNumberUtil::EXCLUDE_LINE_FORMAT, $originalPhoneNumber);
                $phoneNumberObj = $this->getPhoneNumberObj($connector, $phoneNumber);
                if (!isset($phoneNumberObj)) {
                    return $phoneNumber;
                }

                if (!$phoneNumberUtil->isValidNumber($phoneNumberObj)) {
                    $phoneNumber = $originalPhoneNumber;
                    $phoneNumberObj = $this->getPhoneNumberObj($connector, $phoneNumber);
                    if (!isset($phoneNumberObj)) {
                        return $phoneNumber;
                    }
                }
                break;
        }

        switch ($format) {
            case PhoneNumberUtil::SEARCH_FORMAT:
                $formattedPhoneNumber = $phoneNumber;
                if ($phoneNumberUtil->isValidNumber($phoneNumberObj)) {
                    //$formattedPhoneNumber = $phoneNumberUtil->formatNumberBasedOnCountry($phoneNumberObj, $connectorData->defaultCountry);
                    $formattedPhoneNumber = $phoneNumberUtil->getInternationalFormat($phoneNumberObj);
                }

                return VoipUtils::formatNumber($formattedPhoneNumber, null, array('\+' => '')); //"0446681800" or "41446681800"
                break;

            case PhoneNumberUtil::NATIONAL_SEARCH_FORMAT:
                $formattedPhoneNumber = $phoneNumberUtil->getNationalFormat($phoneNumberObj);
                return VoipUtils::formatNumber($formattedPhoneNumber, null, array('\+' => '')); //"0446681800" or "41446681800"
                break;

            case PhoneNumberUtil::DIAL_FORMAT:
                $formattedPhoneNumber = $phoneNumber;
                if ($phoneNumberUtil->isValidNumber($phoneNumberObj)) {
                    $dialPattern = isset($connectorData->dialFormat) ? $connectorData->dialFormat : null;
                    $formattedPhoneNumber = $phoneNumberUtil->getDialFormat($phoneNumberObj, $connectorData->defaultCountry, $dialPattern);
                }
                return VoipUtils::formatNumber($formattedPhoneNumber);
                break;

            case PhoneNumberUtil::DISPLAY_FORMAT:
                if (!$phoneNumberUtil->isValidNumber($phoneNumberObj)) {
                    return $phoneNumber;
                }

                return $phoneNumberUtil->getInternationalFormat($phoneNumberObj); // "+41 44 668 18 00"
                break;

            case PhoneNumberUtil::E164_FORMAT:
                return $phoneNumberUtil->getE164Format($phoneNumberObj); // "+41446681800"
                break;

            case PhoneNumberUtil::INTERNATIONAL_FORMAT:
                return $phoneNumberUtil->getInternationalFormat($phoneNumberObj); // "+41446681800"
                break;

            case PhoneNumberUtil::LINE_FORMAT:
                if (isset($connectorData->lines)) {
                    $formattedPhoneNumber = VoipUtils::formatNumber($phoneNumber, null, array('\+' => ''));
                    foreach ($connectorData->lines as $line) {
                        $phoneNumberLineLen = substr($formattedPhoneNumber, 0, strlen($line));
                        if ($phoneNumberLineLen == $line) {
                            return $line;
                        }
                    }
                }
                return null;
                break;

            case PhoneNumberUtil::EXCLUDE_LINE_FORMAT:
                if (isset($connectorData->lines)) {
                    $line = $this->formatPhoneNumber($connector, $phoneNumber, PhoneNumberUtil::LINE_FORMAT, $originalPhoneNumber);
                    if (isset($line)) {
                        return substr($phoneNumber, strlen($line));
                    }
                }
                return $phoneNumber;
                break;
        }

        return $phoneNumber;
    }

    protected function formatSipPhoneNumber($connector, $phoneNumber, $format, $originalPhoneNumber = null)
    {
        switch ($format) {
            case PhoneNumberUtil::DISPLAY_FORMAT:
            case PhoneNumberUtil::SIP_USER_FORMAT:
            case PhoneNumberUtil::EXCLUDE_LINE_FORMAT:
                $phoneNumber = $this->formatSipPhoneNumber($connector, $phoneNumber, PhoneNumberUtil::SIP_FORMAT);
                $phoneNumber = preg_replace('/@.*$/', '', $phoneNumber);
                break;

            case PhoneNumberUtil::DIAL_FORMAT:
                if (!preg_match('/^sip:/', $phoneNumber)) {
                    $phoneNumber = 'sip:' . $phoneNumber;
                }
                break;

            case PhoneNumberUtil::LINE_FORMAT:
                $sipDialFormat = $this->formatSipPhoneNumber($connector, $phoneNumber, PhoneNumberUtil::SIP_FORMAT);
                $phoneNumber = preg_replace('/^.*@/', '', $sipDialFormat);
                break;

            case PhoneNumberUtil::SEARCH_FORMAT:
            case PhoneNumberUtil::NATIONAL_SEARCH_FORMAT:
            case PhoneNumberUtil::SIP_FORMAT:
            default:
                $phoneNumber = preg_replace('/^sip:/', '', $phoneNumber);
                $phoneNumber = preg_replace('/;.*/', '', $phoneNumber);
                break;
        }

        return $phoneNumber;
    }

    /**
     * Check if a phone number is valid
     *
     * @param  string  $connector
     * @param  string  $phoneNumber
     *
     * @return boolean
     */
    public function isValidPhoneNumber($connector, $phoneNumber)
    {
        $phoneNumberObj = $this->getPhoneNumberObj($connector, $phoneNumber);
        if (!isset($phoneNumberObj)) {
            return false;
        }

        return $this->getPhoneNumberUtil()->isValidNumber($phoneNumberObj);
    }

    public function isSipPhoneNumber($phoneNumber)
    {
        return VoipUtils::isSipPhoneNumber($phoneNumber);
    }

    /**
     * Get phone number type
     * @param  string $connector
     * @param  string $phoneNumber
     * @return string|null ['sip', 'phone']
     */
    public function getPhoneNumberType($connector, $phoneNumber)
    {
        if ($this->isValidPhoneNumber($connector, $phoneNumber)) {
            return 'phone';
        }

        if ($this->isSipPhoneNumber($phoneNumber)) {
            $sipUserExt = $this->formatSipPhoneNumber($connector, $phoneNumber, PhoneNumberUtil::SIP_USER_FORMAT);
            if ($this->isValidPhoneNumber($connector, $sipUserExt)) {
                return 'phone';
            }

            return 'sip';
        }
    }
}
