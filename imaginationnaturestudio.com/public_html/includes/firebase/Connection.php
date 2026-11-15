<?php
declare(strict_types=1);

namespace IMS\Firebase;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Firestore;

/**
 * Firebase connection singleton.
 * Reads credentials from FIREBASE_CREDENTIALS env var (path to service account JSON)
 * or from the file at __DIR__/serviceAccount.json
 */
class Connection
{
    private static ?Firestore $instance = null;

    public static function firestore(): Firestore
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $credPath = getenv('FIREBASE_CREDENTIALS')
            ?: (__DIR__ . '/serviceAccount.json');

        if (!file_exists($credPath)) {
            throw new \RuntimeException(
                "Firebase service account not found at: {$credPath}\n" .
                "Set FIREBASE_CREDENTIALS env var or place serviceAccount.json in includes/firebase/"
            );
        }

        $factory = (new Factory)->withServiceAccount($credPath);
        self::$instance = $factory->createFirestore();

        return self::$instance;
    }

    /** Force a new connection (useful in tests). */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
