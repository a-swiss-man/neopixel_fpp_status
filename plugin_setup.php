<?php
// FPP NeoPixel Trinkey Status Plugin Setup
?>
<div id="neopixel_setup" class="settings">
    <fieldset>
        <legend>NeoPixel Trinkey Status</legend>
        <p>This plugin controls a NeoPixel Trinkey via USB to display FPP status.</p>
        <p><b>Instructions:</b></p>
        <ul>
            <li>Flash your NeoPixel Trinkey with the CircuitPython firmware found in the <code>circuitpython_firmware</code> folder.</li>
            <li>Connect the Trinkey to a USB port on the FPP device.</li>
            <li>The plugin automatically detects the device and sends status updates.</li>
        </ul>
        <p>Log File: <code>/home/fpp/media/logs/neopixel_status.log</code></p>
    </fieldset>
</div>
