<?php

namespace App\Services\Receipts;

use Symfony\Component\Process\Process;

class ReceiptOcr
{
    public function read(string $path): string
    {
        $process = new Process(['/usr/bin/tesseract', $path, 'stdout', '-l', 'eng+rus', '--psm', '6'], null, ['OMP_THREAD_LIMIT' => '1']);
        $process->setTimeout(35);
        $process->mustRun();

        return trim($process->getOutput());
    }
}
