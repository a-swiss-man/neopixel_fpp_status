#!/bin/bash

# fpp_install.sh
# This script runs when the plugin is installed.

echo "Installing NeoPixel Trinkey Status Plugin..."

PLUGIN_DIR="/home/fpp/media/plugins/neopixel_fpp_status"
CALLBACKS_SCRIPT="$PLUGIN_DIR/callbacks.sh"
LOG_FILE="/home/fpp/media/logs/neopixel_status.log"
EVENTS_DIR="/home/fpp/media/events"

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

# Create event hooks as a backup method (FPP's automatic detection isn't always reliable)
# FPP will call scripts in /home/fpp/media/events/ when events occur
echo ""
echo "Creating event hooks for explicit callback registration..."

# Create events directory if it doesn't exist
mkdir -p "$EVENTS_DIR"
chmod 755 "$EVENTS_DIR"

# Create event hook scripts that call our callbacks.sh
# These will be called by FPP when events occur
# FPP may call these with different argument formats, so we handle multiple cases
create_event_hook() {
    local event_name=$1
    local event_type=$2  # playlist, media, or fppd
    local action=$3     # start or stop
    local hook_file="$EVENTS_DIR/${event_name}.sh"
    
    cat > "$hook_file" << EOF
#!/bin/bash
# Auto-generated event hook for NeoPixel Trinkey Status Plugin
# This file calls the plugin's callbacks.sh when FPP events occur
# Event: $event_name (Type: $event_type, Action: $action)

# FPP may call this script with different argument formats
# We translate them to the format our callbacks.sh expects: TYPE ACTION

# If called with no args, use the event name to determine type and action
if [ -z "\$1" ]; then
    $CALLBACKS_SCRIPT "$event_type" "$action"
# If called with args, pass them through
else
    $CALLBACKS_SCRIPT "\$@"
fi
EOF
    chmod +x "$hook_file"
    echo "  Created: $hook_file"
}

# Create hooks for the events we handle
# Note: FPP event naming may vary, so we create hooks for common patterns
create_event_hook "playlist-start" "playlist" "start"
create_event_hook "playlist-stop" "playlist" "stop"
create_event_hook "media-start" "media" "start"
create_event_hook "media-stop" "media" "stop"
create_event_hook "fppd-start" "fppd" "start"
create_event_hook "fppd-stop" "fppd" "stop"

# Also create hooks with underscore format (some FPP versions use this)
create_event_hook "playlist_start" "playlist" "start"
create_event_hook "playlist_stop" "playlist" "stop"
create_event_hook "media_start" "media" "start"
create_event_hook "media_stop" "media" "stop"
create_event_hook "fppd_start" "fppd" "start"
create_event_hook "fppd_stop" "fppd" "stop"

echo ""
echo "Installation Complete."
echo ""
echo "Callback Registration Methods:"
echo "  1. Automatic: FPP should detect callbacks.sh (may require FPP restart)"
echo "  2. Event Hooks: Created event scripts in $EVENTS_DIR"
echo ""
echo "If events are not being received:"
echo "  1. Restart FPPD: sudo systemctl restart fppd"
echo "  2. Check log file: $LOG_FILE"
echo "  3. Test manually: $CALLBACKS_SCRIPT fppd start"
echo "  4. Check event hooks: ls -la $EVENTS_DIR/*.sh"
