#!/bin/bash

# pull-free-db.sh - Download free GeoLite2 Country database for testing
# This script downloads the GeoLite2-Country.mmdb file from the GitHub mirror

set -e  # Exit on any error

# Define variables
DOWNLOAD_URL="https://github.com/P3TERX/GeoLite.mmdb/raw/download/GeoLite2-Country.mmdb"
TARGET_DIR="tests/fixtures"
TARGET_FILE="$TARGET_DIR/GeoLite2-Country.mmdb"

echo "🌍 Downloading free GeoLite2-Country database..."

# Create target directory if it doesn't exist
mkdir -p "$TARGET_DIR"

# Download the database file
if command -v wget >/dev/null 2>&1; then
    echo "Using wget to download..."
    wget -O "$TARGET_FILE" "$DOWNLOAD_URL"
elif command -v curl >/dev/null 2>&1; then
    echo "Using curl to download..."
    curl -L -o "$TARGET_FILE" "$DOWNLOAD_URL"
else
    echo "❌ Error: Neither wget nor curl is available. Please install one of them."
    exit 1
fi

# Verify the file was downloaded
if [ -f "$TARGET_FILE" ]; then
    FILE_SIZE=$(stat -c%s "$TARGET_FILE" 2>/dev/null || stat -f%z "$TARGET_FILE" 2>/dev/null || echo "unknown")
    echo "✅ Database downloaded successfully!"
    echo "   File: $TARGET_FILE"
    echo "   Size: $FILE_SIZE bytes"
else
    echo "❌ Error: Database download failed!"
    exit 1
fi

echo "🎉 GeoLite2 database ready for testing!"