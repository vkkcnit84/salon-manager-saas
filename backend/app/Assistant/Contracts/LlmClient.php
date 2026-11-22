<?php

namespace App\Assistant\Contracts;

/**
 * Tool-calling LLM seam. Swap RuleBasedLlmClient for a provider (Claude/OpenAI/Gemini via the Laravel AI SDK
 * or Prism) later. The model may only *choose* a whitelisted tool; it never sees or writes SQL.
 */
interface LlmClient
{
    /** @return array{tool:?string,args:array,language:string,reply:?string} */
    public function decide(string $question, array $toolNames): array;

    /** Turn a tool result into a short answer in the user's language. */
    public function compose(string $tool, array $result, string $language): string;
}
