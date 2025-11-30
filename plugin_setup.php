<?php
// FPP NeoPixel Trinkey Status Plugin Setup

$logFile = "/home/fpp/media/logs/neopixel_status.log";

if (isset($_POST['clear_log'])) {
    if (file_exists($logFile)) {
        file_put_contents($logFile, "");
        echo "<script>$.jGrowl('Log cleared');</script>";
    }
}

$logContent = "";
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
} else {
    $logContent = "Log file not found.";
}
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
        <p>Log File: <code><?php echo $logFile; ?></code></p>
    </fieldset>

    <fieldset>
        <legend>Log Viewer</legend>
        <form method="post" action="">
            <textarea style="width: 100%; height: 300px; font-family: monospace;" readonly><?php echo htmlspecialchars($logContent); ?></textarea>
            <br><br>
            <input type="submit" name="clear_log" value="Clear Log" class="buttons">
            <input type="button" value="Refresh" onclick="location.reload();" class="buttons">
        </form>
    </fieldset>
</div>


