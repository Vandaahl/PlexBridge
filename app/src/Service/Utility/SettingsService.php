<?php declare(strict_types=1);

namespace App\Service\Utility;

use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;

class SettingsService
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    )
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Save settings to the database.
     *
     * @param array $settings ['services' => ['trakt', 'letterbox']]
     * @return void
     */
    public function saveSettings(array $settings): void
    {
        $repository = $this->entityManager->getRepository(Setting::class);

        /* @var array $value */
        foreach ($settings as $key => $value) {
            $setting = $repository->findOneBy(['settingKey' => $key]);
            if (!$setting) {
                $setting = new Setting();
                $setting->setSettingKey($key);
            }
            $setting->setSettingValue($value);
            $this->entityManager->persist($setting);
            $this->entityManager->flush();
        }
    }

    public function getSettings(string $key): array
    {
        $repository = $this->entityManager->getRepository(Setting::class);
        $setting = $repository->findOneBy(['settingKey' => $key]);

        if ($setting) {
            return $setting->getSettingValue();
        }

        return [];
    }
}