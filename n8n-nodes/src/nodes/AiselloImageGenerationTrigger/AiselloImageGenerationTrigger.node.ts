import {
	IWebhookFunctions,
	INodeType,
	INodeTypeDescription,
	IWebhookResponseData,
	IDataObject,
} from 'n8n-workflow';

export class AiselloImageGenerationTrigger implements INodeType {
	description: INodeTypeDescription = {
		displayName: 'Aisello Image Generation Trigger',
		name: 'aiselloImageGenerationTrigger',
		icon: 'file:aisello.svg',
		group: ['trigger'],
		version: 1,
		subtitle: 'Receives image generation requests',
		description: 'IMPORTANT: Copy the Webhook URL and paste it in Aisello brand settings. Starts when Aisello requests image generation for a post. Use with AI image nodes (DALL-E, Stable Diffusion, etc.) and finish with Aisello Respond node.',
		defaults: { name: 'Image Generation Trigger' },
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
				path: 'aisello-image-generation',
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
