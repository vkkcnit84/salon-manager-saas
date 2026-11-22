<?php

namespace App\Assistant\Http;

use App\Assistant\Contracts\SpeechToText;
use App\Assistant\Services\AssistantService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AssistantController extends Controller
{
    private function gate(Request $r): void
    {
        abort_unless($r->user()->is_owner, 403, 'The assistant is available to the shop owner.');
    }

    public function ask(Request $r, AssistantService $svc)
    {
        $this->gate($r);

        return $svc->ask($r->user(), (string) $r->validate(['question' => ['required', 'string', 'max:500']])['question']);
    }

    /** Voice: the client sends the audio file and, optionally, a browser-made transcript. The STT provider is swappable. */
    public function voice(Request $r, AssistantService $svc, SpeechToText $stt)
    {
        $this->gate($r);
        $d = $r->validate(['audio' => ['sometimes', 'file', 'max:5120'], 'transcript' => ['sometimes', 'nullable', 'string', 'max:500'], 'language' => ['sometimes', 'string', 'max:10']]);
        $text = $stt->transcribe($r->file('audio')?->getRealPath(), $d['language'] ?? 'hi-IN', $d['transcript'] ?? null);
        abort_if($text === '', 422, "Couldn't transcribe the voice note. Please type your question.");

        return ['transcript' => $text] + $svc->ask($r->user(), $text);
    }
}
