<?php

namespace App\Controller;

use App\Feed\FeedFetcherInterface;
use App\Old\Entity\Uzivatel;
use App\Repository\HighlineCrossingRepository;
use App\Repository\HighlinePhotoRepository;
use Doctrine\Persistence\ManagerRegistry;
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

    #[Route('/o-projektu', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('pages/about.html.twig');
    }

    #[Route('/old-users', name: 'app_old_users')]
    public function oldUsers(ManagerRegistry $doctrine): Response
    {
        $oldEm = $doctrine->getManager('old');
        $users = $oldEm->getRepository(Uzivatel::class)->findAll();

        return $this->render('pages/old_users.html.twig', [
            'users' => $users
        ]);
    }
}
