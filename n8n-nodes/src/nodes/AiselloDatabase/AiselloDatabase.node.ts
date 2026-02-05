import {
	IExecuteFunctions,
	ILoadOptionsFunctions,
	INodeExecutionData,
	INodePropertyOptions,
	INodeType,
	INodeTypeDescription,
	IDataObject,
} from 'n8n-workflow';

import { aiselloApiRequest, aiselloApiRequestAllItems } from '../Aisello/GenericFunctions';

import { baseOperations, baseFields } from './descriptions/BaseDescription';
import { tableOperations, tableFields } from './descriptions/TableDescription';
import { fieldOperations, fieldFields } from './descriptions/FieldDescription';
import { rowOperations, rowFields } from './descriptions/RowDescription';
import { cellOperations, cellFields } from './descriptions/CellDescription';
import { attachmentOperations, attachmentFields } from './descriptions/AttachmentDescription';

export class AiselloDatabase implements INodeType {
	description: INodeTypeDescription = {
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
			...baseOperations,
			...tableOperations,
			...fieldOperations,
			...rowOperations,
			...cellOperations,
			...attachmentOperations,
			...baseFields,
			...tableFields,
			...fieldFields,
			...rowFields,
			...cellFields,
			...attachmentFields,
		],
	};

	methods = {
		loadOptions: {
			async getBases(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]> {
				try {
					const responseData = await aiselloApiRequest.call(this, 'GET', '/bases', {}, { per_page: 100 });
					const bases = responseData.data || responseData;
					return Array.isArray(bases)
						? bases.map((b: any) => ({ name: b.name, value: b.id }))
						: [];
				} catch {
					return [];
				}
			},
			async getTables(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]> {
				try {
					const baseId = this.getCurrentNodeParameter('baseId') as string;
					if (!baseId) return [];
					const responseData = await aiselloApiRequest.call(this, 'GET', `/bases/${baseId}/tables`, {}, { per_page: 100 });
					const tables = responseData.data || responseData;
					return Array.isArray(tables)
						? tables.map((t: any) => ({ name: t.name, value: t.id }))
						: [];
				} catch {
					return [];
				}
			},
			async getFields(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]> {
				try {
					const tableId = this.getCurrentNodeParameter('tableId') as string;
					if (!tableId) return [];
					const responseData = await aiselloApiRequest.call(this, 'GET', `/tables/${tableId}/fields`, {}, { per_page: 100 });
					const fields = responseData.data || responseData;
					return Array.isArray(fields)
						? fields.map((f: any) => ({ name: f.name, value: f.id }))
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

				// ==================== BASE ====================
				if (resource === 'base') {
					if (operation === 'getAll') {
						const returnAll = this.getNodeParameter('returnAll', i) as boolean;
						if (returnAll) {
							responseData = await aiselloApiRequestAllItems.call(this, 'GET', '/bases');
						} else {
							const limit = this.getNodeParameter('limit', i) as number;
							responseData = await aiselloApiRequest.call(this, 'GET', '/bases', {}, { per_page: limit });
							responseData = responseData.data || responseData;
						}
					} else if (operation === 'get') {
						const baseId = this.getNodeParameter('baseId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'GET', `/bases/${baseId}`);
						responseData = responseData.data || responseData;
					} else if (operation === 'create') {
						const name = this.getNodeParameter('name', i) as string;
						const additionalFields = this.getNodeParameter('additionalFields', i) as IDataObject;
						responseData = await aiselloApiRequest.call(this, 'POST', '/bases', { name, ...additionalFields });
						responseData = responseData.data || responseData;
					} else if (operation === 'update') {
						const baseId = this.getNodeParameter('baseId', i) as string;
						const updateFields = this.getNodeParameter('updateFields', i) as IDataObject;
						responseData = await aiselloApiRequest.call(this, 'PUT', `/bases/${baseId}`, updateFields);
						responseData = responseData.data || responseData;
					} else if (operation === 'delete') {
						const baseId = this.getNodeParameter('baseId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'DELETE', `/bases/${baseId}`);
					}
				}

				// ==================== TABLE ====================
				else if (resource === 'table') {
					if (operation === 'getAll') {
						const baseId = this.getNodeParameter('baseId', i) as string;
						const returnAll = this.getNodeParameter('returnAll', i) as boolean;
						if (returnAll) {
							responseData = await aiselloApiRequestAllItems.call(this, 'GET', `/bases/${baseId}/tables`);
						} else {
							const limit = this.getNodeParameter('limit', i) as number;
							responseData = await aiselloApiRequest.call(this, 'GET', `/bases/${baseId}/tables`, {}, { per_page: limit });
							responseData = responseData.data || responseData;
						}
					} else if (operation === 'get') {
						const tableId = this.getNodeParameter('tableId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'GET', `/tables/${tableId}`);
						responseData = responseData.data || responseData;
					} else if (operation === 'create') {
						const baseId = this.getNodeParameter('baseId', i) as string;
						const name = this.getNodeParameter('name', i) as string;
						responseData = await aiselloApiRequest.call(this, 'POST', `/bases/${baseId}/tables`, { name });
						responseData = responseData.data || responseData;
					} else if (operation === 'update') {
						const tableId = this.getNodeParameter('tableId', i) as string;
						const updateFields = this.getNodeParameter('updateFields', i) as IDataObject;
						responseData = await aiselloApiRequest.call(this, 'PUT', `/tables/${tableId}`, updateFields);
						responseData = responseData.data || responseData;
					} else if (operation === 'delete') {
						const tableId = this.getNodeParameter('tableId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'DELETE', `/tables/${tableId}`);
					} else if (operation === 'reorder') {
						const tableId = this.getNodeParameter('tableId', i) as string;
						const position = this.getNodeParameter('position', i) as number;
						responseData = await aiselloApiRequest.call(this, 'POST', `/tables/${tableId}/reorder`, { position });
					}
				}

				// ==================== FIELD ====================
				else if (resource === 'field') {
					if (operation === 'getAll') {
						const tableId = this.getNodeParameter('tableId', i) as string;
						const returnAll = this.getNodeParameter('returnAll', i) as boolean;
						if (returnAll) {
							responseData = await aiselloApiRequestAllItems.call(this, 'GET', `/tables/${tableId}/fields`);
						} else {
							const limit = this.getNodeParameter('limit', i) as number;
							responseData = await aiselloApiRequest.call(this, 'GET', `/tables/${tableId}/fields`, {}, { per_page: limit });
							responseData = responseData.data || responseData;
						}
					} else if (operation === 'get') {
						const fieldId = this.getNodeParameter('fieldId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'GET', `/fields/${fieldId}`);
						responseData = responseData.data || responseData;
					} else if (operation === 'create') {
						const tableId = this.getNodeParameter('tableId', i) as string;
						const name = this.getNodeParameter('name', i) as string;
						const type = this.getNodeParameter('type', i) as string;
						const additionalFields = this.getNodeParameter('additionalFields', i) as IDataObject;
						const body: IDataObject = { name, type, ...additionalFields };
						if (body.options && typeof body.options === 'string') {
							try { body.options = JSON.parse(body.options as string); } catch { /* keep as string */ }
						}
						responseData = await aiselloApiRequest.call(this, 'POST', `/tables/${tableId}/fields`, body);
						responseData = responseData.data || responseData;
					} else if (operation === 'update') {
						const fieldId = this.getNodeParameter('fieldId', i) as string;
						const updateFields = this.getNodeParameter('updateFields', i) as IDataObject;
						responseData = await aiselloApiRequest.call(this, 'PUT', `/fields/${fieldId}`, updateFields);
						responseData = responseData.data || responseData;
					} else if (operation === 'delete') {
						const fieldId = this.getNodeParameter('fieldId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'DELETE', `/fields/${fieldId}`);
					} else if (operation === 'reorder') {
						const fieldId = this.getNodeParameter('fieldId', i) as string;
						const position = this.getNodeParameter('position', i) as number;
						responseData = await aiselloApiRequest.call(this, 'POST', `/fields/${fieldId}/reorder`, { position });
					} else if (operation === 'addChoice') {
						const fieldId = this.getNodeParameter('fieldId', i) as string;
						const choiceName = this.getNodeParameter('choiceName', i) as string;
						const additionalFields = this.getNodeParameter('additionalFields', i) as IDataObject;
						responseData = await aiselloApiRequest.call(this, 'POST', `/fields/${fieldId}/choices`, { name: choiceName, ...additionalFields });
						responseData = responseData.data || responseData;
					}
				}

				// ==================== ROW ====================
				else if (resource === 'row') {
					if (operation === 'getAll') {
						const tableId = this.getNodeParameter('tableId', i) as string;
						const returnAll = this.getNodeParameter('returnAll', i) as boolean;
						if (returnAll) {
							responseData = await aiselloApiRequestAllItems.call(this, 'GET', `/tables/${tableId}/rows`);
						} else {
							const limit = this.getNodeParameter('limit', i) as number;
							responseData = await aiselloApiRequest.call(this, 'GET', `/tables/${tableId}/rows`, {}, { per_page: limit });
							responseData = responseData.data || responseData;
						}
					} else if (operation === 'get') {
						const rowId = this.getNodeParameter('rowId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'GET', `/rows/${rowId}`);
						responseData = responseData.data || responseData;
					} else if (operation === 'create') {
						const tableId = this.getNodeParameter('tableId', i) as string;
						const cellsStr = this.getNodeParameter('cells', i) as string;
						let cells: IDataObject;
						try { cells = JSON.parse(cellsStr); } catch { cells = {}; }
						responseData = await aiselloApiRequest.call(this, 'POST', `/tables/${tableId}/rows`, { values: cells });
						responseData = responseData.data || responseData;
					} else if (operation === 'update') {
						const rowId = this.getNodeParameter('rowId', i) as string;
						const cellsParam = this.getNodeParameter('cells', i);
						let cells: IDataObject = {};
						
						if (typeof cellsParam === 'string') {
							const trimmed = cellsParam.trim();
							if (trimmed === '' || trimmed === '{}') {
								cells = {};
							} else {
								try { 
									const parsed = JSON.parse(trimmed);
									if (typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)) {
										cells = parsed as IDataObject;
									} else {
										cells = {};
									}
								} catch { 
									cells = {}; 
								}
							}
						} else if (typeof cellsParam === 'object' && cellsParam !== null && !Array.isArray(cellsParam)) {
							cells = cellsParam as IDataObject;
						}
						
						// Ensure we always send an object (not array) for values
						const body: IDataObject = { values: cells };
						responseData = await aiselloApiRequest.call(this, 'PUT', `/rows/${rowId}`, body);
						responseData = responseData.data || responseData;
					} else if (operation === 'delete') {
						const rowId = this.getNodeParameter('rowId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'DELETE', `/rows/${rowId}`);
					} else if (operation === 'bulkCreate') {
						const tableId = this.getNodeParameter('tableId', i) as string;
						const rowsStr = this.getNodeParameter('rows', i) as string;
						let rows: IDataObject[];
						try { rows = JSON.parse(rowsStr); } catch { rows = []; }
						// Transform cells to values for backward compatibility
						rows = rows.map((row: IDataObject) => {
							if (row.cells && !row.values) {
								return { ...row, values: row.cells, cells: undefined };
							}
							return row;
						});
						responseData = await aiselloApiRequest.call(this, 'POST', `/tables/${tableId}/rows/bulk`, { rows });
						responseData = responseData.data || responseData;
					} else if (operation === 'bulkDelete') {
						const tableId = this.getNodeParameter('tableId', i) as string;
						const rowIds = (this.getNodeParameter('rowIds', i) as string).split(',').map((id) => id.trim());
						responseData = await aiselloApiRequest.call(this, 'DELETE', `/tables/${tableId}/rows/bulk`, { row_ids: rowIds });
					} else if (operation === 'reorder') {
						const rowId = this.getNodeParameter('rowId', i) as string;
						const position = this.getNodeParameter('position', i) as number;
						responseData = await aiselloApiRequest.call(this, 'POST', `/rows/${rowId}/reorder`, { position });
					}
				}

				// ==================== CELL ====================
				else if (resource === 'cell') {
					const rowId = this.getNodeParameter('rowId', i) as string;
					if (operation === 'update') {
						const fieldId = this.getNodeParameter('fieldId', i) as string;
						const value = this.getNodeParameter('value', i) as string;
						responseData = await aiselloApiRequest.call(this, 'PUT', `/rows/${rowId}/cells/${fieldId}`, { value });
						responseData = responseData.data || responseData;
					} else if (operation === 'bulkUpdate') {
						const cellsStr = this.getNodeParameter('cells', i) as string;
						let cells: IDataObject;
						try { cells = JSON.parse(cellsStr); } catch { cells = {}; }
						responseData = await aiselloApiRequest.call(this, 'PUT', `/rows/${rowId}/cells`, { values: cells });
						responseData = responseData.data || responseData;
					}
				}

				// ==================== ATTACHMENT ====================
				else if (resource === 'attachment') {
					if (operation === 'upload') {
						const cellId = this.getNodeParameter('cellId', i) as string;
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
						responseData = await aiselloApiRequest.call(this, 'POST', `/cells/${cellId}/attachments`, {}, {}, undefined, {
							formData,
						});
						responseData = responseData.data || responseData;
					} else if (operation === 'delete') {
						const attachmentId = this.getNodeParameter('attachmentId', i) as string;
						responseData = await aiselloApiRequest.call(this, 'DELETE', `/attachments/${attachmentId}`);
					} else if (operation === 'reorder') {
						const attachmentId = this.getNodeParameter('attachmentId', i) as string;
						const position = this.getNodeParameter('position', i) as number;
						responseData = await aiselloApiRequest.call(this, 'POST', `/attachments/${attachmentId}/reorder`, { position });
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
