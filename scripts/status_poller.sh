#!/bin/bash

# FPP NeoPixel Trinkey Status Poller
# This script polls FPP's status API and updates the Trinkey accordingly
# This is a backup method if callbacks aren't working reliably

PLUGIN_DIR="/home/fpp/media/plugins/neopixel_fpp_status"
CALLBACKS_SCRIPT="$PLUGIN_DIR/callbacks.sh"
LOG_FILE="/home/fpp/media/logs/neopixel_status.log"
POLL_INTERVAL=2  # Poll every 2 seconds

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - [POLLER] $1" >> "$LOG_FILE"
}

# Function to get FPP status
get_fpp_status() {
    # Try FPP's API endpoint for status
    # FPP typically runs on localhost:32320 or similar
    STATUS=$(curl -s http://localhost:32320/api/status 2>/dev/null)
    
    if [ -z "$STATUS" ]; then
        # Try alternative endpoint
        STATUS=$(curl -s http://127.0.0.1:32320/api/status 2>/dev/null)
    fi
    
    echo "$STATUS"
}

# Function to determine status from FPP API response
determine_status() {
    local status_json="$1"
    
    # FPP API returns JSON with status information
    # Check various possible status indicators
    
    # Method 1: Check status_name field (common in FPP API)
    if echo "$status_json" | grep -qi '"status_name"[[:space:]]*:[[:space:]]*"playing"'; then
        echo "P"  # Playing
        return
    fi
    
    if echo "$status_json" | grep -qi '"status_name"[[:space:]]*:[[:space:]]*"idle"'; then
        echo "I"  # Idle
        return
    fi
    
    if echo "$status_json" | grep -qi '"status_name"[[:space:]]*:[[:space:]]*"stopped"'; then
        echo "I"  # Stopped/Idle
        return
    fi
    
    # Method 2: Check if there's an active playlist
    if echo "$status_json" | grep -qi '"playlist"[[:space:]]*:[[:space:]]*"[^"]*"'; then
        # Check if playlist is actually playing (not just defined)
        if echo "$status_json" | grep -qi '"status"[[:space:]]*:[[:space:]]*[^0]'; then
            echo "P"  # Playing
            return
        fi
    fi
    
    # Method 3: Check sequence status
    if echo "$status_json" | grep -qi '"sequence"[[:space:]]*:[[:space:]]*"[^"]*"'; then
        echo "P"  # Playing sequence
        return
    fi
    
    # Method 4: Check mode field
    if echo "$status_json" | grep -qi '"mode"[[:space:]]*:[[:space:]]*"playlist"'; then
        echo "P"  # In playlist mode (likely playing)
        return
    fi
    
    # Default to idle if we can't determine
    echo "I"
}

# Main polling loop
log_message "Status poller started (polling every ${POLL_INTERVAL} seconds)"
LAST_STATUS=""

while true; do
    # Get current FPP status
    FPP_STATUS=$(get_fpp_status)
    
    if [ -n "$FPP_STATUS" ]; then
        CURRENT_STATUS=$(determine_status "$FPP_STATUS")
        
        # Only update if status changed
        if [ "$CURRENT_STATUS" != "$LAST_STATUS" ]; then
            log_message "Status changed: $LAST_STATUS -> $CURRENT_STATUS"
            "$CALLBACKS_SCRIPT" test "$CURRENT_STATUS"
            LAST_STATUS="$CURRENT_STATUS"
        fi
    else
        # If we can't get status, FPP might be down
        if [ "$LAST_STATUS" != "S" ]; then
            log_message "Cannot reach FPP API, marking as stopped"
            "$CALLBACKS_SCRIPT" test "S"
            LAST_STATUS="S"
        fi
    fi
    
    sleep "$POLL_INTERVAL"
done

