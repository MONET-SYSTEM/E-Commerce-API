<?php
class PaymentController {
    private $db;
    private $payment;
    private $logger;
    
    public function __construct($db) {
        $this->db = $db;
        $this->payment = new Payment($db);
        $this->logger = new Logger($db);
    }
    
    // Create a new payment
    public function createPayment($data) {
        // Validate payment data
        if (!Validator::validatePaymentRequest($data)) {
            $this->logger->log('payment_create', $data, 'failed', 'Invalid payment data');
            return [
                'success' => false,
                'message' => 'Invalid payment data',
                'data' => null
            ];
        }
        
        // Set payment properties
        $this->payment->orderId = $data->orderId;
        $this->payment->amount = $data->amount;
        $this->payment->paymentMethod = $data->paymentMethod;
        $this->payment->status = isset($data->status) ? $data->status : 'pending';
        $this->payment->transactionId = isset($data->transactionId) ? $data->transactionId : $this->generateTransactionId();
        
        // Create the payment
        if ($this->payment->create()) {
            // Log success
            $this->logger->log('payment_create', [
                'payment_id' => $this->payment->id,
                'order_id' => $this->payment->orderId,
                'amount' => $this->payment->amount,
                'payment_method' => $this->payment->paymentMethod
            ], 'success');
            
            return [
                'success' => true,
                'message' => 'Payment was created successfully',
                'data' => [
                    'id' => $this->payment->id,
                    'order_id' => $this->payment->orderId,
                    'amount' => $this->payment->amount,
                    'payment_method' => $this->payment->paymentMethod,
                    'status' => $this->payment->status,
                    'transaction_id' => $this->payment->transactionId
                ]
            ];
        } else {
            // Log failure
            $this->logger->log('payment_create', $data, 'failed', 'Database error');
            
            return [
                'success' => false,
                'message' => 'Unable to create payment',
                'data' => null
            ];
        }
    }
    
    // Get payment by ID
    public function getPayment($id) {
        // Validate ID
        if (!is_numeric($id) || $id <= 0) {
            $this->logger->log('payment_read', ['id' => $id], 'failed', 'Invalid payment ID');
            return [
                'success' => false,
                'message' => 'Invalid payment ID',
                'data' => null
            ];
        }
        
        $this->payment->id = $id;
        
        // Get the payment
        if ($this->payment->readOne()) {
            // Log success
            $this->logger->log('payment_read', ['id' => $id], 'success');
            
            return [
                'success' => true,
                'message' => 'Payment found',
                'data' => [
                    'id' => $this->payment->id,
                    'order_id' => $this->payment->orderId,
                    'amount' => $this->payment->amount,
                    'payment_method' => $this->payment->paymentMethod,
                    'status' => $this->payment->status,
                    'transaction_id' => $this->payment->transactionId,
                    'created_at' => $this->payment->createdAt
                ]
            ];
        } else {
            // Log failure
            $this->logger->log('payment_read', ['id' => $id], 'failed', 'Payment not found');
            
            return [
                'success' => false,
                'message' => 'Payment not found',
                'data' => null
            ];
        }
    }
    
    // Get payments by order ID
    public function getPaymentsByOrder($orderId) {
        // Validate order ID
        if (!is_numeric($orderId) || $orderId <= 0) {
            $this->logger->log('payment_read_by_order', ['order_id' => $orderId], 'failed', 'Invalid order ID');
            return [
                'success' => false,
                'message' => 'Invalid order ID',
                'data' => null
            ];
        }
        
        $this->payment->orderId = $orderId;
        
        // Get the payments
        $payments = $this->payment->readByOrder();
        
        // Log success
        $this->logger->log('payment_read_by_order', ['order_id' => $orderId], 'success');
        
        return [
            'success' => true,
            'message' => count($payments) > 0 ? 'Payments found' : 'No payments found for this order',
            'data' => $payments
        ];
    }
    
    // Update payment status
    public function updatePaymentStatus($data) {
        // Validate payment status update
        if (!Validator::validatePaymentStatusUpdate($data)) {
            $this->logger->log('payment_update_status', $data, 'failed', 'Invalid payment status update data');
            return [
                'success' => false,
                'message' => 'Invalid payment status update data',
                'data' => null
            ];
        }
        
        // Set payment properties
        $this->payment->id = $data->id;
        $this->payment->status = $data->status;
        
        // Check if payment exists
        if (!$this->payment->readOne()) {
            $this->logger->log('payment_update_status', $data, 'failed', 'Payment not found');
            return [
                'success' => false,
                'message' => 'Payment not found',
                'data' => null
            ];
        }
        
        // Update the payment status
        if ($this->payment->updateStatus()) {
            // Log success
            $this->logger->log('payment_update_status', [
                'id' => $this->payment->id,
                'status' => $this->payment->status
            ], 'success');
            
            return [
                'success' => true,
                'message' => 'Payment status was updated successfully',
                'data' => [
                    'id' => $this->payment->id,
                    'status' => $this->payment->status
                ]
            ];
        } else {
            // Log failure
            $this->logger->log('payment_update_status', $data, 'failed', 'Database error');
            
            return [
                'success' => false,
                'message' => 'Unable to update payment status',
                'data' => null
            ];
        }
    }
    
    // Get all payments with pagination
    public function getAllPayments($page = 1, $limit = 10) {
        // Validate pagination parameters
        $page = max(1, (int)$page);
        $limit = max(1, min(100, (int)$limit));
        $offset = ($page - 1) * $limit;
        
        // Get payments
        $payments = $this->payment->read($limit, $offset);
        $total = $this->payment->count();
        
        // Calculate pagination info
        $totalPages = ceil($total / $limit);
        
        // Log success
        $this->logger->log('payment_read_all', [
            'page' => $page,
            'limit' => $limit
        ], 'success');
        
        return [
            'success' => true,
            'message' => count($payments) > 0 ? 'Payments retrieved successfully' : 'No payments found',
            'data' => [
                'payments' => $payments,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => $totalPages
                ]
            ]
        ];
    }

    // Delete payment by ID
    public function deletePayment($id) {
        // Validate ID
        if (!is_numeric($id) || $id <= 0) {
            $this->logger->log('payment_delete', ['id' => $id], 'failed', 'Invalid payment ID');
            return [
                'success' => false,
                'message' => 'Invalid payment ID',
                'data' => null
            ];
        }
        
        $this->payment->id = $id;
        
        // Delete the payment
        if ($this->payment->delete()) {
            // Log success
            $this->logger->log('payment_delete', ['id' => $id], 'success');
            
            return [
                'success' => true,
                'message' => 'Payment was deleted successfully',
                'data' => null
            ];
        } else {
            // Log failure
            $this->logger->log('payment_delete', ['id' => $id], 'failed', 'Database error');
            
            return [
                'success' => false,
                'message' => 'Unable to delete payment',
                'data' => null
            ];
        }
    }
    
    // Helper function to generate a transaction ID
    public function generateTransactionId() {
        // Generate a unique transaction ID
        return 'TRX' . time() . rand(1000, 9999);
    }
}
?>