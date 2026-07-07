<?php

class ApplicationProgressManager {
    
    private $pdo;

    public const STAGE_SUBMITTED      = 'Application Submitted';
    public const STAGE_DOC_VERIFY     = 'Documents Verification';
    public const STAGE_REFEREES       = 'Referee Report';
    public const STAGE_DEPT_REVIEW    = 'Departmental Review';
    public const STAGE_PG_REVIEW      = 'PG Review';
    public const STAGE_DECISION       = 'Final Decisions';

    public const STATUS_PENDING       = 'Pending';
    public const STATUS_IN_PROGRESS   = 'In Progress';
    public const STATUS_COMPLETED     = 'Completed';

    public const ALL_STAGES = [
        self::STAGE_SUBMITTED,
        self::STAGE_DOC_VERIFY,
        self::STAGE_REFEREES,
        self::STAGE_DEPT_REVIEW,
        self::STAGE_PG_REVIEW,
        self::STAGE_DECISION,
    ];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

 
    public function initializeApplication(int $appId): bool {
        try {
            $this->pdo->beginTransaction();

            $sql = "INSERT IGNORE INTO application_progress (application_id, stage, stage_status, stage_updated_at) VALUES (?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);

            foreach (self::ALL_STAGES as $stage) {
                $status = self::STATUS_PENDING;
                
                if ($stage === self::STAGE_SUBMITTED) {
                    $status = self::STATUS_COMPLETED;
                }

                $stmt->execute([$appId, $stage, $status]);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Progress Init Error: " . $e->getMessage());
            return false;
        }
    }

   
    public function updateStageStatus(int $appId, string $stageName, string $newStatus): bool {
        if (!in_array($stageName, self::ALL_STAGES)) {
            throw new InvalidArgumentException("Invalid stage name: $stageName");
        }
        
        $allowedStatuses = [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED];
        if (!in_array($newStatus, $allowedStatuses)) {
            throw new InvalidArgumentException("Invalid status: $newStatus");
        }

        $stmt = $this->pdo->prepare("
            UPDATE application_progress 
            SET stage_status = ?, stage_updated_at = NOW() 
            WHERE application_id = ? AND stage = ?
        ");
        
        return $stmt->execute([$newStatus, $appId, $stageName]);
    }

 
    public function advanceToNextStage(int $appId): string {
        $history = $this->getAllProgress($appId);
        
        foreach (self::ALL_STAGES as $stage) {
            $status = $history[$stage]['status'] ?? self::STATUS_PENDING;
            
            if ($status !== self::STATUS_COMPLETED) {
                
                if ($status === self::STATUS_PENDING) {
                    $this->updateStageStatus($appId, $stage, self::STATUS_IN_PROGRESS);
                    return "Moved '$stage' to In Progress";
                }
                
                if ($status === self::STATUS_IN_PROGRESS) {
                    $this->updateStageStatus($appId, $stage, self::STATUS_COMPLETED);
                    
                    return "Moved '$stage' to Completed";
                }
            }
        }
        
        return "Application is fully complete.";
    }


    public function getAllProgress(int $appId): array {
        $stmt = $this->pdo->prepare("SELECT stage, stage_status, stage_updated_at FROM application_progress WHERE application_id = ?");
        $stmt->execute([$appId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['stage']] = [
                'status' => $row['stage_status'],
                'date'   => $row['stage_updated_at']
            ];
        }
        return $result;
    }

    public function isStageCompleted(int $appId, string $stageName): bool {
        $history = $this->getAllProgress($appId);
        $status = strtoupper(trim($history[$stageName]['status'] ?? ''));
        return in_array($status, ['COMPLETED', 'APPROVED', 'REJECTED'], true);
    }

    public function canAdvanceToStage(int $appId, string $stageName, &$missingStage = null): bool {
        $history = $this->getAllProgress($appId);
        if (empty($history)) {
            $this->initializeApplication($appId);
            $history = $this->getAllProgress($appId);
        }
        foreach (self::ALL_STAGES as $stage) {
            if ($stage === $stageName) {
                break;
            }
            $status = strtoupper(trim($history[$stage]['status'] ?? ''));
            if (!in_array($status, ['COMPLETED', 'APPROVED', 'REJECTED'], true)) {
                $missingStage = $stage;
                return false;
            }
        }
        return true;
    }
}
?>