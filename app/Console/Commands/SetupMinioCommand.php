<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SetupMinioCommand extends Command
{
    protected $signature   = 'app:setup-minio';
    protected $description = 'Create required MinIO buckets';

    public function handle(): int
    {
        $buckets = array_map('trim', explode(',', env('MINIO_BUCKETS', 'asip-uploads,asip-reports,asip-exports')));

        foreach ($buckets as $bucket) {
            try {
                $client = Storage::disk('s3')->getClient();

                if (!$client->doesBucketExist($bucket)) {
                    $client->createBucket(['Bucket' => $bucket]);
                    $this->info("Created bucket: {$bucket}");
                } else {
                    $this->line("Bucket exists: {$bucket}");
                }
            } catch (\Exception $e) {
                $this->error("Failed for bucket {$bucket}: " . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
