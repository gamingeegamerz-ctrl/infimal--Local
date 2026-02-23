<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ImportController extends Controller
{
    public function import(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'file' => 'required|file|mimes:csv,txt',
                'list_id' => 'required|integer'
            ]);

            $file = $request->file('file');
            $listId = $request->input('list_id', 1);
            $userId = Auth::id();
            
            // Check if file exists
            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'No file uploaded'
                ], 400);
            }
            
            // Open file
            $handle = fopen($file->getRealPath(), 'r');
            if (!$handle) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot open CSV file'
                ], 400);
            }
            
            // Skip header (first line)
            fgetcsv($handle);
            
            // Initialize stats
            $stats = [
                'total' => 0,
                'imported' => 0,
                'skipped' => 0,
                'errors' => 0
            ];
            
            $batch = [];
            $batchSize = 100;
            
            // Process CSV
            while (($row = fgetcsv($handle)) !== false) {
                $stats['total']++;
                
                // Skip empty rows
                if (empty($row[0]) || trim($row[0]) === '') {
                    $stats['skipped']++;
                    continue;
                }
                
                $email = trim($row[0]);
                $firstName = isset($row[1]) ? trim($row[1]) : '';
                $lastName = isset($row[2]) ? trim($row[2]) : '';
                
                // Basic email validation
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $stats['errors']++;
                    continue;
                }
                
                // Check for duplicates
                $exists = DB::table('subscribers')
                    ->where('user_id', $userId)
                    ->where('list_id', $listId)
                    ->where('email', $email)
                    ->exists();
                    
                if ($exists) {
                    $stats['skipped']++;
                    continue;
                }
                
                // Add to batch
                $batch[] = [
                    'user_id' => $userId,
                    'list_id' => $listId,
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'status' => 'active',
                    'source' => 'csv_import',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                
                // Insert batch when full
                if (count($batch) >= $batchSize) {
                    try {
                        DB::table('subscribers')->insert($batch);
                        $stats['imported'] += count($batch);
                        $batch = [];
                    } catch (\Exception $e) {
                        $stats['errors'] += count($batch);
                        $batch = [];
                    }
                }
            }
            
            // Insert remaining records
            if (!empty($batch)) {
                try {
                    DB::table('subscribers')->insert($batch);
                    $stats['imported'] += count($batch);
                } catch (\Exception $e) {
                    $stats['errors'] += count($batch);
                }
            }
            
            fclose($handle);
            
            // Return success response
            return response()->json([
                'success' => true,
                'message' => 'Import completed successfully!',
                'total' => $stats['total'],
                'imported' => $stats['imported'],
                'skipped' => $stats['skipped'],
                'errors' => $stats['errors']
            ]);
            
        } catch (\Exception $e) {
            // Return error response
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
