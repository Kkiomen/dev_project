"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.postMediaFields = exports.postMediaOperations = void 0;
exports.postMediaOperations = [
    {
        displayName: 'Operation',
        name: 'operation',
        type: 'options',
        noDataExpression: true,
        displayOptions: { show: { resource: ['postMedia'] } },
        options: [
            { name: 'Delete', value: 'delete', description: 'Delete a media item', action: 'Delete a media item' },
            { name: 'Get Many', value: 'getAll', description: 'Get all media for a post', action: 'Get post media' },
            { name: 'Reorder', value: 'reorder', description: 'Reorder media items', action: 'Reorder media' },
            { name: 'Upload', value: 'upload', description: 'Upload media to a post', action: 'Upload media' },
        ],
        default: 'getAll',
    },
];
exports.postMediaFields = [
    {
        displayName: 'Post Name or ID',
        name: 'postId',
        type: 'options',
        typeOptions: { loadOptionsMethod: 'getPosts' },
        required: true,
        default: '',
        description: 'The post to use. Choose from the list, or specify an ID using an <a href="https://docs.n8n.io/code/expressions/">expression</a>.',
        displayOptions: { show: { resource: ['postMedia'], operation: ['getAll', 'upload', 'reorder'] } },
    },
    {
        displayName: 'Media ID',
        name: 'mediaId',
        type: 'string',
        required: true,
        default: '',
        description: 'The ID of the media item',
        displayOptions: { show: { resource: ['postMedia'], operation: ['delete'] } },
    },
    {
        displayName: 'Binary Property',
        name: 'binaryPropertyName',
        type: 'string',
        required: true,
        default: 'data',
        description: 'Name of the binary property containing the file to upload',
        displayOptions: { show: { resource: ['postMedia'], operation: ['upload'] } },
    },
    {
        displayName: 'Order',
        name: 'order',
        type: 'json',
        required: true,
        default: '[]',
        description: 'JSON array of media IDs in desired order',
        displayOptions: { show: { resource: ['postMedia'], operation: ['reorder'] } },
    },
];
