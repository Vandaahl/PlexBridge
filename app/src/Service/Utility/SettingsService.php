<?php declare(strict_types=1);

namespace App\Service\Utility;

class SettingsService
{
    public const SETTINGS_LOCATION = "/app/var/settings.json";

    /**
     * Save settings to disk.
     *
     * @param array $settings
     * @return void
     * @throws \Exception
     */
    public function saveSettings(array $settings): void
    {
        $json = $this->prepareSettings($settings);
        $file = $this::SETTINGS_LOCATION;

        if (!file_exists($file)) {
            if (fopen($file, "w") === false) {
                throw new \Exception('Failed to create location for settings file');
            }
        }

        if (file_put_contents($file, $json) === false) {
            throw new \Exception('Failed to save settings to disk');
        }
    }

    /**
     * @return null|array{settings: array}
     * @throws \Exception
     */
    public function getSettingsFromStorage(): ?array
    {
        $settings = file_get_contents($this::SETTINGS_LOCATION);

        if ($settings === "") {
            return null;
        }

        if ($settings === false) {
            throw new \Exception('Failed to load settings from disk');
        }

        $settings = json_decode($settings, true);

        if (!$settings) {
            throw new \Exception('Failed to convert settings data to array');
        }

        return $settings;
    }

    private function prepareSettings(array $settings): false|string
    {
        return json_encode(['settings' => $settings]);
    }
}