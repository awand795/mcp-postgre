<?php

namespace App\Services\Database;

use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\Log;

class SshTunnelManager
{
    /**
     * Start the SSH tunnel if it's not already running.
     * Returns the local port to connect to.
     */
    public static function start(DatabaseConnection $db): int
    {
        if (!$db->use_ssh) {
            return $db->port;
        }

        // Use database ID or a random identifier for unsaved connections
        $dbId = $db->id ?: 'temp_' . uniqid();
        $localPort = $db->id ? (50000 + $db->id) : self::findFreePort(50000, 60000);

        // Check if tunnel is already active on this port
        if (self::isPortOpen('127.0.0.1', $localPort)) {
            Log::info("SSH Tunnel for DB {$db->name} is already active on port {$localPort}");
            return $localPort;
        }

        // Write private key file if key auth is used
        $keyFile = null;
        if ($db->ssh_auth_type === 'key') {
            $keyFile = self::writeKeyFile($db, $dbId);
        }

        // Stop any orphan processes first
        self::stop($db);

        // Build SSH command parameters
        $sshHost = $db->ssh_host;
        $sshPort = $db->ssh_port ?: 22;
        $sshUser = $db->ssh_username;
        $dbHost = $db->host;
        $dbPort = $db->port;

        $isWindows = DIRECTORY_SEPARATOR === '\\';

        if ($db->ssh_auth_type === 'key') {
            if (!$keyFile) {
                throw new \Exception("SSH Private Key is required for key authentication.");
            }

            // Build key command
            if ($isWindows) {
                // Windows PowerShell command with Start-Process
                $args = [
                    '-N',
                    '-L', "{$localPort}:{$dbHost}:{$dbPort}",
                    '-i', $keyFile,
                    '-p', (string)$sshPort,
                    '-o', 'StrictHostKeyChecking=no',
                    '-o', 'UserKnownHostsFile=NUL',
                    '-o', 'ExitOnForwardFailure=yes',
                    "{$sshUser}@{$sshHost}"
                ];

                $argString = implode(' ', array_map(fn($a) => '"' . str_replace('"', '""', $a) . '"', $args));
                $cmd = "powershell -Command \"Start-Process ssh -ArgumentList {$argString} -WindowStyle Hidden -PassThru | Select-Object -ExpandProperty Id\"";
            } else {
                // Linux command using nohup
                $cmd = sprintf(
                    "nohup ssh -N -L %d:%s:%d -i %s -p %d -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ExitOnForwardFailure=yes %s@%s > /dev/null 2>&1 & echo \$!",
                    $localPort, $dbHost, $dbPort, escapeshellarg($keyFile), $sshPort, escapeshellarg($sshUser), escapeshellarg($sshHost)
                );
            }
        } else {
            // Password authentication
            $sshPassword = $db->ssh_password;

            if ($isWindows) {
                // Windows does not support sshpass natively
                throw new \Exception("SSH Password authentication is not natively supported on Windows. Silakan gunakan otentikasi SSH Key.");
            } else {
                // Linux with sshpass
                $cmd = sprintf(
                    "nohup sshpass -p %s ssh -N -L %d:%s:%d -p %d -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ExitOnForwardFailure=yes %s@%s > /dev/null 2>&1 & echo \$!",
                    escapeshellarg($sshPassword), $localPort, $dbHost, $dbPort, $sshPort, escapeshellarg($sshUser), escapeshellarg($sshHost)
                );
            }
        }

        Log::info("Starting SSH Tunnel for DB {$db->name} (Port: {$localPort})");

        // Execute command and get PID
        $pid = null;
        $output = [];
        $resultCode = null;
        exec($cmd, $output, $resultCode);

        if ($resultCode === 0 && !empty($output)) {
            $pid = trim(implode('', $output));
            if (is_numeric($pid)) {
                $pidVal = (int)$pid;
                if ($db->id) {
                    $db->update(['ssh_pid' => $pidVal]);
                } else {
                    // Store temp pid in request session or static tracking for cleanup
                    $tempPids = session('temp_ssh_pids', []);
                    $tempPids[$dbId] = $pidVal;
                    session(['temp_ssh_pids' => $tempPids]);
                }
                Log::info("SSH Tunnel started with PID {$pidVal} on port {$localPort}");
            }
        }

        // Wait up to 3 seconds for the port to open and verify connection
        $maxAttempts = 30; // 3 seconds total (30 * 100ms)
        $portOpened = false;
        for ($i = 0; $i < $maxAttempts; $i++) {
            if (self::isPortOpen('127.0.0.1', $localPort)) {
                $portOpened = true;
                break;
            }
            usleep(100000); // 100ms
        }

        if (!$portOpened) {
            // Check if process died immediately
            if ($pid && !self::isProcessRunning((int)$pid)) {
                if ($db->id) {
                    $db->update(['ssh_pid' => null]);
                }
                throw new \Exception("Proses SSH langsung keluar. Silakan periksa kredensial SSH atau ketersediaan port local.");
            }
            throw new \Exception("Gagal membuka SSH tunnel di port lokal {$localPort}. Batas waktu habis.");
        }

        // For temporary connections, register a shutdown function to clean it up at end of request
        if (!$db->id) {
            register_shutdown_function(function() use ($db, $localPort, $keyFile) {
                try {
                    $tempPids = session('temp_ssh_pids', []);
                    $tempId = 'temp_' . uniqid(); // fallback
                    // We need to find the correct tempId used
                    foreach ($tempPids as $tId => $tPid) {
                        if (self::isPortOpen('127.0.0.1', $localPort)) {
                            // Kill it
                            $isWindows = DIRECTORY_SEPARATOR === '\\';
                            if ($isWindows) {
                                exec("taskkill /F /PID {$tPid} > NUL 2>&1");
                            } else {
                                exec("kill -9 {$tPid} > /dev/null 2>&1");
                            }
                            unset($tempPids[$tId]);
                        }
                    }
                    session(['temp_ssh_pids' => $tempPids]);
                } catch (\Exception $e) {}

                if ($keyFile && file_exists($keyFile)) {
                    @unlink($keyFile);
                }
            });
        }

        return $localPort;
    }

