"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.Aisello = void 0;
const GenericFunctions_1 = require("./GenericFunctions");
const BrandDescription_1 = require("./descriptions/BrandDescription");
const BrandAutomationDescription_1 = require("./descriptions/BrandAutomationDescription");
const PostDescription_1 = require("./descriptions/PostDescription");
const PostAutomationDescription_1 = require("./descriptions/PostAutomationDescription");
const PostMediaDescription_1 = require("./descriptions/PostMediaDescription");
const PlatformPostDescription_1 = require("./descriptions/PlatformPostDescription");
const ContentPlanDescription_1 = require("./descriptions/ContentPlanDescription");
const CalendarEventDescription_1 = require("./descriptions/CalendarEventDescription");
const BoardDescription_1 = require("./descriptions/BoardDescription");
const BoardColumnDescription_1 = require("./descriptions/BoardColumnDescription");
const BoardCardDescription_1 = require("./descriptions/BoardCardDescription");
const NotificationDescription_1 = require("./descriptions/NotificationDescription");
const ApprovalTokenDescription_1 = require("./descriptions/ApprovalTokenDescription");
const StockPhotoDescription_1 = require("./descriptions/StockPhotoDescription");
class Aisello {
    constructor() {
        this.description = {
            displayName: 'Aisello',
            name: 'aisello',
            icon: 'file:aisello.svg',
            group: ['transform'],
            version: 1,
            subtitle: '={{$parameter["operation"] + ": " + $parameter["resource"]}}',
            description: 'Interact with the Aisello API',
            defaults: { name: 'Aisello' },
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
                        { name: 'Approval Token', value: 'approvalToken' },
                        { name: 'Board', value: 'board' },
                        { name: 'Board Card', value: 'boardCard' },
                        { name: 'Board Column', value: 'boardColumn' },
                        { name: 'Brand', value: 'brand' },
                        { name: 'Brand Automation', value: 'brandAutomation' },
                        { name: 'Calendar Event', value: 'calendarEvent' },
                        { name: 'Content Plan', value: 'contentPlan' },
                        { name: 'Notification', value: 'notification' },
                        { name: 'Platform Post', value: 'platformPost' },
                        { name: 'Post', value: 'post' },
                        { name: 'Post Automation', value: 'postAutomation' },
                        { name: 'Post Media', value: 'postMedia' },
                        { name: 'Stock Photo', value: 'stockPhoto' },
                    ],
                    default: 'post',
                },
                // Operations
                ...BrandDescription_1.brandOperations,
                ...BrandAutomationDescription_1.brandAutomationOperations,
                ...PostDescription_1.postOperations,
                ...PostAutomationDescription_1.postAutomationOperations,
                ...PostMediaDescription_1.postMediaOperations,
                ...PlatformPostDescription_1.platformPostOperations,
                ...ContentPlanDescription_1.contentPlanOperations,
                ...CalendarEventDescription_1.calendarEventOperations,
                ...BoardDescription_1.boardOperations,
                ...BoardColumnDescription_1.boardColumnOperations,
                ...BoardCardDescription_1.boardCardOperations,
                ...NotificationDescription_1.notificationOperations,
                ...ApprovalTokenDescription_1.approvalTokenOperations,
                ...StockPhotoDescription_1.stockPhotoOperations,
                // Fields
                ...BrandDescription_1.brandFields,
                ...BrandAutomationDescription_1.brandAutomationFields,
                ...PostDescription_1.postFields,
                ...PostAutomationDescription_1.postAutomationFields,
                ...PostMediaDescription_1.postMediaFields,
                ...PlatformPostDescription_1.platformPostFields,
                ...ContentPlanDescription_1.contentPlanFields,
                ...CalendarEventDescription_1.calendarEventFields,
                ...BoardDescription_1.boardFields,
                ...BoardColumnDescription_1.boardColumnFields,
                ...BoardCardDescription_1.boardCardFields,
                ...NotificationDescription_1.notificationFields,
                ...ApprovalTokenDescription_1.approvalTokenFields,
                ...StockPhotoDescription_1.stockPhotoFields,
            ],
        };
        this.methods = {
            loadOptions: {
                async getBrands() {
                    try {
                        const responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', '/brands', {}, { per_page: 100 });
                        const brands = responseData.data || responseData;
                        return Array.isArray(brands)
                            ? brands.map((b) => ({ name: b.name, value: b.id }))
                            : [];
                    }
                    catch {
                        return [];
                    }
                },
                async getPosts() {
                    try {
                        const responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', '/posts', {}, { per_page: 100 });
                        const posts = responseData.data || responseData;
                        return Array.isArray(posts)
                            ? posts.map((p) => ({
                                name: p.title || (p.content ? p.content.substring(0, 50) + '...' : `Post ${p.id}`),
                                value: p.id,
                            }))
                            : [];
                    }
                    catch {
                        return [];
                    }
                },
                async getBoards() {
                    try {
                        const responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', '/boards', {}, { per_page: 100 });
                        const boards = responseData.data || responseData;
                        return Array.isArray(boards)
                            ? boards.map((b) => ({ name: b.name, value: b.id }))
                            : [];
                    }
                    catch {
                        return [];
                    }
                },
                async getApprovalTokens() {
                    try {
                        const responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', '/approval-tokens', {}, { per_page: 100 });
                        const tokens = responseData.data || responseData;
                        return Array.isArray(tokens)
                            ? tokens.map((t) => ({ name: t.name, value: t.id }))
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
                // ==================== BRAND ====================
                if (resource === 'brand') {
                    if (operation === 'getAll') {
                        const returnAll = this.getNodeParameter('returnAll', i);
                        if (returnAll) {
                            responseData = await GenericFunctions_1.aiselloApiRequestAllItems.call(this, 'GET', '/brands');
                        }
                        else {
                            const limit = this.getNodeParameter('limit', i);
                            responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', '/brands', {}, { per_page: limit });
                            responseData = responseData.data || responseData;
                        }
                    }
                    else if (operation === 'get') {
                        const brandId = this.getNodeParameter('brandId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', `/brands/${brandId}`);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'create') {
                        const name = this.getNodeParameter('name', i);
                        const additionalFields = this.getNodeParameter('additionalFields', i);
                        const body = { name, ...additionalFields };
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', '/brands', body);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'update') {
                        const brandId = this.getNodeParameter('brandId', i);
                        const updateFields = this.getNodeParameter('updateFields', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'PUT', `/brands/${brandId}`, updateFields);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'delete') {
                        const brandId = this.getNodeParameter('brandId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'DELETE', `/brands/${brandId}`);
                    }
                    else if (operation === 'setCurrent') {
                        const brandId = this.getNodeParameter('brandId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/brands/${brandId}/set-current`);
                    }
                }
                // ==================== BRAND AUTOMATION ====================
                else if (resource === 'brandAutomation') {
                    const brandId = this.getNodeParameter('brandId', i);
                    if (operation === 'getStats') {
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', `/brands/${brandId}/automation/stats`);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'enable') {
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/brands/${brandId}/automation/enable`);
                    }
                    else if (operation === 'disable') {
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/brands/${brandId}/automation/disable`);
                    }
                    else if (operation === 'trigger') {
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/brands/${brandId}/automation/process`);
                    }
                    else if (operation === 'extendQueue') {
                        const count = this.getNodeParameter('count', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/brands/${brandId}/automation/extend`, { count });
                    }
                    else if (operation === 'updateSettings') {
                        const settings = this.getNodeParameter('settings', i);
                        const body = {};
                        for (const key of Object.keys(settings)) {
                            const val = settings[key];
                            if (typeof val === 'string' && (val.startsWith('[') || val.startsWith('{'))) {
                                try {
                                    body[key] = JSON.parse(val);
                                }
                                catch {
                                    body[key] = val;
                                }
                            }
                            else {
                                body[key] = val;
                            }
                        }
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'PUT', `/brands/${brandId}/automation/settings`, body);
                    }
                }
                // ==================== POST ====================
                else if (resource === 'post') {
                    if (operation === 'getAll') {
                        const brandId = this.getNodeParameter('brandId', i);
                        const returnAll = this.getNodeParameter('returnAll', i);
                        const filters = this.getNodeParameter('filters', i);
                        const qs = { brand_id: brandId, ...filters };
                        if (returnAll) {
                            responseData = await GenericFunctions_1.aiselloApiRequestAllItems.call(this, 'GET', '/posts', {}, qs);
                        }
                        else {
                            const limit = this.getNodeParameter('limit', i);
                            qs.per_page = limit;
                            responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', '/posts', {}, qs);
                            responseData = responseData.data || responseData;
                        }
                    }
                    else if (operation === 'get') {
                        const postId = this.getNodeParameter('postId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', `/posts/${postId}`);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'create') {
                        const brandId = this.getNodeParameter('brandId', i);
                        const content = this.getNodeParameter('content', i);
                        const additionalFields = this.getNodeParameter('additionalFields', i);
                        const body = { brand_id: brandId, content, ...additionalFields };
                        if (body.platforms && typeof body.platforms === 'string') {
                            try {
                                body.platforms = JSON.parse(body.platforms);
                            }
                            catch { /* keep as string */ }
                        }
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', '/posts', body);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'update') {
                        const postId = this.getNodeParameter('postId', i);
                        const updateFields = this.getNodeParameter('updateFields', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'PUT', `/posts/${postId}`, updateFields);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'delete') {
                        const postId = this.getNodeParameter('postId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'DELETE', `/posts/${postId}`);
                    }
                    else if (operation === 'reschedule') {
                        const postId = this.getNodeParameter('postId', i);
                        const scheduledAt = this.getNodeParameter('scheduledAt', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/posts/${postId}/reschedule`, { scheduled_at: scheduledAt });
                    }
                    else if (operation === 'duplicate') {
                        const postId = this.getNodeParameter('postId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/posts/${postId}/duplicate`);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'approve') {
                        const postId = this.getNodeParameter('postId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/posts/${postId}/approve`);
                    }
                    else if (operation === 'reject') {
                        const postId = this.getNodeParameter('postId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/posts/${postId}/reject`);
                    }
                    else if (operation === 'publish') {
                        const postId = this.getNodeParameter('postId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/posts/${postId}/publish`);
                    }
                    else if (operation === 'aiGenerate') {
                        const brandId = this.getNodeParameter('brandId', i);
                        const prompt = this.getNodeParameter('prompt', i);
                        const additionalFields = this.getNodeParameter('additionalFields', i);
                        const body = { brand_id: brandId, prompt, ...additionalFields };
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', '/posts/ai/generate', body);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'aiModify') {
                        const postId = this.getNodeParameter('postId', i);
                        const instruction = this.getNodeParameter('instruction', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', '/posts/ai/modify', { post_id: postId, instruction });
                        responseData = responseData.data || responseData;
                    }
                }
                // ==================== POST AUTOMATION ====================
                else if (resource === 'postAutomation') {
                    if (operation === 'getAll') {
                        const returnAll = this.getNodeParameter('returnAll', i);
                        const filters = this.getNodeParameter('filters', i);
                        if (returnAll) {
                            responseData = await GenericFunctions_1.aiselloApiRequestAllItems.call(this, 'GET', '/posts/automation', {}, filters);
                        }
                        else {
                            const limit = this.getNodeParameter('limit', i);
                            responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', '/posts/automation', {}, { per_page: limit, ...filters });
                            responseData = responseData.data || responseData;
                        }
                    }
                    else if (operation === 'generateText') {
                        const postId = this.getNodeParameter('postId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/posts/${postId}/generate-text`);
                    }
                    else if (operation === 'generateImagePrompt') {
                        const postId = this.getNodeParameter('postId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/posts/${postId}/generate-image-prompt`);
                    }
                    else if (operation === 'webhookPublish') {
                        const postId = this.getNodeParameter('postId', i);
                        const additionalFields = this.getNodeParameter('additionalFields', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/posts/${postId}/webhook-publish`, additionalFields);
                    }
                    else if (operation === 'bulkGenerateText') {
                        const postIds = this.getNodeParameter('postIds', i).split(',').map((id) => id.trim());
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', '/posts/bulk-generate-text', { post_ids: postIds });
                    }
                    else if (operation === 'bulkGenerateImage') {
                        const postIds = this.getNodeParameter('postIds', i).split(',').map((id) => id.trim());
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', '/posts/bulk-generate-image-prompt', { post_ids: postIds });
                    }
                }
                // ==================== POST MEDIA ====================
                else if (resource === 'postMedia') {
                    if (operation === 'getAll') {
                        const postId = this.getNodeParameter('postId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', `/posts/${postId}/media`);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'upload') {
                        const postId = this.getNodeParameter('postId', i);
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
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/posts/${postId}/media`, {}, {}, undefined, {
                            formData,
                        });
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'delete') {
                        const mediaId = this.getNodeParameter('mediaId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'DELETE', `/media/${mediaId}`);
                    }
                    else if (operation === 'reorder') {
                        const postId = this.getNodeParameter('postId', i);
                        const order = this.getNodeParameter('order', i);
                        let orderArr;
                        try {
                            orderArr = JSON.parse(order);
                        }
                        catch {
                            orderArr = [];
                        }
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/posts/${postId}/media/reorder`, { order: orderArr });
                    }
                }
                // ==================== PLATFORM POST ====================
                else if (resource === 'platformPost') {
                    const postId = this.getNodeParameter('postId', i);
                    const platform = this.getNodeParameter('platform', i);
                    if (operation === 'update') {
                        const updateFields = this.getNodeParameter('updateFields', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'PUT', `/posts/${postId}/platforms/${platform}`, updateFields);
                    }
                    else if (operation === 'sync') {
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/posts/${postId}/platforms/${platform}/sync`);
                    }
                    else if (operation === 'toggle') {
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/posts/${postId}/platforms/${platform}/toggle`);
                    }
                }
                // ==================== CONTENT PLAN ====================
                else if (resource === 'contentPlan') {
                    const brandId = this.getNodeParameter('brandId', i);
                    if (operation === 'generatePlan') {
                        const additionalFields = this.getNodeParameter('additionalFields', i);
                        const body = { brand_id: brandId };
                        for (const key of Object.keys(additionalFields)) {
                            const val = additionalFields[key];
                            if (typeof val === 'string' && (val.startsWith('[') || val.startsWith('{'))) {
                                try {
                                    body[key] = JSON.parse(val);
                                }
                                catch {
                                    body[key] = val;
                                }
                            }
                            else {
                                body[key] = val;
                            }
                        }
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', '/content-plan/generate', body);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'generateContent') {
                        const planId = this.getNodeParameter('planId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', '/content-plan/generate-content', { brand_id: brandId, plan_id: planId });
                    }
                    else if (operation === 'regenerateContent') {
                        const planId = this.getNodeParameter('planId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', '/content-plan/regenerate-content', { brand_id: brandId, plan_id: planId });
                    }
                }
                // ==================== CALENDAR EVENT ====================
                else if (resource === 'calendarEvent') {
                    if (operation === 'getAll') {
                        const returnAll = this.getNodeParameter('returnAll', i);
                        const filters = this.getNodeParameter('filters', i);
                        const qs = { ...filters };
                        if (returnAll) {
                            responseData = await GenericFunctions_1.aiselloApiRequestAllItems.call(this, 'GET', '/events', {}, qs);
                        }
                        else {
                            const limit = this.getNodeParameter('limit', i);
                            qs.per_page = limit;
                            responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', '/events', {}, qs);
                            responseData = responseData.data || responseData;
                        }
                    }
                    else if (operation === 'get') {
                        const eventId = this.getNodeParameter('eventId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', `/events/${eventId}`);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'create') {
                        const title = this.getNodeParameter('title', i);
                        const startAt = this.getNodeParameter('startAt', i);
                        const additionalFields = this.getNodeParameter('additionalFields', i);
                        const body = { title, start_at: startAt, ...additionalFields };
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', '/events', body);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'update') {
                        const eventId = this.getNodeParameter('eventId', i);
                        const updateFields = this.getNodeParameter('updateFields', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'PUT', `/events/${eventId}`, updateFields);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'delete') {
                        const eventId = this.getNodeParameter('eventId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'DELETE', `/events/${eventId}`);
                    }
                    else if (operation === 'reschedule') {
                        const eventId = this.getNodeParameter('eventId', i);
                        const startAt = this.getNodeParameter('startAt', i);
                        const endAt = this.getNodeParameter('endAt', i);
                        const body = { start_at: startAt };
                        if (endAt)
                            body.end_at = endAt;
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/events/${eventId}/reschedule`, body);
                    }
                }
                // ==================== BOARD ====================
                else if (resource === 'board') {
                    if (operation === 'getAll') {
                        const returnAll = this.getNodeParameter('returnAll', i);
                        if (returnAll) {
                            responseData = await GenericFunctions_1.aiselloApiRequestAllItems.call(this, 'GET', '/boards');
                        }
                        else {
                            const limit = this.getNodeParameter('limit', i);
                            responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', '/boards', {}, { per_page: limit });
                            responseData = responseData.data || responseData;
                        }
                    }
                    else if (operation === 'get') {
                        const boardId = this.getNodeParameter('boardId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', `/boards/${boardId}`);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'create') {
                        const name = this.getNodeParameter('name', i);
                        const additionalFields = this.getNodeParameter('additionalFields', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', '/boards', { name, ...additionalFields });
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'update') {
                        const boardId = this.getNodeParameter('boardId', i);
                        const updateFields = this.getNodeParameter('updateFields', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'PUT', `/boards/${boardId}`, updateFields);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'delete') {
                        const boardId = this.getNodeParameter('boardId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'DELETE', `/boards/${boardId}`);
                    }
                }
                // ==================== BOARD COLUMN ====================
                else if (resource === 'boardColumn') {
                    if (operation === 'create') {
                        const boardId = this.getNodeParameter('boardId', i);
                        const name = this.getNodeParameter('name', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/boards/${boardId}/columns`, { name });
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'update') {
                        const columnId = this.getNodeParameter('columnId', i);
                        const updateFields = this.getNodeParameter('updateFields', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'PUT', `/columns/${columnId}`, updateFields);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'delete') {
                        const columnId = this.getNodeParameter('columnId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'DELETE', `/columns/${columnId}`);
                    }
                    else if (operation === 'reorder') {
                        const columnId = this.getNodeParameter('columnId', i);
                        const position = this.getNodeParameter('position', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/columns/${columnId}/reorder`, { position });
                    }
                }
                // ==================== BOARD CARD ====================
                else if (resource === 'boardCard') {
                    if (operation === 'create') {
                        const columnId = this.getNodeParameter('columnId', i);
                        const title = this.getNodeParameter('title', i);
                        const additionalFields = this.getNodeParameter('additionalFields', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/columns/${columnId}/cards`, { title, ...additionalFields });
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'update') {
                        const cardId = this.getNodeParameter('cardId', i);
                        const updateFields = this.getNodeParameter('updateFields', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'PUT', `/cards/${cardId}`, updateFields);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'delete') {
                        const cardId = this.getNodeParameter('cardId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'DELETE', `/cards/${cardId}`);
                    }
                    else if (operation === 'move') {
                        const cardId = this.getNodeParameter('cardId', i);
                        const targetColumnId = this.getNodeParameter('targetColumnId', i);
                        const position = this.getNodeParameter('position', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'PUT', `/cards/${cardId}/move`, { column_id: targetColumnId, position });
                    }
                    else if (operation === 'reorder') {
                        const cardId = this.getNodeParameter('cardId', i);
                        const position = this.getNodeParameter('position', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/cards/${cardId}/reorder`, { position });
                    }
                }
                // ==================== NOTIFICATION ====================
                else if (resource === 'notification') {
                    if (operation === 'getAll') {
                        const returnAll = this.getNodeParameter('returnAll', i);
                        if (returnAll) {
                            responseData = await GenericFunctions_1.aiselloApiRequestAllItems.call(this, 'GET', '/notifications');
                        }
                        else {
                            const limit = this.getNodeParameter('limit', i);
                            responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', '/notifications', {}, { per_page: limit });
                            responseData = responseData.data || responseData;
                        }
                    }
                    else if (operation === 'getUnreadCount') {
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', '/notifications/unread-count');
                    }
                    else if (operation === 'markAllRead') {
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', '/notifications/mark-all-read');
                    }
                    else if (operation === 'markRead') {
                        const notificationId = this.getNodeParameter('notificationId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/notifications/${notificationId}/mark-read`);
                    }
                }
                // ==================== APPROVAL TOKEN ====================
                else if (resource === 'approvalToken') {
                    if (operation === 'getAll') {
                        const returnAll = this.getNodeParameter('returnAll', i);
                        if (returnAll) {
                            responseData = await GenericFunctions_1.aiselloApiRequestAllItems.call(this, 'GET', '/approval-tokens');
                        }
                        else {
                            const limit = this.getNodeParameter('limit', i);
                            responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', '/approval-tokens', {}, { per_page: limit });
                            responseData = responseData.data || responseData;
                        }
                    }
                    else if (operation === 'get') {
                        const approvalTokenId = this.getNodeParameter('approvalTokenId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', `/approval-tokens/${approvalTokenId}`);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'create') {
                        const name = this.getNodeParameter('name', i);
                        const additionalFields = this.getNodeParameter('additionalFields', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', '/approval-tokens', { name, ...additionalFields });
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'delete') {
                        const approvalTokenId = this.getNodeParameter('approvalTokenId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'DELETE', `/approval-tokens/${approvalTokenId}`);
                    }
                    else if (operation === 'regenerate') {
                        const approvalTokenId = this.getNodeParameter('approvalTokenId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'POST', `/approval-tokens/${approvalTokenId}/regenerate`);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'getStats') {
                        const approvalTokenId = this.getNodeParameter('approvalTokenId', i);
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', `/approval-tokens/${approvalTokenId}/stats`);
                        responseData = responseData.data || responseData;
                    }
                }
                // ==================== STOCK PHOTO ====================
                else if (resource === 'stockPhoto') {
                    const limit = this.getNodeParameter('limit', i);
                    if (operation === 'search') {
                        const query = this.getNodeParameter('query', i);
                        const additionalFields = this.getNodeParameter('additionalFields', i);
                        const qs = { query, per_page: limit, ...additionalFields };
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', '/stock-photos/search', {}, qs);
                        responseData = responseData.data || responseData;
                    }
                    else if (operation === 'getFeatured') {
                        responseData = await GenericFunctions_1.aiselloApiRequest.call(this, 'GET', '/stock-photos/featured', {}, { per_page: limit });
                        responseData = responseData.data || responseData;
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
exports.Aisello = Aisello;
