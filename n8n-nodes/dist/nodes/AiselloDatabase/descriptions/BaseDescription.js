"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.baseFields = exports.baseOperations = void 0;
exports.baseOperations = [
    {
        displayName: 'Operation',
        name: 'operation',
        type: 'options',
        noDataExpression: true,
        displayOptions: { show: { resource: ['base'] } },
        options: [
            { name: 'Create', value: 'create', description: 'Create a base', action: 'Create a base' },
            { name: 'Delete', value: 'delete', description: 'Delete a base', action: 'Delete a base' },
            { name: 'Get', value: 'get', description: 'Get a base', action: 'Get a base' },
            { name: 'Get Many', value: 'getAll', description: 'Get many bases', action: 'Get many bases' },
            { name: 'Update', value: 'update', description: 'Update a base', action: 'Update a base' },
        ],
        default: 'getAll',
    },
];
exports.baseFields = [
    {
        displayName: 'Return All',
        name: 'returnAll',
        type: 'boolean',
        default: false,
        description: 'Whether to return all results or only up to a given limit',
        displayOptions: { show: { resource: ['base'], operation: ['getAll'] } },
    },
    {
        displayName: 'Limit',
        name: 'limit',
        type: 'number',
        default: 50,
        typeOptions: { minValue: 1 },
        description: 'Max number of results to return',
        displayOptions: { show: { resource: ['base'], operation: ['getAll'], returnAll: [false] } },
    },
    {
        displayName: 'Base Name or ID',
        name: 'baseId',
        type: 'options',
        typeOptions: { loadOptionsMethod: 'getBases' },
        required: true,
        default: '',
        description: 'The base to use. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
        displayOptions: { show: { resource: ['base'], operation: ['get', 'delete', 'update'] } },
    },
    {
        displayName: 'Name',
        name: 'name',
        type: 'string',
        required: true,
        default: '',
        description: 'Base name',
        displayOptions: { show: { resource: ['base'], operation: ['create'] } },
    },
    {
        displayName: 'Additional Fields',
        name: 'additionalFields',
        type: 'collection',
        placeholder: 'Add Field',
        default: {},
        displayOptions: { show: { resource: ['base'], operation: ['create'] } },
        options: [
            { displayName: 'Description', name: 'description', type: 'string', default: '', description: 'Base description' },
        ],
    },
    {
        displayName: 'Update Fields',
        name: 'updateFields',
        type: 'collection',
        placeholder: 'Add Field',
        default: {},
        displayOptions: { show: { resource: ['base'], operation: ['update'] } },
        options: [
            { displayName: 'Name', name: 'name', type: 'string', default: '', description: 'Base name' },
            { displayName: 'Description', name: 'description', type: 'string', default: '', description: 'Base description' },
        ],
    },
];
