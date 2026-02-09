import { INodeProperties } from 'n8n-workflow';

export const rssArticleOperations: INodeProperties[] = [
	{
		displayName: 'Operation',
		name: 'operation',
		type: 'options',
		noDataExpression: true,
		displayOptions: { show: { resource: ['rssArticle'] } },
		options: [
			{ name: 'Get Feed Articles', value: 'getFeedArticles', description: 'Get articles from a specific feed', action: 'Get feed articles' },
			{ name: 'Get Many', value: 'getAll', description: 'Get many RSS articles', action: 'Get many RSS articles' },
		],
		default: 'getAll',
	},
];

export const rssArticleFields: INodeProperties[] = [
	// ----------------------------------
	//         rssArticle: getAll
	// ----------------------------------
	{
		displayName: 'Return All',
		name: 'returnAll',
		type: 'boolean',
		default: false,
		description: 'Whether to return all results or only up to a given limit',
		displayOptions: { show: { resource: ['rssArticle'], operation: ['getAll'] } },
	},
	{
		displayName: 'Limit',
		name: 'limit',
		type: 'number',
		default: 50,
		description: 'Max number of results to return',
		typeOptions: { minValue: 1 },
		displayOptions: { show: { resource: ['rssArticle'], operation: ['getAll'], returnAll: [false] } },
	},
	{
		displayName: 'Filters',
		name: 'filters',
		type: 'collection',
		placeholder: 'Add Filter',
		default: {},
		displayOptions: { show: { resource: ['rssArticle'], operation: ['getAll'] } },
		options: [
			{
				displayName: 'Feed Name or ID',
				name: 'feed_id',
				type: 'options',
				typeOptions: { loadOptionsMethod: 'getRssFeeds' },
				default: '',
				description: 'Filter by RSS feed. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
			},
			{ displayName: 'Search', name: 'search', type: 'string', default: '', description: 'Search articles by keyword' },
			{ displayName: 'Since', name: 'since', type: 'dateTime', default: '', description: 'Only return articles published after this date' },
			{ displayName: 'Category', name: 'category', type: 'string', default: '', description: 'Filter by article category' },
		],
	},

	// ----------------------------------
	//         rssArticle: getFeedArticles
	// ----------------------------------
	{
		displayName: 'Feed ID',
		name: 'feedId',
		type: 'string',
		required: true,
		default: '',
		description: 'The ID of the RSS feed to get articles from',
		displayOptions: { show: { resource: ['rssArticle'], operation: ['getFeedArticles'] } },
	},
	{
		displayName: 'Return All',
		name: 'returnAll',
		type: 'boolean',
		default: false,
		description: 'Whether to return all results or only up to a given limit',
		displayOptions: { show: { resource: ['rssArticle'], operation: ['getFeedArticles'] } },
	},
	{
		displayName: 'Limit',
		name: 'limit',
		type: 'number',
		default: 50,
		description: 'Max number of results to return',
		typeOptions: { minValue: 1 },
		displayOptions: { show: { resource: ['rssArticle'], operation: ['getFeedArticles'], returnAll: [false] } },
	},
];
