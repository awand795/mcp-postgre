<?php

namespace App\Services\Analysis;

use App\Services\BaseService;
use Illuminate\Support\Collection;

/**
 * StatisticalAnalysisService
 *
 * Contains all 13 statistical analysis methods:
 * - Trend, Anomaly, Period Comparison
 * - Prediction, Forecasting (single & hierarchy)
 * - Root Cause, KPI Correlation
 * - Risk Detection, Scenario Simulation
 * - Clustering, Cohort Analysis
 * - Dataset Audit, Business Insight Generation
 */
class StatisticalAnalysisService extends BaseService
{
    // ── analyze_trend ─────────────────────────────────────────────────────────
    public function analyzeTrend(array $data, string $valueCol, string $periodCol): string
    {
        if (empty($data)) return $this->errorResponse('Data is empty.');

        $series = collect($data)->sortBy($periodCol)->values();
        $count = $series->count();

        if ($count < 2) return $this->errorResponse('Not enough data points for trend analysis.');

        $first = $this->toFloat($series[0][$valueCol] ?? 0);
        $last = $this->toFloat($series[$count - 1][$valueCol] ?? 0);

        $totalGrowth = $first != 0 ? (($last - $first) / abs($first)) * 100 : 0;
        $avgGrowth = 0;
        $growths = [];

        for ($i = 1; $i < $count; $i++) {
            $prev = $this->toFloat($series[$i-1][$valueCol] ?? 0);
            $curr = $this->toFloat($series[$i][$valueCol] ?? 0);
            $g = $prev != 0 ? (($curr - $prev) / abs($prev)) * 100 : 0;
            $growths[] = $g;
        }

        $avgGrowth = count($growths) > 0 ? array_sum($growths) / count($growths) : 0;

        return $this->safeJsonEncode([
            'trend' => $last > $first ? 'UPWARD' : ($last < $first ? 'DOWNWARD' : 'STABLE'),
            'total_growth_pct' => round($totalGrowth, 2),
            'avg_periodic_growth_pct' => round($avgGrowth, 2),
            'start_value' => $first,
            'end_value' => $last,
            'data_points' => $count
        ]);
    }

    // ── detect_anomalies ──────────────────────────────────────────────────────
    public function detectAnomalies(array $data, string $valueCol): string
    {
        if (empty($data)) return $this->errorResponse('Data is empty.');

        $values = collect($data)->pluck($valueCol)->map(fn($v) => (float)$v);
        $count = $values->count();

        if ($count < 3) return $this->errorResponse('Insufficient data for anomaly detection.');

        $avg = $values->avg();
        // Calculate Standard Deviation
        $variance = $values->reduce(fn($carry, $val) => $carry + pow($val - $avg, 2), 0) / $count;
        $stdDev = sqrt($variance);

        $anomalies = [];
        foreach ($data as $index => $row) {
            $val = (float)($row[$valueCol] ?? 0);
            if ($stdDev > 0) {
                $zScore = ($val - $avg) / $stdDev;
                if (abs($zScore) > 2) { // 2 Sigma Threshold
                    $anomalies[] = [
                        'row_index' => $index,
                        'value' => $val,
                        'z_score' => round($zScore, 2),
                        'severity' => abs($zScore) > 3 ? 'HIGH' : 'MEDIUM',
                        'data' => $row
                    ];
                }
            }
        }

        return $this->safeJsonEncode([
            'avg_value' => round($avg, 2),
            'std_dev' => round($stdDev, 2),
            'anomalies_found' => count($anomalies),
            'anomalies' => $anomalies
        ]);
    }

