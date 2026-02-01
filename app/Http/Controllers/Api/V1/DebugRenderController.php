<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Debug controller for AI/Cursor to test PSD import rendering.
 *
 * This controller provides endpoints to render parsed PSD data
 * and compare results during development/debugging.
 */
class DebugRenderController extends Controller
{
    protected string $templateRendererUrl;
    protected string $psdParserUrl;

    public function __construct()
    {
        $this->templateRendererUrl = config('services.template_renderer.url', 'http://template-renderer:3336');
        $this->psdParserUrl = config('services.psd_parser.url', 'http://psd-parser:3335');
    }

    /**
     * Render parsed PSD data to PNG image.
     *
     * Accepts JSON in the format returned by psd-parser /parse endpoint
     * and renders it using the template-renderer service.
     *
     * @param Request $request
     * @return Response
     *
     * Usage:
     * curl -X POST http://localhost/api/v1/debug/render \
     *   -H "Content-Type: application/json" \
     *   -d @parsed.json \
     *   -o rendered.png
     */
    public function render(Request $request): Response
    {
        $data = $request->all();

        if (empty($data['layers'])) {
            return response()->json(['error' => 'No layers data provided'], 400);
        }

        $width = $data['width'] ?? 1080;
        $height = $data['height'] ?? 1080;
        $scale = $request->query('scale', 1);

        // Transform PSD parser format to template-renderer format
        $templateData = $this->transformPsdToTemplate($data);

        try {
            $response = Http::timeout(60)
                ->post("{$this->templateRendererUrl}/render", [
                    'template' => $templateData,
                    'width' => $width,
                    'height' => $height,
                    'scale' => $scale,
                ]);

            if ($response->failed()) {
                return response()->json([
                    'error' => 'Render failed',
                    'details' => $response->json() ?? $response->body(),
                ], 500);
            }

            return response($response->body(), 200)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'inline; filename="debug-render.png"');

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Render service error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse PSD file and render to PNG in one request.
     *
     * Upload a PSD file and get the rendered result directly.
     *
     * @param Request $request
     * @return Response
     *
     * Usage:
     * curl -X POST http://localhost/api/v1/debug/render-psd \
     *   -F "file=@template.psd" \
     *   -o rendered.png
     */
    public function renderPsd(Request $request): Response
    {
        $request->validate([
            'file' => 'required|file|max:512000',
        ]);

        $file = $request->file('file');
        $scale = $request->query('scale', 1);

        try {
            // Parse PSD using psd-parser service
            $parseResponse = Http::timeout(120)
                ->attach('file', $file->get(), $file->getClientOriginalName())
                ->post("{$this->psdParserUrl}/parse");

            if ($parseResponse->failed()) {
                return response()->json([
                    'error' => 'PSD parsing failed',
                    'details' => $parseResponse->json() ?? $parseResponse->body(),
                ], 500);
            }

            $psdData = $parseResponse->json();

            if (isset($psdData['error'])) {
                return response()->json([
                    'error' => 'PSD parsing error',
                    'details' => $psdData['error'],
                ], 500);
            }

            // Transform and render
            $templateData = $this->transformPsdToTemplate($psdData);

            $renderResponse = Http::timeout(60)
                ->post("{$this->templateRendererUrl}/render", [
                    'template' => $templateData,
                    'width' => $psdData['width'] ?? 1080,
                    'height' => $psdData['height'] ?? 1080,
                    'scale' => $scale,
                ]);

            if ($renderResponse->failed()) {
                return response()->json([
                    'error' => 'Render failed',
                    'details' => $renderResponse->json() ?? $renderResponse->body(),
                ], 500);
            }

            return response($renderResponse->body(), 200)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'inline; filename="debug-render.png"');

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Service error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get original PSD composite (how Photoshop renders it).
     *
     * @param Request $request
     * @return Response
     *
     * Usage:
     * curl -X POST http://localhost/api/v1/debug/psd-original \
     *   -F "file=@template.psd" \
     *   -o original.png
     */
    public function psdOriginal(Request $request): Response
    {
        $request->validate([
            'file' => 'required|file|max:512000',
        ]);

        $file = $request->file('file');
        $scale = $request->query('scale', 0.5);

        try {
            $response = Http::timeout(120)
                ->attach('file', $file->get(), $file->getClientOriginalName())
                ->post("{$this->psdParserUrl}/render-psd?scale={$scale}");

            if ($response->failed()) {
                return response()->json([
                    'error' => 'PSD render failed',
                    'details' => $response->json() ?? $response->body(),
                ], 500);
            }

            return response($response->body(), 200)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'inline; filename="psd-original.png"');

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Service error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compare original PSD with parsed render (returns both as JSON with base64).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * Usage:
     * curl -X POST http://localhost/api/v1/debug/compare \
     *   -F "file=@template.psd" | jq
     */
    public function compare(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:512000',
        ]);

        $file = $request->file('file');
        $scale = $request->query('scale', 0.5);

        try {
            // Get original PSD render
            $originalResponse = Http::timeout(120)
                ->attach('file', $file->get(), $file->getClientOriginalName())
                ->post("{$this->psdParserUrl}/render-psd?scale={$scale}");

            // Parse PSD
            $parseResponse = Http::timeout(120)
                ->attach('file', $file->get(), $file->getClientOriginalName())
                ->post("{$this->psdParserUrl}/parse");

            $psdData = $parseResponse->json();

            // Render parsed data
            $templateData = $this->transformPsdToTemplate($psdData);

            $renderResponse = Http::timeout(60)
                ->post("{$this->templateRendererUrl}/render", [
                    'template' => $templateData,
                    'width' => $psdData['width'] ?? 1080,
                    'height' => $psdData['height'] ?? 1080,
                    'scale' => $scale,
                ]);

            return response()->json([
                'original' => [
                    'success' => $originalResponse->successful(),
                    'image' => $originalResponse->successful()
                        ? 'data:image/png;base64,' . base64_encode($originalResponse->body())
                        : null,
                ],
                'parsed' => [
                    'success' => $parseResponse->successful(),
                    'width' => $psdData['width'] ?? null,
                    'height' => $psdData['height'] ?? null,
                    'layer_count' => count($psdData['layers'] ?? []),
                    'warnings' => $psdData['warnings'] ?? [],
                ],
                'rendered' => [
                    'success' => $renderResponse->successful(),
                    'image' => $renderResponse->successful()
                        ? 'data:image/png;base64,' . base64_encode($renderResponse->body())
                        : null,
                    'error' => $renderResponse->failed() ? $renderResponse->body() : null,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Comparison failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Simulate full PSD import and render exactly as frontend would see it.
     *
     * This endpoint simulates the entire import flow:
     * 1. Parse PSD via psd-parser
     * 2. Apply Laravel default properties (like effective_properties accessor)
     * 3. Render via template-renderer
     *
     * This is the most accurate way to see what the editor will display.
     *
     * @param Request $request
     * @return Response
     *
     * Usage:
     * curl -X POST http://localhost/api/v1/debug/simulate-import \
     *   -F "file=@template.psd" \
     *   -o simulated.png
     */
    public function simulateImport(Request $request): Response
    {
        $request->validate([
            'file' => 'required|file|max:512000',
        ]);

        $file = $request->file('file');
        $scale = $request->query('scale', 0.5);

        try {
            // Parse PSD using psd-parser service
            $parseResponse = Http::timeout(120)
                ->attach('file', $file->get(), $file->getClientOriginalName())
                ->post("{$this->psdParserUrl}/parse");

            if ($parseResponse->failed()) {
                return response()->json([
                    'error' => 'PSD parsing failed',
                    'details' => $parseResponse->json() ?? $parseResponse->body(),
                ], 500);
            }

            $psdData = $parseResponse->json();

            // Transform using the same logic as PsdImportService + LayerResource
            $templateData = $this->transformPsdToTemplateWithDefaults($psdData);

            $renderResponse = Http::timeout(60)
                ->post("{$this->templateRendererUrl}/render", [
                    'template' => $templateData,
                    'width' => $psdData['width'] ?? 1080,
                    'height' => $psdData['height'] ?? 1080,
                    'scale' => $scale,
                ]);

            if ($renderResponse->failed()) {
                return response()->json([
                    'error' => 'Render failed',
                    'details' => $renderResponse->json() ?? $renderResponse->body(),
                ], 500);
            }

            return response($renderResponse->body(), 200)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'inline; filename="simulated-import.png"');

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Service error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Transform PSD parser output to template-renderer format.
     *
     * @param array $psdData
     * @return array
     */
    protected function transformPsdToTemplate(array $psdData): array
    {
        $layers = $this->flattenLayers($psdData['layers'] ?? []);
        $images = collect($psdData['images'] ?? [])->keyBy('id')->toArray();

        // Transform layers to template format
        $transformedLayers = [];
        foreach ($layers as $layer) {
            $transformed = [
                'id' => $layer['position'] ?? Str::uuid()->toString(),
                'type' => $layer['type'],
                'name' => $layer['name'] ?? 'Layer',
                'x' => $layer['x'] ?? 0,
                'y' => $layer['y'] ?? 0,
                'width' => $layer['width'] ?? 100,
                'height' => $layer['height'] ?? 100,
                'rotation' => $layer['rotation'] ?? 0,
                'scale_x' => $layer['scale_x'] ?? 1,
                'scale_y' => $layer['scale_y'] ?? 1,
                'opacity' => $layer['opacity'] ?? 1,
                'visible' => $layer['visible'] ?? true,
                'locked' => $layer['locked'] ?? false,
                'position' => $layer['position'] ?? 0,
                'properties' => $layer['properties'] ?? [],
            ];

            // Add image source if available
            if ($layer['type'] === 'image' && isset($layer['image_id'])) {
                $imageId = $layer['image_id'];
                if (isset($images[$imageId])) {
                    $transformed['properties']['src'] = $images[$imageId]['data'] ?? null;
                }
            }

            $transformedLayers[] = $transformed;
        }

        // Sort by position for correct z-order
        usort($transformedLayers, fn($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

        return [
            'id' => 'debug-' . Str::uuid()->toString(),
            'name' => 'Debug Render',
            'width' => $psdData['width'] ?? 1080,
            'height' => $psdData['height'] ?? 1080,
            'backgroundColor' => $psdData['background_color'] ?? '#ffffff',
            'layers' => $transformedLayers,
        ];
    }

    /**
     * Transform PSD parser output with default properties applied.
     *
     * This mimics what LayerResource::effective_properties does:
     * array_merge($defaults, $this->properties ?? [])
     *
     * @param array $psdData
     * @return array
     */
    protected function transformPsdToTemplateWithDefaults(array $psdData): array
    {
        $layers = $this->flattenLayers($psdData['layers'] ?? []);
        $images = collect($psdData['images'] ?? [])->keyBy('id')->toArray();

        // Default properties per layer type (from LayerType enum)
        $defaultProperties = [
            'text' => [
                'text' => '',
                'fontFamily' => 'Montserrat',
                'fontSize' => 24,
                'fontWeight' => 'normal',
                'fontStyle' => 'normal',
                'lineHeight' => 1.2,
                'letterSpacing' => 0,
                'fill' => '#000000',
                'align' => 'left',
                'verticalAlign' => 'top',
                'textDirection' => 'horizontal',
            ],
            'image' => [
                'src' => null,
                'fit' => 'cover',
                'clipPath' => null,
            ],
            'rectangle' => [
                'fill' => '#CCCCCC',
                'stroke' => null,
                'strokeWidth' => 0,
                'cornerRadius' => 0,
            ],
            'ellipse' => [
                'fill' => '#CCCCCC',
                'stroke' => null,
                'strokeWidth' => 0,
            ],
        ];

        // Transform layers to template format
        $transformedLayers = [];
        foreach ($layers as $layer) {
            $type = $layer['type'];
            $defaults = $defaultProperties[$type] ?? [];
            $layerProperties = $layer['properties'] ?? [];

            // Merge defaults with layer properties (layer properties override defaults)
            $effectiveProperties = array_merge($defaults, $layerProperties);

            // Add image source if available
            if ($type === 'image' && isset($layer['image_id'])) {
                $imageId = $layer['image_id'];
                if (isset($images[$imageId])) {
                    $effectiveProperties['src'] = $images[$imageId]['data'] ?? null;
                }
            }

            $transformed = [
                'id' => $layer['position'] ?? Str::uuid()->toString(),
                'type' => $type,
                'name' => $layer['name'] ?? 'Layer',
                'x' => $layer['x'] ?? 0,
                'y' => $layer['y'] ?? 0,
                'width' => $layer['width'] ?? 100,
                'height' => $layer['height'] ?? 100,
                'rotation' => $layer['rotation'] ?? 0,
                'scale_x' => $layer['scale_x'] ?? 1,
                'scale_y' => $layer['scale_y'] ?? 1,
                'opacity' => $layer['opacity'] ?? 1,
                'visible' => $layer['visible'] ?? true,
                'locked' => $layer['locked'] ?? false,
                'position' => $layer['position'] ?? 0,
                'properties' => $effectiveProperties,
            ];

            $transformedLayers[] = $transformed;
        }

        // Sort by position for correct z-order
        usort($transformedLayers, fn($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

        return [
            'id' => 'debug-' . Str::uuid()->toString(),
            'name' => 'Debug Render',
            'width' => $psdData['width'] ?? 1080,
            'height' => $psdData['height'] ?? 1080,
            'backgroundColor' => $psdData['background_color'] ?? '#ffffff',
            'layers' => $transformedLayers,
        ];
    }

    /**
     * Flatten nested layer groups into a single array.
     *
     * @param array $layers
     * @return array
     */
    protected function flattenLayers(array $layers): array
    {
        $result = [];

        foreach ($layers as $layer) {
            if ($layer['type'] === 'group') {
                // Recursively flatten children
                $children = $this->flattenLayers($layer['children'] ?? []);
                $result = array_merge($result, $children);
            } else {
                $result[] = $layer;
            }
        }

        return $result;
    }

    /**
     * Render using Vue EditorCanvas component (single source of truth).
     *
     * This renders exactly what the editor shows by using the /render-vue
     * endpoint in template-renderer, which opens the RenderPreviewPage.vue.
     *
     * @param Request $request
     * @return Response
     *
     * Usage:
     * curl -X POST http://localhost/api/v1/debug/render-vue \
     *   -H "Content-Type: application/json" \
     *   -d '{"template": {...}}' \
     *   -o rendered.png
     */
    public function renderVue(Request $request): Response
    {
        $data = $request->all();

        if (empty($data['template']) && empty($data['layers'])) {
            return response()->json(['error' => 'No template or layers data provided'], 400);
        }

        // If only layers provided, wrap in template structure
        $template = $data['template'] ?? [
            'width' => $data['width'] ?? 1080,
            'height' => $data['height'] ?? 1080,
            'backgroundColor' => $data['background_color'] ?? '#ffffff',
            'layers' => $data['layers'] ?? [],
        ];

        $width = $template['width'] ?? 1080;
        $height = $template['height'] ?? 1080;
        $scale = $request->query('scale', 2);

        try {
            $response = Http::timeout(60)
                ->post("{$this->templateRendererUrl}/render-vue", [
                    'template' => $template,
                    'width' => $width,
                    'height' => $height,
                    'scale' => $scale,
                ]);

            if ($response->failed()) {
                return response()->json([
                    'error' => 'Vue render failed',
                    'details' => $response->json() ?? $response->body(),
                ], 500);
            }

            return response($response->body(), 200)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'inline; filename="render-vue.png"');

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Render service error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Render PSD using Vue EditorCanvas component (single source of truth).
     *
     * This parses a PSD file and renders it exactly as the editor would show it.
     *
     * @param Request $request
     * @return Response
     *
     * Usage:
     * curl -X POST http://localhost/api/v1/debug/render-psd-vue \
     *   -F "file=@template.psd" \
     *   -o rendered.png
     */
    public function renderPsdVue(Request $request): Response
    {
        $request->validate([
            'file' => 'required|file|max:512000',
        ]);

        $file = $request->file('file');
        $scale = $request->query('scale', 2);

        try {
            // Parse PSD using psd-parser service
            $parseResponse = Http::timeout(120)
                ->attach('file', $file->get(), $file->getClientOriginalName())
                ->post("{$this->psdParserUrl}/parse");

            if ($parseResponse->failed()) {
                return response()->json([
                    'error' => 'PSD parsing failed',
                    'details' => $parseResponse->json() ?? $parseResponse->body(),
                ], 500);
            }

            $psdData = $parseResponse->json();

            if (isset($psdData['error'])) {
                return response()->json([
                    'error' => 'PSD parsing error',
                    'details' => $psdData['error'],
                ], 500);
            }

            // Transform to template format with defaults
            $templateData = $this->transformPsdToTemplateWithDefaults($psdData);

            // Render using Vue endpoint
            $renderResponse = Http::timeout(60)
                ->post("{$this->templateRendererUrl}/render-vue", [
                    'template' => $templateData,
                    'width' => $psdData['width'] ?? 1080,
                    'height' => $psdData['height'] ?? 1080,
                    'scale' => $scale,
                ]);

            if ($renderResponse->failed()) {
                return response()->json([
                    'error' => 'Vue render failed',
                    'details' => $renderResponse->json() ?? $renderResponse->body(),
                ], 500);
            }

            return response($renderResponse->body(), 200)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'inline; filename="render-psd-vue.png"');

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Service error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compare all three render methods: original PSD, Konva render, Vue render.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * Usage:
     * curl -X POST http://localhost/api/v1/debug/compare-all \
     *   -F "file=@template.psd" | jq
     */
    public function compareAll(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:512000',
        ]);

        $file = $request->file('file');
        $scale = $request->query('scale', 0.5);

        try {
            // Get original PSD render
            $originalResponse = Http::timeout(120)
                ->attach('file', $file->get(), $file->getClientOriginalName())
                ->post("{$this->psdParserUrl}/render-psd?scale={$scale}");

            // Parse PSD
            $parseResponse = Http::timeout(120)
                ->attach('file', $file->get(), $file->getClientOriginalName())
                ->post("{$this->psdParserUrl}/parse");

            $psdData = $parseResponse->json();

            // Render parsed data (Konva standalone)
            $templateData = $this->transformPsdToTemplate($psdData);
            $konvaResponse = Http::timeout(60)
                ->post("{$this->templateRendererUrl}/render", [
                    'template' => $templateData,
                    'width' => $psdData['width'] ?? 1080,
                    'height' => $psdData['height'] ?? 1080,
                    'scale' => $scale,
                ]);

            // Render with Vue (single source of truth)
            $templateDataWithDefaults = $this->transformPsdToTemplateWithDefaults($psdData);
            $vueResponse = Http::timeout(60)
                ->post("{$this->templateRendererUrl}/render-vue", [
                    'template' => $templateDataWithDefaults,
                    'width' => $psdData['width'] ?? 1080,
                    'height' => $psdData['height'] ?? 1080,
                    'scale' => $scale,
                ]);

            return response()->json([
                'original' => [
                    'success' => $originalResponse->successful(),
                    'image' => $originalResponse->successful()
                        ? 'data:image/png;base64,' . base64_encode($originalResponse->body())
                        : null,
                ],
                'parsed' => [
                    'success' => $parseResponse->successful(),
                    'width' => $psdData['width'] ?? null,
                    'height' => $psdData['height'] ?? null,
                    'layer_count' => count($psdData['layers'] ?? []),
                    'warnings' => $psdData['warnings'] ?? [],
                ],
                'konva_render' => [
                    'success' => $konvaResponse->successful(),
                    'image' => $konvaResponse->successful()
                        ? 'data:image/png;base64,' . base64_encode($konvaResponse->body())
                        : null,
                    'error' => $konvaResponse->failed() ? $konvaResponse->body() : null,
                ],
                'vue_render' => [
                    'success' => $vueResponse->successful(),
                    'image' => $vueResponse->successful()
                        ? 'data:image/png;base64,' . base64_encode($vueResponse->body())
                        : null,
                    'error' => $vueResponse->failed() ? $vueResponse->body() : null,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Comparison failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
