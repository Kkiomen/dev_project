import { INodeProperties } from 'n8n-workflow';

export const contentPlanOperations: INodeProperties[] = [
	{
		displayName: 'Operation',
		name: 'operation',
		type: 'options',
		noDataExpression: true,
		displayOptions: { show: { resource: ['contentPlan'] } },
		options: [
			{ name: 'Generate Content', value: 'generateContent', description: 'Generate content from a plan', action: 'Generate content' },
			{ name: 'Generate Plan', value: 'generatePlan', description: 'Generate a content plan', action: 'Generate a content plan' },
			{ name: 'Regenerate Content', value: 'regenerateContent', description: 'Regenerate content from a plan', action: 'Regenerate content' },
		],
		default: 'generatePlan',
	},
];

export const contentPlanFields: INodeProperties[] = [
	{
		displayName: 'Brand Name or ID',
		name: 'brandId',
		type: 'options',
		typeOptions: { loadOptionsMethod: 'getBrands' },
		required: true,
		default: '',
		description: 'The brand to generate the plan for. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
		displayOptions: { show: { resource: ['contentPlan'] } },
	},
	{
		displayName: 'Additional Fields',
		name: 'additionalFields',
		type: 'collection',
		placeholder: 'Add Field',
		default: {},
		displayOptions: { show: { resource: ['contentPlan'], operation: ['generatePlan'] } },
		options: [
			{ displayName: 'Period', name: 'period', type: 'string', default: '', description: 'Planning period (e.g. "week", "month")' },
			{ displayName: 'Topics', name: 'topics', type: 'json', default: '[]', description: 'JSON array of topics' },
			{ displayName: 'Platforms', name: 'platforms', type: 'json', default: '[]', description: 'JSON array of platforms' },
			{ displayName: 'Posts Per Day', name: 'posts_per_day', type: 'number', default: 1, description: 'Number of posts per day' },
		],
	},
	{
		displayName: 'Plan ID',
		name: 'planId',
		type: 'string',
		required: true,
		default: '',
		description: 'The ID of the content plan',
		displayOptions: { show: { resource: ['contentPlan'], operation: ['generateContent', 'regenerateContent'] } },
	},
];
