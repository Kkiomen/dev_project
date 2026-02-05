import {
	IExecuteFunctions,
	INodeType,
	INodeTypeDescription,
	INodeExecutionData,
	IDataObject,
	NodeOperationError,
} from 'n8n-workflow';

export class AiselloRespond implements INodeType {
	description: INodeTypeDescription = {
		displayName: 'Aisello Respond',
		name: 'aiselloRespond',
		icon: 'file:aisello.svg',
		group: ['transform'],
		version: 1,
		subtitle: '={{ $parameter["responseType"] }}',
		description: 'Sends processing results back to Aisello via the callback URL. Use at the end of workflows started by Aisello triggers.',
		defaults: { name: 'Aisello Respond' },
		inputs: ['main'],
		outputs: ['main'],
		credentials: [],
		properties: [
			{
				displayName: 'Response Type',
				name: 'responseType',
				type: 'options',
				options: [
					{
						name: 'Text Generation',
						value: 'text_generation',
						description: 'Return generated caption and/or title',
					},
					{
						name: 'Image Generation',
						value: 'image_generation',
						description: 'Return image prompt and/or generated image',
					},
					{
						name: 'Publish',
						value: 'publish',
						description: 'Return publishing result with platform and external ID',
					},
				],
				default: 'text_generation',
				description: 'Type of response to send back to Aisello',
			},
			{
				displayName: 'Callback URL',
				name: 'callbackUrl',
				type: 'string',
				default: '',
				placeholder: '={{ $json.callback_url }}',
				description: 'Callback URL to send the response to. Use expression to get from trigger data, e.g. {{ $json.callback_url }} or {{ $(\"Webhook\").item.json.callback_url }}',
			},
			{
				displayName: 'Post ID',
				name: 'postId',
				type: 'string',
				default: '',
				placeholder: '={{ $json.post_id }}',
				description: 'Post ID to update. Use expression to get from trigger data, e.g. {{ $json.post_id }} or {{ $(\"Webhook\").item.json.post_id }}',
			},
			// Text generation fields
			{
				displayName: 'Caption',
				name: 'caption',
				type: 'string',
				typeOptions: {
					rows: 4,
				},
				default: '',
				description: 'Generated caption/content for the post',
				displayOptions: {
					show: {
						responseType: ['text_generation'],
					},
				},
			},
			{
				displayName: 'Title',
				name: 'title',
				type: 'string',
				default: '',
				description: 'Generated title for the post (optional)',
				displayOptions: {
					show: {
						responseType: ['text_generation'],
					},
				},
			},
			// Image generation fields
			{
				displayName: 'Image Prompt',
				name: 'imagePrompt',
				type: 'string',
				typeOptions: {
					rows: 2,
				},
				default: '',
				description: 'Image generation prompt to save on the post',
				displayOptions: {
					show: {
						responseType: ['image_generation'],
					},
				},
			},
			{
				displayName: 'Image Base64',
				name: 'imageBase64',
				type: 'string',
				default: '',
				description: 'Base64-encoded image data (with or without data URI prefix). The image will be saved as post media.',
				displayOptions: {
					show: {
						responseType: ['image_generation'],
					},
				},
			},
			// Publish fields
			{
				displayName: 'Platform',
				name: 'platform',
				type: 'options',
				options: [
					{ name: 'Facebook', value: 'facebook' },
					{ name: 'Instagram', value: 'instagram' },
					{ name: 'YouTube', value: 'youtube' },
					{ name: 'TikTok', value: 'tiktok' },
					{ name: 'LinkedIn', value: 'linkedin' },
					{ name: 'Twitter/X', value: 'twitter' },
					{ name: 'Other', value: 'other' },
				],
				default: 'facebook',
				description: 'Platform where the post was published',
				displayOptions: {
					show: {
						responseType: ['publish'],
					},
				},
			},
			{
				displayName: 'External ID',
				name: 'externalId',
				type: 'string',
				default: '',
				description: 'Platform-specific post ID returned after publishing',
				displayOptions: {
					show: {
						responseType: ['publish'],
					},
				},
			},
			// Common fields
			{
				displayName: 'Success',
				name: 'success',
				type: 'boolean',
				default: true,
				description: 'Whether the operation was successful',
			},
			{
				displayName: 'Error Message',
				name: 'errorMessage',
				type: 'string',
				default: '',
				description: 'Error message if the operation failed',
				displayOptions: {
					show: {
						success: [false],
					},
				},
			},
			{
				displayName: 'Webhook Secret',
				name: 'webhookSecret',
				type: 'string',
				typeOptions: { password: true },
				default: '',
				description: 'Optional secret to send in X-Webhook-Secret header. Should match Aisello configuration.',
			},
		],
	};

