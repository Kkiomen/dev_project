import {
	IWebhookFunctions,
	INodeType,
	INodeTypeDescription,
	IWebhookResponseData,
	IDataObject,
} from 'n8n-workflow';

export class AiselloTrigger implements INodeType {
	description: INodeTypeDescription = {
		displayName: 'Aisello Trigger',
		name: 'aiselloTrigger',
		icon: 'file:aisello.svg',
		group: ['trigger'],
		version: 1,
		subtitle: 'Receives publishing result callbacks',
		description: 'Starts the workflow when Aisello sends a publishing result webhook',
		defaults: { name: 'Aisello Trigger' },
		inputs: [],
		outputs: ['main'],
		credentials: [
			{
				name: 'aiselloApi',
				required: false,
			},
		],
		webhooks: [
			{
				name: 'default',
				httpMethod: 'POST',
				responseMode: 'onReceived',
				path: 'aisello-webhook',
			},
		],
		properties: [
			{
				displayName: 'Webhook Secret',
				name: 'webhookSecret',
				type: 'string',
				typeOptions: { password: true },
				default: '',
				description: 'Optional secret to validate X-Webhook-Secret header. Leave empty to skip validation.',
			},
		],
	};

	async webhook(this: IWebhookFunctions): Promise<IWebhookResponseData> {
		const req = this.getRequestObject();
		const body = this.getBodyData() as IDataObject;

		// Validate webhook secret if configured
		const webhookSecret = this.getNodeParameter('webhookSecret', '') as string;
		if (webhookSecret) {
			const headerSecret = req.headers['x-webhook-secret'] as string;
			if (headerSecret !== webhookSecret) {
				return {
					webhookResponse: 'Unauthorized',
					noWebhookResponse: true,
				} as unknown as IWebhookResponseData;
			}
		}

		return {
			workflowData: [
				this.helpers.returnJsonArray(body),
			],
		};
	}
}
