<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class EnsureNodeServicesAlive extends Command
{
    protected $signature = 'node-services:ensure-alive
                            {--cooldown=45 : Minimum seconds between full PM2 checks}
                            {--dry-run : Report what would be restarted without changing PM2}';

    protected $description = 'Ensure PM2 managed Node services are online with a single lightweight health check';

    private const NODE_VERSION = 'v20.20.2';

    public function handle(): int
    {
        if (!$this->acquireLock()) {
            $this->line('Node service health check already running.');
            return Command::SUCCESS;
        }

        if ($this->ranRecently((int) $this->option('cooldown'))) {
            $this->line('Node service health check skipped by cooldown.');
            return Command::SUCCESS;
        }

        $home = $this->homePath();
        $binDir = env('NODE_SERVICES_BIN_DIR', "{$home}/.nvm/versions/node/" . self::NODE_VERSION . '/bin');
        $pm2 = env('NODE_SERVICES_PM2_BIN', "{$binDir}/pm2");

        if (!File::exists($pm2)) {
            Log::warning("Node service health check skipped: PM2 binary not found at {$pm2}");
            $this->warn("PM2 binary not found at {$pm2}");
            return Command::SUCCESS;
        }

        $services = $this->services();
        $processes = $this->pm2Processes($pm2, $binDir, $home);
        $changed = false;

        foreach ($services as $service) {
            $status = $this->serviceStatus($processes, $service['name']);

            if ($status === 'online') {
                $this->line("{$service['name']} is online.");
                continue;
            }

            if (!File::isDirectory($service['cwd'])) {
                Log::warning("Node service health check skipped {$service['name']}: directory not found at {$service['cwd']}");
                $this->warn("{$service['name']} directory not found: {$service['cwd']}");
                continue;
            }

            Log::warning("Node service {$service['name']} is not online; attempting PM2 start.", [
                'status' => $status,
                'cwd' => $service['cwd'],
            ]);

            $this->warn("{$service['name']} is " . ($status ?: 'missing') . '; starting with PM2.');

            if (!$this->option('dry-run')) {
                $result = $this->runProcess([$pm2, 'start', 'ecosystem.config.cjs', '--update-env'], $service['cwd'], $binDir, $home);

                if (!$result['ok']) {
                    Log::error("Node service {$service['name']} PM2 start failed.", [
                        'exit_code' => $result['exit_code'],
                        'output' => $result['output'],
                    ]);
                    $this->error("Failed to start {$service['name']}.");
                    continue;
                }

                $changed = true;
            }
        }

        if ($changed && !$this->option('dry-run')) {
            $this->runProcess([$pm2, 'save', '--force'], null, $binDir, $home);
        }

        $this->touchLastRun();

        return Command::SUCCESS;
    }

    private function services(): array
    {
        return [
            [
                'name' => env('XSENDER_PM2_NAME', 'xsender-whatsapp'),
                'cwd' => env('XSENDER_WHATSAPP_SERVICE_PATH', base_path('../xsender-whatsapp-service')),
            ],
            [
                'name' => env('WA_SERVER_PM2_NAME', 'wa-server'),
                'cwd' => env('WA_SERVER_SERVICE_PATH', base_path('../../wa-server/wa-server-js')),
            ],
        ];
    }

    private function pm2Processes(string $pm2, string $binDir, string $home): array
    {
        $result = $this->runProcess([$pm2, 'jlist'], null, $binDir, $home);

        if (!$result['ok']) {
            Log::warning('Node service health check could not read PM2 process list.', [
                'exit_code' => $result['exit_code'],
                'output' => $result['output'],
            ]);
            return [];
        }

        $jsonStart = strpos($result['output'], '[');

        if ($jsonStart === false) {
            Log::warning('Node service health check received an unexpected PM2 process list.', [
                'output' => $result['output'],
            ]);
            return [];
        }

        $processes = json_decode(substr($result['output'], $jsonStart), true);

        return is_array($processes) ? $processes : [];
    }

    private function serviceStatus(array $processes, string $name): ?string
    {
        foreach ($processes as $process) {
            if (($process['name'] ?? null) === $name) {
                return $process['pm2_env']['status'] ?? null;
            }
        }

        return null;
    }

    private function runProcess(array $command, ?string $cwd, string $binDir, string $home): array
    {
        $path = $binDir . PATH_SEPARATOR . (getenv('PATH') ?: '');
        $process = new Process($command, $cwd, [
            'HOME' => $home,
            'PM2_HOME' => "{$home}/.pm2",
            'PATH' => $path,
        ]);

        $process->setTimeout(25);
        $process->run();

        return [
            'ok' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode(),
            'output' => trim($process->getOutput() . $process->getErrorOutput()),
        ];
    }

    private function homePath(): string
    {
        $configured = env('NODE_SERVICES_HOME');

        if ($configured) {
            return rtrim($configured, '/\\');
        }

        $home = getenv('HOME');

        if ($home) {
            return rtrim($home, '/\\');
        }

        $basePath = str_replace('\\', '/', base_path());
        $domainsPosition = strpos($basePath, '/domains/');

        if ($domainsPosition !== false) {
            return substr($basePath, 0, $domainsPosition);
        }

        return rtrim(dirname(base_path()), '/\\');
    }

    private function acquireLock(): bool
    {
        $lockFile = $this->lockFile();
        File::ensureDirectoryExists(dirname($lockFile));

        $handle = fopen($lockFile, 'c');

        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            return false;
        }

        app()->instance('node-services-lock-handle', $handle);

        return true;
    }

    private function ranRecently(int $cooldown): bool
    {
        if ($cooldown <= 0) {
            return false;
        }

        $lastRunFile = $this->lastRunFile();

        return File::exists($lastRunFile) && (time() - File::lastModified($lastRunFile)) < $cooldown;
    }

    private function touchLastRun(): void
    {
        File::ensureDirectoryExists(dirname($this->lastRunFile()));
        File::put($this->lastRunFile(), (string) time());
    }

    private function lockFile(): string
    {
        return storage_path('framework/node-services-health.lock');
    }

    private function lastRunFile(): string
    {
        return storage_path('framework/node-services-health.last');
    }
}