    // ── compare_periods ───────────────────────────────────────────────────────
    public function comparePeriods(array $data, string $valueCol, string $periodCol, string $base, string $compare): string
    {
        $baseData = collect($data)->firstWhere($periodCol, $base);
        $compareData = collect($data)->firstWhere($periodCol, $compare);

        if (!$baseData || !$compareData) {
            return $this->errorResponse("Could not find one or both periods: {$base} or {$compare}");
        }

        $vBase = $this->toFloat($baseData[$valueCol] ?? 0);
        $vComp = $this->toFloat($compareData[$valueCol] ?? 0);

        $diff = $vComp - $vBase;
        $diffPct = $vBase != 0 ? ($diff / abs($vBase)) * 100 : 0;

        return $this->safeJsonEncode([
            'base_period' => $base,
            'compare_period' => $compare,
            'base_value' => $vBase,
            'compare_value' => $vComp,
            'absolute_difference' => $diff,
            'percentage_difference' => round($diffPct, 2),
            'status' => $diff > 0 ? 'INCREASE' : ($diff < 0 ? 'DECREASE' : 'NO_CHANGE')
        ]);
    }

    // ── predict_future ────────────────────────────────────────────────────────
    public function predictFuture(array $data, string $valueCol, string $periodCol, int $periodsToProject): string
    {
        if (empty($data)) return $this->errorResponse('Data is empty.');

        $series = collect($data)->sortBy($periodCol)->values();
        $n = $series->count();

        if ($n < 3) return $this->errorResponse('Minimum 3 data points are required for forecasting.');

        $sumX = 0; $sumY = 0; $sumXY = 0; $sumXX = 0; $sumYY = 0;

        foreach ($series as $i => $row) {
            $x = $i;
            $y = $this->toFloat($row[$valueCol] ?? 0);

            $sumX += $x;
            $sumY += $y;
            $sumXY += ($x * $y);
            $sumXX += ($x * $x);
            $sumYY += ($y * $y);
        }

        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        if ($denominator == 0) return $this->errorResponse('Cannot calculate regression (all dates may be identical).');

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        // Calculate R-Squared (Confidence)
        $avgY = $sumY / $n;
        $ssTot = 0; $ssRes = 0;
        foreach ($series as $i => $row) {
            $y = $this->toFloat($row[$valueCol] ?? 0);
            $yPred = ($slope * $i) + $intercept;
            $ssTot += pow($y - $avgY, 2);
            $ssRes += pow($y - $yPred, 2);
        }
        $rSquared = $ssTot != 0 ? 1 - ($ssRes / $ssTot) : 0;

        $projections = [];
        for ($i = 0; $i < $periodsToProject; $i++) {
            $futureX = $n + $i;
            $val = ($slope * $futureX) + $intercept;
            $projections[] = [
                'period_index' => $futureX,
                'projected_value' => round($val, 2)
            ];
        }

        return $this->safeJsonEncode([
            'slope' => round($slope, 2),
            'intercept' => round($intercept, 2),
            'confidence_score_r2' => round($rSquared, 2), // R^2: 1 = exact fit, 0 = no fit
            'prediction_strength' => $rSquared > 0.8 ? 'STRONG' : ($rSquared > 0.5 ? 'MODERATE' : 'WEAK'),
            'projections' => $projections,
            'message' => 'Proyeksi berdasarkan tren linear historis.'
        ]);
    }

