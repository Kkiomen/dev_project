"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.postAutomationFields = exports.postAutomationOperations = void 0;
exports.postAutomationOperations = [
    {
        displayName: 'Operation',
        name: 'operation',
        type: 'options',
        noDataExpression: true,
        displayOptions: { show: { resource: ['postAutomation'] } },
        options: [
            { name: 'Bulk Generate Image', value: 'bulkGenerateImage', description: 'Bulk generate image prompts for posts', action: 'Bulk generate image prompts' },
            { name: 'Bulk Generate Text', value: 'bulkGenerateText', description: 'Bulk generate text for posts', action: 'Bulk generate text' },
            { name: 'Generate Image Prompt', value: 'generateImagePrompt', description: 'Generate image prompt for a post', action: 'Generate image prompt' },
            { name: 'Generate Text', value: 'generateText', description: 'Generate text for a post', action: 'Generate text' },
            { name: 'Get Image Generation Data', value: 'getImageGenerationData', description: 'Get image prompt and system prompt for AI generation', action: 'Get image generation data' },
            { name: 'Get Many', value: 'getAll', description: 'Get automation posts', action: 'Get automation posts' },
            { name: 'Get Text Generation Data', value: 'getTextGenerationData', description: 'Get text prompt and system prompt for AI generation', action: 'Get text generation data' },
            { name: 'Webhook Publish', value: 'webhookPublish', description: 'Publish a post via webhook', action: 'Webhook publish a post' },
        ],
        default: 'getAll',
    },
];
exports.postAutomationFields = [
    // ----------------------------------
    //         postAutomation: getAll
    // ----------------------------------
    {
        displayName: 'Return All',
        name: 'returnAll',
        type: 'boolean',
        default: false,
        description: 'Whether to return all results or only up to a given limit',
        displayOptions: { show: { resource: ['postAutomation'], operation: ['getAll'] } },
    },
    {
        displayName: 'Limit',
        name: 'limit',
        type: 'number',
        default: 50,
        typeOptions: { minValue: 1 },
        description: 'Max number of results to return',
        displayOptions: { show: { resource: ['postAutomation'], operation: ['getAll'], returnAll: [false] } },
    },
    {
        displayName: 'Filters',
        name: 'filters',
        type: 'collection',
        placeholder: 'Add Filter',
        default: {},
        displayOptions: { show: { resource: ['postAutomation'], operation: ['getAll'] } },
        options: [
            {
                displayName: 'Brand Name or ID',
                name: 'brand_id',
                type: 'options',
                typeOptions: { loadOptionsMethod: 'getBrands' },
                default: '',
                description: 'Filter by brand. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
            },
            {
                displayName: 'Status',
                name: 'status',
                type: 'options',
                options: [
                    { name: 'All', value: '' },
                    { name: 'Draft', value: 'draft' },
                    { name: 'Pending Approval', value: 'pending_approval' },
                    { name: 'Approved', value: 'approved' },
                    { name: 'Scheduled', value: 'scheduled' },
                    { name: 'Published', value: 'published' },
                    { name: 'Failed', value: 'failed' },
                ],
                default: '',
                description: 'Filter by post status',
            },
        ],
    },
    // ----------------------------------
    //         postAutomation: generateText / generateImagePrompt / webhookPublish / getTextGenerationData / getImageGenerationData
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
            show: { resource: ['postAutomation'], operation: ['generateText', 'generateImagePrompt', 'webhookPublish', 'getTextGenerationData', 'getImageGenerationData'] },
        },
    },
    // ----------------------------------
    //         postAutomation: webhookPublish
    // ----------------------------------
    {
        displayName: 'Additional Fields',
        name: 'additionalFields',
        type: 'collection',
        placeholder: 'Add Field',
        default: {},
        displayOptions: { show: { resource: ['postAutomation'], operation: ['webhookPublish'] } },
        options: [
            {
                displayName: 'Platform',
                name: 'platform',
                type: 'options',
                options: [
                    { name: 'All Enabled', value: '' },
                    { name: 'Facebook', value: 'facebook' },
                    { name: 'Instagram', value: 'instagram' },
                    { name: 'Twitter/X', value: 'twitter' },
                    { name: 'LinkedIn', value: 'linkedin' },
                    { name: 'TikTok', value: 'tiktok' },
                    { name: 'YouTube', value: 'youtube' },
                    { name: 'Pinterest', value: 'pinterest' },
                    { name: 'Threads', value: 'threads' },
                ],
                default: '',
                description: 'Target platform for publishing (empty = all enabled platforms)',
            },
            { displayName: 'Webhook URL', name: 'webhook_url', type: 'string', default: '', description: 'Custom webhook URL (overrides brand settings)' },
        ],
    },
    // ----------------------------------
    //         postAutomation: bulkGenerateText / bulkGenerateImage
    // ----------------------------------
    {
        displayName: 'Post IDs',
        name: 'postIds',
        type: 'string',
        required: true,
        default: '',
        description: 'Comma-separated list of post IDs',
        displayOptions: {
            show: { resource: ['postAutomation'], operation: ['bulkGenerateText', 'bulkGenerateImage'] },
        },
    },
];
