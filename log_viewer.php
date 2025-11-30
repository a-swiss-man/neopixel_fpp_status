<?php
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

<div id="neopixel_log_viewer" class="settings">
    <fieldset>
        <legend>NeoPixel Trinkey Status Log</legend>
        <form method="post" action="">
            <textarea style="width: 100%; height: 500px; font-family: monospace;" readonly><?php echo htmlspecialchars($logContent); ?></textarea>
            <br><br>
            <input type="submit" name="clear_log" value="Clear Log" class="buttons">
            <input type="button" value="Refresh" onclick="location.reload();" class="buttons">
        </form>
    </fieldset>
</div>
