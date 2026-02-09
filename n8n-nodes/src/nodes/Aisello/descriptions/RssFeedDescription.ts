import { INodeProperties } from 'n8n-workflow';

export const rssFeedOperations: INodeProperties[] = [
	{
		displayName: 'Operation',
		name: 'operation',
		type: 'options',
		noDataExpression: true,
		displayOptions: { show: { resource: ['rssFeed'] } },
		options: [
			{ name: 'Create', value: 'create', description: 'Create an RSS feed', action: 'Create an RSS feed' },
			{ name: 'Delete', value: 'delete', description: 'Delete an RSS feed', action: 'Delete an RSS feed' },
			{ name: 'Get', value: 'get', description: 'Get an RSS feed by ID', action: 'Get an RSS feed' },
			{ name: 'Get Many', value: 'getAll', description: 'Get many RSS feeds', action: 'Get many RSS feeds' },
			{ name: 'Refresh', value: 'refresh', description: 'Trigger a feed refresh', action: 'Refresh an RSS feed' },
			{ name: 'Update', value: 'update', description: 'Update an RSS feed', action: 'Update an RSS feed' },
		],
		default: 'getAll',
	},
];

export const rssFeedFields: INodeProperties[] = [
	// ----------------------------------
	//         rssFeed: getAll
	// ----------------------------------
	{
		displayName: 'Return All',
		name: 'returnAll',
		type: 'boolean',
		default: false,
		description: 'Whether to return all results or only up to a given limit',
		displayOptions: { show: { resource: ['rssFeed'], operation: ['getAll'] } },
	},
	{
		displayName: 'Limit',
		name: 'limit',
		type: 'number',
		default: 50,
		description: 'Max number of results to return',
		typeOptions: { minValue: 1 },
		displayOptions: { show: { resource: ['rssFeed'], operation: ['getAll'], returnAll: [false] } },
	},

	// ----------------------------------
	//         rssFeed: get / delete / update / refresh
	// ----------------------------------
	{
		displayName: 'Feed ID',
		name: 'feedId',
		type: 'string',
		required: true,
		default: '',
		description: 'The ID of the RSS feed',
		displayOptions: { show: { resource: ['rssFeed'], operation: ['get', 'delete', 'update', 'refresh'] } },
	},

	// ----------------------------------
	//         rssFeed: create
	// ----------------------------------
	{
		displayName: 'URL',
		name: 'url',
		type: 'string',
		required: true,
		default: '',
		description: 'The URL of the RSS feed',
		displayOptions: { show: { resource: ['rssFeed'], operation: ['create'] } },
	},
	{
		displayName: 'Additional Fields',
		name: 'additionalFields',
		type: 'collection',
		placeholder: 'Add Field',
		default: {},
		displayOptions: { show: { resource: ['rssFeed'], operation: ['create'] } },
		options: [
			{ displayName: 'Name', name: 'name', type: 'string', default: '', description: 'Custom name for the feed' },
		],
	},

	// ----------------------------------
	//         rssFeed: update
	// ----------------------------------
	{
		displayName: 'Update Fields',
		name: 'updateFields',
		type: 'collection',
		placeholder: 'Add Field',
		default: {},
		displayOptions: { show: { resource: ['rssFeed'], operation: ['update'] } },
		options: [
			{ displayName: 'Name', name: 'name', type: 'string', default: '', description: 'Feed name' },
			{
				displayName: 'Status',
				name: 'status',
				type: 'options',
				options: [
					{ name: 'Active', value: 'active' },
					{ name: 'Paused', value: 'paused' },
				],
				default: 'active',
				description: 'Feed status',
			},
		],
	},
];
