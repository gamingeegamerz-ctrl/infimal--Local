#!/bin/bash
# Backup
cp routes/web.php routes/web.php.bak

# Replace 'admin' middleware with simple check
sed -i "s/Route::middleware(\['auth', 'admin'\])/Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {\n    \$user = auth()->user();\n    \$adminEmails = ['admin@infimal.site', 'contact@infimal.site', 'sainikhilsaini143@gmail.com', 'khileshrathod1729@gmail.com', 'admin@infimal.com'];\n    if (!in_array(\$user->email, \$adminEmails)) {\n        abort(403, 'Admin access required.');\n    }/g" routes/web.php

echo "Routes fixed!"
