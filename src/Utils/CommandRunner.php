<?php

namespace mediablue\Utils;

class CommandRunner
{
    /**
     * コマンドを実行し、結果を連想配列で返す
     */
    public static function run(string $command, int $timeout = 10): array
    {
        $descriptorspec = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"],  // stderr
        ];

        $process = proc_open($command, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            return [
                'exit_code' => -1,
                'stdout' => '',
                'stderr' => 'Failed to execute command: ' . $command,
                'success' => false,
            ];
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $status = proc_close($process);

        return [
            'exit_code' => $status,
            'stdout' => trim($stdout),
            'stderr' => trim($stderr),
            'success' => ($status === 0),
        ];
    }

    /**
     * コマンドがシステムに存在するか確認する
     */
    public static function hasCommand(string $command): bool
    {
        $checkCmd = (DIRECTORY_SEPARATOR === '\\')
            ? "where " . escapeshellarg($command) . " 2>NUL"
            : "command -v " . escapeshellarg($command) . " 2>/dev/null";

        $res = self::run($checkCmd);
        return $res['success'] && !empty($res['stdout']);
    }
}
