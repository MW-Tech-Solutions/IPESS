<?php

function getPublicFilePath(string $storedPath): string
{
    $storedPath = ltrim($storedPath, '/');

  
    if (!str_starts_with($storedPath, 'uploads/')) {
        return '#';
    }

    return BASE_URL . $storedPath;
}