    // ── audit_dataset (The "Proactive Insight" Wrapper) ───────────────────────
    public function auditDataset(array $data, string $valueCol, string $labelCol): string
    {
        if (empty($data)) return $this->errorResponse('Data is empty.');

        $collection = collect($data);
        $total = $collection->sum($valueCol);

        // 1. Trend Analysis
        $trend = $this->decodeJson($this->analyzeTrend($data, $valueCol, $labelCol), true);

        // 2. Anomaly Detection
        $anomalies = $this->decodeJson($this->detectAnomalies($data, $valueCol), true);

        // 3. Pareto Analysis (Top Contributors)
        $sorted = $collection->sortByDesc($valueCol)->values();
        $top3 = $sorted->take(3)->map(function($row) use ($valueCol, $labelCol, $total) {
            $val = (float)$row[$valueCol];
            return [
                'label' => $row[$labelCol] ?? 'Unknown',
                'value' => $val,
                'pct' => $total != 0 ? round(($val / $total) * 100, 1) : 0
            ];
        });

        $top3Pct = $top3->sum('pct');

        // 4. Volatility (CV)
        $values = $collection->pluck($valueCol)->map(fn($v) => (float)$v);
        $mean = $values->avg();
        $variance = $values->reduce(fn($carry, $val) => $carry + pow($val - $mean, 2), 0) / $values->count();
        $stdDev = sqrt($variance);
        $volatility = $mean != 0 ? ($stdDev / abs($mean)) : 0;

        return $this->safeJsonEncode([
            'audit_summary' => [
                'total_value' => $total,
                'volatility_score' => round($volatility, 2), // CV
                'volatility_label' => $volatility > 0.5 ? 'HIGH' : ($volatility > 0.2 ? 'MODERATE' : 'STABLE'),
                'is_concentrated' => $top3Pct > 70, // 70-80 rule
                'top_3_drivers_pct' => $top3Pct
            ],
            'top_contributors' => $top3,
            'trend_summary'    => $trend,
            'anomalies'        => $anomalies['anomalies'] ?? [],
            'strategic_hint'   => $top3Pct > 70 ? "Peringatan: Bisnis sangat bergantung pada 3 item teratas ($top3Pct% total). Risiko tinggi jika pasar bergeser." : "Distribusi bisnis cukup sehat dan tersebar."
        ]);
    }

    // ── analyze_root_cause ────────────────────────────────────────────────────
    public function analyzeRootCause(array $data, string $valueCol, string $dimCol, string $periodCol, string $base, string $compare): string
    {
        if (empty($data)) return $this->errorResponse('Data is empty.');

        $col = collect($data);
        $baseData    = $col->where($periodCol, $base)->values();
        $compareData = $col->where($periodCol, $compare)->values();

        if ($baseData->isEmpty() || $compareData->isEmpty()) {
            return $this->errorResponse("Could not find periods: {$base} or {$compare} in column {$periodCol}.");
        }

        // Index by dimension
        $baseMap    = $baseData->keyBy($dimCol);
        $compareMap = $compareData->keyBy($dimCol);

        $totalBase    = $baseData->sum(fn($r) => $this->toFloat($r[$valueCol] ?? 0));
        $totalCompare = $compareData->sum(fn($r) => $this->toFloat($r[$valueCol] ?? 0));
        $totalDelta   = $totalCompare - $totalBase;

        $drivers = [];
        $allDims = $baseMap->keys()->merge($compareMap->keys())->unique();

        foreach ($allDims as $dim) {
            $bVal = $this->toFloat(($baseMap->get($dim) ?? [])[$valueCol] ?? 0);
            $cVal = $this->toFloat(($compareMap->get($dim) ?? [])[$valueCol] ?? 0);
            $delta = $cVal - $bVal;
            $contribution = $totalDelta != 0 ? round(($delta / abs($totalDelta)) * 100, 1) : 0;
            $drivers[] = [
                'dimension'        => $dim,
                'base_value'       => $bVal,
                'compare_value'    => $cVal,
                'delta'            => round($delta, 2),
                'contribution_pct' => $contribution,
                'direction'        => $delta >= 0 ? 'POSITIVE' : 'NEGATIVE',
            ];
        }

        usort($drivers, fn($a, $b) => abs($b['delta']) <=> abs($a['delta']));

        return $this->safeJsonEncode([
            'base_period'           => $base,
            'compare_period'        => $compare,
            'total_base'            => round($totalBase, 2),
            'total_compare'         => round($totalCompare, 2),
            'total_change'          => round($totalDelta, 2),
            'total_change_pct'      => $totalBase != 0 ? round(($totalDelta / abs($totalBase)) * 100, 2) : 0,
            'trigger_threshold_met' => abs($totalDelta / ($totalBase ?: 1)) * 100 > 3,
            'top_drivers'           => array_slice($drivers, 0, 10),
        ]);
    }

