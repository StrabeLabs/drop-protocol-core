<?php

declare(strict_types=1);

namespace DropProtocol\Storage;

use DropProtocol\Contracts\StorageInterface;

/**
 * In-memory storage implementation
 * 
 * Suitable for testing and single-server deployments.
 * Sessions are lost on process restart.
 */
final class MemoryStorage implements StorageInterface
{
    private array $sessions = [];
    private array $userSessions = [];

    public function store(string $sessionId, string $userId, array $data, int $ttl): void
    {
        $this->sessions[$sessionId] = [
            'user_id' => $userId,
            'data' => $data,
            'created_at' => $data['created_at'] ?? time(),
            'expires_at' => time() + $ttl,
            'last_activity' => time()
        ];

        if (!isset($this->userSessions[$userId])) {
            $this->userSessions[$userId] = [];
        }

        if (!in_array($sessionId, $this->userSessions[$userId], true)) {
            $this->userSessions[$userId][] = $sessionId;
        }

        $this->cleanup();
    }

    public function retrieve(string $sessionId): ?array
    {
        $this->cleanup();

        if (!isset($this->sessions[$sessionId])) {
            return null;
        }

        $session = $this->sessions[$sessionId];

        if ($session['expires_at'] < time()) {
            unset($this->sessions[$sessionId]);
            return null;
        }

        return [
            'user_id' => $session['user_id'],
            'data' => $session['data'],
            'created_at' => $session['created_at'],
            'last_activity' => $session['last_activity']
        ];
    }

    public function delete(string $sessionId): void
    {
        if (isset($this->sessions[$sessionId])) {
            $userId = $this->sessions[$sessionId]['user_id'];
            unset($this->sessions[$sessionId]);

            if (isset($this->userSessions[$userId])) {
                $this->userSessions[$userId] = array_values(
                    array_filter(
                        $this->userSessions[$userId],
                        fn($s) => $s !== $sessionId
                    )
                );
            }
        }
    }

    public function deleteUserSessions(string $userId): void
    {
        if (!isset($this->userSessions[$userId])) {
            return;
        }

        $sessions = $this->userSessions[$userId];

        foreach ($sessions as $sessionId) {
            if (isset($this->sessions[$sessionId])) {
                unset($this->sessions[$sessionId]);
            }
        }

        unset($this->userSessions[$userId]);
    }


    public function touch(string $sessionId, int $ttl): void
    {
        if (isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId]['expires_at'] = time() + $ttl;
            $this->sessions[$sessionId]['last_activity'] = time();
        }
    }

    public function rotateAtomic(
        string $oldId,
        string $newId,
        string $userId,
        array $data,
        int $ttl
    ): bool {
        if (!isset($this->sessions[$oldId])) {
            return false;
        }

        if ($this->sessions[$oldId]['user_id'] !== $userId) {
            return false;
        }

        $oldSession = $this->sessions[$oldId];

        $this->sessions[$newId] = [
            'user_id' => $userId,
            'data' => $data,
            'created_at' => $oldSession['created_at'],
            'expires_at' => time() + $ttl,
            'last_activity' => time()
        ];

        if (!isset($this->userSessions[$userId])) {
            $this->userSessions[$userId] = [];
        }
        $this->userSessions[$userId][] = $newId;

        unset($this->sessions[$oldId]);
        $this->userSessions[$userId] = array_values(
            array_filter(
                $this->userSessions[$userId],
                fn($s) => $s !== $oldId
            )
        );

        return true;
    }

    public function countUserSessions(string $userId): int
    {
        $this->cleanup();
        return isset($this->userSessions[$userId]) ? count($this->userSessions[$userId]) : 0;
    }

    public function updateUserData(string $sessionId, array $userData): bool
    {
        $this->cleanup();

        if (!isset($this->sessions[$sessionId])) {
            return false;
        }

        $session = $this->sessions[$sessionId];

        if ($session['expires_at'] < time()) {
            unset($this->sessions[$sessionId]);
            return false;
        }

        $this->sessions[$sessionId]['data']['user_data'] = $userData;
        $this->sessions[$sessionId]['last_activity'] = time();

        return true;
    }

    /**
     * Remove expired sessions
     *
     * @return void
     */
    private function cleanup(): void
    {
        $now = time();

        foreach ($this->sessions as $sessionId => $session) {
            if ($session['expires_at'] < $now) {
                $this->delete($sessionId);
            }
        }
    }
}
