#!/bin/bash

# fpp_uninstall.sh
# This script runs when the plugin is uninstalled.

echo "Uninstalling NeoPixel Trinkey Status Plugin..."

EVENTS_DIR="/home/fpp/media/events"

# Stop and remove systemd services
echo "Stopping and removing systemd services..."

# Status poller service
if sudo systemctl stop neopixel-status-poller.service 2>/dev/null; then
    echo "✓ Status poller service stopped"
fi

if sudo systemctl disable neopixel-status-poller.service 2>/dev/null; then
    echo "✓ Status poller service disabled"
fi

if sudo rm -f /etc/systemd/system/neopixel-status-poller.service 2>/dev/null; then
    echo "✓ Status poller service file removed"
else
    echo "⚠ Warning: Could not remove status poller service file (may need sudo)"
fi

# Touch listener service
if sudo systemctl stop neopixel-touch-listener.service 2>/dev/null; then
    echo "✓ Touch listener service stopped"
fi

if sudo systemctl disable neopixel-touch-listener.service 2>/dev/null; then
    echo "✓ Touch listener service disabled"
fi

if sudo rm -f /etc/systemd/system/neopixel-touch-listener.service 2>/dev/null; then
    echo "✓ Touch listener service file removed"
else
    echo "⚠ Warning: Could not remove touch listener service file (may need sudo)"
fi

if sudo systemctl daemon-reload 2>/dev/null; then
    echo "✓ Systemd daemon reloaded"
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