    // ── analyze_kpi_correlation ───────────────────────────────────────────────
    public function analyzeKpiCorrelation(array $data, string $targetKpi, array $candidateCols): string
    {
        if (empty($data) || empty($candidateCols)) return $this->errorResponse('Data or candidate_columns is empty.');

        $n = count($data);
        if ($n < 3) return $this->errorResponse('Minimum 3 rows required for correlation.');

        $yValues = array_map(fn($r) => (float)($r[$targetKpi] ?? 0), $data);
        $yMean   = array_sum($yValues) / $n;

        $correlations = [];
        foreach ($candidateCols as $col) {
            if ($col === $targetKpi) continue;
            $xValues = array_map(fn($r) => (float)($r[$col] ?? 0), $data);
            $xMean   = array_sum($xValues) / $n;

            $num = 0; $denX = 0; $denY = 0;
            for ($i = 0; $i < $n; $i++) {
                $dx   = $xValues[$i] - $xMean;
                $dy   = $yValues[$i] - $yMean;
                $num  += $dx * $dy;
                $denX += $dx * $dx;
                $denY += $dy * $dy;
            }
            $denom = sqrt($denX * $denY);
            $r     = $denom != 0 ? $num / $denom : 0;

            $correlations[] = [
                'column'    => $col,
                'r'         => round($r, 4),
                'strength'  => abs($r) > 0.7 ? 'STRONG' : (abs($r) > 0.4 ? 'MODERATE' : 'WEAK'),
                'direction' => $r >= 0 ? 'POSITIVE' : 'NEGATIVE',
            ];
        }

        usort($correlations, fn($a, $b) => abs($b['r']) <=> abs($a['r']));

        return $this->safeJsonEncode(['target_kpi' => $targetKpi, 'correlations' => $correlations]);
    }

    // ── forecast_metric ───────────────────────────────────────────────────────
    public function forecastMetric(array $data, string $valueCol, string $periodCol, int $periods, bool $includeCI = true): string
    {
        if (empty($data)) return $this->errorResponse('Data is empty.');
        $series = collect($data)->sortBy($periodCol)->values();
        $n = $series->count();
        if ($n < 3) return $this->errorResponse('Minimum 3 data points required.');

        $sumX = 0; $sumY = 0; $sumXY = 0; $sumXX = 0;
        foreach ($series as $i => $row) {
            $y = $this->toFloat($row[$valueCol] ?? 0);
            $sumX += $i; $sumY += $y; $sumXY += $i * $y; $sumXX += $i * $i;
        }
        $denom = ($n * $sumXX) - ($sumX * $sumX);
        if ($denom == 0) return $this->errorResponse('Cannot calculate regression.');

        $slope     = (($n * $sumXY) - ($sumX * $sumY)) / $denom;
        $intercept = ($sumY - ($slope * $sumX)) / $n;
        $avgY      = $sumY / $n;

        $ssTot = 0; $ssRes = 0; $residuals = [];
        foreach ($series as $i => $row) {
            $y    = $this->toFloat($row[$valueCol] ?? 0);
            $yHat = ($slope * $i) + $intercept;
            $ssTot   += pow($y - $avgY, 2);
            $ssRes   += pow($y - $yHat, 2);
            $residuals[] = pow($y - $yHat, 2);
        }
        $rSquared = $ssTot != 0 ? 1 - ($ssRes / $ssTot) : 0;
        $se       = $n > 2 ? sqrt($ssRes / ($n - 2)) : 0; // Standard Error

        $projections = [];
        for ($i = 0; $i < $periods; $i++) {
            $futureX = $n + $i;
            $val     = ($slope * $futureX) + $intercept;
            $proj    = ['period_index' => $futureX, 'projected_value' => round($val, 2)];
            if ($includeCI && $se > 0) {
                $proj['ci_95_lower'] = round($val - 1.96 * $se, 2);
                $proj['ci_95_upper'] = round($val + 1.96 * $se, 2);
            }
            $projections[] = $proj;
        }

        return $this->safeJsonEncode([
            'r_squared'          => round($rSquared, 4),
            'slope'              => round($slope, 2),
            'intercept'          => round($intercept, 2),
            'standard_error'     => round($se, 2),
            'projections'        => $projections,
            'prediction_strength' => $rSquared > 0.8 ? 'STRONG' : ($rSquared > 0.5 ? 'MODERATE' : 'WEAK'),
        ]);
    }

