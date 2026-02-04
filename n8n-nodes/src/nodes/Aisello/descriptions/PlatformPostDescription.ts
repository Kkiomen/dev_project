import { INodeProperties } from 'n8n-workflow';

export const platformPostOperations: INodeProperties[] = [
	{
		displayName: 'Operation',
		name: 'operation',
		type: 'options',
		noDataExpression: true,
		displayOptions: { show: { resource: ['platformPost'] } },
		options: [
			{ name: 'Sync', value: 'sync', description: 'Sync platform post', action: 'Sync platform post' },
			{ name: 'Toggle', value: 'toggle', description: 'Toggle platform post on/off', action: 'Toggle platform post' },
			{ name: 'Update', value: 'update', description: 'Update platform-specific content', action: 'Update platform post' },
		],
		default: 'update',
	},
];

export const platformPostFields: INodeProperties[] = [
	{
		displayName: 'Post Name or ID',
		name: 'postId',
		type: 'options',
		typeOptions: { loadOptionsMethod: 'getPosts' },
		required: true,
		default: '',
		description: 'The post to use. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
		displayOptions: { show: { resource: ['platformPost'] } },
	},
	{
		displayName: 'Platform',
		name: 'platform',
		type: 'options',
		required: true,
		options: [
			{ name: 'Facebook', value: 'facebook' },
			{ name: 'Instagram', value: 'instagram' },
			{ name: 'LinkedIn', value: 'linkedin' },
			{ name: 'TikTok', value: 'tiktok' },
			{ name: 'X (Twitter)', value: 'twitter' },
			{ name: 'YouTube', value: 'youtube' },
		],
		default: 'facebook',
		description: 'Target platform',
		displayOptions: { show: { resource: ['platformPost'] } },
	},
	{
		displayName: 'Update Fields',
		name: 'updateFields',
		type: 'collection',
		placeholder: 'Add Field',
		default: {},
		displayOptions: { show: { resource: ['platformPost'], operation: ['update'] } },
		options: [
			{ displayName: 'Content', name: 'content', type: 'string', typeOptions: { rows: 5 }, default: '', description: 'Platform-specific content' },
			{ displayName: 'Title', name: 'title', type: 'string', default: '', description: 'Platform-specific title' },
		],
	},
];
