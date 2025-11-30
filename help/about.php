<!DOCTYPE html>
<html>
<head>
    <title>NeoPixel Trinkey Status Help</title>
</head>
<body>
    <h2>NeoPixel Trinkey Status Plugin</h2>
    <p>This plugin controls an Adafruit NeoPixel Trinkey connected via USB to indicate FPP status.</p>
    
    <h3>Setup</h3>
    <ol>
        <li><strong>Hardware:</strong> Connect the NeoPixel Trinkey to a USB port on your FPP device.</li>
        <li><strong>Firmware:</strong> You must install the provided CircuitPython firmware on the Trinkey.
            <ul>
                <li>Plug the Trinkey into your computer.</li>
                <li>Copy the <code>circuitpython_firmware/code.py</code> file from this plugin to the <code>CIRCUITPY</code> drive.</li>
                <li>Ensure the necessary libraries (neopixel, etc.) are on the Trinkey (usually built-in for Trinkey builds).</li>
            </ul>
        </li>
    </ol>

    <h3>Status Colors</h3>
    <ul>
        <li><strong>Idle:</strong> Blue</li>
        <li><strong>Playing:</strong> Green</li>
        <li><strong>Stopped:</strong> Red</li>
    </ul>

    <h3>Troubleshooting</h3>
    <p>Check the log file at <code>/home/fpp/media/logs/neopixel_status.log</code> for debug information.</p>
    <p>Ensure the Trinkey is detected as a USB Serial device (e.g., <code>/dev/ttyACM0</code>).</p>
</body>
</html>
