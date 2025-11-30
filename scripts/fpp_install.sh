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

# Make status poller and test scripts executable (backup method if callbacks don't work)
POLLER_SCRIPT="$PLUGIN_DIR/scripts/status_poller.sh"
TEST_SCRIPT="$PLUGIN_DIR/scripts/test_status_methods.sh"
SERVICE_FILE="$PLUGIN_DIR/scripts/neopixel-status-poller.service"
SYSTEMD_DIR="/etc/systemd/system"

if [ -f "$POLLER_SCRIPT" ]; then
    chmod +x "$POLLER_SCRIPT"
    echo "✓ Status poller script is executable"
fi
if [ -f "$TEST_SCRIPT" ]; then
    chmod +x "$TEST_SCRIPT"
    echo "✓ Status test script is executable"
fi

# Set up systemd service for automatic startup
if [ -f "$SERVICE_FILE" ]; then
    echo ""
    echo "Setting up systemd service for automatic startup..."
    
    # Copy service file to systemd directory
    if sudo cp "$SERVICE_FILE" "$SYSTEMD_DIR/neopixel-status-poller.service" 2>/dev/null; then
        echo "✓ Service file installed to $SYSTEMD_DIR"
        
        # Reload systemd
        if sudo systemctl daemon-reload 2>/dev/null; then
            echo "✓ Systemd daemon reloaded"
            
            # Enable service to start on boot
            if sudo systemctl enable neopixel-status-poller.service 2>/dev/null; then
                echo "✓ Service enabled for automatic startup"
            else
                echo "⚠ Warning: Could not enable service (may need sudo)"
            fi
            
            # Start the service now
            if sudo systemctl start neopixel-status-poller.service 2>/dev/null; then
                echo "✓ Service started"
            else
                echo "⚠ Warning: Could not start service (may need sudo)"
                echo "  You can start it manually with: sudo systemctl start neopixel-status-poller"
            fi
        else
            echo "⚠ Warning: Could not reload systemd (may need sudo)"
        fi
    else
        echo "⚠ Warning: Could not install service file (may need sudo)"
        echo "  You can install it manually:"
        echo "    sudo cp $SERVICE_FILE $SYSTEMD_DIR/"
        echo "    sudo systemctl daemon-reload"
        echo "    sudo systemctl enable neopixel-status-poller"
        echo "    sudo systemctl start neopixel-status-poller"
    fi
else
    echo "⚠ Warning: Service file not found: $SERVICE_FILE"
fi

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

# Verify plugin structure matches FPP expectations (like fpp-FPPMon)
echo ""
echo "Verifying plugin structure..."
if [ -f "$PLUGIN_DIR/pluginInfo.json" ] && [ -f "$CALLBACKS_SCRIPT" ]; then
    echo "✓ Plugin structure matches FPP expectations (similar to fpp-FPPMon)"
    echo "  - pluginInfo.json: Found"
    echo "  - callbacks.sh: Found and executable"
else
    echo "⚠ Warning: Plugin structure may be incomplete"
fi

echo ""
echo "Installation Complete."
echo ""
echo "How FPP Discovers callbacks.sh (based on fpp-FPPMon pattern):"
echo "  1. FPP scans /home/fpp/media/plugins/*/callbacks.sh"
echo "  2. FPP queries each callbacks.sh with: callbacks.sh --list"
echo "  3. FPP calls callbacks.sh with: callbacks.sh <TYPE> <ACTION>"
echo ""
echo "Callback Registration Methods:"
echo "  1. Automatic: FPP should detect callbacks.sh (REQUIRES FPP RESTART)"
echo "  2. Event Hooks: Created event scripts in $EVENTS_DIR (backup method)"
echo ""
echo "Status Monitoring Methods:"
echo "  1. Callbacks (Primary): FPP calls callbacks.sh on events"
echo "  2. Status Poller (Backup): Polls FPP API every 2 seconds"
echo "     - To enable: $POLLER_SCRIPT &"
echo "     - Or add to systemd/cron for auto-start"
echo ""
echo "IMPORTANT - After installation:"
echo "  1. RESTART FPPD: sudo systemctl restart fppd"
echo "  2. Verify plugin is enabled in FPP web interface (Settings > Plugins)"
echo "  3. Test manually: $CALLBACKS_SCRIPT fppd start"
echo "  4. Check log file: $LOG_FILE"
echo ""
echo "If callbacks don't work, use the status poller:"
echo "  $POLLER_SCRIPT &"
echo ""
echo "To verify FPP is calling callbacks.sh:"
echo "  - Look for 'callbacks.sh executed with args' in the log"
echo "  - If you don't see this, FPP may not be detecting the plugin"
echo "  - Use the status poller as a reliable backup method"
