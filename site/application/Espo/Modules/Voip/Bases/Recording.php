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

namespace Espo\Modules\Voip\Bases;

use Espo\Modules\Voip\Entities\VoipEvent as VoipEventEntity;

abstract class Recording
{
    private $container;

    private $voipHelper;

    public function __construct(\Espo\Core\Container $container)
    {
        $this->container = $container;
    }

    protected function getContainer()
    {
        return $this->container;
    }

    protected function getEntityManager()
    {
        return $this->container->get('entityManager');
    }

    protected function getVoipHelper()
    {
        if (!isset($this->voipHelper)) {
            $this->voipHelper = $this->container->get('voipEventHelper');
        }

        return $this->voipHelper;
    }

    /**
     * Generate Recording URL bases on settings
     *
     * @param  VoipEventEntity $voipEventEntity
     * @param  mixed           $returns
     *
     * @return string | null
     */
    public function generateUrl(VoipEventEntity $voipEventEntity)
    {
        $connectorData = $this->getConnectorData($voipEventEntity);
        if (empty($connectorData)) {
            return null;
        }

        if (!$connectorData->playRecordings) {
            return null;
        }

        switch ($voipEventEntity->get('type')) {
            case 'outgoingCall':
                $useOutgoingCallRecording = $connectorData->useOutgoingCallRecording ?? false;
                if ($useOutgoingCallRecording) {
                    return $this->normalizeRecordingUrl($connectorData->outgoingCallRecordingUrl, $voipEventEntity);
                }
                break;
        }

        return $this->normalizeRecordingUrl($connectorData->recordingUrl, $voipEventEntity);
    }

    /**
     * Replace values for recording URL
     *
     * @param  string          $recordingUrl
     * @param  VoipEventEntity $voipEventEntity
     *
     * @return string
     */
    protected function normalizeRecordingUrl($recordingUrl, VoipEventEntity $voipEventEntity)
    {
        $recordingUrl = $this->bindData($recordingUrl, [
            'VOIP_UNIQUEID' => $voipEventEntity->get('uniqueid'), //deprecated
            'UNIQUEID' => $voipEventEntity->get('uniqueid'),
            'DESTUNIQUEID' => $voipEventEntity->get('destuniqueid'),
        ]);

        $dateStart = $voipEventEntity->get('dateStart');
        if (!empty($dateStart)) {
            $dateStartTimestamp = strtotime($dateStart);

            preg_match_all('/\{([a-z]+?)\}/i', $recordingUrl, $matches);
            if (isset($matches[1])) {
                foreach ($matches[1] as $value) {
                    $recordingUrl = str_replace('{'.$value.'}', date($value, $dateStartTimestamp), $recordingUrl);
                }
            }
        }

        return $recordingUrl;
    }

    protected function bindData($recordingUrl, array $data)
    {
        foreach ($data as $key => $value) {
            $recordingUrl = str_replace('{'.$key.'}', $value, $recordingUrl);
        }

        return $recordingUrl;
    }

    /**
     * Get connector for VoipEvent entity
     *
     * @param  \Espo\Modules\Voip\Entities\VoipEvent $voipEventEntity
     *
     * @return \stdClass | null
     */
    protected function getConnectorData(VoipEventEntity $voipEventEntity)
    {
        $connector = $voipEventEntity->get('connector');
        if (empty($connector)) {
            if ($voipEventEntity->get('assignedUserId')) {
                $assignedUser = $this->getEntityManager()->getEntity('User', $voipEventEntity->get('assignedUserId'));
            }

            if (!empty($assignedUser) && $assignedUser->get('voipConnector')) {
                $connector = $assignedUser->get('voipConnector');
            }
        }

        if (!empty($connector)) {
            $connectorEntity = $this->getEntityManager()->getEntity('Integration', $connector);
            if (!empty($connectorEntity)) {
                return $connectorEntity->get('data');
            }
        }
    }
}
