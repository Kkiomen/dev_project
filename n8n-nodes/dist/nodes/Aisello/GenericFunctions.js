"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.aiselloApiRequest = aiselloApiRequest;
exports.aiselloApiRequestAllItems = aiselloApiRequestAllItems;
const n8n_workflow_1 = require("n8n-workflow");
async function aiselloApiRequest(method, endpoint, body = {}, qs = {}, uri, option = {}) {
    const credentials = await this.getCredentials('aiselloApi');
    const baseUrl = credentials.apiUrl.replace(/\/$/, '');
    const options = {
        method,
        qs,
        url: uri || `${baseUrl}/api/v1${endpoint}`,
        json: true,
        ...option,
    };
    if (Object.keys(body).length > 0) {
        options.body = body;
    }
    try {
        const response = await this.helpers.httpRequestWithAuthentication.call(this, 'aiselloApi', options);
        return response;
    }
    catch (error) {
        throw new n8n_workflow_1.NodeApiError(this.getNode(), error);
    }
}
async function aiselloApiRequestAllItems(method, endpoint, body = {}, qs = {}) {
    const returnData = [];
    let page = 1;
    qs.per_page = qs.per_page || 50;
    let responseData;
    do {
        qs.page = page;
        responseData = await aiselloApiRequest.call(this, method, endpoint, body, qs);
        const items = responseData.data || responseData;
        if (Array.isArray(items)) {
            returnData.push(...items);
        }
        page++;
    } while (responseData.meta?.current_page < responseData.meta?.last_page ||
        responseData.current_page < responseData.last_page);
    return returnData;
}
