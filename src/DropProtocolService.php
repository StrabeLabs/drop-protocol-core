<?php

declare(strict_types=1);

namespace DropProtocol;

use DropProtocol\Configuration\DropProtocolConfig;
use DropProtocol\Contracts\CookieManagerInterface;
use DropProtocol\Contracts\StorageInterface;
use DropProtocol\Exceptions\InvalidSessionException;
use DropProtocol\Exceptions\SecurityViolationException;
use DropProtocol\Exceptions\SessionLimitException;
use DropProtocol\Services\NativeCookieManager;
use DropProtocol\Services\SecurityValidator;
use DropProtocol\Services\SessionService;

class DropProtocolService
{
    private StorageInterface $storage;
    private DropProtocolConfig $config;
    private CookieManagerInterface $cookieManager;

    public function __construct(
        StorageInterface $storage,
        DropProtocolConfig $config,
        ?CookieManagerInterface $cookieManager = null
    ) {
        $this->storage = $storage;
        $this->config = $config;
        $this->cookieManager = $cookieManager ?? new NativeCookieManager();
    }

    /**
     * Authenticate user and create session
     *
     * @param string $userId User identifier
     * @param array $userData Optional user metadata
     * @return array{session_id: string, expires: int}
     * @throws SessionLimitException If maximum sessions limit reached
     */
    public function login(string $userId, array $userData = []): array
    {
        if (!$this->config->allowsMultipleSessions()) {
            $this->storage->deleteUserSessions($userId);
        } else {
            $activeSessions = $this->storage->countUserSessions($userId);

            if ($activeSessions >= $this->config->getMaxSessionsPerUser()) {
                throw new SessionLimitException(
                    sprintf(
                        'Maximum sessions limit reached (%d/%d). Logout from another device first.',
                        $activeSessions,
                        $this->config->getMaxSessionsPerUser()
                    )
                );
            }
        }

        $sessionId = SessionService::generate();

        $this->storage->store($sessionId, $userId, [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => time(),
            'user_data' => $userData
        ], $this->config->getSessionExpiry());

        $this->setCookie($sessionId);

        return [
            'session_id' => $sessionId,
            'expires' => $this->config->getSessionExpiry()
        ];
    }


    /**
     * Validate session with automatic rotation
     * 
     * Performs the following actions:
     * 1. Retrieves session from cookie
     * 2. Validates security context (IP/User-Agent)
     * 3. Updates session TTL (sliding expiration)
     * 4. Rotates session ID if threshold reached
     *
     * @return array{user_id: string, data: array, created_at: int, last_activity: int}
     * @throws InvalidSessionException If session not found or expired
     * @throws SecurityViolationException If security validation fails in strict mode
     */
    public function validate(): array
    {
        $sessionId = $this->getSessionIdFromCookie();

        if (!$sessionId) {
            throw new InvalidSessionException('No session cookie found');
        }

        $sessionData = $this->storage->retrieve($sessionId);

        if (!$sessionData) {
            $this->clearCookie();
            throw new InvalidSessionException('Session not found or expired');
        }

        $securityCheck = SecurityValidator::validate(
            $sessionData['data'],
            $this->config->isStrictIpValidation(),
            $this->config->isStrictUaValidation()
        );

        if (!$securityCheck['valid']) {
            $this->storage->delete($sessionId);
            $this->clearCookie();
            throw new SecurityViolationException(
                'Security violation: ' . implode(', ', $securityCheck['warnings'])
            );
        }

        if ($this->config->hasSlidingExpiration()) {
            $this->storage->touch($sessionId, $this->config->getSessionExpiry());
        }

        $shouldRotate = $this->shouldRotate($sessionData);

        if ($shouldRotate) {
            $newSessionId = SessionService::generate();

            $success = $this->storage->rotateAtomic(
                $sessionId,
                $newSessionId,
                $sessionData['user_id'],
                $sessionData['data'],
                $this->config->getSessionExpiry()
            );

            if ($success) {
                $this->setCookie($newSessionId);
                $sessionId = $newSessionId;
            } else {
                error_log("DROP: Rotation failed for session, revalidating...");

                $sessionData = $this->storage->retrieve($sessionId);

                if (!$sessionData) {
                    $this->clearCookie();
                    throw new InvalidSessionException(
                        'Session was rotated concurrently, please retry'
                    );
                }
            }
        }

        return $sessionData;
    }

