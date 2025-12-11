<?php

declare(strict_types=1);

namespace DropProtocol\Storage;

use DropProtocol\Contracts\StorageInterface;
use Redis;

/**
 * Redis storage implementation with atomic operations
 * 
 * Recommended for production use with multiple servers.
 * Uses Lua scripts for atomic rotation without race conditions.
 */
final class RedisStorage implements StorageInterface
{
    private Redis $redis;
    private string $prefix;

    /**
     * @param Redis $redis Connected Redis instance
     * @param string $prefix Key prefix for namespacing
     */
    public function __construct(Redis $redis, string $prefix = 'drop:')
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
    }

    public function store(string $sessionId, string $userId, array $data, int $ttl): void
    {
        $key = $this->prefix . 'session:' . $sessionId;
        $sessionData = [
            'user_id' => $userId,
            'data' => $data,
            'created_at' => $data['created_at'] ?? time(),
            'last_activity' => time(),
        ];

        $this->redis->setex($key, $ttl, json_encode($sessionData));

        $userKey = $this->prefix . 'user:' . $userId;
        $this->redis->sAdd($userKey, $sessionId);
        $this->redis->expire($userKey, $ttl);
    }

    public function retrieve(string $sessionId): ?array
    {
        $key = $this->prefix . 'session:' . $sessionId;
        $data = $this->redis->get($key);

        if ($data === false) {
            return null;
        }

        $decoded = json_decode($data, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    public function delete(string $sessionId): void
    {
        $key = $this->prefix . 'session:' . $sessionId;
        $this->redis->del($key);
    }

    public function deleteUserSessions(string $userId): void
    {
        $userKey = $this->prefix . 'user:' . $userId;
        $sessions = $this->redis->sMembers($userKey);

        if (!empty($sessions)) {
            foreach ($sessions as $sessionId) {
                $this->delete($sessionId);
            }
            $this->redis->del($userKey);
        }
    }

    public function touch(string $sessionId, int $ttl): void
    {
        $key = $this->prefix . 'session:' . $sessionId;

        $data = $this->retrieve($sessionId);
        if ($data) {
            $data['last_activity'] = time();
            $this->redis->setex($key, $ttl, json_encode($data));
        }
    }

    public function rotateAtomic(
        string $oldId,
        string $newId,
        string $userId,
        array $data,
        int $ttl
    ): bool {
        $oldKey = $this->prefix . 'session:' . $oldId;
        $newKey = $this->prefix . 'session:' . $newId;

        $script = <<<LUA
            if redis.call('exists', KEYS[1]) == 1 then
                local oldData = redis.call('get', KEYS[1])
                local decoded = cjson.decode(oldData)
                
                if decoded.user_id ~= ARGV[3] then
                    return 0
                end
                
                local newData = cjson.decode(ARGV[1])
                newData.created_at = decoded.created_at
                
                redis.call('set', KEYS[2], cjson.encode(newData))
                redis.call('expire', KEYS[2], ARGV[2])
                redis.call('del', KEYS[1])
                return 1
            else
                return 0
            end
LUA;

        $sessionData = json_encode([
            'user_id' => $userId,
            'data' => $data,
            'created_at' => $data['created_at'] ?? time(),
            'last_activity' => time(),
        ]);

        $result = $this->redis->eval(
            $script,
            [$oldKey, $newKey, $sessionData, $ttl, $userId],
            2
        );

        return $result === 1;
    }

    public function countUserSessions(string $userId): int
    {
        $userKey = $this->prefix . 'user:' . $userId;
        $count = $this->redis->sCard($userKey);
        return $count !== false ? (int)$count : 0;
    }

    public function updateUserData(string $sessionId, array $userData): bool
    {
        $key = $this->prefix . 'session:' . $sessionId;

        $sessionData = $this->retrieve($sessionId);
        if (!$sessionData) {
            return false;
        }

        $sessionData['data']['user_data'] = $userData;
        $sessionData['last_activity'] = time();

        $ttl = $this->redis->ttl($key);
        if ($ttl <= 0) {
            return false;
        }

        $this->redis->setex($key, $ttl, json_encode($sessionData));

        return true;
    }
}
