import { INodeProperties } from 'n8n-workflow';

export const brandAutomationOperations: INodeProperties[] = [
	{
		displayName: 'Operation',
		name: 'operation',
		type: 'options',
		noDataExpression: true,
		displayOptions: { show: { resource: ['brandAutomation'] } },
		options: [
			{ name: 'Disable', value: 'disable', description: 'Disable automation for a brand', action: 'Disable brand automation' },
			{ name: 'Enable', value: 'enable', description: 'Enable automation for a brand', action: 'Enable brand automation' },
			{ name: 'Extend Queue', value: 'extendQueue', description: 'Extend automation queue', action: 'Extend automation queue' },
			{ name: 'Get Resolved Prompt', value: 'getResolvedPrompt', description: 'Get system prompt with variables replaced', action: 'Get resolved prompt' },
			{ name: 'Get Stats', value: 'getStats', description: 'Get automation statistics', action: 'Get automation stats' },
			{ name: 'Get System Prompts', value: 'getSystemPrompts', description: 'Get text and image system prompts', action: 'Get system prompts' },
			{ name: 'Trigger', value: 'trigger', description: 'Trigger automation processing', action: 'Trigger automation' },
			{ name: 'Update Settings', value: 'updateSettings', description: 'Update automation settings', action: 'Update automation settings' },
			{ name: 'Update System Prompts', value: 'updateSystemPrompts', description: 'Update text or image system prompts', action: 'Update system prompts' },
		],
		default: 'getStats',
	},
];

export const brandAutomationFields: INodeProperties[] = [
	{
		displayName: 'Brand Name or ID',
		name: 'brandId',
		type: 'options',
		typeOptions: { loadOptionsMethod: 'getBrands' },
		required: true,
		default: '',
		description: 'The brand to use. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
		displayOptions: { show: { resource: ['brandAutomation'] } },
	},
	// ----------------------------------
	//   extendQueue
	// ----------------------------------
	{
		displayName: 'Count',
		name: 'count',
		type: 'number',
		default: 5,
		description: 'Number of posts to add to the queue',
		displayOptions: { show: { resource: ['brandAutomation'], operation: ['extendQueue'] } },
	},
	// ----------------------------------
	//   updateSettings
	// ----------------------------------
	{
		displayName: 'Settings',
		name: 'settings',
		type: 'collection',
		placeholder: 'Add Setting',
		default: {},
		displayOptions: { show: { resource: ['brandAutomation'], operation: ['updateSettings'] } },
		options: [
			{ displayName: 'Frequency', name: 'frequency', type: 'string', default: '', description: 'Posting frequency' },
			{ displayName: 'Time Slots', name: 'time_slots', type: 'json', default: '[]', description: 'JSON array of time slots' },
			{ displayName: 'Platforms', name: 'platforms', type: 'json', default: '[]', description: 'JSON array of target platforms' },
			{ displayName: 'Auto Approve', name: 'auto_approve', type: 'boolean', default: false, description: 'Whether to auto-approve generated posts' },
			{ displayName: 'Topics', name: 'topics', type: 'json', default: '[]', description: 'JSON array of topics for content generation' },
		],
	},
	// ----------------------------------
	//   getResolvedPrompt
	// ----------------------------------
	{
		displayName: 'Prompt Type',
		name: 'promptType',
		type: 'options',
		options: [
			{ name: 'Text', value: 'text', description: 'Text generation system prompt' },
			{ name: 'Image', value: 'image', description: 'Image generation system prompt' },
		],
		default: 'text',
		required: true,
		description: 'The type of system prompt to get',
		displayOptions: { show: { resource: ['brandAutomation'], operation: ['getResolvedPrompt'] } },
	},
	// ----------------------------------
	//   updateSystemPrompts
	// ----------------------------------
	{
		displayName: 'Prompts',
		name: 'prompts',
		type: 'collection',
		placeholder: 'Add Prompt',
		default: {},
		displayOptions: { show: { resource: ['brandAutomation'], operation: ['updateSystemPrompts'] } },
		options: [
			{
				displayName: 'Text System Prompt',
				name: 'text_system_prompt',
				type: 'string',
				typeOptions: { rows: 6 },
				default: '',
				description: 'System prompt for text generation. Use variables like {{brand_name}}, {{industry}}, {{pain_points}}, etc.',
			},
			{
				displayName: 'Image System Prompt',
				name: 'image_system_prompt',
				type: 'string',
				typeOptions: { rows: 6 },
				default: '',
				description: 'System prompt for image generation. Use variables like {{brand_name}}, {{industry}}, {{tone}}, etc.',
			},
		],
	},
];
