<?php

declare(strict_types=1);

namespace App\Modules\System\Support;

/**
 * Replaces secrets (passwords, API keys, tokens, merchant ids, card data) before an external call is logged
 * (roadmap D0.4, D16). Handles JSON bodies, form bodies, query strings and headers.
 */
final class IntegrationRedactor
{
    /** @var array<string, true> */
    private array $keys;

    private readonly string $replacement;

    /**
     * @param  list<string>  $extraKeys  Fields secret only for one call, e.g. the OTP "text" sent to the SMS provider.
     */
    public function __construct(array $extraKeys = [])
    {
        /** @var list<string> $keys */
        $keys = config('integrations.log.redact_keys', []);
        $this->keys = array_fill_keys(array_map($this->normalizeKey(...), [...$keys, ...$extraKeys]), true);
        $this->replacement = (string) config('integrations.log.redacted', '[REDACTED]');
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public function redactArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSecret($key)) {
                $values[$key] = $this->replacement;
            } elseif (is_array($value)) {
                $values[$key] = $this->redactArray($value);
            }
        }

        return $values;
    }

    /**
     * A JSON or form-encoded body with its secret fields replaced; other bodies are returned unchanged.
     */
    public function redactBody(string $body, string $contentType = ''): string
    {
        if ($body === '') {
            return $body;
        }

        $json = json_decode($body, true);

        if (is_array($json)) {
            return (string) json_encode($this->redactArray($json), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded') || preg_match('/^[^\s=&]+=[^\s]*(&[^\s=&]+=[^\s]*)*$/', $body) === 1) {
            parse_str($body, $fields);

            return http_build_query($this->redactArray($fields));
        }

        return $body;
    }

    public function redactUrl(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return $url;
        }

        parse_str($query, $fields);

        return str_replace($query, http_build_query($this->redactArray($fields)), $url);
    }

    /**
     * @param  array<string, array<string>|string>  $headers
     * @return array<string, string>
     */
    public function redactHeaders(array $headers): array
    {
        $redacted = [];

        foreach ($headers as $name => $values) {
            $redacted[$name] = $this->isSecret($name) ? $this->replacement : implode(', ', (array) $values);
        }

        return $redacted;
    }

    private function isSecret(string $key): bool
    {
        return isset($this->keys[$this->normalizeKey($key)]);
    }

    private function normalizeKey(string $key): string
    {
        return str_replace(['_', '-', ' '], '', mb_strtolower($key));
    }
}
