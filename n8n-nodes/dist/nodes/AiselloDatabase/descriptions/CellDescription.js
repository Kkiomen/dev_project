"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.cellFields = exports.cellOperations = void 0;
exports.cellOperations = [
    {
        displayName: 'Operation',
        name: 'operation',
        type: 'options',
        noDataExpression: true,
        displayOptions: { show: { resource: ['cell'] } },
        options: [
            { name: 'Bulk Update', value: 'bulkUpdate', description: 'Update multiple cells in a row', action: 'Bulk update cells' },
            { name: 'Update', value: 'update', description: 'Update a single cell', action: 'Update a cell' },
        ],
        default: 'update',
    },
];
exports.cellFields = [
    // Helper baseId + tableId for fieldId dropdown
    {
        displayName: 'Base Name or ID',
        name: 'baseId',
        type: 'options',
        typeOptions: { loadOptionsMethod: 'getBases' },
        required: true,
        default: '',
        description: 'Select the base first to populate the table dropdown. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
        displayOptions: { show: { resource: ['cell'], operation: ['update'] } },
    },
    {
        displayName: 'Table Name or ID',
        name: 'tableId',
        type: 'options',
        typeOptions: { loadOptionsMethod: 'getTables', loadOptionsDependsOn: ['baseId'] },
        required: true,
        default: '',
        description: 'Select the table to populate the field dropdown. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
        displayOptions: { show: { resource: ['cell'], operation: ['update'] } },
    },
    {
        displayName: 'Row ID',
        name: 'rowId',
        type: 'string',
        required: true,
        default: '',
        description: 'The ID of the row',
        displayOptions: { show: { resource: ['cell'] } },
    },
    {
        displayName: 'Field Name or ID',
        name: 'fieldId',
        type: 'options',
        typeOptions: { loadOptionsMethod: 'getFields', loadOptionsDependsOn: ['tableId'] },
        required: true,
        default: '',
        description: 'The field to update. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
        displayOptions: { show: { resource: ['cell'], operation: ['update'] } },
    },
    {
        displayName: 'Value',
        name: 'value',
        type: 'string',
        required: true,
        default: '',
        description: 'The new value for the cell',
        displayOptions: { show: { resource: ['cell'], operation: ['update'] } },
    },
    {
        displayName: 'Cells',
        name: 'cells',
        type: 'json',
        required: true,
        default: '{}',
        description: 'JSON object mapping field IDs to values',
        displayOptions: { show: { resource: ['cell'], operation: ['bulkUpdate'] } },
    },
];
