<?php

namespace App\Controller;

use App\Entity\Event;
use App\Service\SyncService;
use App\Service\Trakt\TraktService;
use App\Service\Utility\SettingsService;
use App\Service\Utility\UtilityService;
use Doctrine\ORM\EntityManagerInterface;
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
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/', name: 'home')]
    public function index(TraktService $traktService, SettingsService $settingService, Request $request, UtilityService $utilityService): Response
    {
        $settingsSaved = false;
        if ($request->request->get('submitSettings')) {
            $settings = ['services' => $request->request->all('services')];
            $settingService->saveSettings($settings);
            $settingsSaved = true;
        }

        $activatedServices = $settingService->getSettings('services');
        $isTraktLoggedIn = $isTraktPrepared = $isLetterboxdPrepared = $traktLog = $letterboxdLog = null;
        if (count($activatedServices)) {
            if (in_array('trakt', $activatedServices)) {
                $isTraktLoggedIn = $traktService->isAccessTokenValid();
                $isTraktPrepared = $utilityService->getDockerSecret('trakt_client_id') && $utilityService->getDockerSecret('trakt_client_secret');
            }
            if (in_array('letterboxd', $activatedServices)) {
                $isLetterboxdPrepared = $utilityService->getDockerSecret('letterboxd_cookie_user_value') && $utilityService->getDockerSecret('letterboxd_cookie_csrf_value');
            }
        }

        /*$events = $this->entityManager->getRepository(Event::class)->findBy(
            [],
            ['id' => 'DESC'],
            10
        );*/

        $events = $this->entityManager->createQueryBuilder()
            ->select('e', 'ep', 'm')
            ->from(Event::class, 'e')
            ->leftJoin('e.episode', 'ep')
            ->leftJoin('e.movie', 'm')
            ->orderBy('e.id', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $this->render('base.html.twig', [
            'message' => '',
            'events' => $events,
            'settingsSaved' => $settingsSaved,
            'services' => $activatedServices,
            'isTraktLoggedIn' => $isTraktLoggedIn,
            'isTraktPrepared' => $isTraktPrepared,
            'isLetterboxdPrepared' => $isLetterboxdPrepared,
            'activatedServices' => $activatedServices,
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
