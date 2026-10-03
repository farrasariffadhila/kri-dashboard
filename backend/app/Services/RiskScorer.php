<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Turns raw KRI values into 0-100 risk scores (higher = worse) and combines them
 * into the weighted Composite Risk Score. Pure logic, no database access.
 *
 * @see config/kri.php
 */
class RiskScorer
{
    public const RATING_CRITICAL = 'Critical';
    public const RATING_WARNING = 'Warning';
    public const RATING_SAFE = 'Safe';

    /** Risk score reached at each threshold anchor. */
    private const ANCHOR_SCORES = ['appetite' => 0, 'tolerance' => 50, 'limit' => 75, 'max' => 100];

    /** @var array<string, float> */
    private array $weights;

    /** @var array<string, array<string, float>> */
    private array $thresholds;

    /** @var array{critical: float, warning: float} */
    private array $bands;

    public function __construct(?array $config = null)
    {
        $config ??= config('kri');

        $this->weights = $config['weights'];
        $this->thresholds = $config['thresholds'];
        $this->bands = $config['bands'];

        $this->assertValidConfig();
    }

    /** @return list<string> */
    public function kris(): array
    {
        return array_keys($this->weights);
    }

    public function weight(string $kri): float
    {
        return $this->weights[$kri];
    }

    /** Map one KRI value onto the 0-100 scale. Null means "no data". */
    public function score(string $kri, int|float|null $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $anchors = $this->thresholds[$kri];
        // Flip descending anchors (e.g. uptime) so "worse" is always "larger".
        $sign = $this->direction($anchors);
        $x = $sign * $value;

        $prevX = null;
        $prevScore = null;
        foreach (self::ANCHOR_SCORES as $name => $anchorScore) {
            $anchorX = $sign * $anchors[$name];
            if ($x <= $anchorX) {
                if ($prevX === null) {
                    return (float) $anchorScore;
                }

                return $prevScore + ($x - $prevX) / ($anchorX - $prevX) * ($anchorScore - $prevScore);
            }
            $prevX = $anchorX;
            $prevScore = $anchorScore;
        }

        return 100.0;
    }

    /**
     * Weighted average of KRI scores. KRIs without data are left out and the
     * remaining weights are re-normalised, so missing data never reads as "safe".
     *
     * @param  array<string, float|null>  $scores
     */
    public function composite(array $scores): ?float
    {
        $weighted = 0.0;
        $weightSum = 0.0;

        foreach ($this->weights as $kri => $weight) {
            if (($scores[$kri] ?? null) === null) {
                continue;
            }
            $weighted += $weight * $scores[$kri];
            $weightSum += $weight;
        }

        return $weightSum > 0 ? $weighted / $weightSum : null;
    }

    public function rating(?float $score): ?string
    {
        return match (true) {
            $score === null => null,
            $score > $this->bands['critical'] => self::RATING_CRITICAL,
            $score >= $this->bands['warning'] => self::RATING_WARNING,
            default => self::RATING_SAFE,
        };
    }

    /** Weights, thresholds and bands, exposed so the UI can explain a score. */
    public function methodology(): array
    {
        return [
            'weights' => $this->weights,
            'thresholds' => $this->thresholds,
            'anchor_scores' => self::ANCHOR_SCORES,
            'bands' => $this->bands,
        ];
    }

    private function direction(array $anchors): int
    {
        return $anchors['max'] >= $anchors['appetite'] ? 1 : -1;
    }

    private function assertValidConfig(): void
    {
        if (abs(array_sum($this->weights) - 1.0) > 1e-9) {
            throw new InvalidArgumentException('KRI weights must sum to 1.');
        }

        foreach ($this->weights as $kri => $weight) {
            $anchors = $this->thresholds[$kri] ?? throw new InvalidArgumentException("Missing thresholds for KRI [{$kri}].");
            $sign = $this->direction($anchors);
            $previous = null;
            foreach (array_keys(self::ANCHOR_SCORES) as $name) {
                $x = $sign * ($anchors[$name] ?? throw new InvalidArgumentException("Missing [{$name}] anchor for KRI [{$kri}]."));
                if ($previous !== null && $x <= $previous) {
                    throw new InvalidArgumentException("Anchors for KRI [{$kri}] must be strictly monotonic.");
                }
                $previous = $x;
            }
        }
    }
}