    /**
     * Stop the SSH tunnel.
     */
    public static function stop(DatabaseConnection $db): void
    {
        $pid = $db->ssh_pid;
        if (!$pid) {
            return;
        }

        Log::info("Stopping SSH Tunnel with PID {$pid} for DB {$db->name}");

        $isWindows = DIRECTORY_SEPARATOR === '\\';
        if ($isWindows) {
            exec("taskkill /F /PID {$pid} > NUL 2>&1");
        } else {
            exec("kill -9 {$pid} > /dev/null 2>&1");
        }

        $db->update(['ssh_pid' => null]);

        // Delete temporary key file if exists
        $keyFile = storage_path("app/ssh_keys/key_{$db->id}");
        if (file_exists($keyFile)) {
            @unlink($keyFile);
        }
    }

    /**
     * Check if a local port is open.
     */
    private static function isPortOpen(string $host, int $port): bool
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, 0.5);
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }
        return false;
    }

    /**
     * Check if a process is running by PID.
     */
    private static function isProcessRunning(int $pid): bool
    {
        $isWindows = DIRECTORY_SEPARATOR === '\\';
        if ($isWindows) {
            $output = [];
            exec("tasklist /FI \"PID eq {$pid}\" 2>NUL", $output);
            return count(array_filter($output, fn($line) => str_contains($line, (string)$pid))) > 0;
        } else {
            if (function_exists('posix_kill')) {
                return posix_kill($pid, 0);
            }
            return file_exists("/proc/{$pid}");
        }
    }

    /**
     * Find a free local port.
     */
    private static function findFreePort(int $start, int $end): int
    {
        for ($port = $start; $port <= $end; $port++) {
            if (!self::isPortOpen('127.0.0.1', $port)) {
                return $port;
            }
        }
        throw new \Exception("Tidak ada port lokal yang kosong di rentang {$start}-{$end}");
    }

    /**
     * Write private key to storage file.
     */
    private static function writeKeyFile(DatabaseConnection $db, $dbId): string
    {
        $keyDir = storage_path('app/ssh_keys');
        if (!is_dir($keyDir)) {
            mkdir($keyDir, 0700, true);
        }

        $keyFile = $keyDir . '/key_' . $dbId;
        file_put_contents($keyFile, $db->ssh_private_key);

        $isWindows = DIRECTORY_SEPARATOR === '\\';
        if ($isWindows) {
            // Remove permissions inheritance to make the private key secure for OpenSSH
            exec("icacls " . escapeshellarg($keyFile) . " /inheritance:r > NUL 2>&1");
            exec("icacls " . escapeshellarg($keyFile) . " /grant:r \"%USERNAME%:(R)\" > NUL 2>&1");
        } else {
            chmod($keyFile, 0600);
        }

        return $keyFile;
    }
}
