<?php

use App\Enums\AiProvider;
use App\Models\Brand;
use App\Models\BrandAiKey;
use App\Models\User;
use App\Services\AI\DirectImageGeneratorService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->brand = Brand::create([
        'user_id' => $this->user->id,
        'name' => 'Test Brand',
        'is_active' => true,
    ]);
    BrandAiKey::create([
        'brand_id' => $this->brand->id,
        'provider' => AiProvider::WaveSpeed,
        'api_key' => 'test-wavespeed-key',
        'is_active' => true,
    ]);
    $this->service = new DirectImageGeneratorService();
});

describe('DirectImageGeneratorService', function () {

    describe('generateFromPrompt', function () {

        it('returns image path on success', function () {
            Storage::fake('public');

            Http::fake([
                '*/api/v3/google/nano-banana/text-to-image' => Http::response([
                    'data' => ['id' => 'job-123'],
                ], 200),
                '*/api/v3/predictions/job-123/result' => Http::response([
                    'data' => [
                        'status' => 'completed',
                        'outputs' => ['https://example.com/image.jpg'],
                    ],
                ], 200),
                'https://example.com/image.jpg' => Http::response('fake-image-data', 200),
            ]);

            $result = $this->service->generateFromPrompt($this->brand, 'A beautiful landscape');

            expect($result['success'])->toBeTrue();
            expect($result['image_path'])->toContain('pipelines/');
            Storage::disk('public')->assertExists($result['image_path']);
        });

        it('returns error when no API key', function () {
            $brandWithoutKey = Brand::create([
                'user_id' => $this->user->id,
                'name' => 'No Key Brand',
                'is_active' => true,
            ]);

            $result = $this->service->generateFromPrompt($brandWithoutKey, 'A prompt');

            expect($result['success'])->toBeFalse();
            expect($result['error_code'])->toBe('no_api_key');
        });

        it('returns error on empty prompt', function () {
            $result = $this->service->generateFromPrompt($this->brand, '');

            expect($result['success'])->toBeFalse();
            expect($result['error'])->toBe('No prompt provided');
        });

    });

    describe('generateFromImage', function () {

        it('sends images array for nano-banana model', function () {
            Storage::fake('public');
            Storage::disk('public')->put('source/test.jpg', 'fake-image');

            Http::fake([
                '*/media/upload/binary' => Http::response([
                    'data' => ['download_url' => 'https://wavespeed.example.com/uploaded.jpg'],
                ], 200),
                '*/api/v3/google/nano-banana/edit' => Http::response([
                    'data' => ['id' => 'job-456'],
                ], 200),
                '*/api/v3/predictions/job-456/result' => Http::response([
                    'data' => [
                        'status' => 'completed',
                        'outputs' => ['https://example.com/result.jpg'],
                    ],
                ], 200),
                'https://example.com/result.jpg' => Http::response('result-data', 200),
            ]);

            $this->service->generateFromImage($this->brand, 'Edit this', 'source/test.jpg', [
                'model' => '/google/nano-banana/edit',
            ]);

            Http::assertSent(function ($request) {
                if (!str_contains($request->url(), '/google/nano-banana/edit')) {
                    return false;
                }
                $body = $request->data();

                return isset($body['images'])
                    && is_array($body['images'])
                    && !isset($body['image'])
                    && !isset($body['strength']);
            });
        });

        it('sends image string for alibaba model', function () {
            Storage::fake('public');
            Storage::disk('public')->put('source/test.jpg', 'fake-image');

            Http::fake([
                '*/media/upload/binary' => Http::response([
                    'data' => ['download_url' => 'https://wavespeed.example.com/uploaded.jpg'],
                ], 200),
                '*/api/v3/alibaba/wan-2.6/image-edit' => Http::response([
                    'data' => ['id' => 'job-789'],
                ], 200),
                '*/api/v3/predictions/job-789/result' => Http::response([
                    'data' => [
                        'status' => 'completed',
                        'outputs' => ['https://example.com/result.jpg'],
                    ],
                ], 200),
                'https://example.com/result.jpg' => Http::response('result-data', 200),
            ]);

            $this->service->generateFromImage($this->brand, 'Edit this', 'source/test.jpg', [
                'model' => '/alibaba/wan-2.6/image-edit',
            ]);

            Http::assertSent(function ($request) {
                if (!str_contains($request->url(), '/alibaba/wan-2.6/image-edit')) {
                    return false;
                }
                $body = $request->data();

                return isset($body['image'])
                    && is_string($body['image'])
                    && isset($body['strength'])
                    && !isset($body['images']);
            });
        });

        it('includes strength with correct value for singular models', function () {
            Storage::fake('public');
            Storage::disk('public')->put('source/test.jpg', 'fake-image');

            Http::fake([
                '*/media/upload/binary' => Http::response([
                    'data' => ['download_url' => 'https://wavespeed.example.com/uploaded.jpg'],
                ], 200),
                '*/api/v3/alibaba/wan-2.6/image-edit' => Http::response([
                    'data' => ['id' => 'job-str'],
                ], 200),
                '*/api/v3/predictions/job-str/result' => Http::response([
                    'data' => [
                        'status' => 'completed',
                        'outputs' => ['https://example.com/result.jpg'],
                    ],
                ], 200),
                'https://example.com/result.jpg' => Http::response('result-data', 200),
            ]);

            $this->service->generateFromImage($this->brand, 'Edit this', 'source/test.jpg', [
                'model' => '/alibaba/wan-2.6/image-edit',
                'strength' => 0.8,
            ]);

            Http::assertSent(function ($request) {
                if (!str_contains($request->url(), '/alibaba/wan-2.6/image-edit')) {
                    return false;
                }

                return $request->data()['strength'] === 0.8;
            });
        });

        it('uploads file before creating job', function () {
            Storage::fake('public');
            Storage::disk('public')->put('source/test.jpg', 'fake-image');

            $callOrder = [];

            Http::fake(function ($request) use (&$callOrder) {
                if (str_contains($request->url(), '/media/upload/binary')) {
                    $callOrder[] = 'upload';

                    return Http::response([
                        'data' => ['download_url' => 'https://wavespeed.example.com/uploaded.jpg'],
                    ], 200);
                }
                if (str_contains($request->url(), '/google/nano-banana/edit')) {
                    $callOrder[] = 'create_job';

                    return Http::response([
                        'data' => ['id' => 'job-order'],
                    ], 200);
                }
                if (str_contains($request->url(), '/predictions/')) {
                    return Http::response([
                        'data' => [
                            'status' => 'completed',
                            'outputs' => ['https://example.com/result.jpg'],
                        ],
                    ], 200);
                }

                return Http::response('fake-image-data', 200);
            });

            $this->service->generateFromImage($this->brand, 'Edit this', 'source/test.jpg');

            expect($callOrder)->toBe(['upload', 'create_job']);
        });

        it('returns error when source file is missing', function () {
            Storage::fake('public');

            $result = $this->service->generateFromImage($this->brand, 'Edit this', 'nonexistent/file.jpg');

            expect($result['success'])->toBeFalse();
            expect($result['error'])->toContain('Source image not found');
        });

        it('returns error when upload fails', function () {
            Storage::fake('public');
            Storage::disk('public')->put('source/test.jpg', 'fake-image');

            Http::fake([
                '*/media/upload/binary' => Http::response('Server Error', 500),
            ]);

            $result = $this->service->generateFromImage($this->brand, 'Edit this', 'source/test.jpg');

            expect($result['success'])->toBeFalse();
            expect($result['error'])->toContain('upload failed');
        });

        it('returns error when no API key', function () {
            $brandWithoutKey = Brand::create([
                'user_id' => $this->user->id,
                'name' => 'No Key Brand',
                'is_active' => true,
            ]);

            $result = $this->service->generateFromImage($brandWithoutKey, 'Edit this', 'source/test.jpg');

            expect($result['success'])->toBeFalse();
            expect($result['error_code'])->toBe('no_api_key');
        });

    });

});