    // ── forecast_hierarchy ────────────────────────────────────────────────────
    public function forecastHierarchy(array $data, string $valueCol, string $periodCol, string $hierarchyCol, int $periods): string
    {
        if (empty($data)) return $this->errorResponse('Data is empty.');

        $entities = collect($data)->pluck($hierarchyCol)->unique();
        $entityForecasts = [];
        $totalParentForecast = 0;

        // Step 1: Forecast each entity separately
        foreach ($entities as $entity) {
            $entityData = collect($data)->where($hierarchyCol, $entity)->values()->toArray();
            $result = $this->decodeJson($this->forecastMetric($entityData, $valueCol, $periodCol, $periods, false), true);

            if (isset($result['error'])) {
                $entityForecasts[] = ['entity' => $entity, 'error' => $result['error']];
                continue;
            }

            $entitySum = array_sum(array_column($result['projections'] ?? [], 'projected_value'));
            $entityForecasts[] = [
                'entity'      => $entity,
                'projections' => $result['projections'],
                'total_forecast' => round($entitySum, 2),
            ];
            $totalParentForecast += $entitySum;
        }

        // Step 2: Calculate parent-level forecast (aggregate all entities)
        $parentResult = $this->decodeJson($this->forecastMetric($data, $valueCol, $periodCol, $periods, false), true);
        $parentTotal = isset($parentResult['projections'])
            ? array_sum(array_column($parentResult['projections'], 'projected_value'))
            : 0;

        // Step 3: Check alignment (child sum vs parent)
        $alignmentGap = $parentTotal != 0 ? abs($totalParentForecast - $parentTotal) / abs($parentTotal) * 100 : 0;

        return $this->safeJsonEncode([
            'entity_forecasts'   => $entityForecasts,
            'sum_of_children'    => round($totalParentForecast, 2),
            'parent_forecast'    => round($parentTotal, 2),
            'alignment_gap_pct'  => round($alignmentGap, 2),
            'consistency_check'  => $alignmentGap < 1 ? 'ALIGNED' : 'MISALIGNED',
            'recommendation'     => $alignmentGap >= 1
                ? 'Child forecasts do not sum to parent total. Consider using a proportional scaling adjustment.'
                : 'Child forecasts are consistent with parent forecast.',
        ]);
    }

