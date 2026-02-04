import { INodeProperties } from 'n8n-workflow';

export const brandOperations: INodeProperties[] = [
	{
		displayName: 'Operation',
		name: 'operation',
		type: 'options',
		noDataExpression: true,
		displayOptions: { show: { resource: ['brand'] } },
		options: [
			{ name: 'Create', value: 'create', description: 'Create a new brand', action: 'Create a brand' },
			{ name: 'Delete', value: 'delete', description: 'Delete a brand', action: 'Delete a brand' },
			{ name: 'Get', value: 'get', description: 'Get a brand by ID', action: 'Get a brand' },
			{ name: 'Get Many', value: 'getAll', description: 'Get many brands', action: 'Get many brands' },
			{ name: 'Set Current', value: 'setCurrent', description: 'Set brand as current', action: 'Set current brand' },
			{ name: 'Update', value: 'update', description: 'Update a brand', action: 'Update a brand' },
		],
		default: 'getAll',
	},
];

export const brandFields: INodeProperties[] = [
	// ----------------------------------
	//         brand: getAll
	// ----------------------------------
	{
		displayName: 'Return All',
		name: 'returnAll',
		type: 'boolean',
		default: false,
		description: 'Whether to return all results or only up to a given limit',
		displayOptions: { show: { resource: ['brand'], operation: ['getAll'] } },
	},
	{
		displayName: 'Limit',
		name: 'limit',
		type: 'number',
		default: 50,
		description: 'Max number of results to return',
		typeOptions: { minValue: 1 },
		displayOptions: { show: { resource: ['brand'], operation: ['getAll'], returnAll: [false] } },
	},

	// ----------------------------------
	//         brand: get / delete / setCurrent
	// ----------------------------------
	{
		displayName: 'Brand Name or ID',
		name: 'brandId',
		type: 'options',
		typeOptions: { loadOptionsMethod: 'getBrands' },
		required: true,
		default: '',
		description: 'The brand to use. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
		displayOptions: { show: { resource: ['brand'], operation: ['get', 'delete', 'setCurrent', 'update'] } },
	},

	// ----------------------------------
	//         brand: create
	// ----------------------------------
	{
		displayName: 'Name',
		name: 'name',
		type: 'string',
		required: true,
		default: '',
		description: 'Name of the brand',
		displayOptions: { show: { resource: ['brand'], operation: ['create'] } },
	},
	{
		displayName: 'Additional Fields',
		name: 'additionalFields',
		type: 'collection',
		placeholder: 'Add Field',
		default: {},
		displayOptions: { show: { resource: ['brand'], operation: ['create'] } },
		options: [
			{ displayName: 'Description', name: 'description', type: 'string', default: '', description: 'Brand description' },
			{ displayName: 'Website', name: 'website', type: 'string', default: '', description: 'Brand website URL' },
			{ displayName: 'Industry', name: 'industry', type: 'string', default: '', description: 'Brand industry' },
			{ displayName: 'Language', name: 'language', type: 'string', default: '', description: 'Primary language code (e.g. en, pl)' },
		],
	},

	// ----------------------------------
	//         brand: update
	// ----------------------------------
	{
		displayName: 'Update Fields',
		name: 'updateFields',
		type: 'collection',
		placeholder: 'Add Field',
		default: {},
		displayOptions: { show: { resource: ['brand'], operation: ['update'] } },
		options: [
			{ displayName: 'Name', name: 'name', type: 'string', default: '', description: 'Brand name' },
			{ displayName: 'Description', name: 'description', type: 'string', default: '', description: 'Brand description' },
			{ displayName: 'Website', name: 'website', type: 'string', default: '', description: 'Brand website URL' },
			{ displayName: 'Industry', name: 'industry', type: 'string', default: '', description: 'Brand industry' },
			{ displayName: 'Language', name: 'language', type: 'string', default: '', description: 'Primary language code' },
		],
	},
];
