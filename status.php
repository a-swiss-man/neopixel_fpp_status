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
    
    <fieldset>
        <legend>Diagnostics</legend>
        <p><b>Callback Registration Status:</b></p>
        <?php
        $pluginDir = "/home/fpp/media/plugins/neopixel_fpp_status";
        $callbackScript = "$pluginDir/callbacks.sh";
        $issues = array();
        $success = array();
        
        // Check if callbacks.sh exists
        if (file_exists($callbackScript)) {
            $success[] = "✓ callbacks.sh exists at: $callbackScript";
            
            // Check if it's executable
            if (is_executable($callbackScript)) {
                $success[] = "✓ callbacks.sh is executable";
            } else {
                $issues[] = "✗ callbacks.sh is NOT executable (run: chmod +x $callbackScript)";
            }
            
            // Test --list functionality
            $output = array();
            $returnVar = 0;
            exec("$callbackScript --list 2>&1", $output, $returnVar);
            if ($returnVar == 0 && count($output) > 0) {
                $success[] = "✓ callbacks.sh responds to --list query";
                $success[] = "  Events registered: " . implode(", ", $output);
            } else {
                $issues[] = "✗ callbacks.sh does not respond correctly to --list";
            }
        } else {
            $issues[] = "✗ callbacks.sh NOT FOUND at: $callbackScript";
        }
        
        // Check if plugin is in the right location
        if (is_dir($pluginDir)) {
            $success[] = "✓ Plugin directory exists: $pluginDir";
        } else {
            $issues[] = "✗ Plugin directory NOT FOUND: $pluginDir";
        }
        
        // Check recent log entries for callback executions
        $recentLogs = array_slice(explode("\n", $logContent), -20);
        $hasCallbacks = false;
        foreach ($recentLogs as $line) {
            if (strpos($line, "callbacks.sh executed") !== false || 
                strpos($line, "Event received") !== false) {
                $hasCallbacks = true;
                break;
            }
        }
        
        if ($hasCallbacks) {
            $success[] = "✓ Recent log entries show callback executions";
        } else {
            $issues[] = "⚠ No recent callback executions found in log (FPP may not be calling callbacks.sh)";
        }
        
        // Display results
        if (count($success) > 0) {
            echo "<div style='color: green; margin: 10px 0;'>";
            foreach ($success as $msg) {
                echo "<div>" . htmlspecialchars($msg) . "</div>";
            }
            echo "</div>";
        }
        
        if (count($issues) > 0) {
            echo "<div style='color: red; margin: 10px 0;'>";
            foreach ($issues as $msg) {
                echo "<div>" . htmlspecialchars($msg) . "</div>";
            }
            echo "</div>";
        }
        
        if (count($success) > 0 && count($issues) == 0) {
            echo "<p style='color: orange;'><b>Note:</b> If callbacks still aren't working, you may need to:</p>";
            echo "<ul style='color: orange;'>";
            echo "<li>Restart FPPD after plugin installation</li>";
            echo "<li>Ensure the plugin is enabled in FPP Settings</li>";
            echo "<li>Check FPP's main log for plugin-related errors</li>";
            echo "</ul>";
        }
        ?>
    </fieldset>
</div>

