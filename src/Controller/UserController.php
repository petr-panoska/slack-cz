<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\HighlineCrossingRepository;
use App\Repository\LonglineCrossingRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    #[Route('/denicky', name: 'app_user_directory')]
    public function directory(UserRepository $users): Response
    {
        return $this->render('pages/denicky.html.twig', [
            'rows' => $users->findDiaryRows(),
        ]);
    }

    #[Route('/denik/{id}', name: 'app_user_denik', requirements: ['id' => '\d+'])]
    public function denik(
        User $user,
        HighlineCrossingRepository $crossings,
        LonglineCrossingRepository $longlines,
    ): Response {
        return $this->render('pages/user_denik.html.twig', [
            'profile' => $user,
            'crossings' => $crossings->findForUser($user),
            'firstCrossingDate' => $crossings->findFirstCrossingDate($user),
            'mapHighlines' => $crossings->findUserHighlinesForMap($user),
            'longlineCrossings' => $longlines->findForUser($user),
        ]);
    }
}
