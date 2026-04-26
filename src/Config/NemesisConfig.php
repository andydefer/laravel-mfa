<?php

declare(strict_types=1);

namespace Kani\Otp\Config;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Value object for Nemesis package configuration.
 *
 * Encapsulates all configuration settings needed by the middleware
 * and other package components, providing a clean, immutable interface.
 */
final class NemesisConfig
{
    /**
     * @param  string  $tokenHeader  The header name containing the bearer token
     * @param  string  $hashAlgorithm  Algorithm used to hash tokens
     * @param  string  $parameterName  Request parameter name for authenticated model
     * @param  bool  $validateOrigin  Whether to validate CORS origins
     * @param  bool  $securityHeaders  Whether to add security headers to responses
     * @param  bool  $allowCredentials  Whether to allow credentials in CORS requests
     * @param  int  $maxAge  Max age for CORS preflight requests (seconds)
     * @param  bool  $exposeTokenInfo  Whether to expose token info in CORS responses
     */
    private function __construct(
        public readonly string $tokenHeader,
        public readonly string $hashAlgorithm,
        public readonly string $parameterName,
        public readonly bool $validateOrigin,
        public readonly bool $securityHeaders,
        public readonly bool $allowCredentials,
        public readonly int $maxAge,
        public readonly bool $exposeTokenInfo,
    ) {}

    /**
     * Create a new config instance from Laravel's config repository.
     *
     * @param  ConfigRepository  $config  Laravel config repository
     * @return self A new immutable config instance
     */
    public static function fromLaravelConfig(ConfigRepository $config): self
    {
        return new self(
            tokenHeader: $config->get('nemesis.middleware.token_header', 'Authorization'),
            hashAlgorithm: $config->get('nemesis.hash_algorithm', 'sha256'),
            parameterName: $config->get('nemesis.middleware.parameter_name', 'nemesisAuth'),
            validateOrigin: $config->get('nemesis.middleware.validate_origin', true),
            securityHeaders: $config->get('nemesis.middleware.security_headers', true),
            allowCredentials: $config->get('nemesis.cors.allow_credentials', true),
            maxAge: $config->get('nemesis.cors.max_age', 86400),
            exposeTokenInfo: $config->get('nemesis.cors.expose_token_info', false),
        );
    }

    /**
     * Create a new config instance with default settings.
     *
     * @return self A new config instance with defaults
     */
    public static function defaults(): self
    {
        return new self(
            tokenHeader: 'Authorization',
            hashAlgorithm: 'sha256',
            parameterName: 'nemesisAuth',
            validateOrigin: true,
            securityHeaders: true,
            allowCredentials: true,
            maxAge: 86400,
            exposeTokenInfo: false,
        );
    }

    /**
     * Create a new config instance for testing purposes.
     *
     * @param  array<string, mixed>  $overrides  Configuration overrides
     * @return self A new config instance with optional overrides
     */
    public static function forTesting(array $overrides = []): self
    {
        $defaults = self::defaults();

        return new self(
            tokenHeader: $overrides['tokenHeader'] ?? $defaults->tokenHeader,
            hashAlgorithm: $overrides['hashAlgorithm'] ?? $defaults->hashAlgorithm,
            parameterName: $overrides['parameterName'] ?? $defaults->parameterName,
            validateOrigin: $overrides['validateOrigin'] ?? $defaults->validateOrigin,
            securityHeaders: $overrides['securityHeaders'] ?? $defaults->securityHeaders,
            allowCredentials: $overrides['allowCredentials'] ?? $defaults->allowCredentials,
            maxAge: $overrides['maxAge'] ?? $defaults->maxAge,
            exposeTokenInfo: $overrides['exposeTokenInfo'] ?? $defaults->exposeTokenInfo,
        );
    }

    /**
     * Get the token extraction method based on header type.
     *
     * @return bool True if using custom header (not Authorization)
     */
    public function isUsingCustomHeader(): bool
    {
        return $this->tokenHeader !== 'Authorization';
    }

    /**
     * Check if security features are enabled.
     *
     * @return bool True if any security features are enabled
     */
    public function hasSecurityFeatures(): bool
    {
        return $this->securityHeaders || $this->validateOrigin;
    }

    /**
     * Convert config to array for debugging.
     *
     * @return array<string, mixed> Configuration as array
     */
    public function toArray(): array
    {
        return [
            'token_header' => $this->tokenHeader,
            'hash_algorithm' => $this->hashAlgorithm,
            'parameter_name' => $this->parameterName,
            'validate_origin' => $this->validateOrigin,
            'security_headers' => $this->securityHeaders,
            'allow_credentials' => $this->allowCredentials,
            'max_age' => $this->maxAge,
            'expose_token_info' => $this->exposeTokenInfo,
        ];
    }
}
