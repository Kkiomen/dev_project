"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.brandAutomationFields = exports.brandAutomationOperations = void 0;
exports.brandAutomationOperations = [
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
            { name: 'Get Stats', value: 'getStats', description: 'Get automation statistics', action: 'Get automation stats' },
            { name: 'Trigger', value: 'trigger', description: 'Trigger automation processing', action: 'Trigger automation' },
            { name: 'Update Settings', value: 'updateSettings', description: 'Update automation settings', action: 'Update automation settings' },
        ],
        default: 'getStats',
    },
];
exports.brandAutomationFields = [
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
];
