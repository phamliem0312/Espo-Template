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

use Espo\Core\Exceptions\Error;
use libphonenumber\PhoneNumber as LibPhoneNumber;
use libphonenumber\PhoneNumberFormat as LibPhoneNumberFormat;

class PhoneNumber
{
    private $phoneNumberUtil;

    const SEARCH_FORMAT = 'SEARCH';
    const NATIONAL_SEARCH_FORMAT = 'NATIONAL_SEARCH';
    const DISPLAY_FORMAT = 'DISPLAY';
    const DIAL_FORMAT = 'DIAL';
    const LINE_FORMAT = 'LINE';
    const EXCLUDE_LINE_FORMAT = 'EXCLUDE_LINE';
    const E164_FORMAT = 'E164';
    const INTERNATIONAL_FORMAT = 'INTERNATIONAL';
    const SIP_FORMAT = 'SIP';
    const SIP_USER_FORMAT = 'SIP_USER';

    public function __construct()
    {
        $this->phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
    }

    public function getPhoneNumberUtil()
    {
        return $this->phoneNumberUtil;
    }

    public function parse($numberToParse, $defaultRegion, LibPhoneNumber $phoneNumber = null, $keepRawInput = false)
    {
        try {
            $phoneNumberObj = $this->getPhoneNumberUtil()->parse($numberToParse, $defaultRegion, $phoneNumber, $keepRawInput);
        } catch (\libphonenumber\NumberParseException $e) {
            throw new Error('Phone Number Error Parsing: ' . $e->getMessage());
        }

        return $phoneNumberObj;
    }

    public function getDialFormat(LibPhoneNumber $number, $regionCallingFrom, $pattern = null)
    {
        $phoneNumberUtil = $this->getPhoneNumberUtil();

        if ($this->isValidNumber($number) && isset($pattern)) {

            $dialPatternMap = [
                'COUNTRY_CODE' => $number->getCountryCode(),
                'NATIONAL_NUMBER' => $phoneNumberUtil->getNationalSignificantNumber($number),
            ];

            $phoneNumber = $pattern;
            foreach ($dialPatternMap as $key => $value) {
                $phoneNumber = str_replace('{'.$key.'}', $value, $phoneNumber);
            }

            return $phoneNumber;
        }

        return $phoneNumberUtil->formatOutOfCountryCallingNumber($number, $regionCallingFrom);
    }

    public function isValidNumber(LibPhoneNumber $number)
    {
        return $this->getPhoneNumberUtil()->isValidNumber($number);
    }

    public function format(LibPhoneNumber $number, $numberFormat)
    {
        return $this->getPhoneNumberUtil()->format($number, $numberFormat);
    }

    public function getNationalFormat(LibPhoneNumber $number)
    {
        return $this->format($number, LibPhoneNumberFormat::NATIONAL); //"044 668 18 00"
    }

    public function getInternationalFormat(LibPhoneNumber $number)
    {
        return $this->format($number, LibPhoneNumberFormat::INTERNATIONAL); //"+41 44 668 18 00"
    }

    public function getE164Format(LibPhoneNumber $number)
    {
        return $this->format($number, LibPhoneNumberFormat::E164); //"+41446681800"
    }

    public function getRFC3966Format(LibPhoneNumber $number)
    {
        return $this->format($number, LibPhoneNumberFormat::RFC3966); //"+41446681800"
    }

    public function formatNumberBasedOnCountry($phoneNumberObj, $coutry)
    {
        $phoneNumberUtil = $this->getPhoneNumberUtil();

        $countyCode = $phoneNumberObj->getCountryCode();
        if ($phoneNumberUtil->getRegionCodeForCountryCode($countyCode) == $coutry) {
            return $this->format($phoneNumberObj, LibPhoneNumberFormat::NATIONAL); //"044 668 18 00"
        }

        return $this->format($phoneNumberObj, LibPhoneNumberFormat::INTERNATIONAL); //"+41 44 668 18 00"
    }
}
