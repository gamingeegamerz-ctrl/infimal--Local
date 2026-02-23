#!/bin/bash
echo "=== Fixing ALL undefined variables in blade file ==="

FILE="resources/views/subscribers/index.blade.php"

if [ ! -f "$FILE" ]; then
    echo "Error: Blade file not found!"
    exit 1
fi

echo "1. Creating backup..."
cp "$FILE" "$FILE.backup.$(date +%s)"

echo "2. Fixing all variable references..."

# List of all possible variables that might be used
VARIABLES=(
    "growthRate"
    "totalSubscribers"
    "activeSubscribers"
    "unsubscribed"
    "activePercentage"
    "inactivePercentage"
    "growthPercentage"
    "subscriberCount"
    "totalCount"
    "activeCount"
    "inactiveCount"
    "bouncedCount"
    "todayCount"
    "yesterdayCount"
    "weekCount"
    "monthCount"
    "avgOpenRate"
    "avgClickRate"
    "engagementRate"
)

# Fix each variable
for var in "${VARIABLES[@]}"; do
    echo "  Fixing: \$$var"
    # Replace {{ $var }} with {{ $var ?? 0 }}
    sed -i "s/{{\s*\\\$$var\s*}}/{{ \$$var ?? 0 }}/g" "$FILE"
    # Replace {{$var}} with {{$var ?? 0}} (no spaces)
    sed -i "s/{{\\\$$var}}/{{\$$var ?? 0}}/g" "$FILE"
    # Replace {{ number_format($var) }} etc.
    sed -i "s/number_format(\\\$$var)/number_format(\$$var ?? 0)/g" "$FILE"
    sed -i "s/round(\\\$$var)/round(\$$var ?? 0)/g" "$FILE"
done

echo "3. Adding variable initialization at top..."
# Remove any existing PHP block at top
sed -i '/^@php.*$/,/^@endphp.*$/d' "$FILE"

# Add new initialization block
sed -i '1i\@php' "$FILE"
sed -i '2i\// Initialize all variables with default values' "$FILE"

for var in "${VARIABLES[@]}"; do
    sed -i "3i\\\$$var = \$$var ?? 0;" "$FILE"
done

sed -i '4i\@endphp' "$FILE"

echo "4. Checking file..."
echo "First 10 lines after fix:"
head -15 "$FILE"

echo "5. Counting variable occurrences..."
for var in "${VARIABLES[@]}"; do
    count=$(grep -c "\\\$$var" "$FILE" 2>/dev/null || echo "0")
    if [ "$count" -gt 0 ]; then
        echo "  \$$var: $count occurrences"
    fi
done

echo "=== Fix completed ==="
