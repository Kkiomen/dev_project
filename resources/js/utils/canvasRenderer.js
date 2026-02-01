/**
 * Canvas Renderer Utilities
 *
 * This file contains the rendering logic shared between:
 * - EditorCanvas.vue (frontend editor)
 * - template-renderer service (PNG generation)
 *
 * IMPORTANT: This is the single source of truth for layer rendering.
 * Any changes to rendering logic should be made here.
 */

/**
 * Convert hex color to rgba with opacity
 */
export function hexToRgba(hex, opacity) {
    if (!hex || typeof hex !== 'string') return `rgba(0, 0, 0, ${opacity})`;
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${opacity})`;
}

/**
 * Get gradient configuration for Konva
 */
export function getGradientConfig(layer) {
    const props = layer.properties || {};
    const gradientType = props.gradientType || 'linear';
    const startColor = props.gradientStartColor || '#3B82F6';
    const endColor = props.gradientEndColor || '#8B5CF6';
    const startOpacity = props.gradientStartOpacity ?? 1;
    const endOpacity = props.gradientEndOpacity ?? 1;
    const angle = props.gradientAngle || 0;
    const width = layer.width || 100;
    const height = layer.height || 100;

    // Convert to rgba with opacity
    const startColorRgba = hexToRgba(startColor, startOpacity);
    const endColorRgba = hexToRgba(endColor, endOpacity);

    if (gradientType === 'radial') {
        return {
            fillRadialGradientStartPoint: { x: width / 2, y: height / 2 },
            fillRadialGradientEndPoint: { x: width / 2, y: height / 2 },
            fillRadialGradientStartRadius: 0,
            fillRadialGradientEndRadius: Math.max(width, height) / 2,
            fillRadialGradientColorStops: [0, startColorRgba, 1, endColorRgba],
        };
    } else {
        // Linear gradient - calculate start/end points based on angle
        // Photoshop angle: 0° = left-to-right, 90° = bottom-to-top
        const angleRad = (angle * Math.PI) / 180;
        const halfWidth = width / 2;
        const halfHeight = height / 2;

        const cos = Math.cos(angleRad);
        const sin = Math.sin(angleRad);
        const length = Math.abs(width * cos) + Math.abs(height * sin);

        // Start at bottom (for 90°), end at top - negate sin for Y axis
        return {
            fillLinearGradientStartPoint: {
                x: halfWidth - (cos * length) / 2,
                y: halfHeight + (sin * length) / 2,
            },
            fillLinearGradientEndPoint: {
                x: halfWidth + (cos * length) / 2,
                y: halfHeight - (sin * length) / 2,
            },
            fillLinearGradientColorStops: [0, startColorRgba, 1, endColorRgba],
        };
    }
}

/**
 * Get blur filter configuration
 */
export function getBlurConfig(layer) {
    const props = layer.properties || {};
    if (!props.blurEnabled) return {};

    return {
        blurRadius: props.blurRadius ?? 10,
    };
}

/**
 * Get shadow configuration
 */
export function getShadowConfig(layer) {
    const props = layer.properties || {};
    if (!props.shadowEnabled) return {};

    return {
        shadowColor: props.shadowColor || '#000000',
        shadowBlur: props.shadowBlur ?? 10,
        shadowOffsetX: props.shadowOffsetX ?? 5,
        shadowOffsetY: props.shadowOffsetY ?? 5,
        shadowOpacity: props.shadowOpacity ?? 0.5,
        shadowEnabled: true,
    };
}

/**
 * Get text configuration for Konva
 */
export function getTextConfig(layer) {
    const props = layer.properties || {};

    // Apply text transform
    let text = props.text || '';
    const transform = props.textTransform;
    if (transform === 'uppercase') text = text.toUpperCase();
    else if (transform === 'lowercase') text = text.toLowerCase();
    else if (transform === 'capitalize') text = text.replace(/\b\w/g, (c) => c.toUpperCase());

    // Handle vertical text direction
    const textDirection = props.textDirection || 'horizontal';
    if (textDirection === 'vertical') {
        text = text.split('').map(char => {
            if (char === ' ') return '';
            if (char === '\n') return '\n';
            return char;
        }).join('\n');
    }

    // Check if text has fixed width (for word wrapping)
    const hasFixedWidth = props.fixedWidth === true;

    // Build fontStyle
    const fontWeight = props.fontWeight || 'normal';
    const fontStyle = props.fontStyle || 'normal';

    const config = {
        x: layer.x || 0,
        y: layer.y || 0,
        width: layer.width,
        height: layer.height,
        rotation: layer.rotation || 0,
        scaleX: layer.scale_x || 1,
        scaleY: layer.scale_y || 1,
        opacity: layer.opacity ?? 1,
        visible: layer.visible !== false,
        text,
        fontSize: props.fontSize || 24,
        fontFamily: props.fontFamily || 'Arial',
        fontStyle: `${fontWeight} ${fontStyle}`,
        fill: props.fill || '#000000',
        align: textDirection === 'vertical' ? 'center' : (props.align || 'left'),
        lineHeight: textDirection === 'vertical' ? 0.9 : (props.lineHeight || 1.2),
        letterSpacing: props.letterSpacing || 0,
        textDecoration: props.textDecoration || '',
        wrap: hasFixedWidth ? 'word' : 'none',
        ...getShadowConfig(layer),
        ...getBlurConfig(layer),
    };

    return config;
}

/**
 * Get rectangle configuration for Konva
 */
export function getRectConfig(layer, fillImage = null) {
    const props = layer.properties || {};
    const fillType = props.fillType;
    const useImageFill = fillType === 'image' && fillImage;
    const useGradientFill = fillType === 'gradient';

    const config = {
        x: layer.x || 0,
        y: layer.y || 0,
        width: layer.width || 100,
        height: layer.height || 100,
        rotation: layer.rotation || 0,
        scaleX: layer.scale_x || 1,
        scaleY: layer.scale_y || 1,
        opacity: layer.opacity ?? 1,
        visible: layer.visible !== false,
        stroke: props.stroke || null,
        strokeWidth: props.strokeWidth || 0,
        cornerRadius: props.cornerRadius || 0,
        ...getShadowConfig(layer),
        ...getBlurConfig(layer),
    };

    // Set fill based on type - don't set fill at all when using gradient
    // Konva fill priority defaults to 'color', so we must not set fill when using gradient
    if (useGradientFill) {
        const gradientConfig = getGradientConfig(layer);
        Object.assign(config, gradientConfig);
        const gradientType = props.gradientType || 'linear';
        config.fillPriority = gradientType === 'radial' ? 'radial-gradient' : 'linear-gradient';
    } else if (useImageFill) {
        // Image fill handled separately
        config.fillPatternImage = fillImage;
        config.fillPriority = 'pattern';
    } else {
        config.fill = props.fill || '#CCCCCC';
    }

    return config;
}

/**
 * Get ellipse configuration for Konva
 */
export function getEllipseConfig(layer, fillImage = null) {
    const props = layer.properties || {};
    const fillType = props.fillType;
    const useImageFill = fillType === 'image' && fillImage;
    const useGradientFill = fillType === 'gradient';

    const config = {
        x: layer.x || 0,
        y: layer.y || 0,
        width: layer.width || 100,
        height: layer.height || 100,
        rotation: layer.rotation || 0,
        scaleX: layer.scale_x || 1,
        scaleY: layer.scale_y || 1,
        opacity: layer.opacity ?? 1,
        visible: layer.visible !== false,
        radiusX: (layer.width || 100) / 2,
        radiusY: (layer.height || 100) / 2,
        stroke: props.stroke || null,
        strokeWidth: props.strokeWidth || 0,
        offsetX: -(layer.width || 100) / 2,
        offsetY: -(layer.height || 100) / 2,
        ...getShadowConfig(layer),
    };

    // Set fill based on type
    if (useGradientFill) {
        const gradientConfig = getGradientConfig(layer);
        Object.assign(config, gradientConfig);
        const gradientType = props.gradientType || 'linear';
        config.fillPriority = gradientType === 'radial' ? 'radial-gradient' : 'linear-gradient';
    } else if (useImageFill) {
        config.fillPatternImage = fillImage;
        config.fillPriority = 'pattern';
    } else {
        config.fill = props.fill || '#CCCCCC';
    }

    return config;
}

/**
 * Get image configuration for Konva
 */
export function getImageConfig(layer) {
    const props = layer.properties || {};

    return {
        x: layer.x || 0,
        y: layer.y || 0,
        width: layer.width,
        height: layer.height,
        rotation: layer.rotation || 0,
        scaleX: layer.scale_x || 1,
        scaleY: layer.scale_y || 1,
        opacity: layer.opacity ?? 1,
        visible: layer.visible !== false,
        ...getShadowConfig(layer),
    };
}

/**
 * Flatten nested layer structure (groups contain children)
 */
export function flattenLayers(layers) {
    const result = [];
    for (const layer of layers) {
        if (layer.type === 'group') {
            if (layer.children && Array.isArray(layer.children)) {
                result.push(...flattenLayers(layer.children));
            }
        } else {
            result.push(layer);
        }
    }
    return result;
}

// Export all functions for use in both Vue and Node.js
export default {
    hexToRgba,
    getGradientConfig,
    getBlurConfig,
    getShadowConfig,
    getTextConfig,
    getRectConfig,
    getEllipseConfig,
    getImageConfig,
    flattenLayers,
};
