<?php
// Professional error handler
class ErrorHandler {
    private static $logFile = '../logs/error.log';
    
    public static function init() {
        set_error_handler([__CLASS__, 'handleError']);
        set_exception_handler([__CLASS__, 'handleException']);
        register_shutdown_function([__CLASS__, 'handleShutdown']);
    }
    
    public static function handleError($level, $message, $file, $line) {
        $error = [
            'type' => 'Error',
            'level' => $level,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'time' => date('Y-m-d H:i:s')
        ];
        
        self::log($error);
        
        // Don't display errors in production
        if (!self::isDevelopment()) {
            return true;
        }
        
        return false;
    }
    
    public static function handleException($exception) {
        $error = [
            'type' => 'Exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'time' => date('Y-m-d H:i:s')
        ];
        
        self::log($error);
        
        if (self::isDevelopment()) {
            echo "<h1>Application Error</h1>";
            echo "<p><strong>Message:</strong> {$error['message']}</p>";
            echo "<p><strong>File:</strong> {$error['file']}:{$error['line']}</p>";
        } else {
            // Show user-friendly error page
            header('Location: /error.php');
        }
        exit;
    }
    
    public static function handleShutdown() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }
    
    private static function log($error) {
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logEntry = sprintf(
            "[%s] %s: %s in %s:%d\n",
            $error['time'],
            $error['type'],
            $error['message'],
            $error['file'],
            $error['line']
        );
        
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private static function isDevelopment() {
        return $_SERVER['SERVER_NAME'] === 'localhost' || strpos($_SERVER['SERVER_NAME'], '192.168.') === 0;
    }
}

// Initialize error handler
ErrorHandler::init();