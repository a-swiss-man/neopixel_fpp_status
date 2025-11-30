#!/bin/bash

# FPP NeoPixel Trinkey Status Plugin
# This script handles FPP events and sends commands to the Trinkey.

# Log file
LOG_FILE="/home/fpp/media/logs/neopixel_status.log"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

# Function to find the Trinkey device
find_device() {
    # Look for a device with Adafruit VID (239a)
    # This is a heuristic; might need adjustment if multiple Adafruit devices are present.
    # We look in /dev/serial/by-id/ which is standard on Linux/FPP
    
    DEVICE=""
    
    # Check if /dev/serial/by-id exists
    if [ -d "/dev/serial/by-id" ]; then
        # Find file containing "Adafruit" and "Trinkey" if possible, or just Adafruit
        DEVICE=$(ls /dev/serial/by-id/*Adafruit*Trinkey* 2>/dev/null | head -n 1)
        
        if [ -z "$DEVICE" ]; then
             # Fallback to just Adafruit
             DEVICE=$(ls /dev/serial/by-id/*Adafruit* 2>/dev/null | head -n 1)
        fi
    fi
    
    # If still not found, check ttyACM* directly (less reliable)
    if [ -z "$DEVICE" ]; then
        # This is risky without ID checking, but a last resort
        # DEVICE="/dev/ttyACM0"
        log_message "Could not find device by ID."
        return 1
    fi
    
    echo "$DEVICE"
}

# Function to send status
send_status() {
    STATUS_CHAR=$1
    DEVICE=$(find_device)
    
    if [ -z "$DEVICE" ]; then
        log_message "Error: NeoPixel Trinkey not found."
        return
    fi
    
    log_message "Sending status '$STATUS_CHAR' to $DEVICE"
    
    # Configure stty to ensure raw communication (optional but good practice)
    # stty -F $DEVICE 115200 raw -echo
    
    # Send the character
    # We use printf to avoid newlines if not needed, but echo is usually fine.
    # The CircuitPython script reads 1 byte.
    echo -n "$STATUS_CHAR" > "$DEVICE"
}

# Main Event Handling
# FPP passes arguments: event_type event_data...

# We need to determine if this script is being called as a callback or directly.
# FPP callbacks usually have specific filenames or are registered.
# For simplicity, we'll assume this script is called by FPP's event system or a wrapper.

# However, the standard way in FPP plugins is to have scripts named like 'callbacks.sh' 
# or specific event scripts. 
# Let's handle standard FPP callbacks if this script is sourced or called.

# Arguments:
# $1: Event Type (e.g., "playlist", "media", "fppd")
# $2: Action (e.g., "start", "stop")
# $3: Details (e.g., Playlist Name)

TYPE=$1
ACTION=$2

log_message "Event received: Type=$TYPE, Action=$ACTION"

case "$TYPE" in
    "playlist")
        if [ "$ACTION" == "start" ]; then
            send_status "P" # Playing
        elif [ "$ACTION" == "stop" ]; then
            send_status "I" # Idle (or Stopped)
        fi
        ;;
    "media")
        # Optional: distinct color for media vs sequence?
        if [ "$ACTION" == "start" ]; then
            send_status "P"
        elif [ "$ACTION" == "stop" ]; then
            send_status "I"
        fi
        ;;
    "fppd")
        if [ "$ACTION" == "start" ]; then
            send_status "I" # FPP Started
        elif [ "$ACTION" == "stop" ]; then
            send_status "S" # FPP Stopped (might not be received if FPP dies)
        fi
        ;;
    *)
        # Default or unknown event
        # Do nothing or log
        ;;
esac
