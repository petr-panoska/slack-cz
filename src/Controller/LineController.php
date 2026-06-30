<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\LineCrossingRepository;
use App\Repository\LineEditRepository;
use App\Repository\LinePhotoCommentRepository;
use App\Repository\LinePhotoLikeRepository;
use App\Repository\LinePhotoRepository;
use App\Repository\LineRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Line;

final class LineController extends AbstractController
{
    // Current production host. Used to build click-through links to the live site.
    private const PRODUCTION_URL = 'https://www.slack.cz';


    #[Route('/mapa', name: 'app_line_map')]
    public function map(): Response
    {
        return $this->render('pages/map.html.twig');
    }

    #[Route('/data-report', name: 'app_data_report', methods: ['GET'])]
    public function dataReport(LineRepository $lines): Response
    {
        return $this->render('pages/data_report.html.twig', [
            'lines' => $lines->findForDataReport(),
            'production_url' => self::PRODUCTION_URL,
        ]);
    }

    #[Route('/lajna/{slug}', name: 'app_line_detail', requirements: ['slug' => '[a-z0-9-]+'])]
    public function detail(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Line $line,
        LineCrossingRepository $crossings,
        LineEditRepository $edits,
        LinePhotoRepository $photos,
        LinePhotoLikeRepository $likes,
        LinePhotoCommentRepository $comments,
    ): Response {
        $photoList = $photos->findForLine($line);
        $photoIds = array_map(static fn ($p) => $p->getId(), $photoList);
        $user = $this->getUser();

        return $this->render('pages/line_detail.html.twig', [
            'line' => $line,
            'crossings' => $crossings->findForLine($line),
            'historyCount' => $edits->countHistoryFor($line),
            'photos' => $photoList,
            'likeCounts' => $likes->countsForPhotos($photoIds),
            'commentCounts' => $comments->countsForPhotos($photoIds),
            'likedIds' => $user instanceof User ? $likes->likedPhotoIdsForUser($user, $photoIds) : [],
        ]);
    }

    #[Route('/mapa/data', name: 'app_line_map_data', methods: ['GET'])]
    public function data(LineRepository $repository): JsonResponse
    {
        return new JsonResponse($repository->findAllForMap());
    }

    #[Route('/mapa/feed', name: 'app_line_map_feed', methods: ['GET'])]
    public function feed(Request $request, LineCrossingRepository $repository): JsonResponse
    {
        // Time-travel playback window (map controller): a virtual "now" minus a fixed span.
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

        // Sidebar filter — explicit date range (inclusive of the whole "to" day).
        $fromParam = $request->query->get('from');
        $toParam = $request->query->get('to');
        if (is_string($fromParam) && is_string($toParam)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromParam) === 1
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toParam) === 1
        ) {
            try {
                $from = new \DateTimeImmutable($fromParam);
                $to = (new \DateTimeImmutable($toParam))->setTime(23, 59, 59);
            } catch (\Exception) {
                return new JsonResponse([], 400);
            }
            if ($from > $to) {
                [$from, $to] = [$to->setTime(0, 0), $from->setTime(23, 59, 59)];
            }
            return new JsonResponse($repository->findForFeedInRange($from, $to));
        }

        // Sidebar filter — last N crossings. Invalid/missing → default RECENT_LIMIT; clamp to a sane ceiling.
        $limit = null;
        $rawLimit = (int) $request->query->get('limit', 0);
        if ($rawLimit >= 1) {
            $limit = min($rawLimit, LineCrossingRepository::FEED_LIMIT_MAX);
        }

        return new JsonResponse($repository->findRecentForJson($limit));
    }

    #[Route('/mapa/timeline-data', name: 'app_line_map_timeline', methods: ['GET'])]
    public function timeline(
        LineRepository $lines,
        LineCrossingRepository $crossings,
    ): JsonResponse {
        return new JsonResponse([
            'lines' => $lines->findAllForTimeline(),
            'crossings' => $crossings->findAllForTimeline(),
        ]);
    }
}
