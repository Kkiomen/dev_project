import express from 'express';
import cors from 'cors';
import puppeteer from 'puppeteer';
import sharp from 'sharp';
import path from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = process.env.PORT || 3336;

// Middleware
app.use(cors());
app.use(express.json({ limit: '50mb' }));
app.use('/static', express.static(path.join(__dirname, 'static')));

// Read render.html template and Konva library
const renderHtmlPath = path.join(__dirname, 'render.html');
const renderHtmlTemplate = fs.readFileSync(renderHtmlPath, 'utf-8');
const konvaJsPath = path.join(__dirname, 'static', 'konva.min.js');
const konvaJs = fs.readFileSync(konvaJsPath, 'utf-8');

// System fonts that don't need to be loaded from Google Fonts
const SYSTEM_FONTS = ['Arial', 'Helvetica', 'Times New Roman', 'Georgia', 'Verdana', 'Courier New', 'Impact', 'Comic Sans MS'];

/**
 * Extract unique font families from template layers.
 * Used to preload Google Fonts before rendering.
 */
function extractFontsFromTemplate(template) {
    const fonts = new Set();

    function processLayers(layers) {
        if (!Array.isArray(layers)) return;
        for (const layer of layers) {
            if (layer.properties?.fontFamily) {
                const font = layer.properties.fontFamily;
                // Skip system fonts
                if (!SYSTEM_FONTS.includes(font)) {
                    fonts.add(font);
                }
            }
            // Process nested layers (groups)
            if (layer.children) {
                processLayers(layer.children);
            }
        }
    }

    processLayers(template.layers || []);
    return [...fonts];
}

// Generate PNG from template data
async function renderTemplate(templateData, width, height, deviceScaleFactor = 2) {
    const browser = await puppeteer.launch({
        headless: 'new',
        executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || '/usr/bin/chromium',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas',
            '--disable-gpu',
            '--window-size=1920,1080',
        ],
    });

    try {
        const page = await browser.newPage();

        // Set viewport to match template size
        await page.setViewport({
            width: Math.ceil(width),
            height: Math.ceil(height),
            deviceScaleFactor: deviceScaleFactor,
        });

        // Inject template data and Konva library into HTML
        const templateDataJson = JSON.stringify(templateData);
        const htmlContent = renderHtmlTemplate
            .replace('__KONVA_JS__', konvaJs)
            .replace('__TEMPLATE_DATA__', templateDataJson)
            .replace(/__CANVAS_WIDTH__/g, width)
            .replace(/__CANVAS_HEIGHT__/g, height);

        // Load the HTML content
        await page.setContent(htmlContent, { waitUntil: 'networkidle0', timeout: 30000 });

        // Wait for fonts to be loaded
        await page.evaluate(() => {
            return document.fonts.ready;
        });

        // Wait for Konva to render
        await page.waitForSelector('#container canvas', { timeout: 10000 });

        // Additional wait for images to load
        await page.evaluate(() => {
            return new Promise((resolve) => {
                // Check if there are any images loading
                const checkImages = () => {
                    const images = document.querySelectorAll('img');
                    const allLoaded = Array.from(images).every(img => img.complete);
                    if (allLoaded) {
                        resolve();
                    } else {
                        setTimeout(checkImages, 100);
                    }
                };
                checkImages();
            });
        });

        // Give Konva a moment to finish rendering
        await new Promise(resolve => setTimeout(resolve, 500));

        // Take screenshot of the container
        const container = await page.$('#container');
        if (!container) {
            throw new Error('Could not find container element');
        }

        const imageBuffer = await container.screenshot({
            type: 'png',
            omitBackground: false,
        });

        return imageBuffer;
    } finally {
        await browser.close();
    }
}

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({ status: 'ok', service: 'template-renderer' });
});

// Laravel app URL (internal Docker network)
const LARAVEL_URL = process.env.LARAVEL_URL || 'http://laravel.test';

/**
 * Render using the real Vue EditorCanvas component.
 * This is the "single source of truth" endpoint - renders exactly what the editor shows.
 *
 * POST /render-vue
 * Body: { template: {...}, width, height, scale }
 */
