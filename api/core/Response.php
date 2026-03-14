<?php
/**
 * Response Class - Standardized API responses
 */
class Response {
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
    
    public static function success($data = null, $statusCode = 200) {
        $response = [
            'success' => true,
            'timestamp' => date('c'),
            'data' => $data
        ];
        self::json($response, $statusCode);
    }
    
    public static function error($message, $statusCode = 400, $errors = null) {
        $response = [
            'success' => false,
            'timestamp' => date('c'),
            'error' => [
                'code' => $statusCode,
                'message' => is_array($message) ? $message : [$message]
            ]
        ];
        
        if ($errors !== null) {
            $response['error']['details'] = $errors;
        }
        
        self::json($response, $statusCode);
    }
    
    public static function paginated($data, $page, $perPage, $total) {
        $response = [
            'success' => true,
            'timestamp' => date('c'),
            'data' => $data,
            'pagination' => [
                'page' => (int)$page,
                'per_page' => (int)$perPage,
                'total' => (int)$total,
                'total_pages' => (int)ceil($total / $perPage)
            ]
        ];
        self::json($response);
    }
    
    public static function created($data = null) {
        self::success($data, 201);
    }
    
    public static function noContent() {
        http_response_code(204);
        exit;
    }
    
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }
    
    public static function forbidden($message = 'Forbidden') {
        self::error($message, 403);
    }
    
    public static function notFound($message = 'Not found') {
        self::error($message, 404);
    }
    
    public static function validationError($errors) {
        self::error('Validation failed', 422, $errors);
    }
    
    public static function serverError($message = 'Internal server error') {
        self::error($message, 500);
    }
}
