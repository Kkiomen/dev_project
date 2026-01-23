import express from 'express';
import cors from 'cors';
import puppeteer from 'puppeteer';
import sharp from 'sharp';

const app = express();
const PORT = process.env.PORT || 3333;

// Middleware
app.use(cors());
app.use(express.json({ limit: '1mb' }));

// Valid options
const VALID_THEMES = ['breeze', 'candy', 'crimson', 'falcon', 'meadow', 'midnight', 'raindrop', 'sunset'];
const VALID_PADDINGS = [16, 32, 64, 128];

// Convert string to base64
function stringToBase64(str) {
    return Buffer.from(str).toString('base64');
}

// Build ray.so URL
function buildRaysoUrl(code, options) {
    const params = new URLSearchParams({
        title: options.title || 'Untitled-1',
        theme: options.theme || 'breeze',
        padding: options.padding || 32,
        background: options.background !== false ? 'true' : 'false',
        darkMode: options.darkMode !== false ? 'true' : 'false',
        code: stringToBase64(code),
    });

    if (options.language && options.language !== 'auto') {
        params.set('language', options.language);
    }

    return `https://ray.so/#${params.toString()}`;
}

// Generate image from code
async function generateImage(code, options = {}) {
    // Use higher device scale factor for better quality when scaling up
    // Scale factor of 3 gives us ~1560px base width which scales well to 4000px
    const deviceScaleFactor = options.width && options.width > 600 ? 3 : 2;

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
        await page.setViewport({
            width: 1920,
            height: 1080,
            deviceScaleFactor: deviceScaleFactor
        });

        const url = buildRaysoUrl(code, options);
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });

        // Wait for the frame to load
        await page.waitForSelector('[class*="Frame_frame__"]', { timeout: 10000 });

        // Wait for rendering
        await new Promise(resolve => setTimeout(resolve, 1000));

        // Hide UI elements
        await page.evaluate(() => {
            // Hide resize drag points
            document.querySelectorAll('[class*="ResizableFrame_windowSizeDragPoint"]').forEach(el => {
                el.style.display = 'none';
            });
            document.querySelectorAll('[class*="ResizableFrame_left"], [class*="ResizableFrame_right"]').forEach(el => {
                el.style.display = 'none';
            });
        });

        // Find the frame element
        const frameElement = await page.$('[class*="Frame_frame__"]');

        if (!frameElement) {
            throw new Error('Could not find code frame element');
        }

        // Take screenshot at natural size
        let imageBuffer = await frameElement.screenshot({
            type: 'png',
            omitBackground: options.background === false,
        });

        // Resize if width is specified
        if (options.width) {
            const image = sharp(imageBuffer);
            const metadata = await image.metadata();

            // Calculate new height maintaining aspect ratio
            const aspectRatio = metadata.height / metadata.width;
            const newHeight = Math.round(options.width * aspectRatio);

            imageBuffer = await image
                .resize(options.width, newHeight, {
                    fit: 'fill',
                    kernel: 'lanczos3'
                })
                .png()
                .toBuffer();
        }

        return imageBuffer;
    } finally {
        await browser.close();
    }
}

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({ status: 'ok', service: 'rayso-api' });
});

// Main code-to-image endpoint
app.post('/generate', async (req, res) => {
    try {
        const {
            code,
            title = 'Untitled-1',
            theme = 'breeze',
            background = true,
            darkMode = true,
            padding = 32,
            language = 'auto',
            width = null,
            height = null
        } = req.body;

        // Validate required field
        if (!code || typeof code !== 'string') {
            return res.status(400).json({
                error: 'Validation failed',
                message: 'The "code" field is required and must be a string'
            });
        }

        // Validate theme
        if (!VALID_THEMES.includes(theme)) {
            return res.status(400).json({
                error: 'Validation failed',
                message: `Invalid theme. Must be one of: ${VALID_THEMES.join(', ')}`
            });
        }

        // Validate padding
        if (!VALID_PADDINGS.includes(padding)) {
            return res.status(400).json({
                error: 'Validation failed',
                message: `Invalid padding. Must be one of: ${VALID_PADDINGS.join(', ')}`
            });
        }

        // Validate width if provided
        if (width !== null && (typeof width !== 'number' || width < 100 || width > 4000)) {
            return res.status(400).json({
                error: 'Validation failed',
                message: 'Width must be a number between 100 and 4000'
            });
        }

        // Generate image
        let imageBuffer = await generateImage(code, {
            title,
            theme,
            background,
            darkMode,
            padding,
            language,
            width
        });

        // If height is specified (separate from width), resize to exact dimensions
        if (height !== null && typeof height === 'number' && height >= 100 && height <= 4000) {
            const image = sharp(imageBuffer);
            imageBuffer = await image
                .resize(width || null, height, {
                    fit: 'fill',
                    kernel: 'lanczos3'
                })
                .png()
                .toBuffer();
        }

        // Return image as PNG
        res.set('Content-Type', 'image/png');
        res.set('Content-Disposition', 'inline; filename="code.png"');
        res.send(imageBuffer);

    } catch (error) {
        console.error('Generation error:', error);
        res.status(500).json({
            error: 'Generation failed',
            message: error.message
        });
    }
});

// Start server
app.listen(PORT, '0.0.0.0', () => {
    console.log(`rayso-api server running on port ${PORT}`);
});
