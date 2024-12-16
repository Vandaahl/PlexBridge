<?php

namespace App\Controller;

use App\Service\Trakt\TraktService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class TraktController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function login(TraktService $traktService): Response
    {
        if ($traktService->isAccessTokenValid()) {
            return $this->redirectToRoute('home');
        }

        $url = $traktService->getAuthorizationUrl();
        return $this->redirect($url);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \ErrorException
     */
    #[Route('/redirect', name: 'redirect')]
    public function traktRedirect(Request $request, TraktService $traktService): Response
    {
        /** @var array{code: string, state: string} $params */
        $params = $request->query->all();

        if (!isset($params['state']) || $params['state'] !== TraktService::TRAKT_STATE) {
            throw new \ErrorException('Something went wrong with getting a code from Trakt');
        }

        $response = $traktService->getAccessToken($params['code']);

        return $this->redirectToRoute('token', $response);
    }

    #[Route('/redirect/token', name: 'token')]
    public function traktRedirectToken(Request $request, TraktService $traktService): Response
    {
        /** @var array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string, created_at: string} $params */
        $tokenData = $request->query->all();

        if (!isset($tokenData['access_token'])) {
            throw new \ErrorException('Something went wrong with getting a token from Trakt');
        }

        $traktService->saveAccessTokenData($tokenData);

        //return $this->render('base.html.twig', ['message' => 'You have been logged in.']);
        return $this->redirectToRoute('home');
    }
}
