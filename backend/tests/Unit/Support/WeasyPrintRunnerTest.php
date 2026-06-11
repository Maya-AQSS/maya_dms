<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\WeasyPrintRunner;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests for WeasyPrintRunner.
 *
 * All tests use Process::fake() — no real weasyprint binary required.
 */
final class WeasyPrintRunnerTest extends TestCase
{
    // ── Happy path: stdout ────────────────────────────────────────────────────

    public function test_run_returns_pdf_bytes_from_stdout(): void
    {
        Process::fake([
            '*' => Process::result(output: '%PDF-1.7 bytes'),
        ]);

        $runner = new WeasyPrintRunner;
        $result = $runner->run('<html/>', 60);

        // Process::result() appends \n in the fake; assertContains is consistent
        // with how ThemePreviewTest validates weasyprint byte output.
        $this->assertStringContainsString('%PDF-1.7 bytes', $result);
    }

    public function test_run_passes_html_via_stdin(): void
    {
        Process::fake([
            '*' => Process::result(output: '%PDF-ok'),
        ]);

        $runner = new WeasyPrintRunner;
        $runner->run('<p>Hola</p>', 60);

        Process::assertRan(function ($process): bool {
            return $process->input === '<p>Hola</p>';
        });
    }

    public function test_run_uses_correct_weasyprint_flags(): void
    {
        Process::fake([
            '*' => Process::result(output: '%PDF-ok'),
        ]);

        $runner = new WeasyPrintRunner;
        $runner->run('<html/>', 60);

        Process::assertRan(function ($process): bool {
            $cmd = $process->command;

            return $cmd[0] === 'weasyprint'
                && in_array('--encoding', $cmd, true)
                && in_array('utf-8', $cmd, true)
                && in_array('--pdf-variant', $cmd, true)
                && in_array('pdf/ua-1', $cmd, true)
                && in_array('-', $cmd, true); // stdin
        });
    }

    // ── Timeout parametrization ───────────────────────────────────────────────

    public function test_run_respects_custom_timeout(): void
    {
        Process::fake([
            '*' => Process::result(output: '%PDF-ok'),
        ]);

        $runner = new WeasyPrintRunner;
        $runner->run('<html/>', 30);

        Process::assertRan(function ($process): bool {
            return $process->timeout === 30;
        });
    }

    public function test_run_respects_sixty_second_timeout(): void
    {
        Process::fake([
            '*' => Process::result(output: '%PDF-ok'),
        ]);

        $runner = new WeasyPrintRunner;
        $runner->run('<html/>', 60);

        Process::assertRan(function ($process): bool {
            return $process->timeout === 60;
        });
    }

    // ── Output target parametrization ─────────────────────────────────────────

    public function test_run_uses_stdout_dash_as_default_output(): void
    {
        Process::fake([
            '*' => Process::result(output: '%PDF-ok'),
        ]);

        $runner = new WeasyPrintRunner;
        $runner->run('<html/>', 60);

        Process::assertRan(function ($process): bool {
            $cmd = $process->command;
            // Last two args: stdin='-' and output='-'
            return end($cmd) === '-';
        });
    }

    public function test_run_returns_empty_string_when_output_is_file_path(): void
    {
        Process::fake([
            '*' => Process::result(output: ''),
        ]);

        $runner = new WeasyPrintRunner;
        $result = $runner->run('<html/>', 60, '/tmp/out.pdf');

        $this->assertSame('', $result);
    }

    public function test_run_passes_file_path_as_last_command_argument(): void
    {
        Process::fake([
            '*' => Process::result(output: ''),
        ]);

        $runner = new WeasyPrintRunner;
        $runner->run('<html/>', 60, '/tmp/out.pdf');

        Process::assertRan(function ($process): bool {
            $cmd = $process->command;

            return end($cmd) === '/tmp/out.pdf';
        });
    }

    // ── Error handling ────────────────────────────────────────────────────────

    public function test_run_throws_runtime_exception_when_process_fails(): void
    {
        Process::fake([
            '*' => Process::result(output: '', exitCode: 1, errorOutput: 'error from weasyprint'),
        ]);

        $this->expectException(RuntimeException::class);

        $runner = new WeasyPrintRunner;
        $runner->run('<html/>', 60);
    }

    public function test_exception_message_contains_caller_context(): void
    {
        Process::fake([
            '*' => Process::result(output: '', exitCode: 1, errorOutput: 'stderr output'),
        ]);

        $runner = new WeasyPrintRunner;

        try {
            $runner->run('<html/>', 60, '-', 'plantilla abc-123');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('plantilla abc-123', $e->getMessage());
            $this->assertStringContainsString('stderr output', $e->getMessage());
        }
    }

    public function test_exception_message_without_caller_context(): void
    {
        Process::fake([
            '*' => Process::result(output: '', exitCode: 1, errorOutput: 'stderr output'),
        ]);

        $runner = new WeasyPrintRunner;

        try {
            $runner->run('<html/>', 60);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('stderr output', $e->getMessage());
        }
    }

    // ── Edge cases ────────────────────────────────────────────────────────────

    public function test_run_with_empty_html_does_not_throw_if_process_succeeds(): void
    {
        Process::fake([
            '*' => Process::result(output: '%PDF-empty'),
        ]);

        $runner = new WeasyPrintRunner;
        $result = $runner->run('', 60);

        $this->assertStringContainsString('%PDF-empty', $result);
    }

    public function test_run_returns_binary_bytes_unchanged(): void
    {
        // Use printable marker to avoid encoding issues in fake output assertion.
        $marker = '%PDF-binary-marker';

        Process::fake([
            '*' => Process::result(output: $marker),
        ]);

        $runner = new WeasyPrintRunner;
        $result = $runner->run('<html/>', 60);

        $this->assertStringContainsString($marker, $result);
    }
}
