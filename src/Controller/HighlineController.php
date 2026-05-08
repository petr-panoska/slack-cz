<?php

namespace App\Controller;

use App\Repository\HighlineCrossingRepository;
use App\Repository\HighlineRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Highline;

final class HighlineController extends AbstractController
{
    #[Route('/mapa', name: 'app_highline_map')]
    public function map(): Response
    {
        return $this->render('pages/mapa.html.twig');
    }

    #[Route('/highline/{slug}', name: 'app_highline_detail', requirements: ['slug' => '[a-z0-9-]+'])]
    public function detail(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Highline $highline,
        HighlineCrossingRepository $crossings,
    ): Response {
        return $this->render('pages/highline_detail.html.twig', [
            'highline' => $highline,
            'crossings' => $crossings->findForHighline($highline),
        ]);
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

    #[Route('/mapa/feed', name: 'app_highline_map_feed', methods: ['GET'])]
    public function feed(Request $request, HighlineCrossingRepository $repository): JsonResponse
    {
        $date = $request->query->get('date');
        $days = (int) $request->query->get('days', 0);

        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && $days > 0) {
            try {
                $until = new \DateTimeImmutable($date);
                $from = $until->modify("-{$days} days");
            } catch (\Exception) {
                return new JsonResponse([], 400);
            }
            return new JsonResponse($repository->findForFeedInRange($from, $until));
        }

        return new JsonResponse($repository->findRecentForFeed(10));
    }

    #[Route('/mapa/timeline-data', name: 'app_highline_map_timeline', methods: ['GET'])]
    public function timeline(
        HighlineRepository $highlines,
        HighlineCrossingRepository $crossings,
    ): JsonResponse {
        return new JsonResponse([
            'highlines' => $highlines->findAllForTimeline(),
            'crossings' => $crossings->findAllForTimeline(),
        ]);
    }
}
