<?php
// FPP NeoPixel Trinkey Status Plugin - Log Viewer

$logFile = "/home/fpp/media/logs/neopixel_status.log";
$configFile = "/home/fpp/media/config/neopixel_status.conf";

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

// Get current device from config if available
$currentDevice = "Auto-detect";
if (file_exists($configFile)) {
    $config = parse_ini_file($configFile);
    if (isset($config['device']) && !empty($config['device'])) {
        $currentDevice = $config['device'];
    }
}
?>

<div id="neopixel_status" class="settings">
    <fieldset>
        <legend>NeoPixel Trinkey Status - Log Viewer</legend>
        <p>This plugin controls a NeoPixel Trinkey via USB to display FPP status.</p>
        <p><b>Current Device:</b> <code><?php echo htmlspecialchars($currentDevice); ?></code></p>
        <p><b>Log File:</b> <code><?php echo $logFile; ?></code></p>
    </fieldset>

    <fieldset>
        <legend>Log Viewer</legend>
        <form method="post" action="">
            <textarea style="width: 100%; height: 400px; font-family: monospace; font-size: 12px;" readonly><?php echo htmlspecialchars($logContent); ?></textarea>
            <br><br>
            <input type="submit" name="clear_log" value="Clear Log" class="buttons">
            <input type="button" value="Refresh" onclick="location.reload();" class="buttons">
        </form>
    </fieldset>
</div>

