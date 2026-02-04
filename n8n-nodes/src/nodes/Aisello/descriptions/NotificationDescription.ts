import { INodeProperties } from 'n8n-workflow';

export const notificationOperations: INodeProperties[] = [
	{
		displayName: 'Operation',
		name: 'operation',
		type: 'options',
		noDataExpression: true,
		displayOptions: { show: { resource: ['notification'] } },
		options: [
			{ name: 'Get Many', value: 'getAll', description: 'Get many notifications', action: 'Get many notifications' },
			{ name: 'Get Unread Count', value: 'getUnreadCount', description: 'Get unread notification count', action: 'Get unread count' },
			{ name: 'Mark All Read', value: 'markAllRead', description: 'Mark all notifications as read', action: 'Mark all notifications read' },
			{ name: 'Mark Read', value: 'markRead', description: 'Mark a notification as read', action: 'Mark notification read' },
		],
		default: 'getAll',
	},
];

export const notificationFields: INodeProperties[] = [
	{
		displayName: 'Return All',
		name: 'returnAll',
		type: 'boolean',
		default: false,
		description: 'Whether to return all results or only up to a given limit',
		displayOptions: { show: { resource: ['notification'], operation: ['getAll'] } },
	},
	{
		displayName: 'Limit',
		name: 'limit',
		type: 'number',
		default: 50,
		typeOptions: { minValue: 1 },
		description: 'Max number of results to return',
		displayOptions: { show: { resource: ['notification'], operation: ['getAll'], returnAll: [false] } },
	},
	{
		displayName: 'Notification ID',
		name: 'notificationId',
		type: 'string',
		required: true,
		default: '',
		description: 'The ID of the notification',
		displayOptions: { show: { resource: ['notification'], operation: ['markRead'] } },
	},
];
