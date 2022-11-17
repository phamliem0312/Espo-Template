<?php

namespace Starface\Api;


class FileTransfer extends Api
{
    const METHOD_INIT_UPLOAD = 'ucp.v22.requests.fax.sendFax.rpcFileUpload.initializeUpload';
    const METHOD_CANCEL_UPLOAD = 'ucp.v22.requests.fax.sendFax.rpcFileUpload.cancelUpload';
    const METHOD_CONTINUE_UPLOAD = 'ucp.v22.requests.fax.sendFax.rpcFileUpload.continueUpload';
    const METHOD_COMPLETE_UPLOAD = 'ucp.v22.requests.fax.sendFax.rpcFileUpload.completeUpload';
    const METHOD_SEND_FAX = 'ucp.v22.requests.fax.sendFax';
    const METHOD_GET_FAX_STATE = 'ucp.v22.requests.fax.getFaxState';
    const METHOD_RECEIVE_FAX_STATE = 'ucp.v22.events.fax.receiveFaxState';
    const METHOD_NEW_FAX_LIST_ENTRY = 'ucp.v22.events.faxList.newFaxListEntry';
    const METHOD_GET_FAX_FILE = 'ucp.v22.requests.faxList.getFaxFile';
    const METHOD_INIT_DOWNLOAD = 'ucp.v22.requests.faxList.getFaxFile.rpcFileDownload.initializeDownload';
    const METHOD_CONTINUE_DOWNLOAD = 'ucp.v22.requests.faxList.getFaxFile.rpcFileDownload.continueDownload';
    const METHOD_COMPLETE_DOWNLOAD = 'ucp.v22.requests.faxList.getFaxFile.rpcFileDownload.completeDownload';
    const METHOD_CANCEL_DOWNLOAD = 'ucp.v22.requests.faxList.getFaxFile.rpcFileDownload.cancelDownload';
}
