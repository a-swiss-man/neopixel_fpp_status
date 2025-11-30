<?php
// FPP NeoPixel Trinkey Status Plugin - Configuration

$configFile = "/home/fpp/media/config/neopixel_status.conf";
$logFile = "/home/fpp/media/logs/neopixel_status.log";

// Ensure config directory exists
$configDir = dirname($configFile);
if (!is_dir($configDir)) {
    mkdir($configDir, 0755, true);
}

// Function to log messages
function log_message($message) {
    global $logFile;
    $timestamp = date('Y-m-d H: i: s');
    file_put_contents($logFile, "$timestamp - $message\n", FILE_APPEND);
}

// Handle service control
$serviceMessage = "";
$serviceMessageType = "";

if (isset($_POST['service_action'])) {
    $action = isset($_POST['service_action']) ? trim($_POST['service_action']) : "";
    $serviceName = "neopixel-status-poller.service";
    
    if (in_array($action, array('start', 'stop', 'restart', 'status'))) {
        $output = array();
        $returnVar = 0;
        exec("sudo systemctl $action $serviceName 2>&1", $output, $returnVar);
        
        if ($returnVar == 0) {
            if ($action == 'status') {
                $statusOutput = array();
                exec("systemctl is-active $serviceName 2>&1", $statusOutput);
                $isActive = implode("", $statusOutput);
                $serviceMessage = "Service status: <strong>$isActive</strong>";
            } else {
                $serviceMessage = "Service $action command executed successfully.";
            }
            $serviceMessageType = "success";
            log_message("Service $action executed");
        } else {
            $errorOutput = implode("\n", array_slice($output, -3));
            $serviceMessage = "Error executing service $action command.<br><small>Error: " . htmlspecialchars($errorOutput) . "</small>";
            $serviceMessageType = "error";
            log_message("Service $action failed: " . implode(" ", $output));
        }
    }
}

// Handle test command
$testMessage = "";
$testMessageType = "";

if (isset($_POST['test_command'])) {
    $command = isset($_POST['test_command']) ? trim($_POST['test_command']) : "";
    $pluginDir = "/home/fpp/media/plugins/neopixel_fpp_status";
    $callbackScript = "$pluginDir/callbacks.sh";
    
    if (in_array($command, array('I', 'P', 'S', 'E', 'R'))) {
        // Call the callback script with a special test mode
        // We'll modify callbacks.sh to handle a "test" event type
        $output = array();
        $returnVar = 0;
        exec("$callbackScript test $command 2>&1", $output, $returnVar);
        
        $statusNames = array(
            'I' => 'Idle (Blue)',
            'P' => 'Playing (Green)',
            'S' => 'Stopped (Red)',
            'E' => 'Error (Blinking Red)',
            'R' => 'Rainbow (Demo)'
        );
        
        if ($returnVar == 0) {
            $testMessage = "Test command '{$statusNames[$command]}' sent successfully! Check your Trinkey to see the result.";
            $testMessageType = "success";
            log_message("Test command sent: $command");
        } else {
            $errorOutput = implode("\n", array_slice($output, -5)); // Last 5 lines
            $testMessage = "Error sending test command '{$statusNames[$command]}'.<br><small>Error details: " . htmlspecialchars($errorOutput) . "</small>";
            $testMessageType = "error";
            log_message("Test command failed: $command - " . implode(" ", $output));
        }
    } else {
        $testMessage = "Invalid test command.";
        $testMessageType = "error";
    }
}

// Handle brightness update
$brightnessMessage = "";
$brightnessMessageType = "";

if (isset($_POST['set_brightness'])) {
    $brightness = isset($_POST['brightness']) ? intval($_POST['brightness']) : 30;
    $brightness = max(0, min(100, $brightness)); // Clamp between 0-100
    
    $pluginDir = "/home/fpp/media/plugins/neopixel_fpp_status";
    $callbackScript = "$pluginDir/callbacks.sh";
    
    $output = array();
    $returnVar = 0;
    exec("$callbackScript brightness $brightness 2>&1", $output, $returnVar);
    
    if ($returnVar == 0) {
        $brightnessMessage = "Brightness set to $brightness% successfully!";
        $brightnessMessageType = "success";
        log_message("Brightness set to $brightness%");
    } else {
        $errorOutput = implode("\n", array_slice($output, -3));
        $brightnessMessage = "Error setting brightness.<br><small>Error: " . htmlspecialchars($errorOutput) . "</small>";
        $brightnessMessageType = "error";
        log_message("Brightness setting failed: " . implode(" ", $output));
    }
}

// Handle form submission
$message = "";
$messageType = "";

