<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TemplateFontResource;
use App\Models\Template;
use App\Models\TemplateFont;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class TemplateFontController extends Controller
{
    public function index(Request $request, Template $template): AnonymousResourceCollection
    {
        $this->authorize('view', $template);

        return TemplateFontResource::collection($template->fonts);
    }

    public function store(Request $request, Template $template): TemplateFontResource
    {
        $this->authorize('update', $template);

        $request->validate([
            'font' => ['required', 'file', 'mimes:ttf,otf,woff,woff2', 'max:10240'],
            'font_family' => ['required', 'string', 'max:100'],
            'font_weight' => ['nullable', 'string', 'max:20'],
            'font_style' => ['nullable', 'string', 'max:20'],
        ]);

        $file = $request->file('font');
        $path = $file->store('templates/' . $template->public_id . '/fonts', 'public');

        $font = $template->fonts()->create([
            'font_family' => $request->input('font_family'),
            'font_file' => $path,
            'font_weight' => $request->input('font_weight', 'normal'),
            'font_style' => $request->input('font_style', 'normal'),
        ]);

        return new TemplateFontResource($font);
    }

    public function destroy(TemplateFont $font)
    {
        $this->authorize('update', $font->template);

        // Delete font file
        if ($font->font_file) {
            Storage::delete($font->font_file);
        }

        $font->delete();

        return response()->noContent();
    }
}
