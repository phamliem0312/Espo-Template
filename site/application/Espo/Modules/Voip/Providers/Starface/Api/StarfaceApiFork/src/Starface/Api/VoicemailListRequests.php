<?php

namespace Starface\Api;


class VoicemailListRequests extends Api
{
    const METHOD_GET_MAIL_BOXES = 'ucp.v22.requests.voicemailList.getMailboxes';
    const METHOD_CALL_MAILBOX_WITH_NUMBER = 'ucp.v22.requests.voicemailList.callMailboxWithNumber';
    const METHOD_CALL_MAILBOX_WITH_PHONE = 'ucp.v22.requests.voicemailList.callMailboxWithPhone';
    const METHOD_GET_VOICEMAIL_LIST = 'ucp.v22.requests.voicemailList.getVoicemailList';
    const METHOD_CALL_VOICEMAIL_WITH_NUMBER = 'ucp.v22.requests.voicemailList.callVoicemailWithNumber';
    const METHOD_CALL_VOICEMAIL_WITH_PHONE = 'ucp.v22.requests.voicemailList.callVoicemailWithPhone';
    const METHOD_GET_VOICEMAIL_FILE = 'ucp.v22.requests.voicemailList.getVoicemailFile';
    const METHOD_GET_VOICEMAIL_FILE_URL = 'ucp.v22.requests.voicemailList.getVoicemailFileUrl';
    const METHOD_MOVE_VOICEMAIL_LIST_ENTRY = 'ucp.v22.requests.voicemailList.moveVoicemailListEntry';
    const METHOD_MOVE_VOICEMAIL_LIST_ENTRIES = 'ucp.v22.requests.voicemailList.moveVoicemailListEntries';
    const METHOD_DELETE_VOICEMAIL_LIST_ENTRY = 'ucp.v22.requests.voicemailList.deleteVoicemailListEntry';
    const METHOD_DELETE_VOICEMAIL_LIST_ENTRIES = 'ucp.v22.requests.voicemailList.deleteVoicemailListEntries';
}
