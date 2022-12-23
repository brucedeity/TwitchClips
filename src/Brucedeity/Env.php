<?php

namespace Brucedeity\Twitchclips;

class Env
{
    /**
     * An array of key-value pairs from the .env file.
     *
     * @var array
     */
    private static $pairs;

    /**
     * Initializes the Env class by reading the .env file.
     *
     * @param string $path The path to the .env file.
     */
    public static function init($path)
    {
        // Parse the .env file into an array of key-value pairs
        self::$pairs = self::parseEnv($path);
    }

    /**
     * Gets the value of a variable from the .env file.
     *
     * @param string $key The name of the variable.
     * @return string The value of the variable.
     */
    public static function get($key)
    {
        // Check if the variable is set in the .env file
        if (!isset(self::$pairs[$key])) {
            throw new Exception("Undefined environment variable: $key");
        }

        // Return the value of the variable
        return self::$pairs[$key];
    }

    /**
     * Parses the .env file into an array of key-value pairs.
     *
     * @param string $path The path to the .env file.
     * @return array The array of key-value pairs.
     */
    private static function parseEnv($path)
    {
        // Read the file into an array of lines
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // Print the contents of the $lines array
        // var_dump($lines);

        // Parse the lines into an array of key-value pairs
        $pairs = [];
        foreach ($lines as $line) {
            // Skip lines that start with a # character
            if (strpos(ltrim($line), '#') === 0) {
                continue;
            }

            // Split the line into a key-value pair
            list($key, $value) = array_map('trim', explode('=', $line, 2));

            // Add the pair to the array
            $pairs[$key] = $value;
        }

        // Return the array of key-value pairs
        return $pairs;
    }
}
