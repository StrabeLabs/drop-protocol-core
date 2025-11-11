<?php
declare(strict_types=1);

namespace DropProtocol\Configuration;

/**
 * Immutable configuration for DROP Protocol
 * 
 * Contains all runtime parameters for session management, security validation,
 * and rotation behavior. Use DropProtocolConfigBuilder to create instances.
 */
final class DropProtocolConfig
{
    private int $sessionExpiry;
    private bool $allowMultipleSessions;
    private int $maxSessionsPerUser;
    private string $cookieName;
    private array $cookieOptions;
    private bool $strictIpValidation;
    private bool $strictUaValidation;
    private int $rotationThreshold;
    private bool $slidingExpiration;
    
    /**
     * @internal Use DropProtocolConfigBuilder instead
     */
    public function __construct(
        int $sessionExpiry,
        bool $allowMultipleSessions,
        int $maxSessionsPerUser,
        string $cookieName,
        array $cookieOptions,
        bool $strictIpValidation,
        bool $strictUaValidation,
        int $rotationThreshold,
        bool $slidingExpiration
    ) {
        $this->sessionExpiry = $sessionExpiry;
        $this->allowMultipleSessions = $allowMultipleSessions;
        $this->maxSessionsPerUser = $maxSessionsPerUser;
        $this->cookieName = $cookieName;
        $this->cookieOptions = $cookieOptions;
        $this->strictIpValidation = $strictIpValidation;
        $this->strictUaValidation = $strictUaValidation;
        $this->rotationThreshold = $rotationThreshold;
        $this->slidingExpiration = $slidingExpiration;
    }
    
    public function getSessionExpiry(): int
    {
        return $this->sessionExpiry;
    }
    
    public function allowsMultipleSessions(): bool
    {
        return $this->allowMultipleSessions;
    }
    
    public function getMaxSessionsPerUser(): int
    {
        return $this->maxSessionsPerUser;
    }
    
    public function getCookieName(): string
    {
        return $this->cookieName;
    }
    
    public function getCookieOptions(): array
    {
        return $this->cookieOptions;
    }
    
    public function isStrictIpValidation(): bool
    {
        return $this->strictIpValidation;
    }
    
    public function isStrictUaValidation(): bool
    {
        return $this->strictUaValidation;
    }
    
    public function getRotationThreshold(): int
    {
        return $this->rotationThreshold;
    }
    
    public function hasSlidingExpiration(): bool
    {
        return $this->slidingExpiration;
    }
}
