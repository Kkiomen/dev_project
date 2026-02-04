"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.calendarEventFields = exports.calendarEventOperations = void 0;
exports.calendarEventOperations = [
    {
        displayName: 'Operation',
        name: 'operation',
        type: 'options',
        noDataExpression: true,
        displayOptions: { show: { resource: ['calendarEvent'] } },
        options: [
            { name: 'Create', value: 'create', description: 'Create a calendar event', action: 'Create a calendar event' },
            { name: 'Delete', value: 'delete', description: 'Delete a calendar event', action: 'Delete a calendar event' },
            { name: 'Get', value: 'get', description: 'Get a calendar event', action: 'Get a calendar event' },
            { name: 'Get Many', value: 'getAll', description: 'Get many calendar events', action: 'Get many calendar events' },
            { name: 'Reschedule', value: 'reschedule', description: 'Reschedule a calendar event', action: 'Reschedule a calendar event' },
            { name: 'Update', value: 'update', description: 'Update a calendar event', action: 'Update a calendar event' },
        ],
        default: 'getAll',
    },
];
exports.calendarEventFields = [
    // ----------------------------------
    //         calendarEvent: getAll
    // ----------------------------------
    {
        displayName: 'Return All',
        name: 'returnAll',
        type: 'boolean',
        default: false,
        description: 'Whether to return all results or only up to a given limit',
        displayOptions: { show: { resource: ['calendarEvent'], operation: ['getAll'] } },
    },
    {
        displayName: 'Limit',
        name: 'limit',
        type: 'number',
        default: 50,
        typeOptions: { minValue: 1 },
        description: 'Max number of results to return',
        displayOptions: { show: { resource: ['calendarEvent'], operation: ['getAll'], returnAll: [false] } },
    },
    {
        displayName: 'Filters',
        name: 'filters',
        type: 'collection',
        placeholder: 'Add Filter',
        default: {},
        displayOptions: { show: { resource: ['calendarEvent'], operation: ['getAll'] } },
        options: [
            { displayName: 'Start Date', name: 'start_date', type: 'dateTime', default: '', description: 'Filter events from this date' },
            { displayName: 'End Date', name: 'end_date', type: 'dateTime', default: '', description: 'Filter events until this date' },
            { displayName: 'Brand ID', name: 'brand_id', type: 'string', default: '', description: 'Filter by brand' },
        ],
    },
    // ----------------------------------
    //         calendarEvent: get / delete / reschedule / update
    // ----------------------------------
    {
        displayName: 'Event ID',
        name: 'eventId',
        type: 'string',
        required: true,
        default: '',
        description: 'The ID of the calendar event',
        displayOptions: { show: { resource: ['calendarEvent'], operation: ['get', 'delete', 'reschedule', 'update'] } },
    },
    // ----------------------------------
    //         calendarEvent: create
    // ----------------------------------
    {
        displayName: 'Title',
        name: 'title',
        type: 'string',
        required: true,
        default: '',
        description: 'Event title',
        displayOptions: { show: { resource: ['calendarEvent'], operation: ['create'] } },
    },
    {
        displayName: 'Start At',
        name: 'startAt',
        type: 'dateTime',
        required: true,
        default: '',
        description: 'Event start date/time',
        displayOptions: { show: { resource: ['calendarEvent'], operation: ['create'] } },
    },
    {
        displayName: 'Additional Fields',
        name: 'additionalFields',
        type: 'collection',
        placeholder: 'Add Field',
        default: {},
        displayOptions: { show: { resource: ['calendarEvent'], operation: ['create'] } },
        options: [
            { displayName: 'Brand ID', name: 'brand_id', type: 'string', default: '', description: 'Associated brand' },
            { displayName: 'Description', name: 'description', type: 'string', default: '', description: 'Event description' },
            { displayName: 'End At', name: 'end_at', type: 'dateTime', default: '', description: 'Event end date/time' },
            { displayName: 'Color', name: 'color', type: 'string', default: '', description: 'Event color' },
            { displayName: 'All Day', name: 'all_day', type: 'boolean', default: false, description: 'Whether this is an all-day event' },
        ],
    },
    // ----------------------------------
    //         calendarEvent: update
    // ----------------------------------
    {
        displayName: 'Update Fields',
        name: 'updateFields',
        type: 'collection',
        placeholder: 'Add Field',
        default: {},
        displayOptions: { show: { resource: ['calendarEvent'], operation: ['update'] } },
        options: [
            { displayName: 'Title', name: 'title', type: 'string', default: '', description: 'Event title' },
            { displayName: 'Description', name: 'description', type: 'string', default: '', description: 'Event description' },
            { displayName: 'Start At', name: 'start_at', type: 'dateTime', default: '', description: 'Event start date/time' },
            { displayName: 'End At', name: 'end_at', type: 'dateTime', default: '', description: 'Event end date/time' },
            { displayName: 'Color', name: 'color', type: 'string', default: '', description: 'Event color' },
            { displayName: 'All Day', name: 'all_day', type: 'boolean', default: false, description: 'Whether all-day event' },
        ],
    },
    // ----------------------------------
    //         calendarEvent: reschedule
    // ----------------------------------
    {
        displayName: 'Start At',
        name: 'startAt',
        type: 'dateTime',
        required: true,
        default: '',
        description: 'New start date/time',
        displayOptions: { show: { resource: ['calendarEvent'], operation: ['reschedule'] } },
    },
    {
        displayName: 'End At',
        name: 'endAt',
        type: 'dateTime',
        default: '',
        description: 'New end date/time',
        displayOptions: { show: { resource: ['calendarEvent'], operation: ['reschedule'] } },
    },
];