    // ── detect_risk_signals ───────────────────────────────────────────────────
    public function detectRiskSignals(array $data, string $valueCol, string $periodCol): string
    {
        if (empty($data)) return $this->errorResponse('Data is empty.');

        $series = collect($data)->sortBy($periodCol)->values();
        $n = $series->count();

        if ($n < 4) return $this->errorResponse('Minimum 4 periods required for risk detection.');

        $values = $series->pluck($valueCol)->map(fn($v) => (float)$v)->toArray();
        $avg    = array_sum($values) / $n;
        $var    = array_reduce($values, fn($c, $v) => $c + pow($v - $avg, 2), 0) / $n;
        $stdDev = sqrt($var);

        $signals = [];
        $consecutiveDecline = 0;

        for ($i = 1; $i < $n; $i++) {
            $curr = $values[$i];
            $prev = $values[$i - 1];
            $change = $prev != 0 ? (($curr - $prev) / abs($prev)) * 100 : 0;

            // Z-Score check
            $zScore = $stdDev > 0 ? ($curr - $avg) / $stdDev : 0;

            // Momentum check
            if ($change < -5) { // >5% decline
                $consecutiveDecline++;
            } else {
                $consecutiveDecline = 0;
            }

            if ($zScore < -2) {
                $signals[] = [
                    'period'           => $series[$i][$periodCol] ?? $i,
                    'value'            => $curr,
                    'z_score'          => round($zScore, 2),
                    'change_pct'       => round($change, 2),
                    'signal_type'      => 'ANOMALY',
                    'severity'         => $zScore < -3 ? 'HIGH' : 'MEDIUM',
                    'consecutive_declines' => $consecutiveDecline,
                ];
            }

            // Momentum warning: 3+ consecutive declines
            if ($consecutiveDecline >= 3) {
                $signals[] = [
                    'period'             => $series[$i][$periodCol] ?? $i,
                    'value'              => $curr,
                    'change_pct'         => round($change, 2),
                    'signal_type'        => 'MOMENTUM',
                    'severity'           => $consecutiveDecline >= 4 ? 'HIGH' : 'MEDIUM',
                    'consecutive_declines' => $consecutiveDecline,
                ];
            }
        }

        return $this->safeJsonEncode([
            'avg_value'    => round($avg, 2),
            'std_dev'      => round($stdDev, 2),
            'risk_signals' => $signals,
            'total_signals' => count($signals),
            'risk_level'   => count(array_filter($signals, fn($s) => $s['severity'] === 'HIGH')) > 0 ? 'HIGH' : (count($signals) > 0 ? 'MEDIUM' : 'LOW'),
        ]);
    }

    // ── simulate_scenario ─────────────────────────────────────────────────────
    public function simulateScenario(array $baseData, string $scenarioName, array $changes, string $outputMetric): string
    {
        if (empty($baseData)) return $this->errorResponse('Base data is empty.');

        $baselineTotal = collect($baseData)->sum(fn($r) => (float)($r[$outputMetric] ?? 0));
        $simulatedData = [];
        $totalImpact = 0;

        foreach ($baseData as $row) {
            $newRow = $row;
            $rowImpact = 0;

            foreach ($changes as $change) {
                $col = $change['column'] ?? '';
                $changeType = $change['change_type'] ?? 'pct';
                $value = $change['value'] ?? 0;

                if (!isset($newRow[$col])) continue;

                $currentVal = (float)$newRow[$col];
                $newVal = $changeType === 'pct'
                    ? $currentVal * (1 + $value / 100)
                    : $currentVal + $value;

                $newRow[$col . '_original'] = $currentVal;
                $newRow[$col] = $newVal;
                $rowImpact += ($newVal - $currentVal);
            }

            $newRow['_scenario_output'] = $newRow[$outputMetric] ?? 0;
            $simulatedData[] = $newRow;
            $totalImpact += $rowImpact;
        }

        $simulatedTotal = collect($simulatedData)->sum(fn($r) => (float)($r[$outputMetric] ?? 0));
        $pctChange = $baselineTotal != 0 ? (($simulatedTotal - $baselineTotal) / abs($baselineTotal)) * 100 : 0;

        return $this->safeJsonEncode([
            'scenario_name'         => $scenarioName,
            'baseline_total'        => round($baselineTotal, 2),
            'simulated_total'       => round($simulatedTotal, 2),
            'absolute_change'       => round($simulatedTotal - $baselineTotal, 2),
            'percentage_change'     => round($pctChange, 2),
            'output_metric'         => $outputMetric,
            'changes_applied'       => $changes,
            'sample_results'        => array_slice($simulatedData, 0, 10), // Top 10 samples
            'total_rows_simulated'  => count($simulatedData),
        ]);
    }

