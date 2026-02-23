<?php
// App\Http\Controllers\ContactController.php

public function import(Request $request)
{
    $request->validate([
        'file' => 'required|file|mimes:csv,txt'
    ]);

    $file = $request->file('file');
    $user_id = auth()->id();
    
    // Process in batches of 100
    $batchSize = 100;
    $contacts = [];
    
    if (($handle = fopen($file->getPathname(), 'r')) !== FALSE) {
        // Skip header row
        fgetcsv($handle);
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $contacts[] = [
                'first_name' => $data[0] ?? '',
                'last_name' => $data[1] ?? '',
                'email' => $data[2] ?? '',
                'phone' => $data[3] ?? '',
                'company' => $data[4] ?? '',
                'status' => 'active',
                'user_id' => $user_id,
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            // Insert in batches
            if (count($contacts) >= $batchSize) {
                \DB::table('contacts')->insert($contacts);
                $contacts = []; // Reset array
            }
        }
        
        // Insert remaining contacts
        if (!empty($contacts)) {
            \DB::table('contacts')->insert($contacts);
        }
        
        fclose($handle);
    }
    
    return back()->with('success', 'Contacts imported successfully!');
}
