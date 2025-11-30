#!/bin/bash

# fpp_uninstall.sh
# This script runs when the plugin is uninstalled.

echo "Uninstalling NeoPixel Trinkey Status Plugin..."

EVENTS_DIR="/home/fpp/media/events"

# Remove event hooks we created
echo "Removing event hooks..."
rm -f "$EVENTS_DIR"/playlist-*.sh
rm -f "$EVENTS_DIR"/media-*.sh
rm -f "$EVENTS_DIR"/fppd-*.sh
rm -f "$EVENTS_DIR"/playlist_*.sh
rm -f "$EVENTS_DIR"/media_*.sh
rm -f "$EVENTS_DIR"/fppd_*.sh

echo "Uninstall Complete."

