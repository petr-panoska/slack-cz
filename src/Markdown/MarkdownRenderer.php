<?php

namespace App\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * GFM markdown → HTML renderer with internal link rewriting.
 *
 * Anything that looks like a relative `*.md` link is rewritten so that
 * cross-references between docs (e.g. `[architecture.md](architecture.md)`)
 * resolve to the in-app /docs/{slug} route instead of 404'ing.
 */
final class MarkdownRenderer
{
    private readonly MarkdownConverter $defaultConverter;

    public function __construct(
        #[Autowire('%docs.internal_route_prefix%')]
        private readonly string $internalDocsRoutePrefix = '/docs',
    ) {
        $this->defaultConverter = $this->buildConverter($this->internalDocsRoutePrefix);
    }

    /**
     * @param string|null $internalRoutePrefix override pro relativní `*.md` linky
     *   (např. `/wiki` při renderování wiki kapitol). `null` = injektovaný `%docs.internal_route_prefix%`.
     */
    public function render(string $markdown, ?string $internalRoutePrefix = null): string
    {
        $converter = $internalRoutePrefix === null
            ? $this->defaultConverter
            : $this->buildConverter($internalRoutePrefix);

        return (string) $converter->convert($markdown);
    }

    private function buildConverter(string $internalRoutePrefix): MarkdownConverter
    {
        $env = new Environment([
            'html_input' => 'allow',         // trusted source (our own GH repo)
            'allow_unsafe_links' => false,
            'renderer' => [
                'soft_break' => "\n",
            ],
        ]);
        $env->addExtension(new CommonMarkCoreExtension());
        $env->addExtension(new GithubFlavoredMarkdownExtension());

        $prefix = rtrim($internalRoutePrefix, '/');
        $env->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event) use ($prefix): void {
            foreach ($event->getDocument()->iterator() as $node) {
                if (!$node instanceof Link) {
                    continue;
                }
                $url = $node->getUrl();
                if (preg_match('/^([A-Za-z0-9._-]+)\.md(#.+)?$/', $url, $m) === 1) {
                    $node->setUrl($prefix . '/' . $m[1] . ($m[2] ?? ''));
                }
            }
        });

        return new MarkdownConverter($env);
    }

    public static function extractTitle(string $markdown): ?string
    {
        if (preg_match('/^#\s+(.+?)\s*$/m', $markdown, $m) === 1) {
            return trim($m[1]);
        }
        return null;
    }
}
