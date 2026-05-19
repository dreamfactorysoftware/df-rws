<?php namespace DreamFactory\Core\Rws\Services;

use Cache;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\BadRequestException;
use Log;

/**
 * OAuth2 client credentials grant helper for outbound RWS requests.
 *
 * Responsibilities:
 *   - Acquire bearer tokens from the configured token URL
 *   - Cache tokens per-service using DF's cache layer
 *   - Handle Oracle OIDS (basic_header) and RFC 6749 (post_body) auth methods
 *   - Invalidate cache on 401 from backend so callers can retry with a fresh token
 *
 * Intentionally stateless aside from caching — instance fields are immutable config.
 */
class OAuth2ClientCredentials
{
    /** 30-second early-refresh buffer so a token doesn't expire mid-flight */
    const REFRESH_BUFFER_SECONDS = 30;

    /** Cache lock timeout to prevent thundering-herd on token endpoint */
    const LOCK_TIMEOUT_SECONDS = 10;

    protected int $serviceId;
    protected string $tokenUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $grantType;
    protected ?string $scope;
    protected string $authMethod;

    public function __construct(int $serviceId, array $config)
    {
        $this->serviceId = $serviceId;
        $this->tokenUrl = (string)array_get($config, 'oauth_token_url');
        $this->clientId = (string)array_get($config, 'oauth_client_id');
        $this->clientSecret = (string)array_get($config, 'oauth_client_secret');
        $this->grantType = (string)array_get($config, 'oauth_grant_type', 'client_credentials');
        $this->scope = array_get($config, 'oauth_scope');
        $this->authMethod = (string)array_get($config, 'oauth_auth_method', 'basic_header');

        if ($this->tokenUrl === '' || $this->clientId === '' || $this->clientSecret === '') {
            throw new BadRequestException(
                'OAuth2 backend auth requires oauth_token_url, oauth_client_id, and oauth_client_secret.'
            );
        }
    }

    /**
     * Detect whether a given RWS config has outbound OAuth2 enabled.
     */
    public static function isConfigured(array $config): bool
    {
        return !empty(array_get($config, 'oauth_token_url'))
            && !empty(array_get($config, 'oauth_client_id'))
            && !empty(array_get($config, 'oauth_client_secret'));
    }

    protected function cacheKey(): string
    {
        return 'rws_oauth_token_' . $this->serviceId;
    }

    /**
     * Return a valid bearer token, fetching a new one if the cache is empty or expired.
     */
    public function getAccessToken(): string
    {
        $cached = Cache::get($this->cacheKey());
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->acquireToken();
    }

    /**
     * Drop the cached token so the next request forces a fresh acquisition.
     * Used after a backend 401 to handle mid-flight token revocation.
     */
    public function invalidateCache(): void
    {
        Cache::forget($this->cacheKey());
    }

    /**
     * POST to the token endpoint and cache the result.
     *
     * @throws InternalServerErrorException on network or provider error
     */
    protected function acquireToken(): string
    {
        $body = ['grant_type' => $this->grantType];
        if (!empty($this->scope)) {
            $body['scope'] = $this->scope;
        }

        $headers = ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'];

        if ($this->authMethod === 'post_body') {
            $body['client_id'] = $this->clientId;
            $body['client_secret'] = $this->clientSecret;
        } else {
            // default: basic_header (Oracle OIDS requirement)
            $headers[] = 'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret);
        }

        $startedAt = microtime(true);
        $ch = curl_init($this->tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $latencyMs = (int)round((microtime(true) - $startedAt) * 1000);

        if ($raw === false) {
            Log::error("RWS OAuth2 token request failed (service_id={$this->serviceId}): {$curlErr}");
            throw new InternalServerErrorException(
                'Failed to reach OAuth2 token endpoint: ' . $curlErr
            );
        }

        $payload = json_decode($raw, true);

        if ($httpCode >= 400) {
            // Log the provider's error code/description but never the secret
            $err = is_array($payload) ? array_get($payload, 'error', 'unknown_error') : 'http_' . $httpCode;
            $desc = is_array($payload) ? array_get($payload, 'error_description', '') : '';
            Log::warning(
                "RWS OAuth2 token endpoint returned {$httpCode} (service_id={$this->serviceId}, " .
                "latency={$latencyMs}ms): {$err} - {$desc}"
            );
            throw new InternalServerErrorException(
                "OAuth2 token acquisition failed ({$err}): {$desc}"
            );
        }

        $accessToken = is_array($payload) ? array_get($payload, 'access_token') : null;
        if (!is_string($accessToken) || $accessToken === '') {
            Log::error("RWS OAuth2 response missing access_token (service_id={$this->serviceId})");
            throw new InternalServerErrorException('OAuth2 token response did not contain access_token.');
        }

        $expiresIn = (int)(is_array($payload) ? array_get($payload, 'expires_in', 3600) : 3600);
        $ttl = max(30, $expiresIn - self::REFRESH_BUFFER_SECONDS);
        Cache::put($this->cacheKey(), $accessToken, $ttl);

        Log::info(
            "RWS OAuth2 token acquired (service_id={$this->serviceId}, " .
            "expires_in={$expiresIn}s, cached_for={$ttl}s, latency={$latencyMs}ms)"
        );

        return $accessToken;
    }
}
