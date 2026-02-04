"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.boardFields = exports.boardOperations = void 0;
exports.boardOperations = [
    {
        displayName: 'Operation',
        name: 'operation',
        type: 'options',
        noDataExpression: true,
        displayOptions: { show: { resource: ['board'] } },
        options: [
            { name: 'Create', value: 'create', description: 'Create a board', action: 'Create a board' },
            { name: 'Delete', value: 'delete', description: 'Delete a board', action: 'Delete a board' },
            { name: 'Get', value: 'get', description: 'Get a board', action: 'Get a board' },
            { name: 'Get Many', value: 'getAll', description: 'Get many boards', action: 'Get many boards' },
            { name: 'Update', value: 'update', description: 'Update a board', action: 'Update a board' },
        ],
        default: 'getAll',
    },
];
exports.boardFields = [
    {
        displayName: 'Return All',
        name: 'returnAll',
        type: 'boolean',
        default: false,
        description: 'Whether to return all results or only up to a given limit',
        displayOptions: { show: { resource: ['board'], operation: ['getAll'] } },
    },
    {
        displayName: 'Limit',
        name: 'limit',
        type: 'number',
        default: 50,
        typeOptions: { minValue: 1 },
        description: 'Max number of results to return',
        displayOptions: { show: { resource: ['board'], operation: ['getAll'], returnAll: [false] } },
    },
    {
        displayName: 'Board Name or ID',
        name: 'boardId',
        type: 'options',
        typeOptions: { loadOptionsMethod: 'getBoards' },
        required: true,
        default: '',
        description: 'The board to use. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
        displayOptions: { show: { resource: ['board'], operation: ['get', 'delete', 'update'] } },
    },
    {
        displayName: 'Name',
        name: 'name',
        type: 'string',
        required: true,
        default: '',
        description: 'Board name',
        displayOptions: { show: { resource: ['board'], operation: ['create'] } },
    },
    {
        displayName: 'Additional Fields',
        name: 'additionalFields',
        type: 'collection',
        placeholder: 'Add Field',
        default: {},
        displayOptions: { show: { resource: ['board'], operation: ['create'] } },
        options: [
            { displayName: 'Description', name: 'description', type: 'string', default: '', description: 'Board description' },
        ],
    },
    {
        displayName: 'Update Fields',
        name: 'updateFields',
        type: 'collection',
        placeholder: 'Add Field',
        default: {},
        displayOptions: { show: { resource: ['board'], operation: ['update'] } },
        options: [
            { displayName: 'Name', name: 'name', type: 'string', default: '', description: 'Board name' },
            { displayName: 'Description', name: 'description', type: 'string', default: '', description: 'Board description' },
        ],
    },
];
