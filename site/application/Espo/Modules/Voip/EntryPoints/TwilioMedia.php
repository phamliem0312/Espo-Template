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

namespace Espo\Modules\Voip\EntryPoints;

use Espo\Core\Exceptions\{
    NotFound,
    Forbidden,
    BadRequest,
};

class TwilioMedia extends \Espo\Core\EntryPoints\Base
{
    public function run()
    {
        $id = $_GET['id'] ?? null;
        $messageId = $_GET['messageId'] ?? null;

        if (empty($id) || empty($messageId)) {
            throw new BadRequest();
        }

        $attachment = $this->getEntityManager()->getEntity('Attachment', $id);

        if (!$attachment) {
            throw new NotFound();
        }

        if ($attachment->get('role') !== 'Twilio' || !$attachment->get('sourceId')) {
            throw new Forbidden();
        }

        $source = $this->getEntityManager()->getEntity('Attachment', $attachment->get('sourceId'));

        if ($source->get('parentId') != $messageId) {
            throw new NotFound();
        }
        $fileName = $this->getEntityManager()->getRepository('Attachment')->getFilePath($attachment);
        if (!file_exists($fileName)) {
            throw new NotFound();
        }

        $type = $attachment->get('type');
        $disposition = 'attachment';

        header('Content-Description: File Transfer');
        if ($type) {
            header('Content-Type: ' . $type);
        }
        header("Content-Disposition: " . $disposition . ";filename=\"" . $attachment->get('name') . "\"");
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($fileName));
        ob_clean();
        flush();
        readfile($fileName);
        exit;
    }
}
