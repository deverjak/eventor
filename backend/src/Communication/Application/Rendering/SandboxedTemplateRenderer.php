<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Rendering;

use Eventor\Communication\Domain\Exception\InvalidTemplate;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityPolicy;

/**
 * Renders the text of an organizer (COM-08): user content, therefore Twig in a sandbox. Only `if` and `for`, a short
 * list of harmless filters, no functions, no includes, strict variables (a typo is an error, not an empty string).
 * The variables are plain arrays and strings from read models, so there is no object whose methods could be called.
 * The output is plain text; the e-mail channel escapes it for the HTML part.
 */
final class SandboxedTemplateRenderer
{
    private const array TAGS = ['if', 'for'];
    private const array FILTERS = ['escape', 'e', 'upper', 'lower', 'capitalize', 'title', 'trim', 'length', 'default', 'join', 'first', 'last', 'number_format'];

    private readonly Environment $twig;

    public function __construct()
    {
        $this->twig = new Environment(new ArrayLoader(), ['autoescape' => false, 'strict_variables' => true, 'cache' => false]);
        $this->twig->addExtension(new SandboxExtension(new SecurityPolicy(self::TAGS, self::FILTERS, [], [], []), true));
    }

    /**
     * @param array<string, mixed> $variables
     *
     * @throws InvalidTemplate
     */
    public function render(string $source, array $variables): string
    {
        try {
            return $this->twig->createTemplate($source)->render($variables);
        } catch (Error $e) {
            throw InvalidTemplate::because(preg_replace('/ in "[^"]*"( at line \d+)?\.?$/', '', $e->getRawMessage()) ?? $e->getRawMessage());
        }
    }

    /**
     * One line, for a subject.
     *
     * @param array<string, mixed> $variables
     */
    public function renderLine(string $source, array $variables): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $this->render($source, $variables)));
    }

    /**
     * Compiles and renders the text against the sample data: a template that passes here cannot fail on a typo later.
     *
     * @throws InvalidTemplate
     */
    public function assertValid(string $subject, string $body): void
    {
        $variables = array_replace_recursive(TemplateVariables::blank(), TemplateVariables::sample());
        /** @var array<string, mixed> $variables */
        $this->renderLine($subject, $variables);
        $this->render($body, $variables);
    }
}
