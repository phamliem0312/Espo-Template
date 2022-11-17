<?php

namespace Starface\Api;


class FaxListRequests extends Api
{
    const METHOD_GET_FAX_LIST = 'ucp.v22.requests.faxList.getFaxList';
    const METHOD_GET_FAX_FILE = 'ucp.v22.requests.faxList.getFaxFile';
    const METHOD_GET_FAX_FILE_URL = 'ucp.v22.requests.faxList.getFaxFileUrl';
    const METHOD_MOVE_FAX_LIST_ENTRY = 'ucp.v22.requests.faxList.moveFaxListEntry';
    const METHOD_MOVE_FAX_LIST_ENTRIES = 'ucp.v22.requests.faxList.moveFaxListEntries';
    const METHOD_DELETE_FAX_LIST_ENTRY = 'ucp.v22.requests.faxList.deleteFaxListEntry';
    const METHOD_DELETE_FAX_LIST_ENTRIES = 'ucp.v22.requests.faxList.deleteFaxListEntries';
}