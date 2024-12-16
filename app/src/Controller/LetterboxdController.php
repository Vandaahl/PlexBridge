<?php

namespace App\Controller;

use App\Service\Letterboxd\LetterboxdService;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class LetterboxdController extends AbstractController
{
    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/letterboxd', name: 'letterboxd_home')]
    public function index(LetterboxdService $letterboxdService): Response
    {
        dd($letterboxdService->publishActivity('tt4225622', 7));
    }

    #[Route('/letterboxd_retry', name: 'letterboxd_retry')]
    public function retry(Request $request, LetterboxdService $letterboxdService, LoggerInterface $letterboxd_retriesLogger): Response
    {
        $submittedToken = $request->request->get('token');

        if (!$this->isCsrfTokenValid('retry-letterboxd', $submittedToken)) {
            throw new \Exception('Invalid CSRF token');
        }

        $rating = $request->request->get('rating');
        $filmId = $request->request->get('id');
        $logDate = $request->request->get('date');

        // Add the movie to the retries log, so we can use it to not display the retry button next to the specific entry.
        $letterboxd_retriesLogger->info("{\"originalLogDate\": \"$logDate\", \"id\": \"$filmId\"}");

        if ($filmId && $rating) {
            $letterboxdService->submitRating($filmId, $rating);
        }

        return $this->redirectToRoute('home');
    }
}
