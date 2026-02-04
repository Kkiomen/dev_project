"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.AiselloDatabase = void 0;
const GenericFunctions_1 = require("../Aisello/GenericFunctions");
const BaseDescription_1 = require("./descriptions/BaseDescription");
const TableDescription_1 = require("./descriptions/TableDescription");
const FieldDescription_1 = require("./descriptions/FieldDescription");
const RowDescription_1 = require("./descriptions/RowDescription");
const CellDescription_1 = require("./descriptions/CellDescription");
const AttachmentDescription_1 = require("./descriptions/AttachmentDescription");
class AiselloDatabase {
    constructor() {
        this.description = {
            displayName: 'Aisello Database',
            name: 'aiselloDatabase',
            icon: 'file:aisello.svg',
            group: ['transform'],
            version: 1,
            subtitle: '={{$parameter["operation"] + ": " + $parameter["resource"]}}',
            description: 'Interact with Aisello Database (Airtable-like tables)',
            defaults: { name: 'Aisello Database' },
            inputs: ['main'],
            outputs: ['main'],
            credentials: [
                {
                    name: 'aiselloApi',
                    required: true,
                },
            ],
            properties: [
                {
                    displayName: 'Resource',
                    name: 'resource',
                    type: 'options',
                    noDataExpression: true,
                    options: [
                        { name: 'Attachment', value: 'attachment' },
                        { name: 'Base', value: 'base' },
                        { name: 'Cell', value: 'cell' },
                        { name: 'Field', value: 'field' },
                        { name: 'Row', value: 'row' },
                        { name: 'Table', value: 'table' },
                    ],
                    default: 'row',
                },
                ...BaseDescription_1.baseOperations,
                ...TableDescription_1.tableOperations,
                ...FieldDescription_1.fieldOperations,
                ...RowDescription_1.rowOperations,
                ...CellDescription_1.cellOperations,
                ...AttachmentDescription_1.attachmentOperations,
                ...BaseDescription_1.baseFields,
                ...TableDescription_1.tableFields,
                ...FieldDescription_1.fieldFields,
                ...RowDescription_1.rowFields,
                ...CellDescription_1.cellFields,
                ...AttachmentDescription_1.attachmentFields,
            ],
        };
        this.methods = {
            loadOptions: {
                async getBases() {
                    try {
                        const responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', '/bases', {}, { per_page: 100 });
                        const bases = responseData.data || responseData;
                        return Array.isArray(bases)
                            ? bases.map((b) => ({ name: b.name, value: b.id }))
                            : [];
                    }
                    catch {
                        return [];
                    }
                },
                async getTables() {
                    try {
                        const baseId = this.getCurrentNodeParameter('baseId');
                        if (!baseId)
                            return [];
                        const responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', `/bases/${baseId}/tables`, {}, { per_page: 100 });
                        const tables = responseData.data || responseData;
                        return Array.isArray(tables)
                            ? tables.map((t) => ({ name: t.name, value: t.id }))
                            : [];
                    }
                    catch {
                        return [];
                    }
                },
                async getFields() {
                    try {
                        const tableId = this.getCurrentNodeParameter('tableId');
                        if (!tableId)
                            return [];
                        const responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', `/tables/${tableId}/fields`, {}, { per_page: 100 });
                        const fields = responseData.data || responseData;
                        return Array.isArray(fields)
                            ? fields.map((f) => ({ name: f.name, value: f.id }))
                            : [];
                    }
                    catch {
                        return [];
                    }
                },
            },
        };
    }
    async execute() {
        const items = this.getInputData();
        const returnData = [];
        const resource = this.getNodeParameter('resource', 0);
        const operation = this.getNodeParameter('operation', 0);
        for (let i = 0; i < items.length; i++) {
            try {
                let responseData;
                // ==================== BASE ====================
                if (resource === 'base') {
                    if (operation === 'getAll') {
                        const returnAll = this.getNodeParameter('returnAll', i);
                        if (returnAll) {
                            responseData = await GenericFunctions_1.aiselloApiRequestAllItems.call(this, 'GET', '/bases');
                        }
                        else {
                            const limit = this.getNodeParameter('limit', i);
                            responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', '/bases', {}, { per_page: limit });
                            responseData = responseData.data || responseData;
                        }
                    }
                    else if (operation === 'get') {
                        const baseId = this.getNodeParameter('baseId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', `/bases/${baseId}`);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'create') {
                        const name = this.getNodeParameter('name', i);
                        const additionalFields = this.getNodeParameter('additionalFields', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', '/bases', { name, ...additionalFields });
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'update') {
                        const baseId = this.getNodeParameter('baseId', i);
                        const updateFields = this.getNodeParameter('updateFields', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'PUT', `/bases/${baseId}`, updateFields);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'delete') {
                        const baseId = this.getNodeParameter('baseId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'DELETE', `/bases/${baseId}`);
                    }
                }
                // ==================== TABLE ====================
                else if (resource === 'table') {
                    if (operation === 'getAll') {
                        const baseId = this.getNodeParameter('baseId', i);
                        const returnAll = this.getNodeParameter('returnAll', i);
                        if (returnAll) {
                            responseData = await GenericFunctions_1.aiselloApiRequestAllItems.call(this, 'GET', `/bases/${baseId}/tables`);
                        }
                        else {
                            const limit = this.getNodeParameter('limit', i);
                            responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', `/bases/${baseId}/tables`, {}, { per_page: limit });
                            responseData = responseData.data || responseData;
                        }
                    }
                    else if (operation === 'get') {
                        const tableId = this.getNodeParameter('tableId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', `/tables/${tableId}`);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'create') {
                        const baseId = this.getNodeParameter('baseId', i);
                        const name = this.getNodeParameter('name', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/bases/${baseId}/tables`, { name });
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'update') {
                        const tableId = this.getNodeParameter('tableId', i);
                        const updateFields = this.getNodeParameter('updateFields', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'PUT', `/tables/${tableId}`, updateFields);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'delete') {
                        const tableId = this.getNodeParameter('tableId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'DELETE', `/tables/${tableId}`);
                    }
                    else if (operation === 'reorder') {
                        const tableId = this.getNodeParameter('tableId', i);
                        const position = this.getNodeParameter('position', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/tables/${tableId}/reorder`, { position });
                    }
                }
                // ==================== FIELD ====================
                else if (resource === 'field') {
                    if (operation === 'getAll') {
                        const tableId = this.getNodeParameter('tableId', i);
                        const returnAll = this.getNodeParameter('returnAll', i);
                        if (returnAll) {
                            responseData = await GenericFunctions_1.aiselloApiRequestAllItems.call(this, 'GET', `/tables/${tableId}/fields`);
                        }
                        else {
                            const limit = this.getNodeParameter('limit', i);
                            responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', `/tables/${tableId}/fields`, {}, { per_page: limit });
                            responseData = responseData.data || responseData;
                        }
                    }
                    else if (operation === 'get') {
                        const fieldId = this.getNodeParameter('fieldId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', `/fields/${fieldId}`);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'create') {
                        const tableId = this.getNodeParameter('tableId', i);
                        const name = this.getNodeParameter('name', i);
                        const type = this.getNodeParameter('type', i);
                        const additionalFields = this.getNodeParameter('additionalFields', i);
                        const body = { name, type, ...additionalFields };
                        if (body.options && typeof body.options === 'string') {
                            try {
                                body.options = JSON.parse(body.options);
                            }
                            catch { /* keep as string */ }
                        }
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/tables/${tableId}/fields`, body);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'update') {
                        const fieldId = this.getNodeParameter('fieldId', i);
                        const updateFields = this.getNodeParameter('updateFields', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'PUT', `/fields/${fieldId}`, updateFields);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'delete') {
                        const fieldId = this.getNodeParameter('fieldId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'DELETE', `/fields/${fieldId}`);
                    }
                    else if (operation === 'reorder') {
                        const fieldId = this.getNodeParameter('fieldId', i);
                        const position = this.getNodeParameter('position', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/fields/${fieldId}/reorder`, { position });
                    }
                    else if (operation === 'addChoice') {
                        const fieldId = this.getNodeParameter('fieldId', i);
                        const choiceName = this.getNodeParameter('choiceName', i);
                        const additionalFields = this.getNodeParameter('additionalFields', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/fields/${fieldId}/choices`, { name: choiceName, ...additionalFields });
                        responseData = responseData.data || responseData;
                    }
                }
                // ==================== ROW ====================
                else if (resource === 'row') {
                    if (operation === 'getAll') {
                        const tableId = this.getNodeParameter('tableId', i);
                        const returnAll = this.getNodeParameter('returnAll', i);
                        if (returnAll) {
                            responseData = await GenericFunctions_1.aiselloApiRequestAllItems.call(this, 'GET', `/tables/${tableId}/rows`);
                        }
                        else {
                            const limit = this.getNodeParameter('limit', i);
                            responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', `/tables/${tableId}/rows`, {}, { per_page: limit });
                            responseData = responseData.data || responseData;
                        }
                    }
                    else if (operation === 'get') {
                        const rowId = this.getNodeParameter('rowId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', `/rows/${rowId}`);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'create') {
                        const tableId = this.getNodeParameter('tableId', i);
                        const cellsStr = this.getNodeParameter('cells', i);
                        let cells;
                        try {
                            cells = JSON.parse(cellsStr);
                        }
                        catch {
                            cells = {};
                        }
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/tables/${tableId}/rows`, { cells });
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'update') {
                        const rowId = this.getNodeParameter('rowId', i);
                        const cellsStr = this.getNodeParameter('cells', i);
                        let cells;
                        try {
                            cells = JSON.parse(cellsStr);
                        }
                        catch {
                            cells = {};
                        }
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'PUT', `/rows/${rowId}`, { cells });
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'delete') {
                        const rowId = this.getNodeParameter('rowId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'DELETE', `/rows/${rowId}`);
                    }
                    else if (operation === 'bulkCreate') {
                        const tableId = this.getNodeParameter('tableId', i);
                        const rowsStr = this.getNodeParameter('rows', i);
                        let rows;
                        try {
                            rows = JSON.parse(rowsStr);
                        }
                        catch {
                            rows = [];
                        }
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/tables/${tableId}/rows/bulk`, { rows });
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'bulkDelete') {
                        const tableId = this.getNodeParameter('tableId', i);
                        const rowIds = this.getNodeParameter('rowIds', i).split(',').map((id) => id.trim());
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'DELETE', `/tables/${tableId}/rows/bulk`, { row_ids: rowIds });
                    }
                    else if (operation === 'reorder') {
                        const rowId = this.getNodeParameter('rowId', i);
                        const position = this.getNodeParameter('position', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/rows/${rowId}/reorder`, { position });
                    }
                }
                // ==================== CELL ====================
                else if (resource === 'cell') {
                    const rowId = this.getNodeParameter('rowId', i);
                    if (operation === 'update') {
                        const fieldId = this.getNodeParameter('fieldId', i);
                        const value = this.getNodeParameter('value', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'PUT', `/rows/${rowId}/cells/${fieldId}`, { value });
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'bulkUpdate') {
                        const cellsStr = this.getNodeParameter('cells', i);
                        let cells;
                        try {
                            cells = JSON.parse(cellsStr);
                        }
                        catch {
                            cells = {};
                        }
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'PUT', `/rows/${rowId}/cells`, { cells });
                        responseData = responseData.data || responseData;
                    }
                }
                // ==================== ATTACHMENT ====================
                else if (resource === 'attachment') {
                    if (operation === 'upload') {
                        const cellId = this.getNodeParameter('cellId', i);
                        const binaryPropertyName = this.getNodeParameter('binaryPropertyName', i);
                        const binaryData = this.helpers.assertBinaryData(i, binaryPropertyName);
                        const dataBuffer = await this.helpers.getBinaryDataBuffer(i, binaryPropertyName);
                        const formData = {
                            file: {
                                value: dataBuffer,
                                options: {
                                    filename: binaryData.fileName || 'upload',
                                    contentType: binaryData.mimeType,
                                },
                            },
                        };
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/cells/${cellId}/attachments`, {}, {}, undefined, {
                            formData,
                        });
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'delete') {
                        const attachmentId = this.getNodeParameter('attachmentId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'DELETE', `/attachments/${attachmentId}`);
                    }
                    else if (operation === 'reorder') {
                        const attachmentId = this.getNodeParameter('attachmentId', i);
                        const position = this.getNodeParameter('position', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/attachments/${attachmentId}/reorder`, { position });
                    }
                }
                // Normalize response
                if (responseData === undefined) {
                    responseData = { success: true };
                }
                const executionData = this.helpers.constructExecutionMetaData(this.helpers.returnJsonArray(responseData), { itemData: { item: i } });
                returnData.push(...executionData);
            }
            catch (error) {
                if (this.continueOnFail()) {
                    const executionData = this.helpers.constructExecutionMetaData(this.helpers.returnJsonArray({ error: error.message }), { itemData: { item: i } });
                    returnData.push(...executionData);
                    continue;
                }
                throw error;
            }
        }
        return [returnData];
    }
}
exports.AiselloDatabase = AiselloDatabase;
