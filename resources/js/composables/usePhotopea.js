import { ref, onMounted, onUnmounted } from 'vue';

/**
 * Composable for communicating with Photopea iframe.
 *
 * Photopea messaging API reference: https://www.photopea.com/api/
 */
export function usePhotopea(iframeRef) {
    const isReady = ref(false);
    const isLoading = ref(false);
    const error = ref(null);

    let messageQueue = [];
    let responseCallbacks = new Map();
    let callbackId = 0;

    /**
     * Handle messages from Photopea iframe.
     */
    const handleMessage = (event) => {
        // Only accept messages from Photopea
        if (event.origin !== 'https://www.photopea.com') return;

        const data = event.data;

        // Check if it's a callback response
        if (typeof data === 'string' && data.startsWith('__callback__')) {
            const [, id, result] = data.split('::');
            const callback = responseCallbacks.get(parseInt(id));
            if (callback) {
                responseCallbacks.delete(parseInt(id));
                try {
                    callback.resolve(JSON.parse(result));
                } catch {
                    callback.resolve(result);
                }
            }
            return;
        }

        // Photopea ready signal
        if (data === 'done') {
            isReady.value = true;
            isLoading.value = false;
            processMessageQueue();
        }
    };

    /**
     * Send a message to Photopea.
     */
    const sendMessage = (message) => {
        return new Promise((resolve, reject) => {
            if (!iframeRef.value?.contentWindow) {
                reject(new Error('Photopea iframe not available'));
                return;
            }

            if (!isReady.value) {
                // Queue message if Photopea is not ready yet
                messageQueue.push({ message, resolve, reject });
                return;
            }

            try {
                iframeRef.value.contentWindow.postMessage(message, '*');
                resolve();
            } catch (err) {
                reject(err);
            }
        });
    };

    /**
     * Execute a script in Photopea and get the result.
     */
    const executeScript = (script) => {
        return new Promise((resolve, reject) => {
            if (!iframeRef.value?.contentWindow) {
                reject(new Error('Photopea iframe not available'));
                return;
            }

            const id = ++callbackId;
            responseCallbacks.set(id, { resolve, reject });

            // Wrap script to send result back
            const wrappedScript = `
                (function() {
                    try {
                        var result = (function() { ${script} })();
                        window.parent.postMessage('__callback__::${id}::' + JSON.stringify(result), '*');
                    } catch (e) {
                        window.parent.postMessage('__callback__::${id}::' + JSON.stringify({ error: e.message }), '*');
                    }
                })();
            `;

            sendMessage(wrappedScript).catch(reject);

            // Timeout after 30 seconds
            setTimeout(() => {
                if (responseCallbacks.has(id)) {
                    responseCallbacks.delete(id);
                    reject(new Error('Script execution timeout'));
                }
            }, 30000);
        });
    };

    /**
     * Process queued messages after Photopea is ready.
     */
    const processMessageQueue = () => {
        while (messageQueue.length > 0) {
            const { message, resolve, reject } = messageQueue.shift();
            sendMessage(message).then(resolve).catch(reject);
        }
    };

    /**
     * Load a file into Photopea from URL.
     */
    const loadFileFromUrl = async (url) => {
        isLoading.value = true;
        error.value = null;

        try {
            await sendMessage(`app.open("${url}")`);
        } catch (err) {
            error.value = err.message;
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    /**
     * Load a file into Photopea from base64.
     */
    const loadFileFromBase64 = async (base64Data, filename = 'file.psd') => {
        isLoading.value = true;
        error.value = null;

        try {
            // Use Photopea's open command with base64 data
            const dataUrl = base64Data.startsWith('data:')
                ? base64Data
                : `data:application/octet-stream;base64,${base64Data}`;

            await sendMessage(`app.open("${dataUrl}", null, "${filename}")`);
        } catch (err) {
            error.value = err.message;
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    /**
     * Load a file into Photopea from ArrayBuffer.
     */
    const loadFile = async (arrayBuffer, filename = 'file.psd') => {
        const base64 = btoa(
            new Uint8Array(arrayBuffer)
                .reduce((data, byte) => data + String.fromCharCode(byte), '')
        );
        return loadFileFromBase64(base64, filename);
    };

    /**
     * Save the current document and get it as ArrayBuffer.
     */
    const saveDocument = async (format = 'psd') => {
        isLoading.value = true;
        error.value = null;

        try {
            // Get the document as base64
            const script = `
                var doc = app.activeDocument;
                if (!doc) return null;

                var options;
                var extension = '${format}';

                switch (extension) {
                    case 'psd':
                        options = new PhotoshopSaveOptions();
                        break;
                    case 'png':
                        options = new PNGSaveOptions();
                        break;
                    case 'jpg':
                    case 'jpeg':
                        options = new JPEGSaveOptions();
                        options.quality = 90;
                        break;
                    default:
                        options = new PhotoshopSaveOptions();
                }

                doc.saveToOE(extension);
            `;

            // Photopea will send the file through postMessage
            const result = await executeScript(script);
            return result;
        } catch (err) {
            error.value = err.message;
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    /**
     * Get the current document structure (layers).
     */
    const getLayers = async () => {
        const script = `
            function getLayerInfo(layer) {
                var info = {
                    name: layer.name,
                    kind: layer.kind ? layer.kind.toString() : 'unknown',
                    visible: layer.visible,
                    opacity: layer.opacity,
                    bounds: layer.bounds ? {
                        left: layer.bounds[0].value,
                        top: layer.bounds[1].value,
                        right: layer.bounds[2].value,
                        bottom: layer.bounds[3].value
                    } : null
                };

                if (layer.layers) {
                    info.children = [];
                    for (var i = 0; i < layer.layers.length; i++) {
                        info.children.push(getLayerInfo(layer.layers[i]));
                    }
                }

                return info;
            }

            var doc = app.activeDocument;
            if (!doc) return null;

            var layers = [];
            for (var i = 0; i < doc.layers.length; i++) {
                layers.push(getLayerInfo(doc.layers[i]));
            }

            return {
                name: doc.name,
                width: doc.width.value,
                height: doc.height.value,
                layers: layers
            };
        `;

        return executeScript(script);
    };

    /**
     * Get document info.
     */
    const getDocumentInfo = async () => {
        const script = `
            var doc = app.activeDocument;
            if (!doc) return null;

            return {
                name: doc.name,
                width: doc.width.value,
                height: doc.height.value,
                resolution: doc.resolution,
                colorMode: doc.mode.toString()
            };
        `;

        return executeScript(script);
    };

    /**
     * Create a new document.
     */
    const createDocument = async (width, height, name = 'Untitled') => {
        const script = `
            app.documents.add(${width}, ${height}, 72, "${name}");
            return true;
        `;

        return executeScript(script);
    };

    /**
     * Build Photopea URL with parameters.
     */
    const buildPhotopeaUrl = (options = {}) => {
        const params = new URLSearchParams();

        // Configure Photopea settings
        const config = {
            environment: {
                customIO: true, // Enable custom file I/O
            },
            ...options.config,
        };

        params.set('p', JSON.stringify(config));

        if (options.fileUrl) {
            // Pre-load a file
            return `https://www.photopea.com#${encodeURIComponent(options.fileUrl)}`;
        }

        return `https://www.photopea.com?${params.toString()}`;
    };

    // Setup message listener
    onMounted(() => {
        window.addEventListener('message', handleMessage);
    });

    onUnmounted(() => {
        window.removeEventListener('message', handleMessage);
        responseCallbacks.clear();
        messageQueue = [];
    });

    return {
        isReady,
        isLoading,
        error,
        sendMessage,
        executeScript,
        loadFile,
        loadFileFromUrl,
        loadFileFromBase64,
        saveDocument,
        getLayers,
        getDocumentInfo,
        createDocument,
        buildPhotopeaUrl,
    };
}
