<?php
// Include necessary files and set headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database, models, controllers, and utilities
include_once '../config/database.php';
include_once '../models/Payment.php';
include_once '../controllers/PaymentController.php';
include_once '../utilities/Logger.php';
include_once '../utilities/Validator.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Create instances
$paymentController = new PaymentController($db);
$logger = new Logger($db);

// Get request method
$requestMethod = $_SERVER["REQUEST_METHOD"];

// Get endpoint segments
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($uri, '/'));
$endpoint = end($segments);

// Process requests
try {
    switch ($requestMethod) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get payment by ID
                $result = $paymentController->getPayment($_GET['id']);
            } elseif (isset($_GET['order_id'])) {
                // Get payments by order ID
                $result = $paymentController->getPaymentsByOrder($_GET['order_id']);
            } else {
                // Get all payments with pagination
                $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
                $result = $paymentController->getAllPayments($page, $limit);
            }
            break;
            
        case 'POST':
            // Get posted data
            $data = json_decode(file_get_contents("php://input"));
            
            if (!$data) {
                throw new Exception("Invalid JSON data");
            }
            
            // Create a new payment
            $result = $paymentController->createPayment($data);
            break;
            
        case 'PUT':
            // Get posted data
            $data = json_decode(file_get_contents("php://input"));
            
            if (!$data) {
                throw new Exception("Invalid JSON data");
            }
            
            // Update payment status
            $result = $paymentController->updatePaymentStatus($data);
            break;
        
        case 'DELETE':
            // Get payment ID from URL
            if (isset($_GET['id'])) {
                // Delete payment by ID
                $result = $paymentController->deletePayment($_GET['id']);
            } else {
                throw new Exception("Payment ID not provided");
            }
            break;
        default:
            // Method not allowed
            http_response_code(405);
            $result = [
                'success' => false,
                'message' => 'Method not allowed',
                'data' => null
            ];
            $logger->log('payment_api', ['method' => $requestMethod], 'failed', 'Method not allowed');
            break;
    }
    
    // Output result
    echo json_encode($result);
    
} catch (Exception $e) {
    // Handle exceptions
    http_response_code(500);
    $errorResult = [
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'data' => null
    ];
    
    // Log the error
    $logger->log('payment_api', ['error' => $e->getMessage()], 'failed', $e->getMessage());
    
    // Output error result
    echo json_encode($errorResult);
}
?>