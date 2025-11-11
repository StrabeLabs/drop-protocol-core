<?php
declare(strict_types=1);

namespace DropProtocol\Services;

/**
 * Security validator for IP and User-Agent verification
 * 
 * Provides two validation modes:
 * - Strict: Rejects requests on changes
 * - Warning: Logs changes but allows requests
 */
final class SecurityValidator
{
    /**
     * Validate security context
     *
     * @param array $storedData Session data containing ip and user_agent
     * @param bool $strictIp Reject on IP change
     * @param bool $strictUa Reject on User-Agent change
     * @return array{valid: bool, warnings: string[]}
     */
    public static function validate(
        array $storedData,
        bool $strictIp = false,
        bool $strictUa = false
    ): array {
        $warnings = [];
        $valid = true;
        
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? null;
        $currentUa = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $storedIp = $storedData['ip'] ?? null;
        $storedUa = $storedData['user_agent'] ?? null;
        
        if ($storedIp && $currentIp !== $storedIp) {
            $warning = "IP change: {$storedIp} -> {$currentIp}";
            $warnings[] = $warning;
            
            if ($strictIp) {
                $valid = false;
            } else {
                error_log("DROP Security Warning: {$warning}");
            }
        }
        
        if ($storedUa && $currentUa !== $storedUa) {
            $similarity = self::calculateUaSimilarity($storedUa, $currentUa);
            
            if ($similarity < 0.8) {
                $warning = "UA change detected (similarity: {$similarity})";
                $warnings[] = $warning;
                
                if ($strictUa) {
                    $valid = false;
                } else {
                    error_log("DROP Security Warning: {$warning}");
                }
            }
        }
        
        return ['valid' => $valid, 'warnings' => $warnings];
    }
    
    /**
     * Calculate User-Agent similarity
     * 
     * Uses levenshtein distance for strings under 255 chars,
     * falls back to similar_text for longer strings.
     *
     * @param string $ua1 First User-Agent
     * @param string $ua2 Second User-Agent
     * @return float Similarity score (0.0 to 1.0)
     */
    private static function calculateUaSimilarity(string $ua1, string $ua2): float
    {
        $ua1 = preg_replace('/\/([\d]+)\.[\d.]+/', '/$1', $ua1) ?? $ua1;
        $ua2 = preg_replace('/\/([\d]+)\.[\d.]+/', '/$1', $ua2) ?? $ua2;
        
        $maxLen = max(strlen($ua1), strlen($ua2));
        if ($maxLen === 0) {
            return 1.0;
        }
        
        if (strlen($ua1) > 255 || strlen($ua2) > 255) {
            similar_text($ua1, $ua2, $percent);
            return $percent / 100;
        }
        
        $distance = levenshtein($ua1, $ua2);
        
        if ($distance === -1) {
            similar_text($ua1, $ua2, $percent);
            return $percent / 100;
        }
        
        return 1.0 - ($distance / $maxLen);
    }
}
