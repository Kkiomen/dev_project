const express = require('express');
const smartcrop = require('smartcrop-sharp');
const sharp = require('sharp');
const Vibrant = require('node-vibrant');

const app = express();
app.use(express.json());

const PORT = process.env.PORT || 3334;

/**
 * Health check endpoint
 */
app.get('/health', (req, res) => {
    res.json({ status: 'healthy', service: 'image-analysis' });
});

/**
 * Analyze image for template composition
 */
app.post('/analyze', async (req, res) => {
    const { imageUrl, width = 1080, height = 1080 } = req.body;

    if (!imageUrl) {
        return res.status(400).json({
            success: false,
            error: 'imageUrl is required'
        });
    }

    try {
        // Download image
        const response = await fetch(imageUrl);
        if (!response.ok) {
            throw new Error(`Failed to fetch image: ${response.status}`);
        }

        const buffer = Buffer.from(await response.arrayBuffer());

        // Get image metadata
        const metadata = await sharp(buffer).metadata();

        // Use smartcrop to find best crop / focal point
        const cropResult = await smartcrop.crop(buffer, {
            width,
            height,
            minScale: 1.0
        });

        const topCrop = cropResult.topCrop;

        // Analyze brightness in quadrants
        const brightness = await analyzeBrightness(buffer);

        // Calculate focal point (center of the best crop area)
        const focalPoint = {
            x: Math.round(topCrop.x + topCrop.width / 2),
            y: Math.round(topCrop.y + topCrop.height / 2),
            normalized: {
                x: (topCrop.x + topCrop.width / 2) / metadata.width,
                y: (topCrop.y + topCrop.height / 2) / metadata.height
            }
        };

        // Calculate safe zones for text placement
        const safeZones = calculateSafeZones(topCrop, metadata, brightness);

        // Determine suggested text position
        const suggestedPosition = getSuggestedTextPosition(focalPoint, brightness, metadata);

        // Calculate busy zones (where NOT to place text)
        const busyZones = calculateBusyZones(topCrop, metadata);

        // Extract dominant colors from image
        const colors = await extractColors(buffer);

        res.json({
            success: true,
            image: {
                width: metadata.width,
                height: metadata.height,
                format: metadata.format
            },
            focal_point: focalPoint,
            brightness: brightness,
            colors: colors,
            suggested_text_position: suggestedPosition,
            safe_zones: safeZones,
            busy_zones: busyZones,
            crop_suggestion: {
                x: topCrop.x,
                y: topCrop.y,
                width: topCrop.width,
                height: topCrop.height
            }
        });

    } catch (error) {
        console.error('Analysis error:', error);
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

/**
 * Analyze brightness in image quadrants
 */
async function analyzeBrightness(buffer) {
    try {
        // Resize to small thumbnail for quick analysis
        const { data, info } = await sharp(buffer)
            .resize(100, 100, { fit: 'fill' })
            .raw()
            .toBuffer({ resolveWithObject: true });

        const quadrants = {
            'top-left': { sum: 0, count: 0 },
            'top-right': { sum: 0, count: 0 },
            'bottom-left': { sum: 0, count: 0 },
            'bottom-right': { sum: 0, count: 0 }
        };

        const halfWidth = Math.floor(info.width / 2);
        const halfHeight = Math.floor(info.height / 2);
        const channels = info.channels;

        for (let y = 0; y < info.height; y++) {
            for (let x = 0; x < info.width; x++) {
                const idx = (y * info.width + x) * channels;

                // Calculate brightness (average of RGB)
                let brightness;
                if (channels >= 3) {
                    brightness = (data[idx] + data[idx + 1] + data[idx + 2]) / 3;
                } else {
                    brightness = data[idx];
                }

                // Determine quadrant
                let quadrant;
                if (y < halfHeight) {
                    quadrant = x < halfWidth ? 'top-left' : 'top-right';
                } else {
                    quadrant = x < halfWidth ? 'bottom-left' : 'bottom-right';
                }

                quadrants[quadrant].sum += brightness;
                quadrants[quadrant].count++;
            }
        }

        // Calculate averages and normalize to 0-1
        const result = {};
        for (const [key, val] of Object.entries(quadrants)) {
            result[key] = Math.round((val.sum / val.count / 255) * 100) / 100;
        }

        // Overall brightness
        const totalSum = Object.values(quadrants).reduce((acc, q) => acc + q.sum, 0);
        const totalCount = Object.values(quadrants).reduce((acc, q) => acc + q.count, 0);
        result.overall = Math.round((totalSum / totalCount / 255) * 100) / 100;

        // Determine if image is dark or light
        result.is_dark = result.overall < 0.5;

        return result;

    } catch (error) {
        console.error('Brightness analysis error:', error);
        return {
            'top-left': 0.5,
            'top-right': 0.5,
            'bottom-left': 0.5,
            'bottom-right': 0.5,
            overall: 0.5,
            is_dark: false
        };
    }
}

/**
 * Calculate safe zones for text placement
 */
function calculateSafeZones(crop, metadata, brightness) {
    const zones = [];
    const margin = 80; // Margin from edges (professional spacing)
    const targetWidth = 1080;
    const targetHeight = 1080;

    // Find the darkest quadrants (best for white text)
    const quadrantBrightness = [
        { position: 'top-left', brightness: brightness['top-left'] },
        { position: 'top-right', brightness: brightness['top-right'] },
        { position: 'bottom-left', brightness: brightness['bottom-left'] },
        { position: 'bottom-right', brightness: brightness['bottom-right'] }
    ];

    // Sort by brightness (darkest first - better for light text)
    quadrantBrightness.sort((a, b) => a.brightness - b.brightness);

    // The 2 darkest quadrants are safe for light text
    for (let i = 0; i < 2; i++) {
        const pos = quadrantBrightness[i].position;
        const zone = getZoneCoordinates(pos, targetWidth, targetHeight, margin);
        zone.recommended_text_color = '#FFFFFF';
        zone.brightness = quadrantBrightness[i].brightness;
        zones.push(zone);
    }

    // Add bottom strip as common safe zone (often works well)
    zones.push({
        position: 'bottom',
        x: margin,
        y: targetHeight - 300,
        width: targetWidth - margin * 2,
        height: 260,
        recommended_text_color: brightness['bottom-left'] < 0.5 || brightness['bottom-right'] < 0.5 ? '#FFFFFF' : '#000000',
        brightness: (brightness['bottom-left'] + brightness['bottom-right']) / 2
    });

    return zones;
}

/**
 * Calculate busy zones where text should NOT be placed
 */
function calculateBusyZones(crop, metadata) {
    // The focal area is busy - don't put text there
    const targetWidth = 1080;
    const targetHeight = 1080;

    // Scale crop coordinates to target dimensions
    const scaleX = targetWidth / metadata.width;
    const scaleY = targetHeight / metadata.height;

    return [{
        position: 'focal',
        x: Math.round(crop.x * scaleX),
        y: Math.round(crop.y * scaleY),
        width: Math.round(crop.width * scaleX),
        height: Math.round(crop.height * scaleY),
        reason: 'Contains main subject/focal point'
    }];
}

/**
 * Get zone coordinates based on position name
 */
function getZoneCoordinates(position, width, height, margin) {
    const halfWidth = Math.floor(width / 2);
    const halfHeight = Math.floor(height / 2);

    switch (position) {
        case 'top-left':
            return { position, x: margin, y: margin, width: halfWidth - margin * 2, height: halfHeight - margin * 2 };
        case 'top-right':
            return { position, x: halfWidth + margin, y: margin, width: halfWidth - margin * 2, height: halfHeight - margin * 2 };
        case 'bottom-left':
            return { position, x: margin, y: halfHeight + margin, width: halfWidth - margin * 2, height: halfHeight - margin * 2 };
        case 'bottom-right':
            return { position, x: halfWidth + margin, y: halfHeight + margin, width: halfWidth - margin * 2, height: halfHeight - margin * 2 };
        default:
            return { position, x: margin, y: margin, width: width - margin * 2, height: height - margin * 2 };
    }
}

/**
 * Determine suggested text position based on focal point and brightness
 */
function getSuggestedTextPosition(focalPoint, brightness, metadata) {
    // If focal point is in upper half, suggest bottom for text
    if (focalPoint.normalized.y < 0.5) {
        return brightness['bottom-left'] < brightness['bottom-right'] ? 'bottom-left' : 'bottom-right';
    }

    // If focal point is in lower half, suggest top for text
    if (focalPoint.normalized.y > 0.5) {
        return brightness['top-left'] < brightness['top-right'] ? 'top-left' : 'top-right';
    }

    // If focal point is in left half, suggest right for text
    if (focalPoint.normalized.x < 0.5) {
        return brightness['top-right'] < brightness['bottom-right'] ? 'top-right' : 'bottom-right';
    }

    // Default: use the darkest quadrant
    const quadrants = [
        { pos: 'top-left', val: brightness['top-left'] },
        { pos: 'top-right', val: brightness['top-right'] },
        { pos: 'bottom-left', val: brightness['bottom-left'] },
        { pos: 'bottom-right', val: brightness['bottom-right'] }
    ];

    quadrants.sort((a, b) => a.val - b.val);
    return quadrants[0].pos;
}

/**
 * Extract dominant colors from image using node-vibrant.
 * Returns a palette of colors suitable for accent/decorative elements.
 */
async function extractColors(buffer) {
    try {
        const palette = await Vibrant.from(buffer).getPalette();

        // Build accent candidates in order of visual impact
        const accentCandidates = [
            palette.Vibrant?.hex,
            palette.DarkVibrant?.hex,
            palette.LightVibrant?.hex,
        ].filter(Boolean);

        return {
            vibrant: palette.Vibrant?.hex || null,
            muted: palette.Muted?.hex || null,
            dark_vibrant: palette.DarkVibrant?.hex || null,
            dark_muted: palette.DarkMuted?.hex || null,
            light_vibrant: palette.LightVibrant?.hex || null,
            light_muted: palette.LightMuted?.hex || null,
            // Ordered by usefulness for accents
            accent_candidates: accentCandidates,
            // Population data (how dominant each color is)
            populations: {
                vibrant: palette.Vibrant?.population || 0,
                muted: palette.Muted?.population || 0,
                dark_vibrant: palette.DarkVibrant?.population || 0,
                dark_muted: palette.DarkMuted?.population || 0,
            }
        };
    } catch (error) {
        console.error('Color extraction error:', error);
        return {
            vibrant: null,
            muted: null,
            dark_vibrant: null,
            dark_muted: null,
            light_vibrant: null,
            light_muted: null,
            accent_candidates: [],
            populations: {}
        };
    }
}

app.listen(PORT, () => {
    console.log(`Image Analysis Service running on port ${PORT}`);
});
