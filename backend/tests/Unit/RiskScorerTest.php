<?php

namespace Tests\Unit;

use App\Services\RiskScorer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RiskScorerTest extends TestCase
{
    private function scorer(array $overrides = []): RiskScorer
    {
        return new RiskScorer(array_replace_recursive([
            'weights' => [
                'unpatched_servers' => 0.35,
                'open_high_audits' => 0.25,
                'uptime' => 0.25,
                'phishing_failure' => 0.15,
            ],
            'thresholds' => [
                'unpatched_servers' => ['appetite' => 2.0, 'tolerance' => 10.0, 'limit' => 20.0, 'max' => 40.0],
                'open_high_audits' => ['appetite' => 0, 'tolerance' => 5, 'limit' => 10, 'max' => 20],
                'uptime' => ['appetite' => 99.95, 'tolerance' => 99.9, 'limit' => 99.5, 'max' => 99.0],
                'phishing_failure' => ['appetite' => 3.0, 'tolerance' => 10.0, 'limit' => 20.0, 'max' => 30.0],
            ],
            'bands' => ['critical' => 75, 'warning' => 50],
        ], $overrides));
    }

    public static function ascendingCases(): array
    {
        return [
            'better than appetite' => [0.5, 0.0],
            'at appetite' => [2.0, 0.0],
            'between appetite and tolerance' => [6.0, 25.0],
            'at tolerance' => [10.0, 50.0],
            'between tolerance and limit' => [15.0, 62.5],
            'at limit' => [20.0, 75.0],
            'between limit and max' => [30.0, 87.5],
            'beyond max is clamped' => [80.0, 100.0],
        ];
    }

    #[DataProvider('ascendingCases')]
    public function test_ascending_kri_is_interpolated_between_anchors(float $value, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, $this->scorer()->score('unpatched_servers', $value), 1e-9);
    }

    public static function descendingCases(): array
    {
        return [
            'perfect uptime' => [100.0, 0.0],
            'at appetite' => [99.95, 0.0],
            'at tolerance' => [99.9, 50.0],
            'between tolerance and limit' => [99.7, 62.5],
            'at limit' => [99.5, 75.0],
            'below max is clamped' => [97.0, 100.0],
        ];
    }

    #[DataProvider('descendingCases')]
    public function test_descending_kri_gets_worse_as_value_falls(float $value, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, $this->scorer()->score('uptime', $value), 1e-9);
    }

    public function test_missing_value_has_no_score(): void
    {
        $this->assertNull($this->scorer()->score('uptime', null));
    }

    public function test_composite_is_the_weighted_average(): void
    {
        $composite = $this->scorer()->composite([
            'unpatched_servers' => 100,
            'open_high_audits' => 40,
            'uptime' => 0,
            'phishing_failure' => 20,
        ]);

        $this->assertEqualsWithDelta(0.35 * 100 + 0.25 * 40 + 0.15 * 20, $composite, 1e-9);
    }

    public function test_composite_renormalises_weights_when_a_kri_has_no_data(): void
    {
        $composite = $this->scorer()->composite([
            'unpatched_servers' => 80,
            'open_high_audits' => 40,
            'uptime' => null,
            'phishing_failure' => null,
        ]);

        $this->assertEqualsWithDelta((0.35 * 80 + 0.25 * 40) / 0.60, $composite, 1e-9);
        $this->assertNull($this->scorer()->composite(array_fill_keys(['unpatched_servers', 'uptime'], null)));
    }

    public static function ratingCases(): array
    {
        return [
            [0.0, RiskScorer::RATING_SAFE],
            [49.9, RiskScorer::RATING_SAFE],
            [50.0, RiskScorer::RATING_WARNING],
            [75.0, RiskScorer::RATING_WARNING],
            [75.1, RiskScorer::RATING_CRITICAL],
            [100.0, RiskScorer::RATING_CRITICAL],
        ];
    }

    #[DataProvider('ratingCases')]
    public function test_rating_follows_heatmap_bands(float $score, string $expected): void
    {
        $this->assertSame($expected, $this->scorer()->rating($score));
    }

    public function test_weights_must_sum_to_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->scorer(['weights' => ['uptime' => 0.5]]);
    }

    public function test_anchors_must_be_monotonic(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->scorer(['thresholds' => ['unpatched_servers' => ['limit' => 5.0]]]);
    }
}
