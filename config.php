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

// Handle form submission
$message = "";
$messageType = "";

if (isset($_POST['save_config'])) {
    $device = isset($_POST['device']) ? trim($_POST['device']) : "";
    
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
    
    if (file_put_contents($configFile, $config)) {
        if (empty($message)) {
            $message = "Configuration saved successfully.";
            $messageType = "success";
        }
        log_message("Configuration updated. Device: " . ($device ? $device : "Auto-detect"));
    } else {
        $message = "Error: Could not save configuration file.";
        $messageType = "error";
    }
}

// Load current configuration
$currentDevice = "";
if (file_exists($configFile)) {
    $config = parse_ini_file($configFile);
    if (isset($config['device']) && !empty($config['device'])) {
        $currentDevice = $config['device'];
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
                </table>
            </fieldset>
            
            <br>
            <input type="submit" name="save_config" value="Save Configuration" class="buttons">
            <input type="button" value="Refresh Device List" onclick="location.reload();" class="buttons">
        </form>
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
        <legend>Current Configuration</legend>
        <p><b>Config File:</b> <code><?php echo $configFile; ?></code></p>
        <p><b>Current Device Setting:</b> 
            <code><?php echo empty($currentDevice) ? 'Auto-detect' : htmlspecialchars($currentDevice); ?></code>
        </p>
        <?php if (file_exists($configFile)): ?>
            <pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd;"><?php echo htmlspecialchars(file_get_contents($configFile)); ?></pre>
        <?php else: ?>
            <p><i>No configuration file found. Using default (auto-detect).</i></p>
        <?php endif; ?>
    </fieldset>
</div>

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

