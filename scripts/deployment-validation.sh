#!/bin/bash

echo "🚀 GENEALOGY APPLICATION DEPLOYMENT VALIDATION"
echo "=============================================="
echo ""

# Function to check URL response
check_url() {
    local url=$1
    local expected_status=${2:-200}
    
    echo -n "Checking $url... "
    
    if command -v curl >/dev/null 2>&1; then
        status=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>/dev/null)
        if [[ "$status" == "$expected_status" ]]; then
            echo "✅ OK ($status)"
            return 0
        else
            echo "❌ FAILED ($status, expected $expected_status)"
            return 1
        fi
    else
        echo "⚠️  curl not available, skipping URL check"
        return 0
    fi
}

# Function to check database connection
check_database() {
    echo -n "Checking database connection... "
    if php artisan tinker --execute="DB::connection()->getPdo(); echo 'Connected';" 2>/dev/null | grep -q "Connected"; then
        echo "✅ Database connected"
        return 0
    else
        echo "❌ Database connection failed"
        return 1
    fi
}

# Function to check security features
check_security() {
    echo "🛡️ SECURITY FEATURE VALIDATION"
    echo "------------------------------"
    
    # Check if security middleware is loaded
    echo -n "Security monitoring middleware... "
    if php artisan route:list | grep -q "SecurityMonitoring"; then
        echo "✅ Active"
    else
        echo "❌ Not active"
    fi
    
    # Check if security headers middleware is loaded
    echo -n "Security headers middleware... "
    if php artisan route:list | grep -q "SecurityHeaders"; then
        echo "✅ Active"
    else
        echo "❌ Not active"
    fi
    
    # Check if audit logging is working
    echo -n "Audit logging system... "
    if php artisan tinker --execute="App\Models\AuditLog::count(); echo 'Working';" 2>/dev/null | grep -q "Working"; then
        echo "✅ Working"
    else
        echo "❌ Not working"
    fi
}

# Function to check user testing requirements
check_user_testing() {
    echo "👥 USER TESTING READINESS"
    echo "------------------------"
    
    # Check if demo data exists
    echo -n "Demo users available... "
    user_count=$(php artisan tinker --execute="echo App\Models\User::count();" 2>/dev/null | grep -o '[0-9]\+' | tail -1)
    if [[ "$user_count" -gt 0 ]]; then
        echo "✅ $user_count users available"
    else
        echo "❌ No demo users found"
    fi
    
    # Check if demo teams exist
    echo -n "Demo teams available... "
    team_count=$(php artisan tinker --execute="echo App\Models\Team::count();" 2>/dev/null | grep -o '[0-9]\+' | tail -1)
    if [[ "$team_count" -gt 0 ]]; then
        echo "✅ $team_count teams available"
    else
        echo "❌ No demo teams found"
    fi
    
    # Check if demo persons exist
    echo -n "Demo genealogy data... "
    person_count=$(php artisan tinker --execute="echo App\Models\Person::count();" 2>/dev/null | grep -o '[0-9]\+' | tail -1)
    if [[ "$person_count" -gt 0 ]]; then
        echo "✅ $person_count persons available"
    else
        echo "❌ No demo genealogy data found"
    fi
}

echo "1. APPLICATION HEALTH CHECK"
echo "---------------------------"

# Determine the application URL
if [[ -n "$APP_URL" ]]; then
    base_url="$APP_URL"
elif docker compose ps | grep -q "genealogy.*app" 2>/dev/null; then
    base_url="http://localhost:8080"
else
    base_url="http://localhost:8000"
fi

echo "Testing application at: $base_url"
echo ""

# Check main application endpoints
check_url "$base_url" 200
check_url "$base_url/login" 200
check_url "$base_url/register" 200
check_url "$base_url/dashboard" 302  # Should redirect to login

echo ""
echo "2. DATABASE CONNECTIVITY"
echo "------------------------"
check_database

echo ""
check_security

echo ""
check_user_testing

echo ""
echo "🎯 DEPLOYMENT VALIDATION COMPLETE"
echo "================================="

# Summary
echo ""
echo "📋 NEXT STEPS FOR USER TESTING:"
echo ""
echo "1. ✅ Access the application at: $base_url"
echo "2. ✅ Register new test accounts or use demo accounts"
echo "3. ✅ Test genealogy features (add persons, create families)"
echo "4. ✅ Test security features (team isolation, permissions)"
echo "5. ✅ Test GEDCOM import/export functionality"
echo "6. ✅ Verify backup and restore functionality"
echo ""
echo "🔧 MONITORING COMMANDS:"
echo "  • View application logs: docker compose logs -f app"
echo "  • Monitor security events: tail -f storage/logs/security.log"
echo "  • Check audit logs: php artisan audit:review"
echo ""
echo "🚨 EMERGENCY PROCEDURES:"
echo "  • Stop application: docker compose down"
echo "  • View error logs: docker compose logs app | grep ERROR"
echo "  • Database backup: php artisan backup:run"