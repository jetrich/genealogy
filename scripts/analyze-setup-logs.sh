#!/bin/bash

# Analyze Setup Logs - Helper script to review setup attempt logs
# Makes it easy to understand what went wrong during setup

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log() {
    local level=$1
    shift
    local message="$*"
    
    case $level in
        "INFO")
            echo -e "${BLUE}[INFO]${NC} $message"
            ;;
        "SUCCESS")
            echo -e "${GREEN}[SUCCESS]${NC} $message"
            ;;
        "WARNING")
            echo -e "${YELLOW}[WARNING]${NC} $message"
            ;;
        "ERROR")
            echo -e "${RED}[ERROR]${NC} $message"
            ;;
    esac
}

echo "📊 Setup Log Analyzer"
echo "====================="
echo ""

# Find log files
LOG_DIR="logs/setup"

if [[ ! -d "$LOG_DIR" ]]; then
    log "ERROR" "Setup log directory not found: $LOG_DIR"
    log "INFO" "Run ./scripts/quick-setup.sh first to generate logs"
    exit 1
fi

# Find the most recent logs
LATEST_LOG=$(find "$LOG_DIR" -name "quick-setup-*.log" -type f -printf '%T@ %p\n' | sort -n | tail -1 | cut -d' ' -f2-)
LATEST_ERROR_LOG=$(find "$LOG_DIR" -name "errors-*.log" -type f -printf '%T@ %p\n' | sort -n | tail -1 | cut -d' ' -f2-)

if [[ -z "$LATEST_LOG" ]]; then
    log "ERROR" "No setup logs found in $LOG_DIR"
    exit 1
fi

log "INFO" "Analyzing latest setup attempt..."
log "INFO" "Main log: $LATEST_LOG"

if [[ -n "$LATEST_ERROR_LOG" && -f "$LATEST_ERROR_LOG" ]]; then
    log "INFO" "Error log: $LATEST_ERROR_LOG"
else
    log "SUCCESS" "No error log found - setup may have completed successfully"
fi

echo ""

# Analyze main log
echo "📋 SETUP ATTEMPT SUMMARY"
echo "========================"

# Get setup start/end times
START_TIME=$(head -1 "$LATEST_LOG" | grep -oP '\[\K[0-9-]+ [0-9:]+' || echo "Unknown")
END_TIME=$(tail -1 "$LATEST_LOG" | grep -oP '\[\K[0-9-]+ [0-9:]+' || echo "Unknown")

echo "🕐 Setup Duration:"
echo "   Started: $START_TIME"
echo "   Ended: $END_TIME"

echo ""
echo "📊 Step Analysis:"

# Count steps and their status
for step in {1..7}; do
    step_line=$(grep "Step $step/" "$LATEST_LOG" | head -1 || echo "")
    if [[ -n "$step_line" ]]; then
        step_desc=$(echo "$step_line" | cut -d':' -f3- | xargs)
        echo "   Step $step: $step_desc"
        
        # Check if step had errors
        if grep -q "Step $step/" "$LATEST_LOG" && grep -A 20 "Step $step/" "$LATEST_LOG" | grep -q "\[ERROR\]"; then
            echo "     ❌ Had errors"
        else
            echo "     ✅ Completed"
        fi
    fi
done

echo ""

# System information
echo "🖥️  SYSTEM INFORMATION"
echo "====================="

if grep -q "SYSTEM INFORMATION" "$LATEST_LOG"; then
    grep -A 10 "SYSTEM INFORMATION" "$LATEST_LOG" | grep -E "(PHP Version|Composer Version|Node Version|Docker Version)" | while read -r line; do
        info=$(echo "$line" | cut -d']' -f3- | xargs)
        echo "   $info"
    done
else
    echo "   System information not found in log"
fi

echo ""

# Error analysis
echo "🚨 ERROR ANALYSIS"
echo "=================="

error_count=$(grep -c "\[ERROR\]" "$LATEST_LOG" 2>/dev/null || echo "0")
warning_count=$(grep -c "\[WARNING\]" "$LATEST_LOG" 2>/dev/null || echo "0")

echo "📊 Error Statistics:"
echo "   Errors: $error_count"
echo "   Warnings: $warning_count"