if (isset($_POST['save_config'])) {
    $device = isset($_POST['device']) ? trim($_POST['device']) : "";
    $brightness = isset($_POST['config_brightness']) ? intval($_POST['config_brightness']) : 30;
    $brightness = max(0, min(100, $brightness)); // Clamp between 0-100
    
    // Validate device path if provided
    if (!empty($device)) {
        // Check if device exists
        if (!file_exists($device)) {
            $message = "Warning: Device path '$device' does not exist. It may be available after the device is connected.";
            $messageType = "warning";
        }
    }
    
    // Save configuration
    $config = "[neopixel_status]\n";
    $config .= "device = " . ($device ? $device : "") . "\n";
    $config .= "brightness = $brightness\n";
    
    if (file_put_contents($configFile, $config)) {
        if (empty($message)) {
            $message = "Configuration saved successfully.";
            $messageType = "success";
        }
        log_message("Configuration updated. Device: " . ($device ? $device : "Auto-detect") . ", Brightness: $brightness%");
    } else {
        $message = "Error: Could not save configuration file.";
        $messageType = "error";
    }
}

// Load current configuration
$currentDevice = "";
$currentBrightness = 30; // Default brightness
if (file_exists($configFile)) {
    $config = parse_ini_file($configFile);
    if (isset($config['device']) && !empty($config['device'])) {
        $currentDevice = $config['device'];
    }
    if (isset($config['brightness'])) {
        $currentBrightness = intval($config['brightness']);
        $currentBrightness = max(0, min(100, $currentBrightness)); // Clamp
    }
}

// Load log content for display
$logContent = "";
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
} else {
    $logContent = "Log file not found.";
}

// Handle log clear
if (isset($_POST['clear_log'])) {
    if (file_exists($logFile)) {
        file_put_contents($logFile, "");
        echo "<script>$.jGrowl('Log cleared');</script>";
        $logContent = "";
    }
}

// Scan for available devices
$availableDevices = array();
$autoDetectOption = array('value' => '', 'text' => 'Auto-detect (recommended)');

// Check /dev/serial/by-id for Adafruit devices
if (is_dir("/dev/serial/by-id")) {
    $devices = glob("/dev/serial/by-id/*Adafruit*");
    foreach ($devices as $device) {
        $realPath = realpath($device);
        if ($realPath) {
            $availableDevices[] = array(
                'value' => $realPath,
                'text' => basename($device) . " -> " . $realPath
            );
        }
    }
}

// Also check common ttyACM and ttyUSB devices
$commonDevices = glob("/dev/ttyACM*");
$commonDevices = array_merge($commonDevices, glob("/dev/ttyUSB*"));
foreach ($commonDevices as $device) {
    if (file_exists($device)) {
        $availableDevices[] = array(
            'value' => $device,
            'text' => basename($device) . " (" . $device . ")"
        );
    }
}
?>

