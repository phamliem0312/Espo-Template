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

namespace Espo\Modules\Voip\Providers\Starface;

use Espo\Core\Exceptions\{
    Forbidden,
    BadRequest,
};

class WebhookHandler extends \Espo\Modules\Voip\Bases\WebhookHandler
{
    public function run(array $data, $request)
    {
        $starfaceUser = $request->getQueryParam('de.vertico.starface.user') ?? $request->getQueryParam('de_vertico_starface_user');
        $starfaceAuth = $request->getQueryParam('de.vertico.starface.auth') ?? $request->getQueryParam('de_vertico_starface_auth');

        $GLOBALS['log']->debug('Starface: Event from Starface server for Starface user [' . $starfaceUser . ']: START.');

        $user = $this->getEntityManager()->getRepository('User')->where([
            'voipUser' => $starfaceUser,
        ])->findOne();

        if (!$user instanceof \Espo\Entities\User) {
            $GLOBALS['log']->debug('Starface: Starface user [' . $starfaceUser . '] is not found in EspoCRM.');
            throw new BadRequest();
        }

        $connectorManager = $this->getConnectorManager();
        $connector = $user->get('voipConnector');

        if ($connector != $this->getConnector()) {
            $connectorManager = $connectorManager->createManagerForConnector($connector);
        }

        $connectorManager->setUser($user);

        if (!$connectorManager->isAuthorized($starfaceAuth)) {
            $GLOBALS['log']->warning('Starface: Invalid credentials for starface user [' . $starfaceUser . '].');
            throw new Forbidden();
        }

        $connectorManager->handleEvent();

        $GLOBALS['log']->debug('Starface: Event from Starface server for Starface user [' . $starfaceUser . ']: END.');
    }
}
