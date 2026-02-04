"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.stockPhotoFields = exports.stockPhotoOperations = void 0;
exports.stockPhotoOperations = [
    {
        displayName: 'Operation',
        name: 'operation',
        type: 'options',
        noDataExpression: true,
        displayOptions: { show: { resource: ['stockPhoto'] } },
        options: [
            { name: 'Get Featured', value: 'getFeatured', description: 'Get featured stock photos', action: 'Get featured stock photos' },
            { name: 'Search', value: 'search', description: 'Search stock photos', action: 'Search stock photos' },
        ],
        default: 'search',
    },
];
exports.stockPhotoFields = [
    {
        displayName: 'Query',
        name: 'query',
        type: 'string',
        required: true,
        default: '',
        description: 'Search query for stock photos',
        displayOptions: { show: { resource: ['stockPhoto'], operation: ['search'] } },
    },
    {
        displayName: 'Limit',
        name: 'limit',
        type: 'number',
        default: 20,
        typeOptions: { minValue: 1 },
        description: 'Max number of results to return',
        displayOptions: { show: { resource: ['stockPhoto'] } },
    },
    {
        displayName: 'Additional Fields',
        name: 'additionalFields',
        type: 'collection',
        placeholder: 'Add Field',
        default: {},
        displayOptions: { show: { resource: ['stockPhoto'], operation: ['search'] } },
        options: [
            {
                displayName: 'Orientation',
                name: 'orientation',
                type: 'options',
                options: [
                    { name: 'All', value: '' },
                    { name: 'Landscape', value: 'landscape' },
                    { name: 'Portrait', value: 'portrait' },
                    { name: 'Square', value: 'squarish' },
                ],
                default: '',
                description: 'Photo orientation',
            },
            { displayName: 'Page', name: 'page', type: 'number', default: 1, description: 'Page number' },
        ],
    },
];
