import { INodeProperties } from 'n8n-workflow';

export const tableOperations: INodeProperties[] = [
	{
		displayName: 'Operation',
		name: 'operation',
		type: 'options',
		noDataExpression: true,
		displayOptions: { show: { resource: ['table'] } },
		options: [
			{ name: 'Create', value: 'create', description: 'Create a table', action: 'Create a table' },
			{ name: 'Delete', value: 'delete', description: 'Delete a table', action: 'Delete a table' },
			{ name: 'Get', value: 'get', description: 'Get a table', action: 'Get a table' },
			{ name: 'Get Many', value: 'getAll', description: 'Get many tables', action: 'Get many tables' },
			{ name: 'Reorder', value: 'reorder', description: 'Reorder a table', action: 'Reorder a table' },
			{ name: 'Update', value: 'update', description: 'Update a table', action: 'Update a table' },
		],
		default: 'getAll',
	},
];

export const tableFields: INodeProperties[] = [
	// baseId for getAll/create (used in execute)
	{
		displayName: 'Base Name or ID',
		name: 'baseId',
		type: 'options',
		typeOptions: { loadOptionsMethod: 'getBases' },
		required: true,
		default: '',
		description: 'The base to use. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
		displayOptions: { show: { resource: ['table'], operation: ['getAll', 'create'] } },
	},
	// helper baseId for get/delete/update/reorder (only for dropdown dependency)
	{
		displayName: 'Base Name or ID',
		name: 'baseId',
		type: 'options',
		typeOptions: { loadOptionsMethod: 'getBases' },
		required: true,
		default: '',
		description: 'Select the base first to populate the table dropdown. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
		displayOptions: { show: { resource: ['table'], operation: ['get', 'delete', 'update', 'reorder'] } },
	},
	{
		displayName: 'Table Name or ID',
		name: 'tableId',
		type: 'options',
		typeOptions: { loadOptionsMethod: 'getTables', loadOptionsDependsOn: ['baseId'] },
		required: true,
		default: '',
		description: 'The table to use. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
		displayOptions: { show: { resource: ['table'], operation: ['get', 'delete', 'update', 'reorder'] } },
	},
	{
		displayName: 'Return All',
		name: 'returnAll',
		type: 'boolean',
		default: false,
		description: 'Whether to return all results or only up to a given limit',
		displayOptions: { show: { resource: ['table'], operation: ['getAll'] } },
	},
	{
		displayName: 'Limit',
		name: 'limit',
		type: 'number',
		default: 50,
		typeOptions: { minValue: 1 },
		description: 'Max number of results to return',
		displayOptions: { show: { resource: ['table'], operation: ['getAll'], returnAll: [false] } },
	},
	{
		displayName: 'Name',
		name: 'name',
		type: 'string',
		required: true,
		default: '',
		description: 'Table name',
		displayOptions: { show: { resource: ['table'], operation: ['create'] } },
	},
	{
		displayName: 'Update Fields',
		name: 'updateFields',
		type: 'collection',
		placeholder: 'Add Field',
		default: {},
		displayOptions: { show: { resource: ['table'], operation: ['update'] } },
		options: [
			{ displayName: 'Name', name: 'name', type: 'string', default: '', description: 'Table name' },
		],
	},
	{
		displayName: 'Position',
		name: 'position',
		type: 'number',
		required: true,
		default: 0,
		description: 'New position for the table',
		displayOptions: { show: { resource: ['table'], operation: ['reorder'] } },
	},
];
