<?php

namespace App\Controller;

use App\Repository\HighlineCrossingRepository;
use App\Repository\HighlineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HighlineController extends AbstractController
{
    #[Route('/mapa', name: 'app_highline_map')]
    public function map(): Response
    {
        return $this->render('pages/mapa.html.twig');
    }

    #[Route('/mapa/data', name: 'app_highline_map_data', methods: ['GET'])]
    public function data(HighlineRepository $repository): JsonResponse
    {
        return new JsonResponse($repository->findAllForMap());
    }

    #[Route('/mapa/recent-users', name: 'app_highline_map_recent_users', methods: ['GET'])]
    public function recentUsers(HighlineCrossingRepository $repository): JsonResponse
    {
        return new JsonResponse($repository->findRecentUsersForMap(10));
    }
}
