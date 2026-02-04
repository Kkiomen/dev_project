import { INodeProperties } from 'n8n-workflow';

export const boardColumnOperations: INodeProperties[] = [
	{
		displayName: 'Operation',
		name: 'operation',
		type: 'options',
		noDataExpression: true,
		displayOptions: { show: { resource: ['boardColumn'] } },
		options: [
			{ name: 'Create', value: 'create', description: 'Create a column', action: 'Create a board column' },
			{ name: 'Delete', value: 'delete', description: 'Delete a column', action: 'Delete a board column' },
			{ name: 'Reorder', value: 'reorder', description: 'Reorder a column', action: 'Reorder a board column' },
			{ name: 'Update', value: 'update', description: 'Update a column', action: 'Update a board column' },
		],
		default: 'create',
	},
];

export const boardColumnFields: INodeProperties[] = [
	{
		displayName: 'Board Name or ID',
		name: 'boardId',
		type: 'options',
		typeOptions: { loadOptionsMethod: 'getBoards' },
		required: true,
		default: '',
		description: 'The board to use. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
		displayOptions: { show: { resource: ['boardColumn'], operation: ['create'] } },
	},
	{
		displayName: 'Column ID',
		name: 'columnId',
		type: 'string',
		required: true,
		default: '',
		description: 'The ID of the column',
		displayOptions: { show: { resource: ['boardColumn'], operation: ['update', 'delete', 'reorder'] } },
	},
	{
		displayName: 'Name',
		name: 'name',
		type: 'string',
		required: true,
		default: '',
		description: 'Column name',
		displayOptions: { show: { resource: ['boardColumn'], operation: ['create'] } },
	},
	{
		displayName: 'Update Fields',
		name: 'updateFields',
		type: 'collection',
		placeholder: 'Add Field',
		default: {},
		displayOptions: { show: { resource: ['boardColumn'], operation: ['update'] } },
		options: [
			{ displayName: 'Name', name: 'name', type: 'string', default: '', description: 'Column name' },
		],
	},
	{
		displayName: 'Position',
		name: 'position',
		type: 'number',
		required: true,
		default: 0,
		description: 'New position for the column',
		displayOptions: { show: { resource: ['boardColumn'], operation: ['reorder'] } },
	},
];
