#!/bin/bash

# FPP NeoPixel Trinkey Status Poller
# This script polls FPP's status and updates the Trinkey accordingly
# Uses multiple methods to reliably get FPP status

PLUGIN_DIR="/home/fpp/media/plugins/neopixel_fpp_status"
CALLBACKS_SCRIPT="$PLUGIN_DIR/callbacks.sh"
LOG_FILE="/home/fpp/media/logs/neopixel_status.log"
POLL_INTERVAL=1  # Poll every 1 second for responsiveness

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - [POLLER] $1" >> "$LOG_FILE"
}

# Method 1: Use FPP command line tool (most reliable)
get_status_via_fpp_command() {
    # FPP has a command-line tool that reports status
    if command -v fpp >/dev/null 2>&1; then
        # Try to get status via fpp command
        FPP_STATUS=$(fpp -s 2>/dev/null || fpp --status 2>/dev/null)
        if [ -n "$FPP_STATUS" ]; then
            echo "$FPP_STATUS"
            return 0
        fi
    fi
    return 1
}

# Method 2: Use FPP REST API
get_status_via_api() {
    # Try FPP's REST API endpoint
    # FPP web server typically runs on port 80 or 32320
    for port in 80 32320; do
        for host in localhost 127.0.0.1; do
            STATUS=$(curl -s "http://${host}:${port}/api/fppd/status" 2>/dev/null)
            if [ -n "$STATUS" ] && [ "$STATUS" != "null" ] && [ "$STATUS" != "{}" ]; then
                echo "$STATUS"
                return 0
            fi
        done
    done
    return 1
}

# Method 3: Check status file
get_status_via_file() {
    # FPP may write status to a file
    STATUS_FILE="/home/fpp/media/status"
    if [ -f "$STATUS_FILE" ]; then
        cat "$STATUS_FILE"
        return 0
    fi
    return 1
}

# Method 4: Check if fppd process is running and get status
get_status_via_process() {
    # Check if fppd is running
    if ! pgrep -f "fppd" >/dev/null 2>&1; then
        echo "stopped"
        return 0
    fi
    
    # Try to get status from fppd directly
    # FPP may have a status socket or pipe
    if [ -S "/tmp/fppd-status" ] || [ -p "/tmp/fppd-status" ]; then
        echo "idle"  # If socket exists, assume idle (can't easily query)
        return 0
    fi
    
    return 1
}

# Main function to get FPP status
get_fpp_status() {
    # Try methods in order of reliability
    STATUS=$(get_status_via_fpp_command)
    if [ $? -eq 0 ] && [ -n "$STATUS" ]; then
        echo "COMMAND:$STATUS"
        return 0
    fi
    
    STATUS=$(get_status_via_api)
    if [ $? -eq 0 ] && [ -n "$STATUS" ]; then
        echo "API:$STATUS"
        return 0
    fi
    
    STATUS=$(get_status_via_file)
    if [ $? -eq 0 ] && [ -n "$STATUS" ]; then
        echo "FILE:$STATUS"
        return 0
    fi
    
    STATUS=$(get_status_via_process)
    if [ $? -eq 0 ] && [ -n "$STATUS" ]; then
        echo "PROCESS:$STATUS"
        return 0
    fi
    
    return 1
}

# Function to determine status character from various formats
determine_status() {
    local status_data="$1"
    local method="${status_data%%:*}"
    local data="${status_data#*:}"
    
    # Method 1: FPP Command output
    if [ "$method" = "COMMAND" ]; then
        if echo "$data" | grep -qi "playing\|play\|running"; then
            echo "P"
        elif echo "$data" | grep -qi "idle\|stopped\|stop"; then
            echo "I"
        else
            echo "I"  # Default
        fi
        return
    fi
    
    # Method 2: API JSON response
    if [ "$method" = "API" ]; then
        # Parse JSON - look for status_name, status, or playing indicators
        if echo "$data" | grep -qi '"status_name"[[:space:]]*:[[:space:]]*"playing"'; then
            echo "P"
        elif echo "$data" | grep -qi '"status"[[:space:]]*:[[:space:]]*[^0]' && echo "$data" | grep -qi '"playlist"'; then
            echo "P"
        elif echo "$data" | grep -qi '"current_playlist"[[:space:]]*:[[:space:]]*"[^"]*"'; then
            echo "P"
        elif echo "$data" | grep -qi '"status_name"[[:space:]]*:[[:space:]]*"idle"'; then
            echo "I"
        else
            echo "I"
        fi
        return
    fi
    
    # Method 3: Status file
    if [ "$method" = "FILE" ]; then
        if echo "$data" | grep -qi "playing\|play"; then
            echo "P"
        else
            echo "I"
        fi
        return
    fi
    
    # Method 4: Process check
    if [ "$method" = "PROCESS" ]; then
        if [ "$data" = "stopped" ]; then
            echo "S"
        else
            echo "I"
        fi
        return
    fi
    
    # Default
    echo "I"
}

# Main polling loop
log_message "Status poller started (polling every ${POLL_INTERVAL} seconds)"
LAST_STATUS=""
FAIL_COUNT=0
MAX_FAILS=5

while true; do
    # Get current FPP status
    FPP_STATUS=$(get_fpp_status)
    
    if [ -n "$FPP_STATUS" ]; then
        FAIL_COUNT=0
        CURRENT_STATUS=$(determine_status "$FPP_STATUS")
        
        # Only update if status changed
        if [ "$CURRENT_STATUS" != "$LAST_STATUS" ]; then
            log_message "Status changed: $LAST_STATUS -> $CURRENT_STATUS (via: ${FPP_STATUS%%:*})"
            if "$CALLBACKS_SCRIPT" test "$CURRENT_STATUS"; then
                LAST_STATUS="$CURRENT_STATUS"
            else
                log_message "Failed to send status to Trinkey"
            fi
        fi
    else
        # If we can't get status, increment fail counter
        FAIL_COUNT=$((FAIL_COUNT + 1))
        
        if [ $FAIL_COUNT -ge $MAX_FAILS ]; then
            # After multiple failures, assume FPP is down
            if [ "$LAST_STATUS" != "S" ]; then
                log_message "Cannot get FPP status after $MAX_FAILS attempts, marking as stopped"
                "$CALLBACKS_SCRIPT" test "S"
                LAST_STATUS="S"
            fi
            FAIL_COUNT=0  # Reset counter
        else
            log_message "Warning: Could not get FPP status (attempt $FAIL_COUNT/$MAX_FAILS)"
        fi
    fi
    
    sleep "$POLL_INTERVAL"
done

