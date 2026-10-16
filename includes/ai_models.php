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

/**
 * An 88MMM Assistant answer: whether the question is about 88MMM Circle and this portal, then the answer.
 * The server shows a fixed refusal instead of the reply when onTopic is false (includes/assistant.php).
 */
final class AiChatReply implements StructuredOutputModel
{
    use StructuredOutputModelTrait;

    #[Constrained(description: 'true only when the message is about 88MMM Circle or this portal (see SCOPE); false for anything else')]
    public bool $onTopic;

    #[Constrained(description: 'The answer to show the person when onTopic is true; an empty string when onTopic is false')]
    public string $reply;
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
