<?php

namespace App\Controller;

use App\Markdown\MarkdownRenderer;
use App\Wiki\WikiFetcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WikiController extends AbstractController
{
    public function __construct(
        private readonly WikiFetcherInterface $wiki,
        private readonly MarkdownRenderer $renderer,
        #[Autowire('%wiki.internal_route_prefix%')]
        private readonly string $internalRoutePrefix,
    ) {
    }

    #[Route('/wiki', name: 'app_wiki_index')]
    public function index(): Response
    {
        $entries = $this->wiki->list();

        $groups = [];
        foreach ($entries as $entry) {
            $groupKey = $entry->group !== '' ? $entry->group : '—';
            $groups[$groupKey][] = $entry;
        }

        return $this->render('pages/wiki/index.html.twig', [
            'entries' => $entries,
            'groups' => $groups,
        ]);
    }

    #[Route('/wiki/{slug}', name: 'app_wiki_show', requirements: ['slug' => '[A-Za-z0-9._-]+'])]
    public function show(string $slug): Response
    {
        $entries = $this->wiki->list();
        $entry = null;
        foreach ($entries as $e) {
            if ($e->slug === $slug) {
                $entry = $e;
                break;
            }
        }
        if ($entry === null) {
            throw $this->createNotFoundException(sprintf('Wiki kapitola "%s" neexistuje.', $slug));
        }

        $page = $this->wiki->get($slug);
        if ($page === null) {
            throw $this->createNotFoundException(sprintf('Wiki kapitola "%s" je nedostupná.', $slug));
        }

        // Pull-quote a title se renderují v template z frontmatter — odstřihnu úvodní H1
        // z body, aby se ve výsledku neobjevoval dvakrát (import script ho prependuje
        // do MD jako fallback pro plain GitHub view).
        $body = preg_replace('/^#\s+.+?\R+/u', '', $page->body, 1) ?? $page->body;
        $html = $this->renderer->render($body, $this->internalRoutePrefix);

        return $this->render('pages/wiki/show.html.twig', [
            'page' => $page,
            'entries' => $entries,
            'body_html' => $html,
        ]);
    }
}
