<?php
require '/var/www/html/vendor/autoload.php';

function highlightMatches(string $label, array $indices): string {
    if ($indices === []) return htmlspecialchars($label, ENT_QUOTES);

    usort($indices, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

    $html = '';
    $cursor = 0;

    foreach ($indices as [$start, $end]) {
        if ($start < $cursor) continue;

        $html .= htmlspecialchars(mb_substr($label, $cursor, $start - $cursor), ENT_QUOTES);
        $html .= '<mark>' . htmlspecialchars(mb_substr($label, $start, $end - $start + 1), ENT_QUOTES) . '</mark>';
        $cursor = $end + 1;
    }

    $html .= htmlspecialchars(mb_substr($label, $cursor), ENT_QUOTES);

    return $html;
}

$items = ['Review', 'ReviewAction', 'CriticReview', 'DriveWheelConfigurationValue', 'UserReview'];
$fuse = new Fuse\Fuse($items, ['includeMatches' => true]);

foreach ($fuse->search('review') as $result) {
    $label = $items[$result['refIndex']];
    $indices = [];
    foreach ($result['matches'] ?? [] as $match) {
        foreach ($match['indices'] ?? [] as $range) $indices[] = $range;
    }
    echo $label . " => " . highlightMatches($label, $indices) . "\n";
}

echo "\n=== no-match fallback ===\n";
echo highlightMatches('Review', []) . "\n";

echo "\n=== HTML-unsafe char safety check (shouldn't occur in practice, but verify no injection) ===\n";
echo highlightMatches('A&B<C>review', [[6, 11]]) . "\n";
