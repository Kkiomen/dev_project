import {
	IExecuteFunctions,
	ILoadOptionsFunctions,
	INodeExecutionData,
	INodePropertyOptions,
	INodeType,
	INodeTypeDescription,
	IDataObject,
	NodeOperationError,
} from 'n8n-workflow';

import { aiselloApiRequest, aiselloApiRequestAllItems } from './GenericFunctions';

import { brandOperations, brandFields } from './descriptions/BrandDescription';
import { brandAutomationOperations, brandAutomationFields } from './descriptions/BrandAutomationDescription';
import { postOperations, postFields } from './descriptions/PostDescription';
import { postAutomationOperations, postAutomationFields } from './descriptions/PostAutomationDescription';
import { postMediaOperations, postMediaFields } from './descriptions/PostMediaDescription';
import { platformPostOperations, platformPostFields } from './descriptions/PlatformPostDescription';
import { contentPlanOperations, contentPlanFields } from './descriptions/ContentPlanDescription';
import { calendarEventOperations, calendarEventFields } from './descriptions/CalendarEventDescription';
import { boardOperations, boardFields } from './descriptions/BoardDescription';
import { boardColumnOperations, boardColumnFields } from './descriptions/BoardColumnDescription';
import { boardCardOperations, boardCardFields } from './descriptions/BoardCardDescription';
import { notificationOperations, notificationFields } from './descriptions/NotificationDescription';
import { approvalTokenOperations, approvalTokenFields } from './descriptions/ApprovalTokenDescription';
import { stockPhotoOperations, stockPhotoFields } from './descriptions/StockPhotoDescription';

