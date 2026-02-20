<?php

namespace App\Services;

use App\Models\VideoProject;
use Illuminate\Support\Str;

class CompositionService
{
    public function buildDefaultComposition(VideoProject $project): array
    {
        $tracks = [];

        // Main video track
        if ($project->video_path) {
            $tracks[] = [
                'id' => 'track_' . Str::random(8),
                'name' => 'Main Video',
                'type' => 'video',
                'muted' => false,
                'locked' => false,
                'visible' => true,
                'elements' => [
                    [
                        'id' => 'el_' . Str::random(8),
                        'type' => 'video',
                        'name' => $project->original_filename ?? 'Video',
                        'time' => 0.0,
                        'duration' => $project->duration ?? 0.0,
                        'source' => 'media://video-projects/' . $project->user_id . '/' . basename($project->video_path),
                        'trim_start' => 0.0,
                        'trim_end' => 0.0,
                        'x' => '50%',
                        'y' => '50%',
                        'width' => '100%',
                        'height' => '100%',
                        'rotation' => 0,
                        'opacity' => 1.0,
                        'fit' => 'cover',
                        'volume' => 0,
                        'fade_in' => 0,
                        'fade_out' => 0,
                        'effects' => [],
                        'transition' => null,
                        'modification_key' => null,
                    ],
                ],
            ];
        }

        // Overlay track (empty) — above audio in timeline
        $tracks[] = [
            'id' => 'track_' . Str::random(8),
            'name' => 'Overlay',
            'type' => 'overlay',
            'muted' => false,
            'locked' => false,
            'visible' => true,
            'elements' => [],
        ];

        // Audio track — always at bottom of timeline, linked to video source
        $audioElements = [];
        if ($project->video_path) {
            $audioElements[] = [
                'id' => 'el_' . Str::random(8),
                'type' => 'audio',
                'name' => $project->original_filename ?? 'Audio',
                'time' => 0.0,
                'duration' => $project->duration ?? 0.0,
                'source' => 'media://video-projects/' . $project->user_id . '/' . basename($project->video_path),
                'trim_start' => 0.0,
                'trim_end' => 0.0,
                'x' => '50%',
                'y' => '50%',
                'width' => '100%',
                'height' => '100%',
                'rotation' => 0,
                'opacity' => 1.0,
                'fit' => 'cover',
                'volume' => 1.0,
                'fade_in' => 0,
                'fade_out' => 0,
                'effects' => [],
                'transition' => null,
                'modification_key' => null,
            ];
        }

        $tracks[] = [
            'id' => 'track_' . Str::random(8),
            'name' => 'Audio',
            'type' => 'audio',
            'muted' => false,
            'locked' => false,
            'visible' => true,
            'elements' => $audioElements,
        ];

        // Captions from transcription
        $captions = [
            'enabled' => $project->hasTranscription(),
            'style' => $project->caption_style ?? 'clean',
            'segments' => $project->getSegments(),
            'settings' => array_merge([
                'position' => 'bottom',
                'font_size' => 48,
                'highlight_keywords' => false,
            ], $project->caption_settings ?? []),
        ];

        return [
            'version' => 1,
            'width' => $project->width ?? 1080,
            'height' => $project->height ?? 1920,
            'fps' => 30,
            'background_color' => '#000000',
            'tracks' => $tracks,
            'captions' => $captions,
        ];
    }

    public function resolveModifications(array $composition, array $modifications): array
    {
        foreach ($composition['tracks'] as &$track) {
            foreach ($track['elements'] as &$element) {
                $key = $element['modification_key'] ?? null;
                if ($key && isset($modifications[$key])) {
                    $mod = $modifications[$key];

                    if (is_string($mod)) {
                        // Simple value: replace source for media, text for text
                        if (in_array($element['type'], ['video', 'image', 'audio'])) {
                            $element['source'] = $mod;
                        } elseif ($element['type'] === 'text') {
                            $element['text'] = $mod;
                        }
                    } elseif (is_array($mod)) {
                        // Object: merge properties
                        $element = array_merge($element, $mod);
                    }
                }
            }
        }

        return $composition;
    }

    public function validateComposition(array $composition): bool
    {
        if (!isset($composition['version'], $composition['width'], $composition['height'], $composition['tracks'])) {
            return false;
        }

        if (!is_array($composition['tracks'])) {
            return false;
        }

        foreach ($composition['tracks'] as $track) {
            if (!isset($track['id'], $track['type'], $track['elements'])) {
                return false;
            }

            if (!in_array($track['type'], ['video', 'audio', 'overlay'])) {
                return false;
            }

            if (!is_array($track['elements'])) {
                return false;
            }

            foreach ($track['elements'] as $element) {
                if (!isset($element['id'], $element['type'], $element['time'], $element['duration'])) {
                    return false;
                }

                if (!in_array($element['type'], ['video', 'image', 'audio', 'text', 'shape'])) {
                    return false;
                }
            }
        }

        return true;
    }

