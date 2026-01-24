<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestS3Connection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 's3:test-connection';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test connection to AWS S3 storage';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing S3 connection...');

        try {
            // Test basic connection by listing files in weapons directory
            $this->info('Checking if weapons directory exists...');
            $files = Storage::disk('s3')->files('weapons');

            $this->info('✓ Connection successful!');
            $this->info('Found ' . count($files) . ' files in weapons directory:');

            if (count($files) > 0) {
                foreach ($files as $file) {
                    $this->line('  - ' . $file);
                }
            } else {
                $this->warn('  No files found in weapons directory');
            }

            // Test write operation
            $this->info('Testing write operation...');
            $testFile = 'weapons/test-connection-' . now()->timestamp . '.txt';
            $testContent = 'S3 connection test - ' . now()->toDateTimeString();

            Storage::disk('s3')->put($testFile, $testContent);
            $this->info('✓ Test file created: ' . $testFile);

            // Test read operation
            $this->info('Testing read operation...');
            $readContent = Storage::disk('s3')->get($testFile);

            if ($readContent === $testContent) {
                $this->info('✓ File read successfully, content matches');
            } else {
                $this->error('✗ File content does not match');
                return Command::FAILURE;
            }

            // Clean up test file
            Storage::disk('s3')->delete($testFile);
            $this->info('✓ Test file cleaned up');

            // Get URL for a file (if any exist)
            if (count($files) > 0) {
                $sampleFile = $files[0];
                $url = Storage::disk('s3')->url($sampleFile);
                $this->info('Sample file URL: ' . $url);
            }

            $this->info('🎉 All S3 tests passed!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ S3 connection failed: ' . $e->getMessage());

            // Additional troubleshooting info
            $this->warn('Troubleshooting tips:');
            $this->warn('1. Check your AWS credentials in .env file');
            $this->warn('2. Verify AWS_BUCKET is set correctly');
            $this->warn('3. Ensure AWS region is correct');
            $this->warn('4. Check if IAM user has proper S3 permissions');

            return Command::FAILURE;
        }
    }
}
