<?php

namespace App\Controller;

use App\Feed\FeedFetcherInterface;
use App\Repository\HighlineCrossingRepository;
use App\Repository\HighlinePhotoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PagesController extends AbstractController
{
    #[Route('/', name: 'app_index')]
    public function index(
        FeedFetcherInterface $feed,
        HighlineCrossingRepository $crossings,
        HighlinePhotoRepository $photos,
    ): Response {
        return $this->render('pages/index.html.twig', [
            'feed_items' => $feed->fetch(12),
            'recent_crossings' => $crossings->findRecent(),
            'recent_photos' => $photos->findRecentForHomepage(6),
        ]);
    }

    #[Route('/profile', name: 'app_profile')]
    public function profile(HighlineCrossingRepository $crossings): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $this->render('pages/profile.html.twig', [
            'first_crossing_date' => $crossings->findFirstCrossingDate($this->getUser()),
        ]);
    }

    #[Route('/tv', name: 'app_tv')]
    public function tv(FeedFetcherInterface $feed): Response
    {
        return $this->render('pages/tv.html.twig', [
            'feed_items' => $feed->fetch(24),
        ]);
    }

    #[Route('/o-projektu', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('pages/about.html.twig');
    }
}