	async execute(this: IExecuteFunctions): Promise<INodeExecutionData[][]> {
		const items = this.getInputData();
		const returnData: INodeExecutionData[] = [];

		for (let i = 0; i < items.length; i++) {
			try {
				const responseType = this.getNodeParameter('responseType', i) as string;
				const success = this.getNodeParameter('success', i) as boolean;
				const webhookSecret = this.getNodeParameter('webhookSecret', i, '') as string;

				// Get callback_url and post_id from parameters (can use expressions) or fall back to input data
				const inputData = items[i].json as IDataObject;
				const callbackUrlParam = this.getNodeParameter('callbackUrl', i, '') as string;
				const postIdParam = this.getNodeParameter('postId', i, '') as string;

				const callbackUrl = callbackUrlParam || (inputData.callback_url as string);
				const postId = postIdParam || (inputData.post_id as string);

				if (!callbackUrl) {
					throw new NodeOperationError(
						this.getNode(),
						'No callback_url provided. Enter a Callback URL or use an expression like {{ $json.callback_url }}',
						{ itemIndex: i }
					);
				}

				if (!postId) {
					throw new NodeOperationError(
						this.getNode(),
						'No post_id provided. Enter a Post ID or use an expression like {{ $json.post_id }}',
						{ itemIndex: i }
					);
				}

				// Build payload based on response type
				const payload: IDataObject = {
					post_id: postId,
					type: responseType,
					success,
				};

				if (!success) {
					payload.error = this.getNodeParameter('errorMessage', i, '') as string;
				}

				if (responseType === 'text_generation') {
					const caption = this.getNodeParameter('caption', i, '') as string;
					const title = this.getNodeParameter('title', i, '') as string;

					if (caption) payload.caption = caption;
					if (title) payload.title = title;
				} else if (responseType === 'image_generation') {
					const imagePrompt = this.getNodeParameter('imagePrompt', i, '') as string;
					const imageBase64 = this.getNodeParameter('imageBase64', i, '') as string;

					if (imagePrompt) payload.image_prompt = imagePrompt;
					if (imageBase64) payload.image_base64 = imageBase64;
				} else if (responseType === 'publish') {
					const platform = this.getNodeParameter('platform', i) as string;
					const externalId = this.getNodeParameter('externalId', i, '') as string;

					payload.platform = platform;
					if (externalId) payload.external_id = externalId;
				}

				// Send callback request
				const headers: IDataObject = {
					'Content-Type': 'application/json',
				};
				if (webhookSecret) {
					headers['X-Webhook-Secret'] = webhookSecret;
				}

				const response = await this.helpers.httpRequest({
					method: 'POST',
					url: callbackUrl,
					body: payload,
					headers,
					json: true,
				});

				returnData.push({
					json: {
						success: true,
						callback_url: callbackUrl,
						payload,
						response,
					},
					pairedItem: { item: i },
				});
			} catch (error) {
				if (this.continueOnFail()) {
					returnData.push({
						json: {
							success: false,
							error: (error as Error).message,
						},
						pairedItem: { item: i },
					});
					continue;
				}
				throw error;
			}
		}

		return [returnData];
	}
}
