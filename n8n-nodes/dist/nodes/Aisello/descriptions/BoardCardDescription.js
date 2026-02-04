"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.boardCardFields = exports.boardCardOperations = void 0;
exports.boardCardOperations = [
    {
        displayName: 'Operation',
        name: 'operation',
        type: 'options',
        noDataExpression: true,
        displayOptions: { show: { resource: ['boardCard'] } },
        options: [
            { name: 'Create', value: 'create', description: 'Create a card', action: 'Create a board card' },
            { name: 'Delete', value: 'delete', description: 'Delete a card', action: 'Delete a board card' },
            { name: 'Move', value: 'move', description: 'Move a card to another column', action: 'Move a board card' },
            { name: 'Reorder', value: 'reorder', description: 'Reorder a card', action: 'Reorder a board card' },
            { name: 'Update', value: 'update', description: 'Update a card', action: 'Update a board card' },
        ],
        default: 'create',
    },
];
exports.boardCardFields = [
    {
        displayName: 'Column ID',
        name: 'columnId',
        type: 'string',
        required: true,
        default: '',
        description: 'The ID of the column',
        displayOptions: { show: { resource: ['boardCard'], operation: ['create'] } },
    },
    {
        displayName: 'Card ID',
        name: 'cardId',
        type: 'string',
        required: true,
        default: '',
        description: 'The ID of the card',
        displayOptions: { show: { resource: ['boardCard'], operation: ['update', 'delete', 'move', 'reorder'] } },
    },
    {
        displayName: 'Title',
        name: 'title',
        type: 'string',
        required: true,
        default: '',
        description: 'Card title',
        displayOptions: { show: { resource: ['boardCard'], operation: ['create'] } },
    },
    {
        displayName: 'Additional Fields',
        name: 'additionalFields',
        type: 'collection',
        placeholder: 'Add Field',
        default: {},
        displayOptions: { show: { resource: ['boardCard'], operation: ['create'] } },
        options: [
            { displayName: 'Description', name: 'description', type: 'string', default: '', description: 'Card description' },
            { displayName: 'Color', name: 'color', type: 'string', default: '', description: 'Card color' },
        ],
    },
    {
        displayName: 'Update Fields',
        name: 'updateFields',
        type: 'collection',
        placeholder: 'Add Field',
        default: {},
        displayOptions: { show: { resource: ['boardCard'], operation: ['update'] } },
        options: [
            { displayName: 'Title', name: 'title', type: 'string', default: '', description: 'Card title' },
            { displayName: 'Description', name: 'description', type: 'string', default: '', description: 'Card description' },
            { displayName: 'Color', name: 'color', type: 'string', default: '', description: 'Card color' },
        ],
    },
    {
        displayName: 'Target Column ID',
        name: 'targetColumnId',
        type: 'string',
        required: true,
        default: '',
        description: 'The column to move the card to',
        displayOptions: { show: { resource: ['boardCard'], operation: ['move'] } },
    },
    {
        displayName: 'Position',
        name: 'position',
        type: 'number',
        default: 0,
        description: 'Position within the target column',
        displayOptions: { show: { resource: ['boardCard'], operation: ['move', 'reorder'] } },
    },
];
