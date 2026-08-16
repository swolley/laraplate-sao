<?php

declare(strict_types=1);

namespace Modules\SAO\Ingest;

use Modules\Core\Logging\Fingerprint\Fingerprinter;

/**
 * The from-payload frame resolver (design §7): SAO receives an already-flattened
 * error — class, file, function and message as strings, sometimes with a stack
 * trace embedded and the file/line missing. It recovers the fingerprint parts
 * (explicitly, or from a flattened `Class: message in /path:line` header) and
 * hashes them through the shared Core {@see Fingerprinter}, so an error
 * fingerprinted in-process and the same error received here yield one key.
 */
final readonly class PayloadFrameResolver
{
    public function __construct(private Fingerprinter $fingerprinter) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function key(array $payload): string
    {
        $signature = $this->signature($payload);

        return $this->fingerprinter->hash(
            $signature['kind'],
            $signature['module'],
            $signature['class'],
            $signature['file'],
            $signature['function'],
            $signature['message'],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{kind: string, module: string, class: string, file: string, function: string, message: string}
     */
    public function signature(array $payload): array
    {
        $class = $this->stringOrNull($payload['class'] ?? null);
        $file = $this->stringOrNull($payload['file'] ?? null);
        $function = $this->stringOrNull($payload['function'] ?? null) ?? '';
        $message = $this->stringOrNull($payload['message'] ?? null) ?? '';

        if (($class === null || $file === null) && $message !== '') {
            $recovered = $this->recoverFromMessage($message);
            $class ??= $recovered['class'];
            $file ??= $recovered['file'];
            $message = $recovered['message'];
        }

        $file = $file !== null ? $this->normalizePath($file) : '';

        return [
            'kind' => $this->stringOrNull($payload['kind'] ?? null) ?? ($class !== null && $class !== '' ? 'exception' : 'log'),
            'module' => $file !== '' ? file_module($file) : '',
            'class' => $class ?? '',
            'file' => $file,
            'function' => $function,
            'message' => $message,
        ];
    }

    /**
     * Recover class, file and the head message from a flattened
     * "Class: message in /path/file.php:line" header.
     *
     * @return array{class: ?string, file: ?string, message: string}
     */
    private function recoverFromMessage(string $message): array
    {
        $pattern = '/^(?<class>[\\\\\w]+):\s*(?<message>.*?)\s+in\s+(?<file>\/[^\s:]+):(?<line>\d+)/s';

        if (preg_match($pattern, $message, $matches) === 1) {
            return [
                'class' => $matches['class'],
                'file' => $matches['file'],
                'message' => mb_trim($matches['message']),
            ];
        }

        return ['class' => null, 'file' => null, 'message' => $message];
    }

    private function normalizePath(string $path): string
    {
        $base_path = base_path() . DIRECTORY_SEPARATOR;

        if (str_starts_with($path, $base_path)) {
            return str_replace('\\', '/', mb_substr($path, mb_strlen($base_path)));
        }

        return str_replace('\\', '/', $path);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
