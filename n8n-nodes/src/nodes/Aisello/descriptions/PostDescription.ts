import { INodeProperties } from 'n8n-workflow';

export const postOperations: INodeProperties[] = [
	{
		displayName: 'Operation',
		name: 'operation',
		type: 'options',
		noDataExpression: true,
		displayOptions: { show: { resource: ['post'] } },
		options: [
			{ name: 'AI Generate', value: 'aiGenerate', description: 'Generate a post with AI', action: 'AI generate a post' },
			{ name: 'AI Modify', value: 'aiModify', description: 'Modify a post with AI', action: 'AI modify a post' },
			{ name: 'Approve', value: 'approve', description: 'Approve a post', action: 'Approve a post' },
			{ name: 'Create', value: 'create', description: 'Create a new post', action: 'Create a post' },
			{ name: 'Delete', value: 'delete', description: 'Delete a post', action: 'Delete a post' },
			{ name: 'Duplicate', value: 'duplicate', description: 'Duplicate a post', action: 'Duplicate a post' },
			{ name: 'Get', value: 'get', description: 'Get a post by ID', action: 'Get a post' },
			{ name: 'Get Many', value: 'getAll', description: 'Get many posts', action: 'Get many posts' },
			{ name: 'Publish', value: 'publish', description: 'Publish a post', action: 'Publish a post' },
			{ name: 'Reject', value: 'reject', description: 'Reject a post', action: 'Reject a post' },
			{ name: 'Reschedule', value: 'reschedule', description: 'Reschedule a post', action: 'Reschedule a post' },
			{ name: 'Update', value: 'update', description: 'Update a post', action: 'Update a post' },
		],
		default: 'getAll',
	},
];

