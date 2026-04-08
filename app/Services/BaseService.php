<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * BaseService
 *
 * Shared utilities for all service classes.
 */
abstract class BaseService
{
    /**
     * Safe JSON encode with error logging.
     */
    protected function safeJsonEncode(mixed $data): string
    {
        $json = json_encode($data);
        if ($json === false) {
            Log::error("[BaseService] JSON encode failed: " . json_last_error_msg());
            return json_encode(['error' => 'Failed to encode response.']);
        }
        return $json;
    }

    /**
     * Log tool execution with standardized format.
     */
    protected function logToolCall(string $toolName, array $arguments): void
    {
        Log::info("[ToolCallExecutor] Tool called: {$toolName}", $arguments);
    }

    /**
     * Log tool failure with standardized format.
     */
    protected function logToolFailure(string $toolName, \Throwable $e): void
    {
        Log::error("[ToolCallExecutor] Tool {$toolName} failed: " . $e->getMessage());
    }

    /**
     * Return error response as JSON string.
     */
    protected function errorResponse(string $message): string
    {
        return $this->safeJsonEncode(['error' => $message]);
    }

    /**
     * Check if array key exists and is not empty.
     */
    protected function getArrayValue(array $array, string $key, mixed $default = null): mixed
    {
        return $array[$key] ?? $default;
    }

    /**
     * Safely cast value to float.
     */
    protected function toFloat(mixed $value): float
    {
        return (float) ($value ?? 0);
    }

    /**
     * Safely cast value to int.
     */
    protected function toInt(mixed $value): int
    {
        return (int) ($value ?? 0);
    }

    /**
     * Decode JSON string safely.
     */
    protected function decodeJson(string $json, bool $associative = true): mixed
    {
        return json_decode($json, $associative);
    }
}
