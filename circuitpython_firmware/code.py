import time
import board
import neopixel
import supervisor
import sys

# Setup NeoPixels
# Trinkey has 4 NeoPixels
pixel_pin = board.NEOPIXEL
num_pixels = 4
current_brightness = 0.3  # Default brightness (30%)
pixels = neopixel.NeoPixel(pixel_pin, num_pixels, brightness=current_brightness, auto_write=False)

# Colors
COLOR_IDLE = (0, 0, 255)      # Blue
COLOR_PLAYING = (0, 255, 0)   # Green
COLOR_STOPPED = (255, 0, 0)   # Red
COLOR_OFF = (0, 0, 0)         # Off

current_status = "I" # Default to Idle
last_check = 0

def set_color(color):
    pixels.fill(color)
    pixels.show()

def rainbow_cycle(wait):
    for j in range(255):
        for i in range(num_pixels):
            pixel_index = (i * 256 // num_pixels) + j
            pixels[i] = wheel(pixel_index & 255)
        pixels.show()
        time.sleep(wait)
        # Check for input during animation
        if supervisor.runtime.serial_bytes_available:
            return

def wheel(pos):
    # Input a value 0 to 255 to get a color value.
    # The colours are a transition r - g - b - back to r.
    if pos < 0 or pos > 255:
        return (0, 0, 0)
    if pos < 85:
        return (255 - pos * 3, pos * 3, 0)
    if pos < 170:
        pos -= 85
        return (0, 255 - pos * 3, pos * 3)
    pos -= 170
    return (pos * 3, 0, 255 - pos * 3)

print("FPP Status Listener Started")
set_color(COLOR_IDLE)

# Valid status characters
VALID_STATUSES = ["I", "P", "S", "E", "R"]

# Flush any garbage data from serial port during startup
# Wait a moment for the system to stabilize, then flush any pending data
time.sleep(1.0)
# Clear any pending serial input that might be garbage from boot
while supervisor.runtime.serial_bytes_available:
    try:
        sys.stdin.read(1)  # Discard any pending data
    except:
        break
print("Startup flush complete, ready for commands")

while True:
    # Check for serial input
    if supervisor.runtime.serial_bytes_available:
        try:
            # Read one byte
            input_char = sys.stdin.read(1)
            if input_char:
                # Check for brightness command (starts with "B")
                if input_char == "B":
                    # Read 3 more digits for brightness (0-100, padded to 3 digits)
                    brightness_str = ""
                    timeout = 0
                    while len(brightness_str) < 3 and timeout < 10:
                        if supervisor.runtime.serial_bytes_available:
                            digit = sys.stdin.read(1)
                            if digit.isdigit():
                                brightness_str += digit
                            else:
                                break
                        else:
                            time.sleep(0.01)
                            timeout += 1
                    
                    if len(brightness_str) == 3:
                        brightness_value = int(brightness_str)
                        # Convert 0-100 to 0.0-1.0
                        current_brightness = brightness_value / 100.0
                        pixels.brightness = current_brightness
                        print(f"Brightness set to {brightness_value}% ({current_brightness})")
                    else:
                        print(f"Invalid brightness command format: B{brightness_str}")
                else:
                    # Strip whitespace and newlines, but keep the character
                    cleaned_char = input_char.strip()
                    # Only update status if we got a valid status character
                    if cleaned_char in VALID_STATUSES:
                        current_status = cleaned_char
                        print(f"Received status: {current_status}")
                    elif input_char.strip():  # If it's not empty after strip but not valid
                        print(f"Invalid status character received: '{input_char}' (ord: {ord(input_char)})")
        except Exception as e:
            print(f"Error reading serial: {e}")

    # Update LEDs based on status
    if current_status == "I": # Idle
        # Maybe a slow breathe or static blue
        set_color(COLOR_IDLE)
    elif current_status == "P": # Playing
        set_color(COLOR_PLAYING)
    elif current_status == "S": # Stopped
        set_color(COLOR_STOPPED)
    elif current_status == "E": # Error
        # Flash Red (but we don't actually use this status, so this shouldn't happen)
        # If it does, it means we received garbage data interpreted as "E"
        set_color(COLOR_STOPPED)
        time.sleep(0.5)
        set_color(COLOR_OFF)
        time.sleep(0.5)
        # Reset to idle after error flash
        current_status = "I"
        continue # Skip the main sleep
    elif current_status == "R": # Rainbow (Demo)
        rainbow_cycle(0.01)
    else:
        # Unknown status - keep current color instead of turning off
        # This prevents the LEDs from turning off due to invalid input
        pass

    time.sleep(0.1)