export const postFields: INodeProperties[] = [
	// ----------------------------------
	//         post: getAll
	// ----------------------------------
	{
		displayName: 'Brand Name or ID',
		name: 'brandId',
		type: 'options',
		typeOptions: { loadOptionsMethod: 'getBrands' },
		required: true,
		default: '',
		description: 'The brand to get posts for. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
		displayOptions: { show: { resource: ['post'], operation: ['getAll'] } },
	},
	{
		displayName: 'Return All',
		name: 'returnAll',
		type: 'boolean',
		default: false,
		description: 'Whether to return all results or only up to a given limit',
		displayOptions: { show: { resource: ['post'], operation: ['getAll'] } },
	},
	{
		displayName: 'Limit',
		name: 'limit',
		type: 'number',
		default: 50,
		typeOptions: { minValue: 1 },
		description: 'Max number of results to return',
		displayOptions: { show: { resource: ['post'], operation: ['getAll'], returnAll: [false] } },
	},
	{
		displayName: 'Filters',
		name: 'filters',
		type: 'collection',
		placeholder: 'Add Filter',
		default: {},
		displayOptions: { show: { resource: ['post'], operation: ['getAll'] } },
		options: [
			{
				displayName: 'Status',
				name: 'status',
				type: 'options',
				options: [
					{ name: 'All', value: '' },
					{ name: 'Draft', value: 'draft' },
					{ name: 'Scheduled', value: 'scheduled' },
					{ name: 'Published', value: 'published' },
					{ name: 'Failed', value: 'failed' },
					{ name: 'Pending Approval', value: 'pending_approval' },
				],
				default: '',
				description: 'Filter by post status',
			},
		],
	},

	// ----------------------------------
	//         post: get / delete / duplicate / approve / reject / publish
	// ----------------------------------
	{
		displayName: 'Post Name or ID',
		name: 'postId',
		type: 'options',
		typeOptions: { loadOptionsMethod: 'getPosts' },
		required: true,
		default: '',
		description: 'The post to use. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
		displayOptions: {
			show: {
				resource: ['post'],
				operation: ['get', 'delete', 'duplicate', 'approve', 'reject', 'publish', 'reschedule', 'update'],
			},
		},
	},

	// ----------------------------------
	//         post: create
	// ----------------------------------
	{
		displayName: 'Brand Name or ID',
		name: 'brandId',
		type: 'options',
		typeOptions: { loadOptionsMethod: 'getBrands' },
		required: true,
		default: '',
		description: 'The brand to create the post for. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
		displayOptions: { show: { resource: ['post'], operation: ['create'] } },
	},
	{
		displayName: 'Content',
		name: 'content',
		type: 'string',
		typeOptions: { rows: 5 },
		default: '',
		description: 'Post content/text',
		displayOptions: { show: { resource: ['post'], operation: ['create'] } },
	},
	{
		displayName: 'Additional Fields',
		name: 'additionalFields',
		type: 'collection',
		placeholder: 'Add Field',
		default: {},
		displayOptions: { show: { resource: ['post'], operation: ['create'] } },
		options: [
			{ displayName: 'Title', name: 'title', type: 'string', default: '', description: 'Post title' },
			{
				displayName: 'Status',
				name: 'status',
				type: 'options',
				options: [
					{ name: 'Draft', value: 'draft' },
					{ name: 'Scheduled', value: 'scheduled' },
				],
				default: 'draft',
				description: 'Post status',
			},
			{ displayName: 'Scheduled At', name: 'scheduled_at', type: 'dateTime', default: '', description: 'Schedule date and time (ISO 8601)' },
			{ displayName: 'Platforms', name: 'platforms', type: 'json', default: '[]', description: 'JSON array of platform names' },
		],
	},

	// ----------------------------------
	//         post: update
	// ----------------------------------
	{
		displayName: 'Update Fields',
		name: 'updateFields',
		type: 'collection',
		placeholder: 'Add Field',
		default: {},
		displayOptions: { show: { resource: ['post'], operation: ['update'] } },
		options: [
			{ displayName: 'Content', name: 'content', type: 'string', typeOptions: { rows: 5 }, default: '', description: 'Post content' },
			{ displayName: 'Title', name: 'title', type: 'string', default: '', description: 'Post title' },
			{
				displayName: 'Status',
				name: 'status',
				type: 'options',
				options: [
					{ name: 'Draft', value: 'draft' },
					{ name: 'Scheduled', value: 'scheduled' },
				],
				default: 'draft',
				description: 'Post status',
			},
			{ displayName: 'Scheduled At', name: 'scheduled_at', type: 'dateTime', default: '', description: 'Schedule date and time' },
		],
	},

	// ----------------------------------
	//         post: reschedule
	// ----------------------------------
	{
		displayName: 'Scheduled At',
		name: 'scheduledAt',
		type: 'dateTime',
		required: true,
		default: '',
		description: 'New scheduled date and time',
		displayOptions: { show: { resource: ['post'], operation: ['reschedule'] } },
	},

	// ----------------------------------
	//         post: aiGenerate
	// ----------------------------------
	{
		displayName: 'Brand Name or ID',
		name: 'brandId',
		type: 'options',
		typeOptions: { loadOptionsMethod: 'getBrands' },
		required: true,
		default: '',
		description: 'The brand to generate the post for. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
		displayOptions: { show: { resource: ['post'], operation: ['aiGenerate'] } },
	},
	{
		displayName: 'Prompt',
		name: 'prompt',
		type: 'string',
		typeOptions: { rows: 3 },
		required: true,
		default: '',
		description: 'AI prompt for generating the post',
		displayOptions: { show: { resource: ['post'], operation: ['aiGenerate'] } },
	},
	{
		displayName: 'Additional Fields',
		name: 'additionalFields',
		type: 'collection',
		placeholder: 'Add Field',
		default: {},
		displayOptions: { show: { resource: ['post'], operation: ['aiGenerate'] } },
		options: [
			{ displayName: 'Platform', name: 'platform', type: 'string', default: '', description: 'Target platform' },
			{ displayName: 'Tone', name: 'tone', type: 'string', default: '', description: 'Desired tone of voice' },
			{ displayName: 'Language', name: 'language', type: 'string', default: '', description: 'Language code' },
		],
	},

	// ----------------------------------
	//         post: aiModify
	// ----------------------------------
	{
		displayName: 'Post Name or ID',
		name: 'postId',
		type: 'options',
		typeOptions: { loadOptionsMethod: 'getPosts' },
		required: true,
		default: '',
		description: 'The post to modify. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
		displayOptions: { show: { resource: ['post'], operation: ['aiModify'] } },
	},
	{
		displayName: 'Instruction',
		name: 'instruction',
		type: 'string',
		typeOptions: { rows: 3 },
		required: true,
		default: '',
		description: 'AI instruction for modifying the post',
		displayOptions: { show: { resource: ['post'], operation: ['aiModify'] } },
	},
];
