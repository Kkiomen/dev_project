FROM node:20-alpine

# Install build dependencies for native modules
RUN apk add --no-cache \
    python3 \
    make \
    g++ \
    vips-dev \
    fftw-dev

WORKDIR /app

# Copy package files
COPY package.json ./

# Install dependencies
RUN npm install --omit=dev

# Copy application code
COPY . .

# Expose port
EXPOSE 3334

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD node -e "require('http').get('http://localhost:3334/health', (r) => process.exit(r.statusCode === 200 ? 0 : 1))"

# Start the service
CMD ["node", "server.js"]
