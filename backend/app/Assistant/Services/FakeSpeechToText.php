<?php

namespace App\Assistant\Services;

use App\Assistant\Contracts\SpeechToText;

/** Local fake: trusts a transcript produced in the browser (Web Speech API). A Sarvam/Whisper client replaces it later. */
class FakeSpeechToText implements SpeechToText
{
    public function transcribe(?string $audioPath, ?string $languageHint = null, ?string $clientTranscript = null): string
    {
        return trim((string) $clientTranscript);
    }
}
