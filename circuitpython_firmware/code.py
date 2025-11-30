import time
import board
import neopixel
import supervisor
import sys

# Setup NeoPixels
# Trinkey has 4 NeoPixels
pixel_pin = board.NEOPIXEL
num_pixels = 4
pixels = neopixel.NeoPixel(pixel_pin, num_pixels, brightness=0.3, auto_write=False)

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

while True:
    # Check for serial input
    if supervisor.runtime.serial_bytes_available:
        try:
            # Read one byte
            input_char = sys.stdin.read(1)
            if input_char:
                current_status = input_char.strip()
                print(f"Received status: {current_status}")
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
        # Flash Red
        set_color(COLOR_STOPPED)
        time.sleep(0.5)
        set_color(COLOR_OFF)
        time.sleep(0.5)
        continue # Skip the main sleep
    elif current_status == "R": # Rainbow (Demo)
        rainbow_cycle(0.01)
    else:
        set_color(COLOR_OFF)

    time.sleep(0.1)
