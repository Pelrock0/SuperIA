<?php

namespace Tests\Feature;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SecurityGatesIntegrationTest extends TestCase
{
    public function test_composer_security_exits_zero(): void
    {
        $composer = (new ExecutableFinder())->find('composer');

        if ($composer === null) {
            $this->markTestSkipped('composer binary not available in test environment.');
        }

        $process = new Process(
            command: [$composer, 'security'],
            cwd: base_path(),
            timeout: 180,
        );

        $process->run();

        $this->assertSame(
            0,
            $process->getExitCode(),
            "composer security failed with exit code {$process->getExitCode()}.\n"
            ."STDOUT:\n{$process->getOutput()}\n"
            ."STDERR:\n{$process->getErrorOutput()}",
        );
    }
}
