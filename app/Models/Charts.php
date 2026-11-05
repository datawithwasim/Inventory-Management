<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Server-rendered charts (inline SVG / plain HTML), no JS library needed.
 * Colours come from CSS variables in charts.css so light/dark stay in sync.
 */
final class Charts
{
    /** "Nice" axis maximum and step for 0..$max. */
    public static function scale(float $max, int $ticks = 4): array
    {
        if ($max <= 0) return [1.0, 0.25];
        $raw = $max / $ticks;
        $pow = 10 ** floor(log10($raw));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($raw <= $m * $pow) { $step = $m * $pow; break; }
        }
        return [$step * $ticks, $step];
    }

    /** Short axis number: 1.2k, 3.4L (lakh), 1.1Cr. */
    public static function short(float $v): string
    {
        $a = abs($v);
        $f = fn($x) => rtrim(rtrim(number_format($x, 1, '.', ''), '0'), '.');
        if ($a >= 1e7) return $f($v / 1e7) . 'Cr';
        if ($a >= 1e5) return $f($v / 1e5) . 'L';
        if ($a >= 1e3) return $f($v / 1e3) . 'k';
        return $f($v);
    }

    /**
     * Line chart. $series = [name => [values...]] (max 4, colours in fixed order).
     * $labels = x labels (already formatted); $fmt = fn(float): string for tooltips.
     */
    public static function line(array $labels, array $series, callable $fmt, string $summary = ''): string
    {
        $n = count($labels);
        if ($n < 1 || !$series) return '<p class="text-muted small mb-0">No data for this period.</p>';
        static $chartNo = 0;
        $chartNo++;
        $W = 1000; $H = 280; $L = 46; $R = 78; $T = 14; $B = 34;
        $max = 0.0;
        foreach ($series as $vals) foreach ($vals as $v) $max = max($max, (float)$v);
        [$top, $step] = self::scale($max);
        $x = fn(int $i) => $n === 1 ? $L + ($W - $L - $R) / 2 : $L + ($W - $L - $R) * $i / ($n - 1);
        $y = fn(float $v) => $T + ($H - $T - $B) * (1 - $v / $top);

        $o = '<svg class="viz-svg" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . e($summary) . '"><defs>';
        // soft fill under each line, fading to nothing at the baseline
        for ($s = 1; $s <= min(4, count($series)); $s++) {
            $o .= '<linearGradient id="vg' . $chartNo . '-' . $s . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" style="stop-color:var(--s' . $s . ');stop-opacity:.22"/><stop offset="1" style="stop-color:var(--s' . $s . ');stop-opacity:0"/></linearGradient>';
        }
        $o .= '</defs>';
        for ($g = 0.0; $g <= $top + 1e-9; $g += $step) {
            $gy = round($y($g), 1);
            $o .= '<line class="grid" x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $gy . '" y2="' . $gy . '"/>'
                . '<text class="axis" x="' . ($L - 6) . '" y="' . ($gy + 3.5) . '" text-anchor="end">' . e(self::short($g)) . '</text>';
        }
        $every = max(1, (int)ceil($n / 7));
        for ($i = 0; $i < $n; $i += $every) {
            $o .= '<text class="axis" x="' . round($x($i), 1) . '" y="' . ($H - 10) . '" text-anchor="' . ($i === 0 ? 'start' : 'middle') . '">' . e($labels[$i]) . '</text>';
        }
        $k = 0;
        $ends = [];
        foreach ($series as $name => $vals) {
            $k++;
            $pts = [];
            foreach (array_values($vals) as $i => $v) $pts[] = [$x($i), $y((float)$v)];
            $d = self::smooth($pts);
            $base = round($y(0.0), 1);
            $o .= '<path class="area s' . $k . '" fill="url(#vg' . $chartNo . '-' . $k . ')" d="' . $d . ' L' . round(end($pts)[0], 1) . ',' . $base . ' L' . round($pts[0][0], 1) . ',' . $base . ' Z"/>';
            $o .= '<path class="line s' . $k . '" pathLength="1" d="' . $d . '"/>';
            $last = count($vals) - 1;
            $ly = $y((float)array_values($vals)[$last]);
            $o .= '<circle class="end s' . $k . '" cx="' . round($x($last), 1) . '" cy="' . round($ly, 1) . '" r="4"/>';
            $ends[$k] = [$name, $ly + 4];
        }
        // direct labels at the line ends, pushed apart so they never overlap
        uasort($ends, fn($a, $b) => $a[1] <=> $b[1]);
        $pos = [];
        $prev = -99.0;
        foreach ($ends as $k2 => [$name, $ty]) { $ty = max($ty, $prev + 13); $prev = $ty; $pos[] = [$name, $ty]; }
        $over = $prev - ($H - $B - 4);
        foreach ($pos as [$name, $ty]) {
            $ty = $over > 0 ? $ty - $over : $ty;
            $o .= '<text class="dlabel" x="' . round($x($n - 1) + 9, 1) . '" y="' . round($ty, 1) . '">' . e($name) . '</text>';
        }
        // hover layer: one column per x position
        $cw = $n === 1 ? ($W - $L - $R) : ($W - $L - $R) / ($n - 1);
        $o .= '<line class="cross" x1="0" x2="0" y1="' . $T . '" y2="' . ($H - $B) . '" style="display:none"/>';
        for ($i = 0; $i < $n; $i++) {
            $tip = [$labels[$i]];
            $kk = 0;
            foreach ($series as $name => $vals) { $kk++; $tip[] = $kk . '|' . $name . '|' . $fmt((float)array_values($vals)[$i]); }
            $dots = [];
            $kk = 0;
            foreach ($series as $vals) { $kk++; $dots[] = round($y((float)array_values($vals)[$i]), 1); }
            $o .= '<rect class="hit" x="' . round($x($i) - $cw / 2, 1) . '" y="0" width="' . round($cw, 1) . '" height="' . $H . '" fill="transparent"'
                . ' data-x="' . round($x($i), 1) . '" data-dots="' . implode(',', $dots) . '" data-tip="' . e(implode("\n", $tip)) . '"/>';
        }
        $o .= '<g class="dots"></g></svg>';

        $leg = '';
        $k = 0;
        if (count($series) >= 2) {
            $leg = '<div class="viz-legend">';
            foreach ($series as $name => $_) { $k++; $leg .= '<span><i class="sw s' . $k . '"></i>' . e($name) . '</span>'; }
            $leg .= '</div>';
        }
        return $leg . $o;
    }

    /**
     * A smooth line through the points that never overshoots them (monotone cubic, Fritsch–Carlson):
     * a day with no sales stays on the baseline instead of dipping below it.
     */
    private static function smooth(array $p): string
    {
        $n = count($p);
        $f = fn(float $v) => round($v, 1);
        if ($n === 1) return 'M' . $f($p[0][0]) . ',' . $f($p[0][1]);
        $dx = $dy = $m = $t = [];
        for ($i = 0; $i < $n - 1; $i++) { $dx[$i] = $p[$i + 1][0] - $p[$i][0]; $dy[$i] = $p[$i + 1][1] - $p[$i][1]; $m[$i] = $dx[$i] ? $dy[$i] / $dx[$i] : 0.0; }
        $t[0] = $m[0]; $t[$n - 1] = $m[$n - 2];
        for ($i = 1; $i < $n - 1; $i++) $t[$i] = ($m[$i - 1] * $m[$i] <= 0) ? 0.0 : ($m[$i - 1] + $m[$i]) / 2;
        for ($i = 0; $i < $n - 1; $i++) {
            if ($m[$i] == 0.0) { $t[$i] = $t[$i + 1] = 0.0; continue; }
            $a = $t[$i] / $m[$i]; $b = $t[$i + 1] / $m[$i]; $h = $a * $a + $b * $b;
            if ($h > 9) { $k = 3 / sqrt($h); $t[$i] = $k * $a * $m[$i]; $t[$i + 1] = $k * $b * $m[$i]; }
        }
        $d = 'M' . $f($p[0][0]) . ',' . $f($p[0][1]);
        for ($i = 0; $i < $n - 1; $i++) {
            $h = $dx[$i] / 3;
            $d .= ' C' . $f($p[$i][0] + $h) . ',' . $f($p[$i][1] + $t[$i] * $h) . ' ' . $f($p[$i + 1][0] - $h) . ',' . $f($p[$i + 1][1] - $t[$i + 1] * $h) . ' ' . $f($p[$i + 1][0]) . ',' . $f($p[$i + 1][1]);
        }
        return $d;
    }

    /** Horizontal bars. $rows = [[label, value, display, optional colourIndex 1-5, optional href]]. */
    public static function hbar(array $rows, string $empty = 'Nothing to show yet.'): string
    {
        if (!$rows) return '<p class="text-muted small mb-0">' . e($empty) . '</p>';
        $max = 0.0;
        foreach ($rows as $r) $max = max($max, (float)$r[1]);
        $o = '<div class="hbars">';
        foreach ($rows as $r) {
            $pct = $max > 0 ? max(0.0, (float)$r[1]) / $max * 78 : 0; // leave room for the value label
            $cls = !empty($r[3]) ? ' o' . (int)$r[3] : '';
            $label = e($r[0]);
            if (!empty($r[4])) $label = '<a href="' . e($r[4]) . '">' . $label . '</a>';
            $o .= '<div class="hrow" data-tip="' . e($r[0] . "\n" . '0|' . 'Value|' . $r[2]) . '">'
                . '<div class="hlabel" title="' . e($r[0]) . '">' . $label . '</div>'
                . '<div class="htrack"><div class="hfill' . $cls . '" style="width:' . ($pct > 0 ? max(1.5, round($pct, 1)) : 0) . '%"></div>'
                . '<span class="hval">' . e($r[2]) . '</span></div></div>';
        }
        return $o . '</div>';
    }

    /** Tiny trend line for stat tiles (decorative; the number carries the meaning). */
    public static function spark(array $vals): string
    {
        $n = count($vals);
        if ($n < 2) return '';
        $max = max($vals); $min = min($vals);
        $span = ($max - $min) ?: 1;
        $pts = [];
        foreach (array_values($vals) as $i => $v) $pts[] = round(100 * $i / ($n - 1), 1) . ',' . round(22 - 20 * (($v - $min) / $span), 1);
        return '<svg class="spark" viewBox="0 0 100 24" preserveAspectRatio="none" aria-hidden="true"><polyline pathLength="1" points="' . implode(' ', $pts) . '"/></svg>';
    }

    /** Change vs previous period, as a small badge. $goodWhenUp=false for things like purchases/dues. */
    public static function delta(float $now, float $prev, ?bool $goodWhenUp = true): string
    {
        if ($prev <= 0.004) return $now > 0.004 ? '<span class="delta flat">new</span>' : '';
        $p = ($now - $prev) / $prev * 100;
        if (abs($p) < 0.5) return '<span class="delta flat">▬ no change</span>';
        $up = $p > 0;
        $cls = $goodWhenUp === null ? 'flat' : (($up === $goodWhenUp) ? 'good' : 'bad');
        return '<span class="delta ' . $cls . '">' . ($up ? '▲' : '▼') . ' ' . number_format(abs($p), 0) . '%</span>';
    }

    /** Wraps a chart with a title, a table twin and a Chart/Table switch. */
    public static function card(string $title, string $chart, string $tableHtml, string $sub = ''): string
    {
        static $n = 0;
        $n++;
        return '<div class="card viz h-100" data-viz><div class="card-body">'
            . '<div class="d-flex justify-content-between align-items-start mb-2"><div><h2 class="h6 mb-0">' . e($title) . '</h2>'
            . ($sub ? '<div class="small text-muted">' . e($sub) . '</div>' : '') . '</div>'
            . '<button type="button" class="btn btn-sm btn-link p-0 viz-toggle" aria-pressed="false" aria-controls="vt' . $n . '">Table</button></div>'
            . '<div class="viz-chart">' . $chart . '</div>'
            . '<div class="viz-table" id="vt' . $n . '" hidden>' . $tableHtml . '</div>'
            . '</div></div>';
    }

    /** Plain table twin: $head = [col labels], $rows = [[cells...]] (cells already escaped/formatted). */
    public static function table(array $head, array $rows): string
    {
        $o = '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr>';
        foreach ($head as $i => $h) $o .= '<th' . ($i ? ' class="text-end"' : '') . '>' . e($h) . '</th>';
        $o .= '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $o .= '<tr>';
            foreach ($r as $i => $c) $o .= '<td' . ($i ? ' class="text-end"' : '') . '>' . e($c) . '</td>';
            $o .= '</tr>';
        }
        return $o . '</tbody></table></div>';
    }
}
