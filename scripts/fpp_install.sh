#!/bin/bash

# fpp_install.sh
# This script runs when the plugin is installed.

echo "Installing NeoPixel Trinkey Status Plugin..."

PLUGIN_DIR="/home/fpp/media/plugins/neopixel_fpp_status"
CALLBACKS_SCRIPT="$PLUGIN_DIR/callbacks.sh"
LOG_FILE="/home/fpp/media/logs/neopixel_status.log"

# Ensure the callbacks script is executable
chmod +x "$CALLBACKS_SCRIPT"

# Create log file if it doesn't exist
touch "$LOG_FILE"
chmod 666 "$LOG_FILE"

# Create config directory if it doesn't exist
mkdir -p /home/fpp/media/config
chmod 755 /home/fpp/media/config

# Test that callbacks.sh can be executed
if [ -x "$CALLBACKS_SCRIPT" ]; then
    echo "✓ Callbacks script is executable"
    # Test the --list functionality
    if "$CALLBACKS_SCRIPT" --list > /dev/null 2>&1; then
        echo "✓ Callbacks script responds to --list query"
    else
        echo "⚠ Warning: Callbacks script may have issues"
    fi
else
    echo "✗ Error: Callbacks script is not executable"
fi

echo ""
echo "Installation Complete."
echo ""
echo "Note: FPP should automatically detect and use callbacks.sh"
echo "      If events are not being received, check:"
echo "      1. Plugin is enabled in FPP web interface"
echo "      2. Check log file: $LOG_FILE"
echo "      3. Test manually: $CALLBACKS_SCRIPT fppd start"
