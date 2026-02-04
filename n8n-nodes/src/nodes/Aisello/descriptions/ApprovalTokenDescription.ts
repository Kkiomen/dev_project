import { INodeProperties } from 'n8n-workflow';

export const approvalTokenOperations: INodeProperties[] = [
	{
		displayName: 'Operation',
		name: 'operation',
		type: 'options',
		noDataExpression: true,
		displayOptions: { show: { resource: ['approvalToken'] } },
		options: [
			{ name: 'Create', value: 'create', description: 'Create an approval token', action: 'Create an approval token' },
			{ name: 'Delete', value: 'delete', description: 'Delete an approval token', action: 'Delete an approval token' },
			{ name: 'Get', value: 'get', description: 'Get an approval token', action: 'Get an approval token' },
			{ name: 'Get Many', value: 'getAll', description: 'Get many approval tokens', action: 'Get many approval tokens' },
			{ name: 'Get Stats', value: 'getStats', description: 'Get approval token statistics', action: 'Get approval token stats' },
			{ name: 'Regenerate', value: 'regenerate', description: 'Regenerate an approval token', action: 'Regenerate an approval token' },
		],
		default: 'getAll',
	},
];

export const approvalTokenFields: INodeProperties[] = [
	{
		displayName: 'Return All',
		name: 'returnAll',
		type: 'boolean',
		default: false,
		description: 'Whether to return all results or only up to a given limit',
		displayOptions: { show: { resource: ['approvalToken'], operation: ['getAll'] } },
	},
	{
		displayName: 'Limit',
		name: 'limit',
		type: 'number',
		default: 50,
		typeOptions: { minValue: 1 },
		description: 'Max number of results to return',
		displayOptions: { show: { resource: ['approvalToken'], operation: ['getAll'], returnAll: [false] } },
	},
	{
		displayName: 'Approval Token Name or ID',
		name: 'approvalTokenId',
		type: 'options',
		typeOptions: { loadOptionsMethod: 'getApprovalTokens' },
		required: true,
		default: '',
		description: 'The approval token to use. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
		displayOptions: { show: { resource: ['approvalToken'], operation: ['get', 'delete', 'regenerate', 'getStats'] } },
	},
	{
		displayName: 'Name',
		name: 'name',
		type: 'string',
		required: true,
		default: '',
		description: 'Token name',
		displayOptions: { show: { resource: ['approvalToken'], operation: ['create'] } },
	},
	{
		displayName: 'Additional Fields',
		name: 'additionalFields',
		type: 'collection',
		placeholder: 'Add Field',
		default: {},
		displayOptions: { show: { resource: ['approvalToken'], operation: ['create'] } },
		options: [
			{ displayName: 'Brand ID', name: 'brand_id', type: 'string', default: '', description: 'Associated brand' },
			{ displayName: 'Expires At', name: 'expires_at', type: 'dateTime', default: '', description: 'Token expiration date' },
		],
	},
];
