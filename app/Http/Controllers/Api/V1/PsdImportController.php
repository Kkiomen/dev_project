<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ImportPsdRequest;
use App\Http\Resources\TemplateResource;
use App\Services\PsdImportService;
use Exception;
use Illuminate\Http\JsonResponse;

class PsdImportController extends Controller
{
    public function __construct(
        protected PsdImportService $psdImportService
    ) {}

    /**
     * Import a PSD file as a new template.
     *
     * @param ImportPsdRequest $request
     * @return TemplateResource|JsonResponse
     */
    public function import(ImportPsdRequest $request): TemplateResource|JsonResponse
    {
        try {
            $template = $this->psdImportService->import(
                file: $request->file('file'),
                user: $request->user(),
                name: $request->input('name'),
                addToLibrary: $request->boolean('add_to_library', false)
            );

            return new TemplateResource($template);
        } catch (Exception $e) {
            return response()->json([
                'message' => __('graphics.psd.errors.uploadFailed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check the health of the PSD parser service.
     *
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        $healthy = $this->psdImportService->isHealthy();

        return response()->json([
            'healthy' => $healthy,
            'service' => 'psd-parser',
        ], $healthy ? 200 : 503);
    }

    /**
     * Analyze a PSD file structure without importing.
     *
     * @param ImportPsdRequest $request
     * @return JsonResponse
     */
    public function analyze(ImportPsdRequest $request): JsonResponse
    {
        try {
            $analysis = $this->psdImportService->analyze($request->file('file'));

            return response()->json($analysis);
        } catch (Exception $e) {
            return response()->json([
                'message' => __('graphics.psd.errors.analyzeFailed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
