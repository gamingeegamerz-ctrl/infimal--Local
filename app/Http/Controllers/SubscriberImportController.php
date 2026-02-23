<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Models\MailingList;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SubscriberImportController extends Controller
{
    public function import(Request $request)
    {
        // Set higher limits for large files
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        
        DB::beginTransaction();
        
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:csv,txt|max:51200',
                'list_id' => 'required|exists:mailing_lists,id',
                'skip_duplicates' => 'boolean'
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $listId = $request->input('list_id');
            $skipDuplicates = $request->input('skip_duplicates', true);
            $userId = Auth::id();
            
            // Check if user owns this list
            $list = MailingList::where('id', $listId)
                ->where('user_id', $userId)
                ->first();
            
            if (!$list) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to import to this list'
                ], 403);
            }

            Log::info('Starting CSV import', [
                'user_id' => $userId,
                'list_id' => $listId,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize()
            ]);

            // Process CSV
            $result = $this->processCSV($file->getRealPath(), $userId, $listId, $skipDuplicates);
            
            DB::commit();
            
            Log::info('CSV import completed', $result);
            
            return response()->json([
                'success' => true,
                'message' => 'Import completed successfully!',
                'total' => $result['total'],
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
                'duplicates' => $result['duplicates']
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('CSV import failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function processCSV($filePath, $userId, $listId, $skipDuplicates)
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception('Failed to open CSV file');
        }

        // Detect and skip BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $stats = [
            'total' => 0,
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0,
            'duplicates' => 0
        ];

        // Read header
        $header = fgetcsv($handle, 0, ',');
        if (!$header) {
            fclose($handle);
            throw new \Exception('Empty CSV file');
        }

        // Normalize header names
        $normalizedHeader = array_map(function($col) {
            return strtolower(trim($col));
        }, $header);

        // Find column indexes
        $emailIndex = array_search('email', $normalizedHeader);
        if ($emailIndex === false) {
            // Try other common names
            $possibleNames = ['email address', 'e-mail', 'emailaddress', 'mail'];
            foreach ($possibleNames as $name) {
                $emailIndex = array_search($name, $normalizedHeader);
                if ($emailIndex !== false) break;
            }
        }

        if ($emailIndex === false) {
            fclose($handle);
            throw new \Exception('CSV must contain an email column. Found columns: ' . implode(', ', $header));
        }

        $firstNameIndex = array_search('first_name', $normalizedHeader);
        if ($firstNameIndex === false) {
            $firstNameIndex = array_search('first name', $normalizedHeader);
        }

        $lastNameIndex = array_search('last_name', $normalizedHeader);
        if ($lastNameIndex === false) {
            $lastNameIndex = array_search('last name', $normalizedHeader);
        }

        Log::info('CSV header detected', [
            'header' => $header,
            'email_index' => $emailIndex,
            'first_name_index' => $firstNameIndex,
            'last_name_index' => $lastNameIndex
        ]);

        $batch = [];
        $batchSize = 500; // Increased for better performance
        $lineNumber = 1; // Start from 1 (header)
        
        // First, count total lines accurately
        rewind($handle);
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);
        
        $totalLines = 0;
        while (fgetcsv($handle, 0, ',') !== false) {
            $totalLines++;
        }
        $stats['total'] = max(0, $totalLines - 1); // Exclude header
        
        Log::info('CSV line count', ['total_lines' => $totalLines, 'records' => $stats['total']]);
        
        // Reset file pointer
        rewind($handle);
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);
        
        // Skip header
        fgetcsv($handle, 0, ',');
        
        // Get existing emails for duplicate check (if skipping duplicates)
        $existingEmails = [];
        if ($skipDuplicates) {
            $existingEmails = Subscriber::where('user_id', $userId)
                ->where('list_id', $listId)
                ->pluck('email')
                ->map(function($email) {
                    return strtolower(trim($email));
                })
                ->toArray();
            
            Log::info('Existing emails for duplicate check', ['count' => count($existingEmails)]);
        }

        $processedCount = 0;
        
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $lineNumber++;
            $processedCount++;
            
            // Skip empty rows
            if (empty($row[$emailIndex])) {
                $stats['skipped']++;
                continue;
            }

            $email = trim($row[$emailIndex]);
            $normalizedEmail = strtolower($email);
            
            $firstName = $firstNameIndex !== false && isset($row[$firstNameIndex]) 
                ? trim($row[$firstNameIndex]) 
                : '';
                
            $lastName = $lastNameIndex !== false && isset($row[$lastNameIndex]) 
                ? trim($row[$lastNameIndex]) 
                : '';

            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stats['errors']++;
                continue;
            }

            // Check for duplicate
            if ($skipDuplicates && in_array($normalizedEmail, $existingEmails)) {
                $stats['duplicates']++;
                continue;
            }

            // Add to existing emails list to prevent duplicates in same batch
            if ($skipDuplicates) {
                $existingEmails[] = $normalizedEmail;
            }

            // Prepare batch data
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

            // Insert batch when size reached
            if (count($batch) >= $batchSize) {
                try {
                    DB::table('subscribers')->insert($batch);
                    $stats['imported'] += count($batch);
                    $batch = [];
                } catch (\Exception $e) {
                    Log::error('Batch insert error: ' . $e->getMessage());
                    $stats['errors'] += count($batch);
                    $batch = [];
                }
            }
            
            // Progress logging every 1000 rows
            if ($processedCount % 1000 === 0) {
                Log::info('Import progress', [
                    'processed' => $processedCount,
                    'total' => $stats['total'],
                    'imported' => $stats['imported'],
                    'errors' => $stats['errors']
                ]);
            }
        }

        // Insert remaining records
        if (!empty($batch)) {
            try {
                DB::table('subscribers')->insert($batch);
                $stats['imported'] += count($batch);
            } catch (\Exception $e) {
                Log::error('Final batch insert error: ' . $e->getMessage());
                $stats['errors'] += count($batch);
            }
        }

        fclose($handle);
        
        // Update skipped count to include duplicates
        $stats['skipped'] += $stats['duplicates'];
        
        return $stats;
    }

    public function countLines(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:csv,txt'
            ]);

            $file = $request->file('file');
            $handle = fopen($file->getRealPath(), 'r');
            
            // Skip BOM
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $lineCount = 0;
            while (($data = fgetcsv($handle, 0, ',')) !== false) {
                $lineCount++;
            }
            
            fclose($handle);
            
            // Subtract header if exists
            $totalRecords = max(0, $lineCount - 1);
            
            return response()->json([
                'success' => true,
                'total_lines' => $lineCount,
                'total_records' => $totalRecords,
                'file_size' => $file->getSize(),
                'file_name' => $file->getClientOriginalName()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to count lines: ' . $e->getMessage()
            ], 500);
        }
    }
}