export class Aisello implements INodeType {
	description: INodeTypeDescription = {
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
			...brandOperations,
			...brandAutomationOperations,
			...postOperations,
			...postAutomationOperations,
			...postMediaOperations,
			...platformPostOperations,
			...contentPlanOperations,
			...calendarEventOperations,
			...boardOperations,
			...boardColumnOperations,
			...boardCardOperations,
			...notificationOperations,
			...approvalTokenOperations,
			...stockPhotoOperations,
			// Fields
			...brandFields,
			...brandAutomationFields,
			...postFields,
			...postAutomationFields,
			...postMediaFields,
			...platformPostFields,
			...contentPlanFields,
			...calendarEventFields,
			...boardFields,
			...boardColumnFields,
			...boardCardFields,
			...notificationFields,
			...approvalTokenFields,
			...stockPhotoFields,
		],
	};

	methods = {
		loadOptions: {
			async getBrands(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]> {
				try {
					const responseData = await aiselloApiRequest.call(this, 'GET', '/brands', {}, { per_page: 100 });
					const brands = responseData.data || responseData;
					return Array.isArray(brands)
						? brands.map((b: any) => ({ name: b.name, value: b.id }))
						: [];
				} catch {
					return [];
				}
			},
			async getPosts(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]> {
				try {
					const responseData = await aiselloApiRequest.call(this, 'GET', '/posts', {}, { per_page: 100 });
					const posts = responseData.data || responseData;
					return Array.isArray(posts)
						? posts.map((p: any) => ({
								name: p.title || (p.content ? p.content.substring(0, 50) + '...' : `Post ${p.id}`),
								value: p.id,
							}))
						: [];
				} catch {
					return [];
				}
			},
			async getBoards(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]> {
				try {
					const responseData = await aiselloApiRequest.call(this, 'GET', '/boards', {}, { per_page: 100 });
					const boards = responseData.data || responseData;
					return Array.isArray(boards)
						? boards.map((b: any) => ({ name: b.name, value: b.id }))
						: [];
				} catch {
					return [];
				}
			},
			async getApprovalTokens(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]> {
				try {
					const responseData = await aiselloApiRequest.call(this, 'GET', '/approval-tokens', {}, { per_page: 100 });
					const tokens = responseData.data || responseData;
					return Array.isArray(tokens)
						? tokens.map((t: any) => ({ name: t.name, value: t.id }))
						: [];
				} catch {
					return [];
				}
			},
		},
	};

	async execute(this: IExecuteFunctions): Promise<INodeExecutionData[][]> {
		const items = this.getInputData();
		const returnData: INodeExecutionData[] = [];
		const resource = this.getNodeParameter('resource', 0) as string;
		const operation = this.getNodeParameter('operation', 0) as string;

		for (let i = 0; i < items.length; i++) {
			try {
				let responseData: any;

				// ==================== BRAND ====================
				if (resource === 'brand') {
					if (operation === 'getAll') {
						const returnAll = this.getNodeParameter('returnAll', i) as boolean;
						if (returnAll) {
							responseData = await aiselloApiRequestAllItems.call(this, 'GET', '/brands');
						} else {
							const limit = this.getNodeParameter('limit', i) as number;
							responseData = await aiselloApiRequest.call(this, 'GET', '/brands', {}, { per_page: limit });
							responseData = responseData.data || responseData;
						}
					} else if (operation === 'get') {
						const brandId = this.getNodeParameter('brandId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'GET', `/brands/${brandId}`);
						responseData = responseData.data || responseData;
					} else if (operation === 'create') {
						const name = this.getNodeParameter('name', i) as string;
						const additionalFields = this.getNodeParameter('additionalFields', i) as IDataObject;
						const body: IDataObject = { name, ...additionalFields };
						responseData = await aiselloApiRequest.call(this, 'POST', '/brands', body);
						responseData = responseData.data || responseData;
					} else if (operation === 'update') {
						const brandId = this.getNodeParameter('brandId', i) as string;
						const updateFields = this.getNodeParameter('updateFields', i) as IDataObject;
						responseData = await aiselloApiRequest.call(this, 'PUT', `/brands/${brandId}`, updateFields);
						responseData = responseData.data || responseData;
					} else if (operation === 'delete') {
						const brandId = this.getNodeParameter('brandId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'DELETE', `/brands/${brandId}`);
					} else if (operation === 'setCurrent') {
						const brandId = this.getNodeParameter('brandId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'POST', `/brands/${brandId}/set-current`);
					}
				}

				// ==================== BRAND AUTOMATION ====================
				else if (resource === 'brandAutomation') {
					const brandId = this.getNodeParameter('brandId', i) as string;
					if (operation === 'getStats') {
						responseData = await aiselloApiRequest.call(this, 'GET', `/brands/${brandId}/automation/stats`);
						responseData = responseData.data || responseData;
					} else if (operation === 'enable') {
						responseData = await aiselloApiRequest.call(this, 'POST', `/brands/${brandId}/automation/enable`);
					} else if (operation === 'disable') {
						responseData = await aiselloApiRequest.call(this, 'POST', `/brands/${brandId}/automation/disable`);
					} else if (operation === 'trigger') {
						responseData = await aiselloApiRequest.call(this, 'POST', `/brands/${brandId}/automation/process`);
					} else if (operation === 'extendQueue') {
						const count = this.getNodeParameter('count', i) as number;
						responseData = await aiselloApiRequest.call(this, 'POST', `/brands/${brandId}/automation/extend`, { count });
					} else if (operation === 'updateSettings') {
						const settings = this.getNodeParameter('settings', i) as IDataObject;
						const body: IDataObject = {};
						for (const key of Object.keys(settings)) {
							const val = settings[key];
							if (typeof val === 'string' && (val.startsWith('[') || val.startsWith('{'))) {
								try { body[key] = JSON.parse(val); } catch { body[key] = val; }
							} else {
								body[key] = val;
							}
						}
						responseData = await aiselloApiRequest.call(this, 'PUT', `/brands/${brandId}/automation/settings`, body);
					} else if (operation === 'getSystemPrompts') {
						responseData = await aiselloApiRequest.call(this, 'GET', `/brands/${brandId}/automation/system-prompts`);
						responseData = responseData.data || responseData;
					} else if (operation === 'updateSystemPrompts') {
						const prompts = this.getNodeParameter('prompts', i) as IDataObject;
						responseData = await aiselloApiRequest.call(this, 'PUT', `/brands/${brandId}/automation/system-prompts`, prompts);
						responseData = responseData.data || responseData;
					} else if (operation === 'getResolvedPrompt') {
						const promptType = this.getNodeParameter('promptType', i) as string;
						responseData = await aiselloApiRequest.call(this, 'GET', `/brands/${brandId}/automation/resolved-prompt`, {}, { type: promptType });
						responseData = responseData.data || responseData;
					}
				}

				// ==================== POST ====================
				else if (resource === 'post') {
					if (operation === 'getAll') {
						const brandId = this.getNodeParameter('brandId', i) as string;
						const returnAll = this.getNodeParameter('returnAll', i) as boolean;
						const filters = this.getNodeParameter('filters', i) as IDataObject;
						const qs: IDataObject = { brand_id: brandId, ...filters };
						if (returnAll) {
							responseData = await aiselloApiRequestAllItems.call(this, 'GET', '/posts', {}, qs);
						} else {
							const limit = this.getNodeParameter('limit', i) as number;
							qs.per_page = limit;
							responseData = await aiselloApiRequest.call(this, 'GET', '/posts', {}, qs);
							responseData = responseData.data || responseData;
						}
					} else if (operation === 'get') {
						const postId = this.getNodeParameter('postId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'GET', `/posts/${postId}`);
						responseData = responseData.data || responseData;
					} else if (operation === 'create') {
						const brandId = this.getNodeParameter('brandId', i) as string;
						const content = this.getNodeParameter('content', i) as string;
						const additionalFields = this.getNodeParameter('additionalFields', i) as IDataObject;
						const body: IDataObject = { brand_id: brandId, content, ...additionalFields };
						if (body.platforms && typeof body.platforms === 'string') {
							try { body.platforms = JSON.parse(body.platforms as string); } catch { /* keep as string */ }
						}
						responseData = await aiselloApiRequest.call(this, 'POST', '/posts', body);
						responseData = responseData.data || responseData;
					} else if (operation === 'update') {
						const postId = this.getNodeParameter('postId', i) as string;
						const updateFields = this.getNodeParameter('updateFields', i) as IDataObject;
						responseData = await aiselloApiRequest.call(this, 'PUT', `/posts/${postId}`, updateFields);
						responseData = responseData.data || responseData;
					} else if (operation === 'delete') {
						const postId = this.getNodeParameter('postId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'DELETE', `/posts/${postId}`);
					} else if (operation === 'reschedule') {
						const postId = this.getNodeParameter('postId', i) as string;
						const scheduledAt = this.getNodeParameter('scheduledAt', i) as string;
						responseData = await aiselloApiRequest.call(this, 'POST', `/posts/${postId}/reschedule`, { scheduled_at: scheduledAt });
					} else if (operation === 'duplicate') {
						const postId = this.getNodeParameter('postId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'POST', `/posts/${postId}/duplicate`);
						responseData = responseData.data || responseData;
					} else if (operation === 'approve') {
						const postId = this.getNodeParameter('postId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'POST', `/posts/${postId}/approve`);
					} else if (operation === 'reject') {
						const postId = this.getNodeParameter('postId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'POST', `/posts/${postId}/reject`);
					} else if (operation === 'publish') {
						const postId = this.getNodeParameter('postId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'POST', `/posts/${postId}/publish`);
					} else if (operation === 'aiGenerate') {
						const brandId = this.getNodeParameter('brandId', i) as string;
						const prompt = this.getNodeParameter('prompt', i) as string;
						const additionalFields = this.getNodeParameter('additionalFields', i) as IDataObject;
						const body: IDataObject = { brand_id: brandId, prompt, ...additionalFields };
						responseData = await aiselloApiRequest.call(this, 'POST', '/posts/ai/generate', body);
						responseData = responseData.data || responseData;
					} else if (operation === 'aiModify') {
						const postId = this.getNodeParameter('postId', i) as string;
						const instruction = this.getNodeParameter('instruction', i) as string;
						responseData = await aiselloApiRequest.call(this, 'POST', '/posts/ai/modify', { post_id: postId, instruction });
						responseData = responseData.data || responseData;
					}
				}

				// ==================== POST AUTOMATION ====================
				else if (resource === 'postAutomation') {
					if (operation === 'getAll') {
						const returnAll = this.getNodeParameter('returnAll', i) as boolean;
						const filters = this.getNodeParameter('filters', i) as IDataObject;
						if (returnAll) {
							responseData = await aiselloApiRequestAllItems.call(this, 'GET', '/posts/automation', {}, filters);
						} else {
							const limit = this.getNodeParameter('limit', i) as number;
							responseData = await aiselloApiRequest.call(this, 'GET', '/posts/automation', {}, { per_page: limit, ...filters });
							responseData = responseData.data || responseData;
						}
					} else if (operation === 'generateText') {
						const postId = this.getNodeParameter('postId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'POST', `/posts/${postId}/generate-text`);
					} else if (operation === 'generateImagePrompt') {
						const postId = this.getNodeParameter('postId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'POST', `/posts/${postId}/generate-image-prompt`);
					} else if (operation === 'webhookPublish') {
						const postId = this.getNodeParameter('postId', i) as string;
						const additionalFields = this.getNodeParameter('additionalFields', i) as IDataObject;
						responseData = await aiselloApiRequest.call(this, 'POST', `/posts/${postId}/webhook-publish`, additionalFields);
					} else if (operation === 'bulkGenerateText') {
						const postIds = (this.getNodeParameter('postIds', i) as string).split(',').map((id) => id.trim());
						responseData = await aiselloApiRequest.call(this, 'POST', '/posts/bulk-generate-text', { post_ids: postIds });
					} else if (operation === 'bulkGenerateImage') {
						const postIds = (this.getNodeParameter('postIds', i) as string).split(',').map((id) => id.trim());
						responseData = await aiselloApiRequest.call(this, 'POST', '/posts/bulk-generate-image-prompt', { post_ids: postIds });
					} else if (operation === 'getTextGenerationData') {
						const postId = this.getNodeParameter('postId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'GET', `/posts/${postId}/text-generation-data`);
						responseData = responseData.data || responseData;
					} else if (operation === 'getImageGenerationData') {
						const postId = this.getNodeParameter('postId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'GET', `/posts/${postId}/image-generation-data`);
						responseData = responseData.data || responseData;
					}
				}

				// ==================== POST MEDIA ====================
				else if (resource === 'postMedia') {
					if (operation === 'getAll') {
						const postId = this.getNodeParameter('postId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'GET', `/posts/${postId}/media`);
						responseData = responseData.data || responseData;
					} else if (operation === 'upload') {
						const postId = this.getNodeParameter('postId', i) as string;
						const binaryPropertyName = this.getNodeParameter('binaryPropertyName', i) as string;
						const binaryData = this.helpers.assertBinaryData(i, binaryPropertyName);
						const dataBuffer = await this.helpers.getBinaryDataBuffer(i, binaryPropertyName);

						const formData: IDataObject = {
							file: {
								value: dataBuffer,
								options: {
									filename: binaryData.fileName || 'upload',
									contentType: binaryData.mimeType,
								},
							},
						};
						responseData = await aiselloApiRequest.call(this, 'POST', `/posts/${postId}/media`, {}, {}, undefined, {
							formData,
						});
						responseData = responseData.data || responseData;
					} else if (operation === 'delete') {
						const mediaId = this.getNodeParameter('mediaId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'DELETE', `/media/${mediaId}`);
					} else if (operation === 'reorder') {
						const postId = this.getNodeParameter('postId', i) as string;
						const order = this.getNodeParameter('order', i) as string;
						let orderArr: string[];
						try { orderArr = JSON.parse(order); } catch { orderArr = []; }
						responseData = await aiselloApiRequest.call(this, 'POST', `/posts/${postId}/media/reorder`, { order: orderArr });
					}
				}

				// ==================== PLATFORM POST ====================
				else if (resource === 'platformPost') {
					const postId = this.getNodeParameter('postId', i) as string;
					const platform = this.getNodeParameter('platform', i) as string;
					if (operation === 'update') {
						const updateFields = this.getNodeParameter('updateFields', i) as IDataObject;
						responseData = await aiselloApiRequest.call(this, 'PUT', `/posts/${postId}/platforms/${platform}`, updateFields);
					} else if (operation === 'sync') {
						responseData = await aiselloApiRequest.call(this, 'POST', `/posts/${postId}/platforms/${platform}/sync`);
					} else if (operation === 'toggle') {
						responseData = await aiselloApiRequest.call(this, 'POST', `/posts/${postId}/platforms/${platform}/toggle`);
					}
				}

				// ==================== CONTENT PLAN ====================
				else if (resource === 'contentPlan') {
					const brandId = this.getNodeParameter('brandId', i) as string;
					if (operation === 'generatePlan') {
						const additionalFields = this.getNodeParameter('additionalFields', i) as IDataObject;
						const body: IDataObject = { brand_id: brandId };
						for (const key of Object.keys(additionalFields)) {
							const val = additionalFields[key];
							if (typeof val === 'string' && (val.startsWith('[') || val.startsWith('{'))) {
								try { body[key] = JSON.parse(val); } catch { body[key] = val; }
							} else {
								body[key] = val;
							}
						}
						responseData = await aiselloApiRequest.call(this, 'POST', '/content-plan/generate', body);
						responseData = responseData.data || responseData;
					} else if (operation === 'generateContent') {
						const planId = this.getNodeParameter('planId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'POST', '/content-plan/generate-content', { brand_id: brandId, plan_id: planId });
					} else if (operation === 'regenerateContent') {
						const planId = this.getNodeParameter('planId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'POST', '/content-plan/regenerate-content', { brand_id: brandId, plan_id: planId });
					}
				}

				// ==================== CALENDAR EVENT ====================
				else if (resource === 'calendarEvent') {
					if (operation === 'getAll') {
						const returnAll = this.getNodeParameter('returnAll', i) as boolean;
						const filters = this.getNodeParameter('filters', i) as IDataObject;
						const qs: IDataObject = { ...filters };
						if (returnAll) {
							responseData = await aiselloApiRequestAllItems.call(this, 'GET', '/events', {}, qs);
						} else {
							const limit = this.getNodeParameter('limit', i) as number;
							qs.per_page = limit;
							responseData = await aiselloApiRequest.call(this, 'GET', '/events', {}, qs);
							responseData = responseData.data || responseData;
						}
					} else if (operation === 'get') {
						const eventId = this.getNodeParameter('eventId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'GET', `/events/${eventId}`);
						responseData = responseData.data || responseData;
					} else if (operation === 'create') {
						const title = this.getNodeParameter('title', i) as string;
						const startAt = this.getNodeParameter('startAt', i) as string;
						const additionalFields = this.getNodeParameter('additionalFields', i) as IDataObject;
						const body: IDataObject = { title, start_at: startAt, ...additionalFields };
						responseData = await aiselloApiRequest.call(this, 'POST', '/events', body);
						responseData = responseData.data || responseData;
					} else if (operation === 'update') {
						const eventId = this.getNodeParameter('eventId', i) as string;
						const updateFields = this.getNodeParameter('updateFields', i) as IDataObject;
						responseData = await aiselloApiRequest.call(this, 'PUT', `/events/${eventId}`, updateFields);
						responseData = responseData.data || responseData;
					} else if (operation === 'delete') {
						const eventId = this.getNodeParameter('eventId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'DELETE', `/events/${eventId}`);
					} else if (operation === 'reschedule') {
						const eventId = this.getNodeParameter('eventId', i) as string;
						const startAt = this.getNodeParameter('startAt', i) as string;
						const endAt = this.getNodeParameter('endAt', i) as string;
						const body: IDataObject = { start_at: startAt };
						if (endAt) body.end_at = endAt;
						responseData = await aiselloApiRequest.call(this, 'POST', `/events/${eventId}/reschedule`, body);
					}
				}

				// ==================== BOARD ====================
				else if (resource === 'board') {
					if (operation === 'getAll') {
						const returnAll = this.getNodeParameter('returnAll', i) as boolean;
						if (returnAll) {
							responseData = await aiselloApiRequestAllItems.call(this, 'GET', '/boards');
						} else {
							const limit = this.getNodeParameter('limit', i) as number;
							responseData = await aiselloApiRequest.call(this, 'GET', '/boards', {}, { per_page: limit });
							responseData = responseData.data || responseData;
						}
					} else if (operation === 'get') {
						const boardId = this.getNodeParameter('boardId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'GET', `/boards/${boardId}`);
						responseData = responseData.data || responseData;
					} else if (operation === 'create') {
						const name = this.getNodeParameter('name', i) as string;
						const additionalFields = this.getNodeParameter('additionalFields', i) as IDataObject;
						responseData = await aiselloApiRequest.call(this, 'POST', '/boards', { name, ...additionalFields });
						responseData = responseData.data || responseData;
					} else if (operation === 'update') {
						const boardId = this.getNodeParameter('boardId', i) as string;
						const updateFields = this.getNodeParameter('updateFields', i) as IDataObject;
						responseData = await aiselloApiRequest.call(this, 'PUT', `/boards/${boardId}`, updateFields);
						responseData = responseData.data || responseData;
					} else if (operation === 'delete') {
						const boardId = this.getNodeParameter('boardId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'DELETE', `/boards/${boardId}`);
					}
				}

				// ==================== BOARD COLUMN ====================
				else if (resource === 'boardColumn') {
					if (operation === 'create') {
						const boardId = this.getNodeParameter('boardId', i) as string;
						const name = this.getNodeParameter('name', i) as string;
						responseData = await aiselloApiRequest.call(this, 'POST', `/boards/${boardId}/columns`, { name });
						responseData = responseData.data || responseData;
					} else if (operation === 'update') {
						const columnId = this.getNodeParameter('columnId', i) as string;
						const updateFields = this.getNodeParameter('updateFields', i) as IDataObject;
						responseData = await aiselloApiRequest.call(this, 'PUT', `/columns/${columnId}`, updateFields);
						responseData = responseData.data || responseData;
					} else if (operation === 'delete') {
						const columnId = this.getNodeParameter('columnId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'DELETE', `/columns/${columnId}`);
					} else if (operation === 'reorder') {
						const columnId = this.getNodeParameter('columnId', i) as string;
						const position = this.getNodeParameter('position', i) as number;
						responseData = await aiselloApiRequest.call(this, 'POST', `/columns/${columnId}/reorder`, { position });
					}
				}

				// ==================== BOARD CARD ====================
				else if (resource === 'boardCard') {
					if (operation === 'create') {
						const columnId = this.getNodeParameter('columnId', i) as string;
						const title = this.getNodeParameter('title', i) as string;
						const additionalFields = this.getNodeParameter('additionalFields', i) as IDataObject;
						responseData = await aiselloApiRequest.call(this, 'POST', `/columns/${columnId}/cards`, { title, ...additionalFields });
						responseData = responseData.data || responseData;
					} else if (operation === 'update') {
						const cardId = this.getNodeParameter('cardId', i) as string;
						const updateFields = this.getNodeParameter('updateFields', i) as IDataObject;
						responseData = await aiselloApiRequest.call(this, 'PUT', `/cards/${cardId}`, updateFields);
						responseData = responseData.data || responseData;
					} else if (operation === 'delete') {
						const cardId = this.getNodeParameter('cardId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'DELETE', `/cards/${cardId}`);
					} else if (operation === 'move') {
						const cardId = this.getNodeParameter('cardId', i) as string;
						const targetColumnId = this.getNodeParameter('targetColumnId', i) as string;
						const position = this.getNodeParameter('position', i) as number;
						responseData = await aiselloApiRequest.call(this, 'PUT', `/cards/${cardId}/move`, { column_id: targetColumnId, position });
					} else if (operation === 'reorder') {
						const cardId = this.getNodeParameter('cardId', i) as string;
						const position = this.getNodeParameter('position', i) as number;
						responseData = await aiselloApiRequest.call(this, 'POST', `/cards/${cardId}/reorder`, { position });
					}
				}

				// ==================== NOTIFICATION ====================
				else if (resource === 'notification') {
					if (operation === 'getAll') {
						const returnAll = this.getNodeParameter('returnAll', i) as boolean;
						if (returnAll) {
							responseData = await aiselloApiRequestAllItems.call(this, 'GET', '/notifications');
						} else {
							const limit = this.getNodeParameter('limit', i) as number;
							responseData = await aiselloApiRequest.call(this, 'GET', '/notifications', {}, { per_page: limit });
							responseData = responseData.data || responseData;
						}
					} else if (operation === 'getUnreadCount') {
						responseData = await aiselloApiRequest.call(this, 'GET', '/notifications/unread-count');
					} else if (operation === 'markAllRead') {
						responseData = await aiselloApiRequest.call(this, 'POST', '/notifications/mark-all-read');
					} else if (operation === 'markRead') {
						const notificationId = this.getNodeParameter('notificationId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'POST', `/notifications/${notificationId}/mark-read`);
					}
				}

				// ==================== APPROVAL TOKEN ====================
				else if (resource === 'approvalToken') {
					if (operation === 'getAll') {
						const returnAll = this.getNodeParameter('returnAll', i) as boolean;
						if (returnAll) {
							responseData = await aiselloApiRequestAllItems.call(this, 'GET', '/approval-tokens');
						} else {
							const limit = this.getNodeParameter('limit', i) as number;
							responseData = await aiselloApiRequest.call(this, 'GET', '/approval-tokens', {}, { per_page: limit });
							responseData = responseData.data || responseData;
						}
					} else if (operation === 'get') {
						const approvalTokenId = this.getNodeParameter('approvalTokenId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'GET', `/approval-tokens/${approvalTokenId}`);
						responseData = responseData.data || responseData;
					} else if (operation === 'create') {
						const name = this.getNodeParameter('name', i) as string;
						const additionalFields = this.getNodeParameter('additionalFields', i) as IDataObject;
						responseData = await aiselloApiRequest.call(this, 'POST', '/approval-tokens', { name, ...additionalFields });
						responseData = responseData.data || responseData;
					} else if (operation === 'delete') {
						const approvalTokenId = this.getNodeParameter('approvalTokenId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'DELETE', `/approval-tokens/${approvalTokenId}`);
					} else if (operation === 'regenerate') {
						const approvalTokenId = this.getNodeParameter('approvalTokenId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'POST', `/approval-tokens/${approvalTokenId}/regenerate`);
						responseData = responseData.data || responseData;
					} else if (operation === 'getStats') {
						const approvalTokenId = this.getNodeParameter('approvalTokenId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'GET', `/approval-tokens/${approvalTokenId}/stats`);
						responseData = responseData.data || responseData;
					}
				}

				// ==================== STOCK PHOTO ====================
				else if (resource === 'stockPhoto') {
					const limit = this.getNodeParameter('limit', i) as number;
					if (operation === 'search') {
						const query = this.getNodeParameter('query', i) as string;
						const additionalFields = this.getNodeParameter('additionalFields', i) as IDataObject;
						const qs: IDataObject = { query, per_page: limit, ...additionalFields };
						responseData = await aiselloApiRequest.call(this, 'GET', '/stock-photos/search', {}, qs);
						responseData = responseData.data || responseData;
					} else if (operation === 'getFeatured') {
						responseData = await aiselloApiRequest.call(this, 'GET', '/stock-photos/featured', {}, { per_page: limit });
						responseData = responseData.data || responseData;
					}
				}

				// Normalize response
				if (responseData === undefined) {
					responseData = { success: true };
				}

				const executionData = this.helpers.constructExecutionMetaData(
					this.helpers.returnJsonArray(responseData as IDataObject | IDataObject[]),
					{ itemData: { item: i } },
				);
				returnData.push(...executionData);
			} catch (error) {
				if (this.continueOnFail()) {
					const executionData = this.helpers.constructExecutionMetaData(
						this.helpers.returnJsonArray({ error: (error as Error).message }),
						{ itemData: { item: i } },
					);
					returnData.push(...executionData);
					continue;
				}
				throw error;
			}
		}

		return [returnData];
	}
}
