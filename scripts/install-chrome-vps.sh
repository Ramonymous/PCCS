#!/bin/bash

###############################################################################
# Chrome/Chromium Installation Script for Ubuntu/Debian VPS
# 
# This script installs Chrome/Chromium browser required for PDF generation
# Run this on your VPS server as root or with sudo
###############################################################################

set -e

echo "=========================================="
echo "Chrome/Chromium Installation for PCCSv2"
echo "=========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  Please run as root or with sudo"
    echo "Usage: sudo bash scripts/install-chrome-vps.sh"
    exit 1
fi

# Detect OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VERSION=$VERSION_ID
else
    echo "❌ Cannot detect OS"
    exit 1
fi

echo "🔍 Detected OS: $OS $VERSION"
echo ""

# Function to install Chromium (lighter, easier)
install_chromium() {
    echo "📦 Installing Chromium browser..."
    
    if [ "$OS" = "ubuntu" ] || [ "$OS" = "debian" ]; then
        apt-get update
        apt-get install -y chromium-browser || apt-get install -y chromium
        
        # Install dependencies
        apt-get install -y \
            fonts-liberation \
            libappindicator3-1 \
            libasound2 \
            libatk-bridge2.0-0 \
            libatk1.0-0 \
            libcups2 \
            libdbus-1-3 \
            libgdk-pixbuf2.0-0 \
            libnspr4 \
            libnss3 \
            libx11-xcb1 \
            libxcomposite1 \
            libxdamage1 \
            libxrandr2 \
            xdg-utils \
            libgbm1 \
            libxshmfence1
    else
        echo "❌ Unsupported OS for automatic installation"
        exit 1
    fi
    
    echo "✅ Chromium installed successfully"
}

# Function to install Chrome (full version)
install_google_chrome() {
    echo "📦 Installing Google Chrome..."
    
    if [ "$OS" = "ubuntu" ] || [ "$OS" = "debian" ]; then
        # Download Chrome
        wget -q -O /tmp/google-chrome.deb https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb
        
        # Install Chrome and dependencies
        apt-get install -y /tmp/google-chrome.deb || true
        apt-get install -f -y
        
        # Cleanup
        rm -f /tmp/google-chrome.deb
        
        echo "✅ Google Chrome installed successfully"
    else
        echo "❌ Unsupported OS for automatic installation"
        exit 1
    fi
}

# Function to install Puppeteer Chrome
install_puppeteer_chrome() {
    echo "📦 Installing Chrome via Puppeteer..."
    
    # Check if npm is installed
    if ! command -v npm &> /dev/null; then
        echo "⚠️  npm not found. Installing Node.js..."
        curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -
        apt-get install -y nodejs
    fi
    
    cd /var/www/sims || cd /var/www/html || exit 1
    
    # Set Puppeteer cache directory
    export PUPPETEER_CACHE_DIR="/var/www/.cache/puppeteer"
    mkdir -p "$PUPPETEER_CACHE_DIR"
    
    # Install Puppeteer
    npm install puppeteer --no-save
    
    # Set permissions
    chown -R www-data:www-data "$PUPPETEER_CACHE_DIR"
    chmod -R 755 "$PUPPETEER_CACHE_DIR"
    
    echo "✅ Puppeteer Chrome installed successfully"
    echo "📁 Chrome location: $PUPPETEER_CACHE_DIR"
}

# Menu
echo "Choose installation method:"
echo "1) Chromium (Recommended - lighter, easier)"
echo "2) Google Chrome (Full version)"
echo "3) Puppeteer Chrome (Node.js package)"
echo ""
read -p "Enter choice [1-3]: " choice

case $choice in
    1)
        install_chromium
        ;;
    2)
        install_google_chrome
        ;;
    3)
        install_puppeteer_chrome
        ;;
    *)
        echo "❌ Invalid choice"
        exit 1
        ;;
esac

echo ""
echo "=========================================="
echo "🎉 Installation Complete!"
echo "=========================================="
echo ""

# Detect Chrome path
CHROME_PATH=""
if [ -f /usr/bin/chromium-browser ]; then
    CHROME_PATH="/usr/bin/chromium-browser"
elif [ -f /usr/bin/chromium ]; then
    CHROME_PATH="/usr/bin/chromium"
elif [ -f /usr/bin/google-chrome ]; then
    CHROME_PATH="/usr/bin/google-chrome"
elif [ -f /usr/bin/google-chrome-stable ]; then
    CHROME_PATH="/usr/bin/google-chrome-stable"
fi

if [ -n "$CHROME_PATH" ]; then
    echo "✅ Chrome found at: $CHROME_PATH"
    echo ""
    echo "Add to your .env file:"
    echo "BROWSERSHOT_CHROME_PATH=$CHROME_PATH"
else
    echo "⚠️  Chrome path not auto-detected"
    echo "Check logs after running the print job"
fi

echo ""
echo "Next steps:"
echo "1. Restart queue worker: php artisan queue:restart"
echo "2. Test print functionality in PCCSv2"
echo ""