    // ── segment_entities ──────────────────────────────────────────────────────
    public function segmentEntities(array $data, string $entityCol, array $featureCols, int $nSegments): string
    {
        if (empty($data)) return $this->errorResponse('Data is empty.');

        // Simplified K-means (fixed initialization, 10 iterations)
        $entities = collect($data)->keyBy($entityCol);

        // Initialize centroids from first N entities
        $centroids = [];
        $entityKeys = $entities->keys()->take($nSegments)->values();
        foreach ($entityKeys as $key) {
            $centroid = [];
            foreach ($featureCols as $feat) {
                $centroid[] = (float)($entities->get($key)[$feat] ?? 0);
            }
            $centroids[] = $centroid;
        }

        // 10 iterations of K-means
        $assignments = [];
        for ($iter = 0; $iter < 10; $iter++) {
            $newAssignments = [];
            $newCentroids = array_fill(0, $nSegments, array_fill(0, count($featureCols), 0));
            $counts = array_fill(0, $nSegments, 0);

            foreach ($entities as $entity => $row) {
                $features = array_map(fn($f) => (float)($row[$f] ?? 0), $featureCols);

                // Find closest centroid
                $minDist = PHP_FLOAT_MAX;
                $closest = 0;
                foreach ($centroids as $cIdx => $centroid) {
                    $dist = 0;
                    foreach ($features as $i => $v) {
                        $dist += pow($v - $centroid[$i], 2);
                    }
                    if ($dist < $minDist) {
                        $minDist = $dist;
                        $closest = $cIdx;
                    }
                }

                $newAssignments[$entity] = $closest;
                $counts[$closest]++;
                foreach ($features as $i => $v) {
                    $newCentroids[$closest][$i] += $v;
                }
            }

            // Update centroids
            for ($c = 0; $c < $nSegments; $c++) {
                if ($counts[$c] > 0) {
                    for ($i = 0; $i < count($featureCols); $i++) {
                        $centroids[$c][$i] = $newCentroids[$c][$i] / $counts[$c];
                    }
                }
            }
            $assignments = $newAssignments;
        }

        // Build segment summary
        $segmentStats = [];
        for ($s = 0; $s < $nSegments; $s++) {
            $members = array_keys(array_filter($assignments, fn($v) => $v === $s));
            $stats = ['segment_id' => $s, 'member_count' => count($members), 'members' => array_slice($members, 0, 10)];
            foreach ($featureCols as $i => $feat) {
                $vals = array_map(fn($e) => (float)($entities->get($e)[$feat] ?? 0), $members);
                $stats['avg_' . $feat] = count($vals) > 0 ? round(array_sum($vals) / count($vals), 2) : 0;
            }
            $segmentStats[] = $stats;
        }

        return $this->safeJsonEncode([
            'n_segments' => $nSegments,
            'segments'   => $segmentStats,
            'features_used' => $featureCols,
            'iterations' => 10,
        ]);
    }

    // ── analyze_cohort ────────────────────────────────────────────────────────
    public function analyzeCohort(array $data, string $entityCol, string $periodCol, string $valueCol, string $cohortDefCol): string
    {
        if (empty($data)) return $this->errorResponse('Data is empty.');

        // Group by cohort
        $cohorts = collect($data)->groupBy($cohortDefCol);
        $cohortResults = [];

        foreach ($cohorts as $cohortName => $cohortData) {
            $entities = $cohortData->pluck($entityCol)->unique();
            $periods = $cohortData->pluck($periodCol)->unique()->sort()->values();

            $retention = [];
            foreach ($periods as $idx => $period) {
                $activeInPeriod = $cohortData->where($periodCol, $period)->pluck($entityCol)->unique()->count();
                $retention[] = [
                    'period' => $period,
                    'period_index' => $idx,
                    'active_entities' => $activeInPeriod,
                    'retention_rate' => $entities->count() > 0 ? round(($activeInPeriod / $entities->count()) * 100, 1) : 0,
                    'total_value' => $cohortData->where($periodCol, $period)->sum($valueCol),
                ];
            }

            $cohortResults[] = [
                'cohort' => $cohortName,
                'initial_entities' => $entities->count(),
                'retention' => $retention,
            ];
        }

        return $this->safeJsonEncode([
            'cohorts' => $cohortResults,
            'total_cohorts' => count($cohortResults),
        ]);
    }

