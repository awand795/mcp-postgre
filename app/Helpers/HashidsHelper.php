<?php

namespace App\Helpers;

class HashidsHelper
{
    private static $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    private static $salt = 123456789; // Salt integer for bitwise obfuscation

    /**
     * Encode an integer ID into an obfuscated Base62 string.
     *
     * @param int $id
     * @return string
     */
    public static function encode(int $id): string
    {
        // Apply XOR bitwise salt to obfuscate sequence (1, 2, 3, etc.)
        $obfuscated = $id ^ self::$salt;
        
        // Convert to Base62
        $base = strlen(self::$alphabet);
        $result = '';
        while ($obfuscated > 0) {
            $rem = $obfuscated % $base;
            $result = self::$alphabet[$rem] . $result;
            $obfuscated = (int)($obfuscated / $base);
        }
        
        return $result ?: self::$alphabet[0];
    }

    /**
     * Decode an obfuscated Base62 string back to the original integer ID.
     *
     * @param string $hash
     * @return int|null
     */
    public static function decode(string $hash): ?int
    {
        if (empty($hash)) {
            return null;
        }

        $base = strlen(self::$alphabet);
        $num = 0;
        $len = strlen($hash);
        
        for ($i = 0; $i < $len; $i++) {
            $char = $hash[$i];
            $pos = strpos(self::$alphabet, $char);
            if ($pos === false) {
                return null;
            }
            $num = $num * $base + $pos;
        }
        
        // Reverse XOR bitwise salt
        $original = $num ^ self::$salt;
        
        // Sanity check: ID must be positive and non-zero
        if ($original <= 0) {
            return null;
        }
        
        return $original;
    }
}
