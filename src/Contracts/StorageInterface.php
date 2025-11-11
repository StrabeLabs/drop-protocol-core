<?php
declare(strict_types=1);

namespace DropProtocol\Contracts;

/**
 * Storage interface for DROP Protocol sessions
 * 
 * Provides persistence layer for session management with support
 * for atomic operations and multi-device session tracking.
 */
interface StorageInterface
{
    /**
     * Store a new session
     *
     * @param string $sessionId Unique session identifier
     * @param string $userId User identifier
     * @param array $data Session metadata
     * @param int $ttl Time-to-live in seconds
     * @return void
     */
    public function store(string $sessionId, string $userId, array $data, int $ttl): void;

    /**
     * Retrieve session data
     *
     * @param string $sessionId Session identifier
     * @return array|null Session data or null if not found/expired
     */
    public function retrieve(string $sessionId): ?array;

    /**
     * Delete a session
     *
     * @param string $sessionId Session to delete
     * @return void
     */
    public function delete(string $sessionId): void;

    /**
     * Delete all sessions for a user
     *
     * @param string $userId User identifier
     * @return void
     */
    public function deleteUserSessions(string $userId): void;
    
    /**
     * Update session TTL and activity timestamp
     *
     * @param string $sessionId Session identifier
     * @param int $ttl New time-to-live in seconds
     * @return void
     */
    public function touch(string $sessionId, int $ttl): void;
    
    /**
     * Atomically rotate session ID
     * 
     * Creates new session and deletes old one in a single atomic operation.
     * Returns false if old session doesn't exist or belongs to different user.
     *
     * @param string $oldId Current session ID
     * @param string $newId New session ID
     * @param string $userId User identifier (for ownership verification)
     * @param array $data Session data to store
     * @param int $ttl Time-to-live in seconds
     * @return bool True if rotation succeeded, false otherwise
     */
    public function rotateAtomic(string $oldId, string $newId, string $userId, array $data, int $ttl): bool;
    
    /**
     * Count active sessions for a user
     *
     * @param string $userId User identifier
     * @return int Number of active sessions
     */
    public function countUserSessions(string $userId): int;
}
