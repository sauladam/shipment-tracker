<?php

namespace Sauladam\ShipmentTracker\Utils;

class Utils
{
    public static function ensureUtf8($input)
    {
        if (is_array($input)) {
            array_walk_recursive($input, function (&$value) {
                if (is_string($value)) {
                    $value = self::ensureUtf8($value);
                }
            });

            return $input;
        }

        return !mb_check_encoding($input, 'UTF-8') ? utf8_encode($input) : $input;
    }
}
