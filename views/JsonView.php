<?php
class JsonView {
    
    public function render($data, $statusCode = 200) {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
    
    public function success($message, $data = null) {
        $response = ['success' => true, 'message' => $message];
        if ($data !== null) {
            $response['data'] = $data;
        }
        return $this->render($response);
    }
    
    public function error($message, $statusCode = 400) {
        return $this->render(['success' => false, 'message' => $message], $statusCode);
    }
    
    public function validationError($errors) {
        return $this->render([
            'success' => false, 
            'message' => 'Validation failed',
            'errors' => $errors
        ], 422);
    }
}
?> 