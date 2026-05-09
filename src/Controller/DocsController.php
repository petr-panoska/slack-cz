<?php

namespace App\Controller;

use App\Docs\DocsFetcherInterface;
use App\Markdown\MarkdownRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DocsController extends AbstractController
{
    public function __construct(
        private readonly DocsFetcherInterface $docs,
        private readonly MarkdownRenderer $renderer,
    ) {
    }

    #[Route('/docs', name: 'app_docs_index')]
    public function index(): Response
    {
        $readme = $this->docs->get('README');
        $bodyHtml = null;
        if ($readme !== null) {
            $bodyHtml = $this->renderer->render($readme->body);
        }

        return $this->render('pages/docs/index.html.twig', [
            'readme' => $readme,
            'entries' => $this->docs->list(),
            'body_html' => $bodyHtml,
        ]);
    }

    #[Route('/docs/{slug}', name: 'app_docs_show', requirements: ['slug' => '[A-Za-z0-9._-]+'])]
    public function show(string $slug): Response
    {
        // README je rendrované jako index `/docs` — `/docs/README` je redundantní.
        if (strcasecmp($slug, 'README') === 0) {
            return $this->redirectToRoute('app_docs_index', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $entries = $this->docs->list();
        $entry = null;
        foreach ($entries as $e) {
            if ($e->slug === $slug) {
                $entry = $e;
                break;
            }
        }
        if ($entry === null) {
            throw $this->createNotFoundException(sprintf('Doc "%s" not found.', $slug));
        }

        $doc = $this->docs->get($slug);
        if ($doc === null) {
            throw $this->createNotFoundException(sprintf('Doc "%s" content unavailable.', $slug));
        }

        $title = MarkdownRenderer::extractTitle($doc->body) ?? $doc->label;
        $html = $this->renderer->render($doc->body);

        return $this->render('pages/docs/show.html.twig', [
            'doc' => $doc,
            'entries' => $entries,
            'title' => $title,
            'body_html' => $html,
        ]);
    }
}
