<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    /** Folder jurnal webhook khusus test (bukan storage/logs proyek), dikosongkan tiap test. */
    protected string $webhookJournalDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->webhookJournalDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mabarku-test-webhooks-'.getmypid();
        File::deleteDirectory($this->webhookJournalDir);
        config(['logging.webhook_journal_path' => $this->webhookJournalDir]);
    }
}
