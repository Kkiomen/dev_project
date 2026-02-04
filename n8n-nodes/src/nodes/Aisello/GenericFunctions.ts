import {
	IExecuteFunctions,
	IHookFunctions,
	ILoadOptionsFunctions,
	IDataObject,
	IHttpRequestMethods,
	NodeApiError,
} from 'n8n-workflow';

export async function aiselloApiRequest(
	this: IExecuteFunctions | ILoadOptionsFunctions | IHookFunctions,
	method: IHttpRequestMethods,
	endpoint: string,
	body: IDataObject | IDataObject[] = {},
	qs: IDataObject = {},
	uri?: string,
	option: IDataObject = {},
): Promise<any> {
	const credentials = await this.getCredentials('aiselloApi');
	const baseUrl = (credentials.apiUrl as string).replace(/\/$/, '');

	const options: IDataObject = {
		method,
		qs,
		url: uri || `${baseUrl}/api/v1${endpoint}`,
		json: true,
		...option,
	};

	if (Object.keys(body).length > 0) {
		options.body = body;
	}

	try {
		const response = await this.helpers.httpRequestWithAuthentication.call(
			this,
			'aiselloApi',
			options as any,
		);
		return response;
	} catch (error) {
		throw new NodeApiError(this.getNode(), error as any);
	}
}

export async function aiselloApiRequestAllItems(
	this: IExecuteFunctions | ILoadOptionsFunctions,
	method: IHttpRequestMethods,
	endpoint: string,
	body: IDataObject = {},
	qs: IDataObject = {},
): Promise<any[]> {
	const returnData: any[] = [];
	let page = 1;

	qs.per_page = qs.per_page || 50;

	let responseData;
	do {
		qs.page = page;
		responseData = await aiselloApiRequest.call(this, method, endpoint, body, qs);

		const items = responseData.data || responseData;
		if (Array.isArray(items)) {
			returnData.push(...items);
		}

		page++;
	} while (
		responseData.meta?.current_page < responseData.meta?.last_page ||
		responseData.current_page < responseData.last_page
	);

	return returnData;
}
