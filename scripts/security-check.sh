#!/bin/bash
echo "🛡️ Running comprehensive security checks..."

# Composer security audit
echo "📦 Checking Composer dependencies..."
composer audit --no-dev --format=table

# NPM security audit  
echo "📦 Checking NPM dependencies..."
npm audit --audit-level=moderate

# Check for common security misconfigurations
echo "🔧 Checking Laravel configuration..."
if grep -r "APP_DEBUG=true" .env* 2>/dev/null; then
    echo "⚠️  WARNING: APP_DEBUG is enabled"
fi

if grep -r "APP_ENV=production" .env* 2>/dev/null; then
    if grep -r "APP_DEBUG=true" .env* 2>/dev/null; then
        echo "🚨 CRITICAL: Debug mode enabled in production!"
    fi
fi

echo "✅ Security check complete"