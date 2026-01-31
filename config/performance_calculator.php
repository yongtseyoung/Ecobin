<?php

require_once '../config/database.php';

class PerformanceCalculator {
    
    private $employee_id;
    private $period_start;
    private $period_end;
    private $period_type;
    
    public function __construct($employee_id, $period_type = 'current_month', $custom_start = null, $custom_end = null) {
        $this->employee_id = $employee_id;
        $this->period_type = $period_type;
        $this->setPeriodDates($period_type, $custom_start, $custom_end);
    }
    

    private function setPeriodDates($period_type, $custom_start, $custom_end) {
        switch ($period_type) {
            case 'current_month':
                $this->period_start = date('Y-m-01');
                $this->period_end = date('Y-m-t');
                break;
                
            case 'last_7_days':
                $this->period_start = date('Y-m-d', strtotime('-7 days'));
                $this->period_end = date('Y-m-d');
                break;
                
            case 'last_30_days':
                $this->period_start = date('Y-m-d', strtotime('-30 days'));
                $this->period_end = date('Y-m-d');
                break;
                
            case 'custom':
                $this->period_start = $custom_start;
                $this->period_end = $custom_end;
                break;
                
            case 'all_time':
                $this->period_start = '2000-01-01';
                $this->period_end = date('Y-m-d');
                break;
                
            default:
                $this->period_start = date('Y-m-01');
                $this->period_end = date('Y-m-t');
        }
    }
    

    public function calculatePerformance() {
        $metrics = [];
        
        $task_metrics = $this->getTaskMetrics();
        $metrics = array_merge($metrics, $task_metrics);
        
        $attendance_metrics = $this->getAttendanceMetrics();
        $metrics = array_merge($metrics, $attendance_metrics);
        
        $collection_metrics = $this->getCollectionMetrics();
        $metrics = array_merge($metrics, $collection_metrics);
        
        $metrics['completion_rate'] = $this->calculateCompletionRate($metrics);
        $metrics['on_time_rate'] = $this->calculateOnTimeRate($metrics);
        $metrics['attendance_rate'] = $this->calculateAttendanceRate($metrics);
        
        $metrics['efficiency_score'] = $this->calculateEfficiencyScore();
        
        $metrics['performance_score'] = $this->calculateOverallScore($metrics);
        
        $metrics['task_performance_stars'] = $this->scoreToStars($metrics['completion_rate']);
        $metrics['attendance_stars'] = $this->scoreToStars($metrics['attendance_rate']);
        $metrics['efficiency_stars'] = $this->scoreToStars($metrics['efficiency_score']);
        $metrics['overall_stars'] = $this->scoreToStars($metrics['performance_score']);
        
        $metrics['performance_grade'] = $this->scoreToGrade($metrics['performance_score']);
        
        $metrics['employee_id'] = $this->employee_id;
        $metrics['period_type'] = $this->period_type;
        $metrics['period_start'] = $this->period_start;
        $metrics['period_end'] = $this->period_end;
        
        return $metrics;
    }
    

    private function getTaskMetrics() {
        // Tasks assigned in period
        $tasks_assigned_in_period = getOne(
            "SELECT COUNT(*) as count FROM tasks 
             WHERE assigned_to = ? 
             AND DATE(created_at) BETWEEN ? AND ?",
            [$this->employee_id, $this->period_start, $this->period_end]
        )['count'] ?? 0;
        
        // Tasks completed in period
        $tasks_completed = getOne(
            "SELECT COUNT(*) as count FROM tasks 
             WHERE assigned_to = ? 
             AND status = 'completed'
             AND DATE(completed_at) BETWEEN ? AND ?",
            [$this->employee_id, $this->period_start, $this->period_end]
        )['count'] ?? 0;

        $tasks_assigned = max($tasks_assigned_in_period, $tasks_completed);
        
        $tasks_on_time = getOne(
            "SELECT COUNT(*) as count FROM tasks 
             WHERE assigned_to = ? 
             AND status = 'completed'
             AND DATE(completed_at) BETWEEN ? AND ?
             AND DATE(completed_at) <= scheduled_date",
            [$this->employee_id, $this->period_start, $this->period_end]
        )['count'] ?? 0;
        
        $avg_completion = getOne(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours
             FROM tasks 
             WHERE assigned_to = ? 
             AND status = 'completed'
             AND DATE(completed_at) BETWEEN ? AND ?",
            [$this->employee_id, $this->period_start, $this->period_end]
        )['avg_hours'] ?? 0;
        
        return [
            'tasks_assigned' => $tasks_assigned,
            'tasks_completed' => $tasks_completed,
            'tasks_on_time' => $tasks_on_time,
            'avg_task_completion_hours' => round($avg_completion, 2)
        ];
    }
    

    private function getAttendanceMetrics() {
        // Total working days in period (excluding weekends)
        $working_days = $this->getWorkingDays($this->period_start, $this->period_end);
        
        // Attendance days (days employee checked in)
        $attendance_days = getOne(
            "SELECT COUNT(DISTINCT attendance_date) as count 
             FROM attendance 
             WHERE employee_id = ? 
             AND attendance_date BETWEEN ? AND ?",
            [$this->employee_id, $this->period_start, $this->period_end]
        )['count'] ?? 0;
        
        // Late days (status = 'late')
        $late_days = getOne(
            "SELECT COUNT(*) as count 
             FROM attendance 
             WHERE employee_id = ? 
             AND attendance_date BETWEEN ? AND ?
             AND status = 'late'",
            [$this->employee_id, $this->period_start, $this->period_end]
        )['count'] ?? 0;
        
        return [
            'working_days' => $working_days,
            'attendance_days' => $attendance_days,
            'late_days' => $late_days
        ];
    }
    

