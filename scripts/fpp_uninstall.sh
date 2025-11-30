#!/bin/bash

# fpp_uninstall.sh
# This script runs when the plugin is uninstalled.

echo "Uninstalling NeoPixel Trinkey Status Plugin..."

EVENTS_DIR="/home/fpp/media/events"

# Stop and remove systemd service
echo "Stopping and removing systemd service..."
if sudo systemctl stop neopixel-status-poller.service 2>/dev/null; then
    echo "✓ Service stopped"
fi

if sudo systemctl disable neopixel-status-poller.service 2>/dev/null; then
    echo "✓ Service disabled"
fi

if sudo rm -f /etc/systemd/system/neopixel-status-poller.service 2>/dev/null; then
    echo "✓ Service file removed"
    if sudo systemctl daemon-reload 2>/dev/null; then
        echo "✓ Systemd daemon reloaded"
    fi
else
    echo "⚠ Warning: Could not remove service file (may need sudo)"
fi

# Remove event hooks we created
echo "Removing event hooks..."
rm -f "$EVENTS_DIR"/playlist-*.sh
rm -f "$EVENTS_DIR"/media-*.sh
rm -f "$EVENTS_DIR"/fppd-*.sh
rm -f "$EVENTS_DIR"/playlist_*.sh
rm -f "$EVENTS_DIR"/media_*.sh
rm -f "$EVENTS_DIR"/fppd_*.sh

echo "Uninstall Complete."