if [[ "$error_count" -gt 0 ]]; then
    echo ""
    echo "🔍 Recent Errors:"
    grep "\[ERROR\]" "$LATEST_LOG" | tail -5 | while read -r line; do
        timestamp=$(echo "$line" | grep -oP '\[\K[0-9-]+ [0-9:]+')
        error_msg=$(echo "$line" | cut -d']' -f3- | xargs)
        echo "   [$timestamp] $error_msg"
    done
fi

if [[ "$warning_count" -gt 0 ]]; then
    echo ""
    echo "⚠️  Recent Warnings:"
    grep "\[WARNING\]" "$LATEST_LOG" | tail -3 | while read -r line; do
        timestamp=$(echo "$line" | grep -oP '\[\K[0-9-]+ [0-9:]+')
        warning_msg=$(echo "$line" | cut -d']' -f3- | xargs)
        echo "   [$timestamp] $warning_msg"
    done
fi

echo ""

# Common issues detection
echo "🔍 COMMON ISSUES DETECTION"
echo "=========================="

issues_found=0

# Check for DOM extension issues
if grep -q "DOMDocument" "$LATEST_LOG"; then
    log "WARNING" "DOM extension issues detected"
    issues_found=$((issues_found + 1))
fi

# Check for Composer issues
if grep -q "composer.*failed\|Composer.*failed" "$LATEST_LOG"; then
    log "WARNING" "Composer installation issues detected"
    issues_found=$((issues_found + 1))
fi

# Check for database issues
if grep -q "database.*error\|migration.*failed" "$LATEST_LOG"; then
    log "WARNING" "Database setup issues detected"
    issues_found=$((issues_found + 1))
fi

# Check for permission issues
if grep -q "permission denied\|Permission denied" "$LATEST_LOG"; then
    log "WARNING" "Permission issues detected"
    issues_found=$((issues_found + 1))
fi

# Check for missing dependencies
if grep -q "command not found\|not found" "$LATEST_LOG"; then
    log "WARNING" "Missing dependencies detected"
    issues_found=$((issues_found + 1))
fi

if [[ "$issues_found" -eq 0 ]]; then
    log "SUCCESS" "No common issues detected"
fi

echo ""

# Recommendations
echo "💡 RECOMMENDATIONS"
echo "=================="

if [[ "$error_count" -gt 0 ]]; then
    echo "🔧 Based on the errors found:"
    echo ""
    
    if grep -q "sudo\|permission" "$LATEST_LOG"; then
        echo "   1. ✅ Run with proper sudo access:"
        echo "      sudo ./scripts/quick-setup.sh"
    fi
    
    if grep -q "DOM\|xml" "$LATEST_LOG"; then
        echo "   2. ✅ Fix PHP extensions manually:"
        echo "      sudo apt install php8.4-dom php8.4-xml"
    fi
    
    if grep -q "composer" "$LATEST_LOG"; then
        echo "   3. ✅ Clear Composer cache:"
        echo "      composer clear-cache"
    fi
    
    if grep -q "npm\|node" "$LATEST_LOG"; then
        echo "   4. ✅ Install Node.js:"
        echo "      curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -"
        echo "      sudo apt-get install -y nodejs"
    fi
else
    echo "✅ Setup appears to have completed successfully!"
    echo ""
    echo "🚀 Next steps:"
    echo "   1. Start the application: php artisan serve"
    echo "   2. Visit: http://localhost:8000"
    echo "   3. Test the genealogy features"
fi

echo ""
echo "📋 LOG FILE LOCATIONS"
echo "===================="
echo "   Main log: $LATEST_LOG"
if [[ -n "$LATEST_ERROR_LOG" && -f "$LATEST_ERROR_LOG" ]]; then
    echo "   Error log: $LATEST_ERROR_LOG"
fi

echo ""
echo "🔍 To view detailed logs:"
echo "   less $LATEST_LOG"
if [[ -n "$LATEST_ERROR_LOG" && -f "$LATEST_ERROR_LOG" ]]; then
    echo "   less $LATEST_ERROR_LOG"
fi

echo ""
log "INFO" "Log analysis completed"