    /**
     * Determine if session should rotate
     *
     * @param array $sessionData Session data with last_activity timestamp
     * @return bool True if rotation threshold reached
     */
    private function shouldRotate(array $sessionData): bool
    {
        $threshold = $this->config->getRotationThreshold();

        if ($threshold === 0) {
            return false;
        }

        if ($threshold === -1) {
            return true;
        }

        $timeSinceActivity = time() - ($sessionData['last_activity'] ?? time());

        return $timeSinceActivity >= $threshold;
    }

    /**
     * Logout current session
     *
     * @return void
     */
    public function logout(): void
    {
        $sessionId = $this->getSessionIdFromCookie();

        if ($sessionId) {
            $this->storage->delete($sessionId);
        }

        $this->clearCookie();
    }

    /**
     * Logout all sessions for current user
     *
     * @return void
     */
    public function logoutAll(): void
    {
        $sessionId = $this->getSessionIdFromCookie();

        if ($sessionId) {
            $data = $this->storage->retrieve($sessionId);
            if ($data) {
                $this->storage->deleteUserSessions($data['user_id']);
            }
        }

        $this->clearCookie();
    }

    /**
     * Get count of active sessions for current user
     *
     * @return int Number of active sessions
     */
    public function getActiveSessionCount(): int
    {
        $sessionId = $this->getSessionIdFromCookie();

        if (!$sessionId) {
            return 0;
        }

        $data = $this->storage->retrieve($sessionId);

        if (!$data) {
            return 0;
        }

        return $this->storage->countUserSessions($data['user_id']);
    }

    /**
     * Get current user ID without throwing exceptions
     *
     * @return string|null User ID or null if not authenticated
     */
    public function getUserId(): ?string
    {
        try {
            $data = $this->validate();
            return $data['user_id'];
        } catch (InvalidSessionException | SecurityViolationException $e) {
            return null;
        }
    }

    /**
     * Check if user is authenticated
     *
     * @return bool True if authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->getUserId() !== null;
    }

    /**
     * Update user data in current session
     *
     * @param array $userData New user data to store
     * @return bool True if update succeeded
     * @throws InvalidSessionException If no active session
     */
    public function updateSessionUserData(array $userData): bool
    {
        $sessionId = $this->getSessionIdFromCookie();

        if (!$sessionId) {
            throw new InvalidSessionException('No session cookie found');
        }

        return $this->storage->updateUserData($sessionId, $userData);
    }

    /**
     * Get session ID from cookie (AMPHP compatible)
     *
     * @return string|null Session ID or null if not found
     */
    private function getSessionIdFromCookie(): ?string
    {
        if ($this->cookieManager instanceof \DropProtocol\Amphp\Services\AmphpCookieManager) {
            return $this->cookieManager->getRequestCookie($this->config->getCookieName());
        }
        
        return $_COOKIE[$this->config->getCookieName()] ?? null;
    }

    /**
     * Set HttpOnly session cookie
     *
     * @param string $sessionId Session identifier
     * @return void
     */
    private function setCookie(string $sessionId): void
    {
        $options = $this->config->getCookieOptions();

        $this->cookieManager->setCookie(
            $this->config->getCookieName(),
            $sessionId,
            [
                'expires' => time() + $this->config->getSessionExpiry(),
                'path' => $options['path'] ?? '/',
                'domain' => $options['domain'] ?? '',
                'secure' => $options['secure'] ?? true,
                'httponly' => $options['httponly'] ?? true,
                'samesite' => $options['samesite'] ?? 'Strict'
            ]
        );
    }

    public function getCookieManager(): CookieManagerInterface
    {
        return $this->cookieManager;
    }

    /**
     * Clear session cookie
     *
     * @return void
     */
    private function clearCookie(): void
    {
        $options = $this->config->getCookieOptions();

        $this->cookieManager->setCookie(
            $this->config->getCookieName(),
            '',
            [
                'expires' => time() - 3600,
                'path' => $options['path'] ?? '/',
                'domain' => $options['domain'] ?? '',
                'secure' => $options['secure'] ?? true,
                'httponly' => $options['httponly'] ?? true,
                'samesite' => $options['samesite'] ?? 'Strict'
            ]
        );
    }
}
