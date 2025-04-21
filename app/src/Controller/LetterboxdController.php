<?php

namespace App\Controller;

use App\Service\Letterboxd\LetterboxdService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LetterboxdController extends AbstractController
{
    #[Route('/letterboxd_retry', name: 'letterboxd_retry')]
    public function retry(Request $request, LetterboxdService $letterboxdService): Response
    {
        $submittedToken = $request->request->get('token');

        if (!$this->isCsrfTokenValid('retry-letterboxd', $submittedToken)) {
            throw new \Exception('Invalid CSRF token');
        }

        $rating = $request->request->get('rating');
        $filmId = $request->request->get('id');
        $eventId = $request->request->get('eventId');

        if ($filmId && $rating) {
            $letterboxdService->submitRating($filmId, $rating, $eventId);
        }

        return $this->redirectToRoute('home');
    }
}