    private function getCollectionMetrics() {
        // Total bins collected
        $bins_collected = getOne(
            "SELECT COUNT(*) as count 
             FROM collection_reports 
             WHERE employee_id = ? 
             AND collection_date BETWEEN ? AND ?",
            [$this->employee_id, $this->period_start, $this->period_end]
        )['count'] ?? 0;
        
        // Total weight collected (in kg)
        $weight_collected = getOne(
            "SELECT COALESCE(SUM(total_weight), 0) as total 
             FROM collection_reports 
             WHERE employee_id = ? 
             AND collection_date BETWEEN ? AND ?",
            [$this->employee_id, $this->period_start, $this->period_end]
        )['total'] ?? 0;
        
        return [
            'total_bins_collected' => $bins_collected,
            'total_weight_collected' => round($weight_collected, 2)
        ];
    }
    
    /**
     * Calculate completion rate
     */
    private function calculateCompletionRate($metrics) {
        if ($metrics['tasks_assigned'] == 0) return 0;
        return round(($metrics['tasks_completed'] / $metrics['tasks_assigned']) * 100, 2);
    }
    
    /**
     * Calculate on-time completion rate
     */
    private function calculateOnTimeRate($metrics) {
        if ($metrics['tasks_completed'] == 0) return 0;
        return round(($metrics['tasks_on_time'] / $metrics['tasks_completed']) * 100, 2);
    }
    
    /**
     * Calculate attendance rate
     */
    private function calculateAttendanceRate($metrics) {
        if ($metrics['working_days'] == 0) return 0;
        return round(($metrics['attendance_days'] / $metrics['working_days']) * 100, 2);
    }
    
    /**
     * Calculate efficiency score based on response time
     * Lower hours = higher score
     * Same day completion (0-8 hours) = 100%
     * Next day (8-24 hours) = 80%
     * 2 days (24-48 hours) = 60%
     * 3+ days = decreasing score
     */
    private function calculateEfficiencyScore() {
        $avg_hours = getOne(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours
             FROM tasks 
             WHERE assigned_to = ? 
             AND status = 'completed'
             AND DATE(completed_at) BETWEEN ? AND ?",
            [$this->employee_id, $this->period_start, $this->period_end]
        )['avg_hours'] ?? 0;
        
        if ($avg_hours == 0) return 100;
        
        if ($avg_hours <= 8) {
            return 100;
        } elseif ($avg_hours <= 24) {
            return 80;
        } elseif ($avg_hours <= 48) {
            return 60;
        } elseif ($avg_hours <= 72) {
            return 40;
        } else {
            return max(20, 100 - ($avg_hours * 0.5));
        }
    }
    
    /**
     * Calculate overall performance score (Weighted)
     * Formula A (Balanced):
     * - Task Completion Rate (30%)
     * - On-Time Completion Rate (30%)
     * - Attendance Rate (25%)
     * - Efficiency Score (15%)
     */
    private function calculateOverallScore($metrics) {
        $score = 
            ($metrics['completion_rate'] * 0.30) +
            ($metrics['on_time_rate'] * 0.30) +
            ($metrics['attendance_rate'] * 0.25) +
            ($metrics['efficiency_score'] * 0.15);
        
        return round($score, 2);
    }
    

    private function scoreToStars($score) {
        if ($score >= 90) return 5;
        if ($score >= 75) return 4;
        if ($score >= 60) return 3;
        if ($score >= 40) return 2;
        return 1;
    }
    

    private function scoreToGrade($score) {
        if ($score >= 90) return 'excellent';
        if ($score >= 75) return 'good';
        if ($score >= 60) return 'average';
        if ($score >= 40) return 'needs_improvement';
        return 'poor';
    }
    

    private function getWorkingDays($start_date, $end_date) {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $end->modify('+1 day'); 
        
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);
        
        $working_days = 0;
        foreach ($period as $date) {
            $day_of_week = $date->format('N'); 
            if ($day_of_week < 6) { 
                $working_days++;
            }
        }
        
        return $working_days;
    }
    

    public function getPerformanceHistory() {
        $history = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $month_start = date('Y-m-01', strtotime("-$i months"));
            $month_end = date('Y-m-t', strtotime("-$i months"));
            
            $calculator = new PerformanceCalculator($this->employee_id, 'custom', $month_start, $month_end);
            $metrics = $calculator->calculatePerformance();
            
            $history[] = [
                'month' => date('M Y', strtotime($month_start)),
                'score' => $metrics['performance_score'],
                'completion_rate' => $metrics['completion_rate'],
                'on_time_rate' => $metrics['on_time_rate'],
                'attendance_rate' => $metrics['attendance_rate']
            ];
        }
        
        return $history;
    }
}


function getEmployeePerformance($employee_id, $period_type = 'current_month', $custom_start = null, $custom_end = null) {
    $calculator = new PerformanceCalculator($employee_id, $period_type, $custom_start, $custom_end);
    return $calculator->calculatePerformance();
}


function calculateAndSavePerformance($employee_id, $period_type = 'current_month') {
    $calculator = new PerformanceCalculator($employee_id, $period_type);
    $metrics = $calculator->calculatePerformance();
    $calculator->saveToDatabase($metrics);
    return $metrics;
}
?>