app.post('/render-vue', async (req, res) => {
    try {
        const {
            template,
            width = 800,
            height = 800,
            scale = 2,
        } = req.body;

        if (!template) {
            return res.status(400).json({
                error: 'Validation failed',
                message: 'The "template" field is required',
            });
        }

        // Store template data in Laravel cache and get a key
        // This avoids URL length limits when template contains large base64 images
        let renderKey;
        try {
            const storeResponse = await fetch(`${LARAVEL_URL}/api/v1/render-data`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ template }),
            });

            if (!storeResponse.ok) {
                throw new Error(`Failed to store render data: ${storeResponse.status}`);
            }

            const storeResult = await storeResponse.json();
            renderKey = storeResult.key;
            console.log('Stored render data with key:', renderKey);
        } catch (storeError) {
            console.error('Failed to store render data:', storeError);
            throw new Error(`Could not store render data: ${storeError.message}`);
        }

        // Open the Vue render-preview page
        const browser = await puppeteer.launch({
            headless: 'new',
            executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || '/usr/bin/chromium',
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--disable-gpu',
                '--window-size=1920,1080',
            ],
        });

        try {
            const page = await browser.newPage();

            // Set viewport
            await page.setViewport({
                width: Math.ceil(width),
                height: Math.ceil(height),
                deviceScaleFactor: scale,
            });

            // Extract fonts from template layers
            const fonts = extractFontsFromTemplate(template);
            if (fonts.length > 0) {
                console.log('Template uses fonts:', fonts);

                // Inject Google Fonts CSS BEFORE page loads via evaluateOnNewDocument
                const fontFamilies = fonts.map(f => `family=${encodeURIComponent(f)}:wght@400;600;700`).join('&');
                const fontUrl = `https://fonts.googleapis.com/css2?${fontFamilies}&display=swap`;

                await page.evaluateOnNewDocument((url) => {
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = url;
                    document.head.appendChild(link);
                }, fontUrl);
            }

            // Navigate to the render page
            const url = `${LARAVEL_URL}/render-preview?key=${encodeURIComponent(renderKey)}`;
            console.log('Opening URL:', url);

            await page.goto(url, {
                waitUntil: 'networkidle0',
                timeout: 30000,
            });

            // After page loads, explicitly force fonts to be loaded and wait
            if (fonts.length > 0) {
                console.log('Waiting for fonts to be ready...');
                const fontsReady = await page.evaluate(async (fontList) => {
                    // Force load all font weights using document.fonts.load()
                    const loadPromises = [];
                    for (const font of fontList) {
                        loadPromises.push(document.fonts.load(`400 48px "${font}"`).catch(() => null));
                        loadPromises.push(document.fonts.load(`600 48px "${font}"`).catch(() => null));
                        loadPromises.push(document.fonts.load(`700 48px "${font}"`).catch(() => null));
                    }
                    await Promise.all(loadPromises);
                    await document.fonts.ready;

                    // Return status
                    return fontList.map(font => ({
                        font,
                        loaded: document.fonts.check(`700 48px "${font}"`)
                    }));
                }, fonts);
                console.log('Fonts status:', JSON.stringify(fontsReady));
            }

            // Wait for Vue to signal render complete
            await page.waitForFunction(
                () => window.__RENDER_COMPLETE__ === true,
                { timeout: 30000 }
            );

            // Additional wait for Konva canvas to fully render
            await new Promise(resolve => setTimeout(resolve, 1000));

            // Take screenshot of the container
            const container = await page.$('#render-preview-container');
            if (!container) {
                throw new Error('Could not find render container');
            }

            const imageBuffer = await container.screenshot({
                type: 'png',
                omitBackground: false,
            });

            res.set('Content-Type', 'image/png');
            res.set('Content-Disposition', 'inline; filename="render-vue.png"');
            res.send(imageBuffer);

        } finally {
            await browser.close();
        }

    } catch (error) {
        console.error('Vue render error:', error);
        res.status(500).json({
            error: 'Vue render failed',
            message: error.message,
        });
    }
});

// Main render endpoint
app.post('/render', async (req, res) => {
    try {
        const {
            template,
            width = 800,
            height = 800,
            scale = 2,
            outputWidth = null,
            outputHeight = null,
        } = req.body;

        // Validate required fields
        if (!template) {
            return res.status(400).json({
                error: 'Validation failed',
                message: 'The "template" field is required',
            });
        }

        if (!template.layers || !Array.isArray(template.layers)) {
            return res.status(400).json({
                error: 'Validation failed',
                message: 'Template must have a "layers" array',
            });
        }

        // Validate dimensions
        if (width < 10 || width > 4000 || height < 10 || height > 4000) {
            return res.status(400).json({
                error: 'Validation failed',
                message: 'Width and height must be between 10 and 4000',
            });
        }

        // Render the template
        let imageBuffer = await renderTemplate(template, width, height, scale);

        // Resize if output dimensions are specified
        if (outputWidth || outputHeight) {
            const image = sharp(imageBuffer);
            imageBuffer = await image
                .resize(outputWidth || null, outputHeight || null, {
                    fit: 'contain',
                    kernel: 'lanczos3',
                })
                .png()
                .toBuffer();
        }

        // Return image as PNG
        res.set('Content-Type', 'image/png');
        res.set('Content-Disposition', 'inline; filename="template.png"');
        res.send(imageBuffer);

    } catch (error) {
        console.error('Render error:', error);
        res.status(500).json({
            error: 'Render failed',
            message: error.message,
        });
    }
});

// Batch render endpoint - renders multiple templates
app.post('/render-batch', async (req, res) => {
    try {
        const { templates, width = 800, height = 800, scale = 2 } = req.body;

        if (!templates || !Array.isArray(templates)) {
            return res.status(400).json({
                error: 'Validation failed',
                message: 'The "templates" field must be an array',
            });
        }

        const results = [];

        for (const templateItem of templates) {
            try {
                const imageBuffer = await renderTemplate(
                    templateItem.template,
                    templateItem.width || width,
                    templateItem.height || height,
                    scale
                );
                results.push({
                    id: templateItem.id,
                    success: true,
                    image: imageBuffer.toString('base64'),
                });
            } catch (error) {
                results.push({
                    id: templateItem.id,
                    success: false,
                    error: error.message,
                });
            }
        }

        res.json({ results });

    } catch (error) {
        console.error('Batch render error:', error);
        res.status(500).json({
            error: 'Batch render failed',
            message: error.message,
        });
    }
});

// Start server
app.listen(PORT, '0.0.0.0', () => {
    console.log(`template-renderer service running on port ${PORT}`);
});
