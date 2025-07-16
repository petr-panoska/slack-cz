<?php

namespace App\Controller;

use App\Entity\Old\Uzivatel;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PagesController extends AbstractController
{
    #[Route('/', name: 'app_index')]
    public function index(): Response
    {
        return $this->render('pages/index.html.twig', [
            'controller_name' => 'PagesController',
        ]);
    }

    #[Route('/profile', name: 'app_profile')]
    public function profile(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $this->render('pages/profile.html.twig');
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
