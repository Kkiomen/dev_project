import {
	IWebhookFunctions,
	INodeType,
	INodeTypeDescription,
	IWebhookResponseData,
	IDataObject,
} from 'n8n-workflow';

export class AiselloPublishTrigger implements INodeType {
	description: INodeTypeDescription = {
		displayName: 'Aisello Publish Trigger',
		name: 'aiselloPublishTrigger',
		icon: 'file:aisello.svg',
		group: ['trigger'],
		version: 1,
		subtitle: 'Receives publish requests',
		description: 'Starts the workflow when Aisello sends a post for publishing. Handle publishing to your platforms (Facebook, Instagram, etc.) and finish with Aisello Respond node.',
		defaults: { name: 'Publish Trigger' },
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
				path: 'aisello-publish',
			},
		],
		properties: [
			{
				displayName: 'Webhook Secret',
				name: 'webhookSecret',
				type: 'string',
				typeOptions: { password: true },
				default: '',
				description: 'Optional secret to validate X-Webhook-Secret header. Must match the secret configured in Aisello brand settings.',
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
					webhookResponse: JSON.stringify({ error: 'Unauthorized' }),
					noWebhookResponse: true,
				} as unknown as IWebhookResponseData;
			}
		}

		// Return 202 Accepted immediately, workflow continues async
		return {
			workflowData: [
				this.helpers.returnJsonArray(body),
			],
		};
	}
}
