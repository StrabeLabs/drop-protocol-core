<?php
declare(strict_types=1);

namespace DropProtocol\Configuration;

/**
 * Fluent builder for DROP Protocol configuration
 * 
 * Provides convenient factory methods for common configurations:
 * - createBalanced(): Recommended for most applications
 * - createHighSecurity(): For banking/financial applications
 * - createDevelopment(): Permissive settings for local development
 */
final class DropProtocolConfigBuilder
{
    private int $sessionExpiry = 3600;
    private bool $allowMultipleSessions = false;
    private int $maxSessionsPerUser = 5;
    private string $cookieName = 'drop_session';
    private array $cookieOptions = [
        'httponly' => true,
        'secure' => true,
        'samesite' => 'Strict',
        'path' => '/'
    ];
    private bool $strictIpValidation = false;
    private bool $strictUaValidation = false;
    private int $rotationThreshold = 300;
    private bool $slidingExpiration = true;
    
    private function __construct()
    {
    }
    
    /**
     * Create new builder with default values
     *
     * @return self
     */
    public static function create(): self
    {
        return new self();
    }
    
    /**
     * Balanced configuration for production use
     * 
     * - 1 hour session expiry
     * - Rotation every 5 minutes
     * - Up to 5 concurrent sessions
     * - Non-strict validation (warnings only)
     *
     * @return self
     */
    public static function createBalanced(): self
    {
        return (new self())
            ->withSessionExpiry(3600)
            ->withRotationThreshold(300)
            ->enableSlidingExpiration()
            ->disableStrictValidation()
            ->withMaxSessionsPerUser(5);
    }
    
    /**
     * High security configuration
     * 
     * - 30 minute session expiry
     * - Rotation every 1 minute
     * - Single session only
     * - Strict IP and User-Agent validation
     *
     * @return self
     */
    public static function createHighSecurity(): self
    {
        return (new self())
            ->withSessionExpiry(1800)
            ->withRotationThreshold(60)
            ->enableStrictIpValidation()
            ->enableStrictUaValidation()
            ->disableMultipleSessions()
            ->withMaxSessionsPerUser(1);
    }
    
    /**
     * Development configuration
     * 
     * - 24 hour session expiry
     * - No rotation
     * - Up to 10 sessions
     * - Non-secure cookies (localhost)
     *
     * @return self
     */
    public static function createDevelopment(): self
    {
        return (new self())
            ->withSessionExpiry(86400)
            ->withRotationThreshold(0)
            ->withCookieOptions(['secure' => false])
            ->disableStrictValidation()
            ->withMaxSessionsPerUser(10);
    }
    
    /**
     * Set session expiry time
     *
     * @param int $seconds Expiry in seconds (minimum 60)
     * @return self
     */
    public function withSessionExpiry(int $seconds): self
    {
        $this->sessionExpiry = $seconds;
        return $this;
    }
    
    /**
     * Allow multiple concurrent sessions per user
     *
     * @return self
     */
    public function enableMultipleSessions(): self
    {
        $this->allowMultipleSessions = true;
        return $this;
    }
    
    /**
     * Force single session per user
     *
     * @return self
     */
    public function disableMultipleSessions(): self
    {
        $this->allowMultipleSessions = false;
        $this->maxSessionsPerUser = 1;
        return $this;
    }
    
    /**
     * Set maximum sessions per user
     *
     * @param int $max Maximum concurrent sessions (minimum 1)
     * @return self
     */
    public function withMaxSessionsPerUser(int $max): self
    {
        $this->maxSessionsPerUser = max(1, $max);
        return $this;
    }
    
    /**
     * Set cookie name
     *
     * @param string $name Cookie name
     * @return self
     */
    public function withCookieName(string $name): self
    {
        $this->cookieName = $name;
        return $this;
    }
    
    /**
     * Merge cookie options
     *
     * @param array $options Cookie options (httponly, secure, samesite, path, domain)
     * @return self
     */
    public function withCookieOptions(array $options): self
    {
        $this->cookieOptions = array_merge($this->cookieOptions, $options);
        return $this;
    }
    
    /**
     * Enable strict IP validation
     * 
     * Rejects requests if IP address changes.
     *
     * @return self
     */
    public function enableStrictIpValidation(): self
    {
        $this->strictIpValidation = true;
        return $this;
    }
    
    /**
     * Enable strict User-Agent validation
     * 
     * Rejects requests if User-Agent changes significantly.
     *
     * @return self
     */
    public function enableStrictUaValidation(): self
    {
        $this->strictUaValidation = true;
        return $this;
    }
    
    /**
     * Disable strict validation
     * 
     * Changes in IP/UA only trigger warnings in logs.
     *
     * @return self
     */
    public function disableStrictValidation(): self
    {
        $this->strictIpValidation = false;
        $this->strictUaValidation = false;
        return $this;
    }
    
    /**
     * Set rotation threshold
     * 
     * Controls when sessions are rotated:
     * - 0: No rotation
     * - -1: Rotate on every request
     * - N: Rotate after N seconds of inactivity
     *
     * @param int $seconds Threshold in seconds
     * @return self
     */
    public function withRotationThreshold(int $seconds): self
    {
        $this->rotationThreshold = $seconds;
        return $this;
    }
    
    /**
     * Enable sliding expiration
     * 
     * Session TTL is extended on each request.
     *
     * @return self
     */
    public function enableSlidingExpiration(): self
    {
        $this->slidingExpiration = true;
        return $this;
    }
    
    /**
     * Disable sliding expiration
     * 
     * Session expires at fixed time regardless of activity.
     *
     * @return self
     */
    public function disableSlidingExpiration(): self
    {
        $this->slidingExpiration = false;
        return $this;
    }
    
    /**
     * Build immutable configuration
     *
     * @return DropProtocolConfig
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function build(): DropProtocolConfig
    {
        if ($this->sessionExpiry < 60) {
            throw new \InvalidArgumentException('Session expiry must be at least 60 seconds');
        }
        
        return new DropProtocolConfig(
            $this->sessionExpiry,
            $this->allowMultipleSessions,
            $this->maxSessionsPerUser,
            $this->cookieName,
            $this->cookieOptions,
            $this->strictIpValidation,
            $this->strictUaValidation,
            $this->rotationThreshold,
            $this->slidingExpiration
        );
    }
}
