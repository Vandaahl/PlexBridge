<?php

namespace App\Controller;

use App\DataTransferObject\LetterboxdLogEntryDTO;
use App\Service\SyncService;
use App\Service\Trakt\TraktService;
use App\Service\Utility\LogService;
use App\Service\Utility\SettingsService;
use App\Service\Utility\UtilityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(TraktService $traktService, SettingsService $settingService, Request $request, LogService $logService, UtilityService $utilityService): Response
    {
        $settingsSaved = false;
        if ($request->request->get('submitSettings')) {
            $settings = ['services' => $request->request->all('services')];
            $settingService->saveSettings($settings);
            $settingsSaved = true;
        }

        $isTraktLoggedIn = $isTraktPrepared = $isLetterboxdPrepared = $traktLog = $letterboxdLog = null;
        if (isset($settingService->getSettingsFromStorage()['settings']['services'])) {
            if (in_array('trakt', $settingService->getSettingsFromStorage()['settings']['services'])) {
                $isTraktLoggedIn = $traktService->isAccessTokenValid();
                $isTraktPrepared = $utilityService->getDockerSecret('trakt_client_id') && $utilityService->getDockerSecret('trakt_client_secret');
                $traktLog = $logService->getLatestLogLines('trakt', 10);
            }
            if (in_array('letterboxd', $settingService->getSettingsFromStorage()['settings']['services'])) {
                $isLetterboxdPrepared = $utilityService->getDockerSecret('letterboxd_cookie_user_value') && $utilityService->getDockerSecret('letterboxd_cookie_csrf_value');
                /** @var LetterboxdLogEntryDTO[] $letterboxdLog */
                $letterboxdLog = $logService->getLatestLogLines('letterboxd', 10);
            }
        }

        return $this->render('base.html.twig', [
            'message' => '',
            'incomingLog' => $logService->getLatestLogLines('incoming', 10),
            'traktLog' => $traktLog,
            'letterboxdLog' => $letterboxdLog,
            'settingsSaved' => $settingsSaved,
            'settings' => $settingService->getSettingsFromStorage(),
            'isTraktLoggedIn' => $isTraktLoggedIn,
            'isTraktPrepared' => $isTraktPrepared,
            'isLetterboxdPrepared' => $isLetterboxdPrepared,
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/sync', name: 'sync')]
    public function sync(Request $request, SyncService $syncService): Response
    {
        $postData = $request->request->get('payload');
        if ($postData) {
            $syncService->handleIncomingRequests($postData);
        }

        return new JsonResponse(
            true
        );
    }
}
