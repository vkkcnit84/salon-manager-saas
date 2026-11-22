<?php

namespace App\Assistant\Contracts;

interface SpeechToText
{
    /** @return string transcript, or '' when nothing could be transcribed */
    public function transcribe(?string $audioPath, ?string $languageHint = null, ?string $clientTranscript = null): string;
}
