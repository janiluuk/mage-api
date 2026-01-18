FROM python:3.11-slim

# Install system dependencies
RUN apt-get update && apt-get install -y \
    ffmpeg \
    git \
    wget \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Install audio-separator package (UVR5 CLI)
RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir audio-separator

# Create directory for models (will be mounted as volume)
RUN mkdir -p /app/models

# Create entrypoint script to download models at runtime if needed
RUN echo '#!/bin/bash\n\
# Download default UVR5 models at runtime if not present\n\
if [ ! -d "/app/models" ] || [ -z "$(ls -A /app/models)" ]; then\n\
    echo "Models directory is empty. Downloading default models..."\n\
    # audio-separator will download models automatically on first use\n\
    # Models will be cached in /app/models\n\
    echo "Models will be downloaded automatically on first use"\n\
fi\n\
\n\
# Execute the provided command\n\
exec "$@"' > /entrypoint.sh && \
    chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["audio-separator", "--help"]