<div id="neopixel_config" class="settings">
    <fieldset>
        <legend>NeoPixel Trinkey Status - Configuration</legend>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>" style="padding: 10px; margin: 10px 0; border: 1px solid #ccc; background-color: #f0f0f0;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <p>Configure the USB device path for your NeoPixel Trinkey.</p>
        <p><b>Note:</b> If you leave this as "Auto-detect", the plugin will automatically search for Adafruit devices. 
        You only need to specify a device path if you have multiple Adafruit devices or want to use a specific port.</p>
        
        <form method="post" action="">
            <fieldset>
                <legend>Device Selection</legend>
                <table>
                    <tr>
                        <td><label for="device">Device Path:</label></td>
                        <td>
                            <select name="device" id="device" style="width: 400px;">
                                <option value="" <?php echo empty($currentDevice) ? 'selected' : ''; ?>>
                                    <?php echo $autoDetectOption['text']; ?>
                                </option>
                                <?php foreach ($availableDevices as $device): ?>
                                    <option value="<?php echo htmlspecialchars($device['value']); ?>" 
                                            <?php echo ($currentDevice == $device['value']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($device['text']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                            <small>Or enter a custom path:</small><br>
                            <input type="text" name="device_custom" id="device_custom" 
                                   value="<?php echo htmlspecialchars($currentDevice); ?>" 
                                   placeholder="/dev/ttyACM0" style="width: 400px; margin-top: 5px;"
                                   onchange="document.getElementById('device').value = this.value;">
                        </td>
                    </tr>
                    <tr>
                        <td><label for="config_brightness">Brightness:</label></td>
                        <td>
                            <input type="range" name="config_brightness" id="config_brightness" 
                                   min="0" max="100" value="<?php echo $currentBrightness; ?>" 
                                   oninput="document.getElementById('brightness_value').textContent = this.value + '%'"
                                   style="width: 300px;">
                            <span id="brightness_value" style="margin-left: 10px;"><?php echo $currentBrightness; ?>%</span>
                            <br><small>Adjust the brightness of the NeoPixel LEDs (0-100%)</small>
                        </td>
                    </tr>
                </table>
            </fieldset>
            
            <br>
            <input type="submit" name="save_config" value="Save Configuration" class="buttons">
            <input type="button" value="Refresh Device List" onclick="location.reload();" class="buttons">
        </form>
    </fieldset>
    
    <fieldset>
        <legend>Brightness Control</legend>
        <p>Quick brightness adjustment - changes take effect immediately.</p>
        
        <?php if ($brightnessMessage): ?>
            <div class="alert alert-<?php echo $brightnessMessageType; ?>" style="padding: 10px; margin: 10px 0; border: 1px solid #ccc; background-color: <?php echo $brightnessMessageType == 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $brightnessMessageType == 'success' ? '#155724' : '#721c24'; ?>;">
                <?php echo $brightnessMessage; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="" style="margin: 10px 0;">
            <table>
                <tr>
                    <td style="padding: 5px;"><label for="brightness">Brightness:</label></td>
                    <td style="padding: 5px;">
                        <input type="range" name="brightness" id="brightness" 
                               min="0" max="100" value="<?php echo $currentBrightness; ?>" 
                               oninput="document.getElementById('brightness_display').textContent = this.value + '%'"
                               style="width: 300px;">
                        <span id="brightness_display" style="margin-left: 10px;"><?php echo $currentBrightness; ?>%</span>
                    </td>
                    <td style="padding: 5px;">
                        <input type="submit" name="set_brightness" value="Set Brightness" class="buttons">
                    </td>
                </tr>
            </table>
        </form>
    </fieldset>
    
    <fieldset>
        <legend>Status Poller Service</legend>
        <p>Control the status poller service that monitors FPP and updates the Trinkey.</p>
        
        <?php if ($serviceMessage): ?>
            <div class="alert alert-<?php echo $serviceMessageType; ?>" style="padding: 10px; margin: 10px 0; border: 1px solid #ccc; background-color: <?php echo $serviceMessageType == 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $serviceMessageType == 'success' ? '#155724' : '#721c24'; ?>;">
                <?php echo $serviceMessage; ?>
            </div>
        <?php endif; ?>
        
        <?php
        // Check service status
        $serviceName = "neopixel-status-poller.service";
        $serviceStatus = "unknown";
        $isActive = false;
        $isEnabled = false;
        
        $output = array();
        exec("systemctl is-active $serviceName 2>&1", $output);
        $serviceStatus = implode("", $output);
        $isActive = ($serviceStatus == "active");
        
        $output = array();
        exec("systemctl is-enabled $serviceName 2>&1", $output);
        $enabledStatus = implode("", $output);
        $isEnabled = ($enabledStatus == "enabled");
        ?>
        
        <table style="width: 100%; margin: 10px 0;">
            <tr>
                <td style="padding: 5px;"><strong>Service Status:</strong></td>
                <td style="padding: 5px;">
                    <?php if ($isActive): ?>
                        <span style="color: green;">● Running</span>
                    <?php else: ?>
                        <span style="color: red;">● Stopped</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 5px;"><strong>Auto-Start:</strong></td>
                <td style="padding: 5px;">
                    <?php if ($isEnabled): ?>
                        <span style="color: green;">● Enabled (starts on boot)</span>
                    <?php else: ?>
                        <span style="color: orange;">● Disabled</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <form method="post" action="" style="margin: 10px 0;">
            <input type="submit" name="service_action" value="start" class="buttons" <?php echo $isActive ? 'disabled' : ''; ?>>
            <input type="submit" name="service_action" value="stop" class="buttons" <?php echo !$isActive ? 'disabled' : ''; ?>>
            <input type="submit" name="service_action" value="restart" class="buttons">
            <input type="submit" name="service_action" value="status" class="buttons">
        </form>
        
        <p><small><i>Note: The status poller automatically monitors FPP status and updates the Trinkey. 
        It runs as a systemd service and will automatically start on boot if enabled.</i></small></p>
    </fieldset>
    
    <fieldset>
        <legend>Device Test</legend>
        <p>Test communication with your NeoPixel Trinkey by sending status commands directly.</p>
        
        <?php if ($testMessage): ?>
            <div class="alert alert-<?php echo $testMessageType; ?>" style="padding: 10px; margin: 10px 0; border: 1px solid #ccc; background-color: <?php echo $testMessageType == 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $testMessageType == 'success' ? '#155724' : '#721c24'; ?>;">
                <?php echo nl2br(htmlspecialchars($testMessage)); ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="" style="margin: 10px 0;">
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 5px;"><strong>Status Command:</strong></td>
                    <td style="padding: 5px;"><strong>Color/Effect:</strong></td>
                    <td style="padding: 5px;"><strong>Action:</strong></td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><code>I</code> - Idle</td>
                    <td style="padding: 5px;">🔵 Blue</td>
                    <td style="padding: 5px;">
                        <button type="submit" name="test_command" value="I" class="buttons" style="background-color: #0066cc; color: white;">Test Idle</button>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><code>P</code> - Playing</td>
                    <td style="padding: 5px;">🟢 Green</td>
                    <td style="padding: 5px;">
                        <button type="submit" name="test_command" value="P" class="buttons" style="background-color: #00cc00; color: white;">Test Playing</button>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><code>S</code> - Stopped</td>
                    <td style="padding: 5px;">🔴 Red</td>
                    <td style="padding: 5px;">
                        <button type="submit" name="test_command" value="S" class="buttons" style="background-color: #cc0000; color: white;">Test Stopped</button>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><code>E</code> - Error</td>
                    <td style="padding: 5px;">🔴 Blinking Red</td>
                    <td style="padding: 5px;">
                        <button type="submit" name="test_command" value="E" class="buttons" style="background-color: #cc6600; color: white;">Test Error</button>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><code>R</code> - Rainbow</td>
                    <td style="padding: 5px;">🌈 Rainbow Cycle</td>
                    <td style="padding: 5px;">
                        <button type="submit" name="test_command" value="R" class="buttons" style="background: linear-gradient(to right, red, orange, yellow, green, blue, indigo, violet); color: white;">Test Rainbow</button>
                    </td>
                </tr>
            </table>
        </form>
        
        <p><small><i>Note: Make sure your device is configured and connected before testing. Check the log file for detailed information about each test.</i></small></p>
    </fieldset>
    
    <fieldset>
        <legend onclick="toggleSection('config_section')" style="cursor: pointer;">
            Current Configuration <span id="config_toggle">▼</span>
        </legend>
        <div id="config_section" style="display: none;">
            <p><b>Config File:</b> <code><?php echo $configFile; ?></code></p>
            <p><b>Current Device Setting:</b> 
                <code><?php echo empty($currentDevice) ? 'Auto-detect' : htmlspecialchars($currentDevice); ?></code>
            </p>
            <p><b>Current Brightness:</b> 
                <code><?php echo $currentBrightness; ?>%</code>
            </p>
            <?php if (file_exists($configFile)): ?>
                <pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd;"><?php echo htmlspecialchars(file_get_contents($configFile)); ?></pre>
            <?php else: ?>
                <p><i>No configuration file found. Using default (auto-detect).</i></p>
            <?php endif; ?>
        </div>
    </fieldset>
    
    <fieldset>
        <legend onclick="toggleSection('log_section')" style="cursor: pointer;">
            Log Viewer <span id="log_toggle">▼</span>
        </legend>
        <div id="log_section" style="display: none;">
            <p><b>Log File:</b> <code><?php echo $logFile; ?></code></p>
            <form method="post" action="">
                <textarea style="width: 100%; height: 400px; font-family: monospace; font-size: 12px;" readonly><?php echo htmlspecialchars($logContent); ?></textarea>
                <br><br>
                <input type="submit" name="clear_log" value="Clear Log" class="buttons">
                <input type="button" value="Refresh" onclick="location.reload();" class="buttons">
            </form>
        </div>
    </fieldset>
    
    <fieldset>
        <legend onclick="toggleSection('diagnostics_section')" style="cursor: pointer;">
            Diagnostics <span id="diagnostics_toggle">▼</span>
        </legend>
        <div id="diagnostics_section" style="display: none;">
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
        </div>
    </fieldset>
</div>

<script>
// Toggle collapsible sections
function toggleSection(sectionId) {
    var section = document.getElementById(sectionId);
    var toggle = document.getElementById(sectionId.replace('_section', '_toggle'));
    if (section.style.display === 'none') {
        section.style.display = 'block';
        toggle.textContent = '▲';
    } else {
        section.style.display = 'none';
        toggle.textContent = '▼';
    }
}

// Sync custom input with dropdown
document.getElementById('device').addEventListener('change', function() {
    var selectedValue = this.value;
    var customInput = document.getElementById('device_custom');
    if (selectedValue && !Array.from(this.options).some(opt => opt.value === selectedValue && opt.text !== '<?php echo $autoDetectOption['text']; ?>')) {
        customInput.value = selectedValue;
    }
});
</script>

<script>
// Sync custom input with dropdown
document.getElementById('device').addEventListener('change', function() {
    var selectedValue = this.value;
    var customInput = document.getElementById('device_custom');
    if (selectedValue && !Array.from(this.options).some(opt => opt.value === selectedValue && opt.text !== '<?php echo $autoDetectOption['text']; ?>')) {
        customInput.value = selectedValue;
    }
});
</script>

