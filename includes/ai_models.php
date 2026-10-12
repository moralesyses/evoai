<?php
/**
 * Structured outputs for includes/ai.php: the SDK turns these classes into
 * the JSON schema Claude answers in (provider "anthropic"), and parses the answer
 * back into them.
 * Loaded only when the Anthropic SDK (vendor/) is installed.
 */
declare(strict_types=1);

use Anthropic\Lib\Attributes\Constrained;
use Anthropic\Lib\Concerns\StructuredOutputModelTrait;
use Anthropic\Lib\Contracts\StructuredOutputModel;

/** An email draft: subject line and plain-text body. */
final class AiEmailDraft implements StructuredOutputModel
{
    use StructuredOutputModelTrait;

    #[Constrained(description: 'The email subject line, without a "Subject:" prefix')]
    public string $subject;

    #[Constrained(description: 'The email body as plain text, with a blank line between paragraphs')]
    public string $body;
}

/** A personal follow-up draft plus a tip for the member. */
final class AiFollowUpDraft implements StructuredOutputModel
{
    use StructuredOutputModelTrait;

    #[Constrained(description: 'The email subject line, without a "Subject:" prefix')]
    public string $subject;

    #[Constrained(description: 'The email body as plain text, with a blank line between paragraphs')]
    public string $body;

    #[Constrained(description: 'One short sentence for the member, not part of the email: the best next step with this contact')]
    public string $nextStep;
}
