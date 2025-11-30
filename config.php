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

