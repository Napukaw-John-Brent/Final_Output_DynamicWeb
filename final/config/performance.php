<?php
class PerformanceMonitor {
    private $startTime;
    private $queries = [];
    private $memoryStart;
    
    public function __construct() {
        $this->startTime = microtime(true);
        $this->memoryStart = memory_get_usage();
    }
    
    public function addQuery($sql, $time) {
        $this->queries[] = [
            'sql' => $sql,
            'time' => $time
        ];
    }
    
    public function getStats() {
        return [
            'execution_time' => round((microtime(true) - $this->startTime) * 1000, 2) . 'ms',
            'memory_usage' => round((memory_get_usage() - $this->memoryStart) / 1024, 2) . 'KB',
            'peak_memory' => round(memory_get_peak_usage() / 1024, 2) . 'KB',
            'queries' => count($this->queries),
            'query_time' => round(array_sum(array_column($this->queries, 'time')) * 1000, 2) . 'ms'
        ];
    }
    
    public function displayStats() {
        if ($_SERVER['SERVER_NAME'] === 'localhost') {
            $stats = $this->getStats();
            echo "<!-- Performance Stats: {$stats['execution_time']}, Memory: {$stats['memory_usage']}, Queries: {$stats['queries']} -->";
        }
    }
}