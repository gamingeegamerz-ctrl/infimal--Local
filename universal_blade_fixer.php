<?php
// Universal blade file fixer
$file = 'resources/views/subscribers/index.blade.php';
$content = file_get_contents($file);

// Find all variables used in blade template
preg_match_all('/\{\{\s*\\$(\w+)\s*\}\}/', $content, $matches);
$variables = array_unique($matches[1]);

// Also find variables in functions
preg_match_all('/\{\{\s*(?:number_format|round|ceil|floor)\s*\(\\$(\w+)\)\s*\}\}/', $content, $funcMatches);
$variables = array_merge($variables, $funcMatches[1]);
$variables = array_unique($variables);

echo "Found variables: " . implode(', ', $variables) . "\n";

// Add initialization at top
$initCode = "@php\n";
foreach ($variables as $var) {
    $initCode .= "\${$var} = \${$var} ?? 0;\n";
    // Also fix references in content
    $content = preg_replace('/\{\{\s*\\$' . $var . '\s*\}\}/', '{{ $' . $var . ' ?? 0 }}', $content);
    $content = preg_replace('/\{\{\s*(number_format|round|ceil|floor)\s*\(\\$' . $var . '\)\s*\}\}/', '{{ $1($' . $var . ' ?? 0) }}', $content);
}
$initCode .= "@endphp\n\n";

// Insert at beginning
$content = $initCode . $content;

file_put_contents($file, $content);
echo "File fixed successfully!\n";
