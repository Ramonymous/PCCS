<?php

namespace App\Services;

use App\Models\Customer\HPM\Pcc;
use App\Models\Customer\HPM\PccTrace;
use App\Models\Customer\HPM\PccEvent;

class PccTraceService
{
    /**
     * Find a PCC by slip barcode with optional eager loads.
     */
    public static function findPccByBarcode(string $barcode, array $with = []): ?Pcc
    {
        $query = Pcc::query();
        if (!empty($with)) {
            $query->with($with);
        }
        return $query->where('slip_barcode', $barcode)->first();
    }

    /**
     * Find a PCC by slip barcode or slip number with optional eager loads.
     */
    public static function findPccByBarcodeOrSlip(string $value, array $with = []): ?Pcc
    {
        $query = Pcc::query();
        if (!empty($with)) {
            $query->with($with);
        }
        return $query->where('slip_barcode', $value)
            ->orWhere('slip_no', $value)
            ->first();
    }

    /**
     * Get the current trace (single row representing current state) for a PCC.
     */
    public static function getCurrentTrace(?Pcc $pcc): ?PccTrace
    {
        if (!$pcc) {
            return null;
        }
        return PccTrace::where('pcc_id', $pcc->id)->first();
    }

    /**
     * Convenience: Find PCC by barcode and fetch its current trace together.
     * Returns [ 'pcc' => ?Pcc, 'trace' => ?PccTrace ].
     */
    public static function findByBarcodeWithTrace(string $barcode, array $with = []): array
    {
        $pcc = self::findPccByBarcode($barcode, $with);
        $trace = self::getCurrentTrace($pcc);
        return ['pcc' => $pcc, 'trace' => $trace];
    }

    /**
     * Get the most recent event for a given trace and event type within the last N minutes.
     */
    public static function getRecentEvent(?PccTrace $trace, string $eventType, int $minutes = 5): ?PccEvent
    {
        if (!$trace) {
            return null;
        }
        return PccEvent::where('pcc_trace_id', $trace->id)
            ->where('event_type', $eventType)
            ->where('event_timestamp', '>', now()->subMinutes($minutes))
            ->first();
    }

    /**
     * Get the latest event of a given type for this trace (no time window).
     */
    public static function getLastEvent(?PccTrace $trace, string $eventType): ?PccEvent
    {
        if (!$trace) {
            return null;
        }
        return PccEvent::where('pcc_trace_id', $trace->id)
            ->where('event_type', $eventType)
            ->latest('event_timestamp')
            ->first();
    }

    /**
     * Determine if the PCC's finish good is DIRECT type.
     */
    public static function isDirect(?Pcc $pcc): bool
    {
        return (bool) ($pcc && $pcc->finishGood && $pcc->finishGood->type === 'DIRECT');
    }

    /**
     * Validate stage transition for a given event type.
     * Returns ['valid' => bool, 'message' => ?string, 'expected' => ?string].
     */
    public static function validateStageTransition(?PccTrace $trace, string $eventType, bool $isDirect): array
    {
        // No trace: check if this is a valid initial stage
        if (!$trace) {
            $validInitialStages = ['PRODUCTION CHECK']; // ASSY starts here
            if ($isDirect) {
                $validInitialStages[] = 'PDI CHECK'; // DIRECT can start at PDI CHECK
            }
            
            if (!in_array($eventType, $validInitialStages, true)) {
                $required = $isDirect ? 'PDI CHECK' : 'PRODUCTION CHECK';
                return [
                    'valid' => false,
                    'message' => "Label has not been processed yet. Must go through '{$required}' first.",
                    'expected' => $required,
                ];
            }
            return ['valid' => true, 'message' => null, 'expected' => null];
        }

        $currentStage = $trace->event_type;

        // Already at target stage
        if ($currentStage === $eventType) {
            return [
                'valid' => false,
                'message' => "Label is already at '{$eventType}' stage. Cannot scan again.",
                'expected' => null,
            ];
        }

        // Define valid stage flows
        $stageFlows = [
            'DIRECT' => [
                'PDI CHECK' => null, // Initial stage
                'RECEIVED' => 'PDI CHECK',
                'DELIVERY' => 'RECEIVED',
            ],
            'ASSY' => [
                'PRODUCTION CHECK' => null, // Initial stage
                'RECEIVED' => 'PRODUCTION CHECK',
                'PDI CHECK' => 'RECEIVED',
                'DELIVERY' => 'PDI CHECK',
            ],
        ];

        $type = $isDirect ? 'DIRECT' : 'ASSY';
        $flow = $stageFlows[$type];

        // Check if target event is in the flow
        if (!isset($flow[$eventType])) {
            return [
                'valid' => false,
                'message' => "'{$eventType}' is not a valid stage for {$type} parts.",
                'expected' => null,
            ];
        }

        $expectedStage = $flow[$eventType];
        
        // Initial stage (no prerequisite)
        if ($expectedStage === null) {
            return ['valid' => true, 'message' => null, 'expected' => null];
        }

        // Check if current stage matches expected
        if ($currentStage === $expectedStage) {
            return ['valid' => true, 'message' => null, 'expected' => null];
        }

        // Already past this stage
        $stageOrder = array_keys($flow);
        $currentIndex = array_search($currentStage, $stageOrder, true);
        $targetIndex = array_search($eventType, $stageOrder, true);

        if ($currentIndex !== false && $targetIndex !== false && $currentIndex > $targetIndex) {
            return [
                'valid' => false,
                'message' => "Label is already at '{$currentStage}'. Cannot return to '{$eventType}'.",
                'expected' => null,
            ];
        }

        // Not at expected prerequisite stage yet
        return [
            'valid' => false,
            'message' => "Label is still at '{$currentStage}'. Must pass '{$expectedStage}' first.",
            'expected' => $expectedStage,
        ];
    }
}
