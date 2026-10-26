<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Communication;

use Eventor\Communication\Application\Rendering\BuiltInTemplates;
use Eventor\Communication\Application\Rendering\SandboxedTemplateRenderer;
use Eventor\Communication\Application\Rendering\TemplateVariables;
use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Exception\InvalidTemplate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * COM-08: organizer text is user content. The sandbox lets through variables, `if` and `for` and a few filters, and
 * nothing else; a typo in a variable is an error at save time, not an empty string in an e-mail.
 */
final class SandboxedTemplateRendererTest extends TestCase
{
    private SandboxedTemplateRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new SandboxedTemplateRenderer();
    }

    public function test_it_renders_variables_conditions_and_loops(): void
    {
        $variables = array_replace_recursive(TemplateVariables::blank(), TemplateVariables::sample());
        /** @var array<string, mixed> $variables */
        $out = $this->renderer->render(
            'Ahoj {{ recipient.name }}!{% for p in participants %} [{{ p.name|upper }}]{% endfor %}{% if payment.iban %} IBAN {{ payment.iban }}{% endif %}',
            $variables,
        );

        self::assertSame('Ahoj Jana Nováková! [ANNA NOVÁKOVÁ] [JANA NOVÁKOVÁ] IBAN CZ6501000000000123456789', $out);
    }

    public function test_a_subject_is_one_line(): void
    {
        self::assertSame('a b', $this->renderer->renderLine("a\n  b", []));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forbidden(): iterable
    {
        yield 'unknown variable (a typo)' => ['Hello {{ recipient.nmae }}'];
        yield 'include' => ['{% include "x" %}'];
        yield 'set' => ['{% set x = 1 %}{{ x }}'];
        yield 'function' => ['{{ constant("PHP_VERSION") }}'];
        yield 'dump of the context' => ['{{ _context|json_encode }}'];
        yield 'filter map (arbitrary callable)' => ['{{ participants|map("system") }}'];
        yield 'filter raw is not whitelisted' => ['{{ recipient.name|raw }}'];
        yield 'syntax error' => ['{{ recipient.name '];
    }

    #[DataProvider('forbidden')]
    public function test_it_refuses_what_the_sandbox_does_not_allow(string $source): void
    {
        $this->expectException(InvalidTemplate::class);

        $this->renderer->assertValid('Subject', $source);
    }

    public function test_every_built_in_template_is_valid_in_every_language(): void
    {
        foreach (TemplateKey::cases() as $key) {
            foreach (MessageLocale::cases() as $locale) {
                $text = BuiltInTemplates::get($key, $locale);
                $this->renderer->assertValid($text['subject'], $text['body']);
            }
        }

        $this->addToAssertionCount(1);
    }

    public function test_a_value_that_looks_like_a_template_is_not_evaluated(): void
    {
        $variables = TemplateVariables::blank();
        $recipient = $variables['recipient'];
        \assert(\is_array($recipient));
        $recipient['name'] = '{{ 7*7 }} {% include "x" %}';
        $variables['recipient'] = $recipient;

        self::assertSame('Hi {{ 7*7 }} {% include "x" %}', $this->renderer->render('Hi {{ recipient.name }}', $variables));
    }
}
