<?php

namespace App\Controller;

use App\Feed\FeedFetcherInterface;
use App\Old\Entity\Uzivatel;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PagesController extends AbstractController
{
    #[Route('/', name: 'app_index')]
    public function index(FeedFetcherInterface $feed): Response
    {
        return $this->render('pages/index.html.twig', [
            'feed_items' => $feed->fetch(12),
        ]);
    }

    #[Route('/profile', name: 'app_profile')]
    public function profile(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $this->render('pages/profile.html.twig');
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
