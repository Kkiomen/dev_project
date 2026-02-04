"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.attachmentFields = exports.attachmentOperations = void 0;
exports.attachmentOperations = [
    {
        displayName: 'Operation',
        name: 'operation',
        type: 'options',
        noDataExpression: true,
        displayOptions: { show: { resource: ['attachment'] } },
        options: [
            { name: 'Delete', value: 'delete', description: 'Delete an attachment', action: 'Delete an attachment' },
            { name: 'Reorder', value: 'reorder', description: 'Reorder an attachment', action: 'Reorder an attachment' },
            { name: 'Upload', value: 'upload', description: 'Upload an attachment', action: 'Upload an attachment' },
        ],
        default: 'upload',
    },
];
exports.attachmentFields = [
    {
        displayName: 'Cell ID',
        name: 'cellId',
        type: 'string',
        required: true,
        default: '',
        description: 'The ID of the cell',
        displayOptions: { show: { resource: ['attachment'], operation: ['upload'] } },
    },
    {
        displayName: 'Attachment ID',
        name: 'attachmentId',
        type: 'string',
        required: true,
        default: '',
        description: 'The ID of the attachment',
        displayOptions: { show: { resource: ['attachment'], operation: ['delete', 'reorder'] } },
    },
    {
        displayName: 'Binary Property',
        name: 'binaryPropertyName',
        type: 'string',
        required: true,
        default: 'data',
        description: 'Name of the binary property containing the file to upload',
        displayOptions: { show: { resource: ['attachment'], operation: ['upload'] } },
    },
    {
        displayName: 'Position',
        name: 'position',
        type: 'number',
        required: true,
        default: 0,
        description: 'New position for the attachment',
        displayOptions: { show: { resource: ['attachment'], operation: ['reorder'] } },
    },
];
