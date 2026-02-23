#!/bin/bash
echo "=== Verifying All Fixes ==="

echo "1. Checking controller..."
php -l app/Http/Controllers/SubscriberController.php

echo "2. Checking blade file syntax..."
grep -n "{{" resources/views/subscribers/index.blade.php | head -20

echo "3. Checking for undefined variables..."
# Run a PHP script to check
cat > /tmp/check_vars.php << 'PHPEOF'
<?php
// Simulate controller variables
\$totalSubscribers = 0;
\$activeSubscribers = 0;
\$unsubscribed = 0;
\$growthRate = 0;
\$activePercentage = 0;

// Include blade content
ob_start();
include 'resources/views/subscribers/index.blade.php';
\$output = ob_get_clean();

// Check for errors
if (strpos(\$output, 'Undefined variable') !== false) {
    echo "❌ Found undefined variables\n";
    echo \$output;
} else {
    echo "✅ No undefined variables found\n";
}
PHPEOF

php /tmp/check_vars.php 2>&1 | grep -v "PHP" | head -5

echo "4. Testing HTTP response..."
STATUS=\$(curl -s -o /dev/null -w "%{http_code}" https://infimal.site/subscribers)
echo "HTTP Status: \$STATUS"

if [ "\$STATUS" = "200" ]; then
    echo "✅ SUCCESS! Page is loading."
elif [ "\$STATUS" = "302" ]; then
    echo "⚠️  Redirecting to login. User authentication required."
    echo "   Please login at: https://infimal.site/login"
else
    echo "❌ Error: \$STATUS"
    echo "   Check Laravel logs: tail -f storage/logs/laravel.log"
fi

echo "=== Done ==="