    public function compositionToEdl(array $composition): array
    {
        $clips = [];

        foreach ($composition['tracks'] ?? [] as $track) {
            if ($track['type'] !== 'video') {
                continue;
            }

            foreach ($track['elements'] ?? [] as $element) {
                if ($element['type'] !== 'video') {
                    continue;
                }

                $clips[] = [
                    'time' => $element['time'] ?? 0,
                    'start' => $element['trim_start'] ?? 0,
                    'end' => ($element['trim_start'] ?? 0) + ($element['duration'] ?? 0),
                    'trimStart' => 0,
                    'trimEnd' => 0,
                ];
            }
        }

        usort($clips, fn($a, $b) => $a['time'] <=> $b['time']);

        // Remove the timeline position key — EDL only needs start/end
        $edlClips = array_map(fn($clip) => [
            'start' => $clip['start'],
            'end' => $clip['end'],
            'trimStart' => $clip['trimStart'],
            'trimEnd' => $clip['trimEnd'],
        ], $clips);

        return [
            'tracks' => [
                [
                    'type' => 'video',
                    'clips' => array_values($edlClips),
                ],
            ],
        ];
    }

    /**
     * Build a render plan from composition: multi-layer with positions matching the NLE player.
     *
     * Layers are ordered bottom-to-top (matching the player's draw order:
     * track[last] drawn first = behind, track[0] drawn last = on top).
     *
     * @return array{layers: array, audio: array, media_sources: array, width: int, height: int, fps: int, total_duration: float}
     */
    public function buildRenderPlan(array $composition): array
    {
        $width = $composition['width'] ?? 1080;
        $height = $composition['height'] ?? 1920;
        $fps = $composition['fps'] ?? 30;
        $totalDuration = $this->calculateDuration($composition);

        $layers = [];
        $audioElements = [];
        $mediaSources = [];

        $tracks = $composition['tracks'] ?? [];

        // Player draws tracks in reverse: track[last] first (behind), track[0] last (on top).
        for ($i = count($tracks) - 1; $i >= 0; $i--) {
            $track = $tracks[$i];
            if (!($track['visible'] ?? true)) {
                continue;
            }

            foreach ($track['elements'] ?? [] as $element) {
                $elType = $element['type'] ?? '';
                $source = $element['source'] ?? null;

                if (in_array($elType, ['video', 'image'])) {
                    $centerX = $this->resolvePosition($element['x'] ?? '50%', $width);
                    $centerY = $this->resolvePosition($element['y'] ?? '50%', $height);
                    $targetW = $this->resolveSize($element['width'] ?? '100%', $width);
                    $targetH = $this->resolveSize($element['height'] ?? '100%', $height);

                    $layers[] = [
                        'type' => $elType,
                        'source' => $source,
                        'time' => round((float) ($element['time'] ?? 0), 4),
                        'duration' => round((float) ($element['duration'] ?? 0), 4),
                        'trim_start' => round((float) ($element['trim_start'] ?? 0), 4),
                        'x' => (int) round($centerX - $targetW / 2),
                        'y' => (int) round($centerY - $targetH / 2),
                        'width' => max(2, (int) round($targetW)),
                        'height' => max(2, (int) round($targetH)),
                        'opacity' => (float) ($element['opacity'] ?? 1.0),
                        'fit' => $element['fit'] ?? 'cover',
                    ];

                    if ($source && !in_array($source, $mediaSources)) {
                        $mediaSources[] = $source;
                    }
                } elseif ($elType === 'audio' && ($track['type'] ?? '') === 'audio') {
                    if (!($track['muted'] ?? false)) {
                        $audioElements[] = [
                            'source' => $source,
                            'time' => round((float) ($element['time'] ?? 0), 4),
                            'duration' => round((float) ($element['duration'] ?? 0), 4),
                            'trim_start' => round((float) ($element['trim_start'] ?? 0), 4),
                            'volume' => (float) ($element['volume'] ?? 1.0),
                        ];
                    }
                }
            }
        }

        return [
            'layers' => $layers,
            'audio' => $audioElements,
            'media_sources' => $mediaSources,
            'width' => $width,
            'height' => $height,
            'fps' => $fps,
            'total_duration' => round($totalDuration, 4),
        ];
    }

    protected function resolvePosition($value, int $total): float
    {
        if (is_string($value) && str_ends_with($value, '%')) {
            return (floatval($value) / 100) * $total;
        }

        return floatval($value);
    }

    protected function resolveSize($value, int $total): float
    {
        if (is_string($value) && str_ends_with($value, '%')) {
            return (floatval($value) / 100) * $total;
        }

        return floatval($value) ?: $total;
    }

    public function calculateDuration(array $composition): float
    {
        $maxEnd = 0.0;

        foreach ($composition['tracks'] ?? [] as $track) {
            foreach ($track['elements'] ?? [] as $element) {
                $end = ($element['time'] ?? 0) + ($element['duration'] ?? 0);
                if ($end > $maxEnd) {
                    $maxEnd = $end;
                }
            }
        }

        return $maxEnd;
    }
}