    // ── generate_business_insight ─────────────────────────────────────────────
    public function generateBusinessInsight(
        string $question,
        string $dataSummary,
        ?array $trendResult = null,
        ?array $anomalies = null,
        ?array $rootCause = null,
        ?array $forecast = null,
        ?array $risks = null,
        string $language = 'id'
    ): string {
        $isIndonesian = $language === 'id';

        $sections = [];

        // Executive Summary
        $sections[] = $isIndonesian ? "## Ringkasan Eksekutif" : "## Executive Summary";
        $sections[] = $isIndonesian
            ? "**Pertanyaan:** {$question}"
            : "**Question:** {$question}";
        $sections[] = $isIndonesian
            ? "**Data Dianalisis:** {$dataSummary}"
            : "**Data Analyzed:** {$dataSummary}";

        // Trend
        if ($trendResult) {
            $trendLabel = $isIndonesian ? "Tren" : "Trend";
            $sections[] = "\n## {$trendLabel}";
            $direction = $trendResult['trend'] ?? 'UNKNOWN';
            $growth = $trendResult['total_growth_pct'] ?? 0;
            $sections[] = $isIndonesian
                ? "- **Arah:** {$direction} (Pertumbuhan: {$growth}%)"
                : "- **Direction:** {$direction} (Growth: {$growth}%)";
        }

        // Anomalies
        if ($anomalies && !empty($anomalies['anomalies'])) {
            $anomLabel = $isIndonesian ? "Anomali" : "Anomalies";
            $count = count($anomalies['anomalies']);
            $sections[] = "\n## {$anomLabel}";
            $sections[] = $isIndonesian
                ? "- Ditemukan {$count} anomali yang signifikan."
                : "- Found {$count} significant anomalies.";
        }

        // Root Cause
        if ($rootCause) {
            $rcLabel = $isIndonesian ? "Penyebab Utama" : "Root Cause";
            $sections[] = "\n## {$rcLabel}";
            if (!empty($rootCause['top_drivers'])) {
                $top = $rootCause['top_drivers'][0];
                $sections[] = $isIndonesian
                    ? "- Driver utama: **{$top['dimension']}** (Kontribusi: {$top['contribution_pct']}%)"
                    : "- Top driver: **{$top['dimension']}** (Contribution: {$top['contribution_pct']}%)";
            }
        }

        // Forecast
        if ($forecast && !empty($forecast['projections'])) {
            $fcLabel = $isIndonesian ? "Proyeksi" : "Forecast";
            $sections[] = "\n## {$fcLabel}";
            foreach ($forecast['projections'] as $proj) {
                $sections[] = "- Period {$proj['period_index']}: {$proj['projected_value']}";
            }
            if (!empty($forecast['r_squared'])) {
                $sections[] = $isIndonesian
                    ? "- Akurasi Model (R²): {$forecast['r_squared']}"
                    : "- Model Accuracy (R²): {$forecast['r_squared']}";
            }
        }

        // Risks
        if ($risks && !empty($risks['risk_signals'])) {
            $riskLabel = $isIndonesian ? "Sinyal Risiko" : "Risk Signals";
            $sections[] = "\n## {$riskLabel}";
            $sections[] = $isIndonesian
                ? "- Level Risiko: **{$risks['risk_level']}** ({$risks['total_signals']} sinyal)"
                : "- Risk Level: **{$risks['risk_level']}** ({$risks['total_signals']} signals)";
        }

        // Recommended Action
        $sections[] = "\n## " . ($isIndonesian ? "Rekomendasi Tindakan" : "Recommended Actions");
        $sections[] = $isIndonesian
            ? "Berdasarkan analisis di atas, tinjau temuan kunci dan pertimbangkan untuk melakukan investigasi lebih lanjut pada area yang menunjukkan anomali atau risiko."
            : "Based on the analysis above, review key findings and consider further investigation in areas showing anomalies or risks.";

        return $this->safeJsonEncode([
            'insight' => implode("\n", $sections),
            'language' => $language,
            'sections_generated' => count($sections),
        ]);
    }
}
