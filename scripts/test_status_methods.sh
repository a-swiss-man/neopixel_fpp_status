#!/bin/bash

# Test script to determine which method works for getting FPP status
# Run this to see what's available on your system

echo "Testing FPP Status Detection Methods..."
echo "========================================"
echo ""

# Test 1: FPP command line tool
echo "Method 1: FPP Command Line Tool"
if command -v fpp >/dev/null 2>&1; then
    echo "  ✓ fpp command found"
    echo "  Testing: fpp -s"
    fpp -s 2>&1 | head -5
    echo "  Testing: fpp --status"
    fpp --status 2>&1 | head -5
else
    echo "  ✗ fpp command not found"
fi
echo ""

# Test 2: REST API
echo "Method 2: FPP REST API"
for port in 80 32320; do
    for host in localhost 127.0.0.1; do
        echo "  Testing: http://${host}:${port}/api/fppd/status"
        STATUS=$(curl -s "http://${host}:${port}/api/fppd/status" 2>&1)
        if [ -n "$STATUS" ] && [ "$STATUS" != "null" ] && [ "$STATUS" != "{}" ] && ! echo "$STATUS" | grep -q "Connection refused\|Could not resolve"; then
            echo "  ✓ SUCCESS on ${host}:${port}"
            echo "$STATUS" | head -10
            break 2
        else
            echo "  ✗ Failed on ${host}:${port}"
        fi
    done
done
echo ""

# Test 3: Status file
echo "Method 3: Status File"
STATUS_FILE="/home/fpp/media/status"
if [ -f "$STATUS_FILE" ]; then
    echo "  ✓ Status file found: $STATUS_FILE"
    echo "  Contents:"
    head -10 "$STATUS_FILE"
else
    echo "  ✗ Status file not found: $STATUS_FILE"
fi
echo ""

# Test 4: Process check
echo "Method 4: Process Check"
if pgrep -f "fppd" >/dev/null 2>&1; then
    echo "  ✓ fppd process is running"
    pgrep -f "fppd" | head -3
else
    echo "  ✗ fppd process not found"
fi
echo ""

# Test 5: Check for other status indicators
echo "Method 5: Other Status Indicators"
echo "  Checking /home/fpp/media/ for status files:"
find /home/fpp/media -maxdepth 2 -name "*status*" -o -name "*playlist*" 2>/dev/null | head -5
echo ""

echo "========================================"
echo "Test complete. Use the method that shows ✓ SUCCESS"

