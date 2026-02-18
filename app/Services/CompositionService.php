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
                        'volume' => 1.0,
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
