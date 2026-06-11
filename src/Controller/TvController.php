<?php

namespace App\Controller;

use App\Feed\Tv\TvFeedInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TvController extends AbstractController
{
    #[Route('/tv', name: 'app_tv')]
    public function index(TvFeedInterface $feed): Response
    {
        return $this->render('pages/tv.html.twig', [
            'sections' => $feed->sections(),
        ]);
    }

    /**
     * Load-more for a single slider: returns the next page of cards for the
     * source addressed by `key`, plus the token for the page after that.
     */
    #[Route('/tv/more', name: 'app_tv_more', methods: ['GET'])]
    public function more(Request $request, TvFeedInterface $feed): JsonResponse
    {
        $key = (string) $request->query->get('key', '');
        $token = (string) $request->query->get('page', '');

        $group = $key !== '' ? $feed->page($key, $token !== '' ? $token : null) : null;

        if ($group === null) {
            return new JsonResponse(['html' => '', 'nextPage' => null]);
        }

        return new JsonResponse([
            'html' => $this->renderView('pages/_tv_cards.html.twig', ['items' => $group->items]),
            'nextPage' => $group->nextPageToken,
        ]);
    }
}
