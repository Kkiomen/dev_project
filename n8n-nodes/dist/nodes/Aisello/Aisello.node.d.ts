import { IExecuteFunctions, ILoadOptionsFunctions, INodeExecutionData, INodePropertyOptions, INodeType, INodeTypeDescription } from 'n8n-workflow';
export declare class Aisello implements INodeType {
    description: INodeTypeDescription;
    methods: {
        loadOptions: {
            getBrands(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]>;
            getPosts(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]>;
            getBoards(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]>;
            getApprovalTokens(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]>;
        };
    };
    execute(this: IExecuteFunctions): Promise<INodeExecutionData[][]>;
}
//# sourceMappingURL=Aisello.node.d.ts.map