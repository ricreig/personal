<?php

declare(strict_types=1);

/**
 * Writes a message to STDOUT falling back to php://output when necessary.
 */
function writeOut(string $message): void
{
    if (defined('STDOUT') && is_resource(STDOUT)) {
        fwrite(STDOUT, $message);
        return;
    }

    $handle = fopen('php://output', 'wb');
    if ($handle === false) {
        echo $message;
        return;
    }

    fwrite($handle, $message);
    fclose($handle);
}
