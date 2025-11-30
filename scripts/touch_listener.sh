#!/bin/bash

# FPP NeoPixel Trinkey Touch Input Listener
# This script reads touch events from the Trinkey and triggers FPP actions

PLUGIN_DIR="/home/fpp/media/plugins/neopixel_fpp_status"
CONFIG_FILE="/home/fpp/media/config/neopixel_status.conf"
LOG_FILE="/home/fpp/media/logs/neopixel_status.log"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - [TOUCH] $1" >> "$LOG_FILE"
}

# Function to get configured device from config file
get_configured_device() {
    if [ -f "$CONFIG_FILE" ]; then
        DEVICE=$(grep "^device[[:space:]]*=" "$CONFIG_FILE" 2>/dev/null | cut -d'=' -f2 | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
        if [ -n "$DEVICE" ]; then
            echo "$DEVICE"
            return 0
        fi
    fi
    return 1
}

# Function to find the Trinkey device (same as callbacks.sh)
find_device() {
    CONFIGURED_DEVICE=$(get_configured_device)
    if [ -n "$CONFIGURED_DEVICE" ] && [ -e "$CONFIGURED_DEVICE" ]; then
        echo "$CONFIGURED_DEVICE"
        return 0
    fi
    
    # Auto-detect
    if [ -d "/dev/serial/by-id" ]; then
        DEVICE_SYMLINK=$(find /dev/serial/by-id -iname "*adafruit*neopixel*trinkey*" 2>/dev/null | head -n 1)
        if [ -z "$DEVICE_SYMLINK" ]; then
            DEVICE_SYMLINK=$(find /dev/serial/by-id -iname "*adafruit*trinkey*" 2>/dev/null | head -n 1)
        fi
        if [ -z "$DEVICE_SYMLINK" ]; then
            DEVICE_SYMLINK=$(ls /dev/serial/by-id/*Adafruit* 2>/dev/null | head -n 1)
        fi
        
        if [ -n "$DEVICE_SYMLINK" ] && [ -L "$DEVICE_SYMLINK" ]; then
            REAL_LINK=$(readlink -f "$DEVICE_SYMLINK" 2>/dev/null)
            if [ -n "$REAL_LINK" ] && [ -e "$REAL_LINK" ]; then
                echo "$REAL_LINK"
                return 0
            fi
        fi
    fi
    
    return 1
}

# Function to load touch configuration
load_touch_config() {
    if [ -f "$CONFIG_FILE" ]; then
        TOUCH1_ACTION=$(grep "^touch1_action[[:space:]]*=" "$CONFIG_FILE" 2>/dev/null | cut -d'=' -f2 | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
        TOUCH2_ACTION=$(grep "^touch2_action[[:space:]]*=" "$CONFIG_FILE" 2>/dev/null | cut -d'=' -f2 | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
        TOUCH1_PLAYLIST=$(grep "^touch1_playlist[[:space:]]*=" "$CONFIG_FILE" 2>/dev/null | cut -d'=' -f2 | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
        TOUCH2_PLAYLIST=$(grep "^touch2_playlist[[:space:]]*=" "$CONFIG_FILE" 2>/dev/null | cut -d'=' -f2 | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
    fi
}

# Function to execute FPP action
execute_fpp_action() {
    local touch_num=$1
    local action=""
    local playlist=""
    
    if [ "$touch_num" == "1" ]; then
        action="$TOUCH1_ACTION"
        playlist="$TOUCH1_PLAYLIST"
    else
        action="$TOUCH2_ACTION"
        playlist="$TOUCH2_PLAYLIST"
    fi
    
    if [ -z "$action" ] || [ "$action" == "none" ]; then
        return 0
    fi
    
    log_message "Touch pad $touch_num triggered: action=$action, playlist=$playlist"
    
    case "$action" in
        "playlist_start")
            if [ -n "$playlist" ]; then
                curl -s "http://localhost/api/command/Start Playlist/$playlist" > /dev/null 2>&1
                log_message "Started playlist: $playlist"
            fi
            ;;
        "playlist_stop")
            curl -s "http://localhost/api/command/Stop" > /dev/null 2>&1
            log_message "Stopped current playlist"
            ;;
        "playlist_toggle")
            # Check if playing, then toggle
            STATUS=$(curl -s "http://localhost/api/fppd/status" 2>/dev/null)
            if echo "$STATUS" | grep -qi '"status_name"[[:space:]]*:[[:space:]]*"playing"'; then
                curl -s "http://localhost/api/command/Stop" > /dev/null 2>&1
                log_message "Toggled: Stopped (was playing)"
            else
                if [ -n "$playlist" ]; then
                    curl -s "http://localhost/api/command/Start Playlist/$playlist" > /dev/null 2>&1
                    log_message "Toggled: Started playlist $playlist"
                fi
            fi
            ;;
        "next_playlist")
            curl -s "http://localhost/api/command/Next Playlist Item" > /dev/null 2>&1
            log_message "Next playlist item"
            ;;
        "volume_up")
            # Get current volume and increase
            STATUS=$(curl -s "http://localhost/api/fppd/status" 2>/dev/null)
            CURRENT_VOL=$(echo "$STATUS" | grep -o '"volume"[[:space:]]*:[[:space:]]*[0-9]*' | grep -o '[0-9]*')
            if [ -n "$CURRENT_VOL" ]; then
                NEW_VOL=$((CURRENT_VOL + 5))
                if [ $NEW_VOL -gt 100 ]; then
                    NEW_VOL=100
                fi
                curl -s "http://localhost/api/command/Volume Set/$NEW_VOL" > /dev/null 2>&1
                log_message "Volume up: $CURRENT_VOL -> $NEW_VOL"
            fi
            ;;
        "volume_down")
            # Get current volume and decrease
            STATUS=$(curl -s "http://localhost/api/fppd/status" 2>/dev/null)
            CURRENT_VOL=$(echo "$STATUS" | grep -o '"volume"[[:space:]]*:[[:space:]]*[0-9]*' | grep -o '[0-9]*')
            if [ -n "$CURRENT_VOL" ]; then
                NEW_VOL=$((CURRENT_VOL - 5))
                if [ $NEW_VOL -lt 0 ]; then
                    NEW_VOL=0
                fi
                curl -s "http://localhost/api/command/Volume Set/$NEW_VOL" > /dev/null 2>&1
                log_message "Volume down: $CURRENT_VOL -> $NEW_VOL"
            fi
            ;;
    esac
}

# Main loop
log_message "Touch input listener started"

DEVICE=$(find_device)
if [ -z "$DEVICE" ]; then
    log_message "ERROR: Could not find Trinkey device. Touch input disabled."
    exit 1
fi

log_message "Listening for touch events on $DEVICE"

# Configure serial port
stty -F "$DEVICE" 115200 raw -echo -echoe -echok 2>/dev/null

# Main read loop
while true; do
    # Load config (in case it changed)
    load_touch_config
    
    # Read line from device (touch events are sent as "T1\n" or "T2\n")
    if read -r -t 1 line < "$DEVICE" 2>/dev/null; then
        line=$(echo "$line" | tr -d '\r\n')
        
        if [ "$line" == "T1" ]; then
            execute_fpp_action "1"
        elif [ "$line" == "T2" ]; then
            execute_fpp_action "2"
        fi
    fi
    
    # Small sleep to prevent CPU spinning
    sleep 0.1
done

