<?php declare(strict_types=1);

namespace App\Service\Utility;

class UtilityService
{
    /**
     * @param string $filename The name of the Docker secret without file extension.
     * @return false|string
     */
    public function getDockerSecret(string $filename): false|string
    {
        $secret = file_get_contents("/run/secrets/$filename");

        if ($secret === false) {
            // For Podman compatability, check if the secret is available as an environment variable.
            $secret = getenv(strtoupper($filename));
        }

        return $secret;
    }
}