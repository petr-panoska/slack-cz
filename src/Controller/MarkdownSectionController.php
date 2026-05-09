<?php

namespace App\Controller;

use App\Markdown\MarkdownRenderer;
use App\Markdown\Section\Config;
use App\Markdown\Section\FetcherInterface;
use App\Markdown\Section\Page;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Single controller pro všechny GitHub-backed MD sekce (`/docs`, `/wiki`).
 *
 * Per-sekci je injektovaný `FetcherInterface` + `Config` přes named services
 * (`app.section.{name}.fetcher`, `app.section.{name}.config`). Šablony jsou
 * sdílené (`templates/pages/_section/{index,show}.html.twig`).
 */
final class MarkdownSectionController extends AbstractController
{
    public function __construct(
        #[Autowire('@app.section.docs.fetcher')]
        private readonly FetcherInterface $docs,
        #[Autowire('@app.section.docs.config')]
        private readonly Config $docsConfig,
        #[Autowire('@app.section.wiki.fetcher')]
        private readonly FetcherInterface $wiki,
        #[Autowire('@app.section.wiki.config')]
        private readonly Config $wikiConfig,
        private readonly MarkdownRenderer $renderer,
    ) {
    }

    #[Route('/docs', name: 'app_docs_index')]
    public function docsIndex(): Response
    {
        return $this->renderIndex($this->docs, $this->docsConfig, 'app_docs_index', 'app_docs_show', 'Docs');
    }

    #[Route('/docs/{slug}', name: 'app_docs_show', requirements: ['slug' => '[A-Za-z0-9._-]+'])]
    public function docsShow(string $slug): Response
    {
        return $this->renderShow($slug, $this->docs, $this->docsConfig, 'app_docs_index', 'app_docs_show', 'Docs');
    }

    #[Route('/wiki', name: 'app_wiki_index')]
    public function wikiIndex(): Response
    {
        return $this->renderIndex($this->wiki, $this->wikiConfig, 'app_wiki_index', 'app_wiki_show', 'Wiki');
    }

    #[Route('/wiki/{slug}', name: 'app_wiki_show', requirements: ['slug' => '[A-Za-z0-9._-]+'])]
    public function wikiShow(string $slug): Response
    {
        return $this->renderShow($slug, $this->wiki, $this->wikiConfig, 'app_wiki_index', 'app_wiki_show', 'Wiki');
    }

    private function renderIndex(
        FetcherInterface $fetcher,
        Config $config,
        string $indexRoute,
        string $showRoute,
        string $sectionLabel,
    ): Response {
        $readme = $fetcher->get('README');
        $bodyHtml = $readme !== null
            ? $this->renderer->render($readme->body, $config->internalRoutePrefix)
            : null;

        return $this->render('pages/_section/index.html.twig', [
            'page' => $readme,
            'entries' => $fetcher->list(),
            'body_html' => $bodyHtml,
            'index_route' => $indexRoute,
            'show_route' => $showRoute,
            'section_label' => $sectionLabel,
            'active_slug' => 'README',
        ]);
    }

    private function renderShow(
        string $slug,
        FetcherInterface $fetcher,
        Config $config,
        string $indexRoute,
        string $showRoute,
        string $sectionLabel,
    ): Response {
        // README je rendrované jako index — `/docs/README` resp. `/wiki/README` 301 na index.
        if (strcasecmp($slug, 'README') === 0) {
            return $this->redirectToRoute($indexRoute, [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $page = $fetcher->get($slug);
        if ($page === null) {
            throw $this->createNotFoundException(sprintf('%s "%s" nenalezeno.', $sectionLabel, $slug));
        }

        $body = $page->body;
        // Pokud frontmatter má `title`, render ho jako H1 v template a stripneme úvodní H1
        // z body (jinak by se zobrazila dvakrát). `\s*` jí blank line po frontmatter stripu.
        if ($page->title !== '') {
            $body = preg_replace('/^\s*#\s+.+?\R+/u', '', $body, 1) ?? $body;
        }
        $bodyHtml = $this->renderer->render($body, $config->internalRoutePrefix);

        return $this->render('pages/_section/show.html.twig', [
            'page' => $page,
            'entries' => $fetcher->list(),
            'body_html' => $bodyHtml,
            'index_route' => $indexRoute,
            'show_route' => $showRoute,
            'section_label' => $sectionLabel,
            'active_slug' => $page->slug,
        ]);
    }
}
