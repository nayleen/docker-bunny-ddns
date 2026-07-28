<?php

declare(strict_types = 1);

namespace BunnyDdns\Tests\Unit;

use Amp\File;
use Amp\Process\Process;
use BunnyDdns\Health;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Safe;

use function Amp\ByteStream\buffer;
use function Amp\delay;

/**
 * @internal
 */
final class HealthTest extends TestCase
{
    /**
     * @var non-empty-string
     */
    private string $filePath;

    protected function setUp(): void
    {
        $this->filePath = sys_get_temp_dir() . '/health-' . bin2hex(random_bytes(4)) . '.json';
    }

    protected function tearDown(): void
    {
        foreach ([$this->filePath, Health::FILE_PATH] as $filePath) {
            if (File\exists($filePath)) {
                File\deleteFile($filePath);
            }
        }
    }

    private function health(float $timeout = Health::MIN_TIMEOUT): Health
    {
        return new Health($timeout, $this->filePath);
    }

    /**
     * @return array{status: non-empty-string, ip: non-empty-string|null, zones: list<non-empty-string>}
     */
    private function published(): array
    {
        $document = Health::query($this->filePath);
        self::assertNotNull($document);

        return $document;
    }

    #[Test]
    public function a_stale_heartbeat_reports_unhealthy(): void
    {
        $this->health(timeout: 0.1)->update(Health::STATUS_HEALTHY, '203.0.113.1', ['example.com']);

        self::assertSame(Health::STATUS_HEALTHY, $this->published()['status']);

        delay(0.2);

        self::assertSame([
            'status' => Health::STATUS_UNHEALTHY,
            'ip' => '203.0.113.1',
            'zones' => ['example.com'],
        ], $this->published());
    }

    #[Test]
    public function another_process_reads_the_published_state(): void
    {
        new Health()->update(Health::STATUS_HEALTHY, '203.0.113.1', ['example.com']);

        $probe = [PHP_BINARY, dirname(__DIR__, 2) . '/app.php', 'healthcheck'];
        $environment = [
            'API_KEY' => '00000000-0000-0000-0000-00000000000000000000-0000-0000-0000-000000000000',
            'ZONES' => 'example.com',
        ];

        $process = Process::start($probe, environment: $environment);
        $output = buffer($process->getStdout());

        self::assertSame(0, $process->join());
        self::assertStringContainsString('"status": "healthy"', $output);
        self::assertStringContainsString('"ip": "203.0.113.1"', $output);

        File\deleteFile(Health::FILE_PATH);

        $process = Process::start($probe, environment: $environment);
        $output = buffer($process->getStdout());

        self::assertSame(1, $process->join());
        self::assertSame(['status' => 'unknown'], Safe\json_decode(trim($output), true));
    }

    #[Test]
    public function each_update_extends_the_expiry(): void
    {
        $health = $this->health(timeout: 0.3);
        $health->update(Health::STATUS_HEALTHY);

        delay(0.1);
        $health->update(Health::STATUS_HEALTHY);
        delay(0.25);

        self::assertSame(Health::STATUS_HEALTHY, $this->published()['status']);
    }

    #[Test]
    public function non_healthy_statuses_do_not_expire(): void
    {
        $this->health(timeout: 0.1)->update(Health::STATUS_STARTING);

        delay(0.2);

        self::assertSame(Health::STATUS_STARTING, $this->published()['status']);
    }

    #[Test]
    public function timeout_for_interval_allows_two_missed_checks(): void
    {
        self::assertSame(60.0, Health::timeoutForInterval(5));
        self::assertSame(60.0, Health::timeoutForInterval(30));
        self::assertSame(1800.0, Health::timeoutForInterval(900));
    }

    #[Test]
    public function updates_replace_the_published_state(): void
    {
        $health = $this->health();
        $health->update(Health::STATUS_HEALTHY, '203.0.113.1', ['example.com']);

        self::assertSame([
            'status' => Health::STATUS_HEALTHY,
            'ip' => '203.0.113.1',
            'zones' => ['example.com'],
        ], $this->published());

        $health->update(Health::STATUS_UNHEALTHY);

        self::assertSame([
            'status' => Health::STATUS_UNHEALTHY,
            'ip' => null,
            'zones' => [],
        ], $this->published());
    }
}
