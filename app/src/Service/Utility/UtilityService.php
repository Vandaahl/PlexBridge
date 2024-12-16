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
        return file_get_contents("/run/secrets/$filename");
    }
}