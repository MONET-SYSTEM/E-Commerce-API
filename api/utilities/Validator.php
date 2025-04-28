<?php
class Validator {
    // Validation method for order requests
    public static function validateOrderRequest($data) {
        // Check if required fields exist
        if (!isset($data->userId) || !isset($data->items) || !isset($data->totalAmount)) {
            return false;
        }
        
        // Check if userId is valid
        if (!is_numeric($data->userId) || $data->userId <= 0) {
            return false;
        }
        
        // Check if items is an array and not empty
        if (!is_array($data->items) || empty($data->items)) {
            return false;
        }
        
        // Check if totalAmount is valid
        if (!is_numeric($data->totalAmount) || $data->totalAmount <= 0) {
            return false;
        }
        
        // Validate each item
        foreach ($data->items as $item) {
            if (!isset($item->productId) || !isset($item->quantity) || !isset($item->price)) {
                return false;
            }
            
            if (!is_numeric($item->productId) || $item->productId <= 0) {
                return false;
            }
            
            if (!is_numeric($item->quantity) || $item->quantity <= 0) {
                return false;
            }
            
            if (!is_numeric($item->price) || $item->price <= 0) {
                return false;
            }
        }
        
        return true;
    }
    
    // Validation method for user requests
    public static function validateUserRequest($data) {
        // Check if required fields exist
        if (!isset($data->name) || !isset($data->email) || !isset($data->password)) {
            return false;
        }
        
        // Validate email format
        if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Validate name and password length
        if (strlen($data->name) < 3 || strlen($data->password) < 6) {
            return false;
        }
        
        return true;
    }
    
    // Validation method for product requests
    public static function validateProductRequest($data) {
        // Check if required fields exist
        if (!isset($data->name) || !isset($data->price) || !isset($data->stock)) {
            return false;
        }
        
        // Validate name
        if (strlen($data->name) < 3) {
            return false;
        }
        
        // Validate price and stock
        if (!is_numeric($data->price) || $data->price <= 0) {
            return false;
        }
        
        if (!is_numeric($data->stock) || $data->stock < 0) {
            return false;
        }
        
        return true;
    }

    // Validation method for payment requests
    public static function validatePaymentRequest($data) {
        // Check if required fields exist
        if (!isset($data->orderId) || !isset($data->amount) || !isset($data->paymentMethod)) {
            return false;
        }
        
        // Check if orderId is valid
        if (!is_numeric($data->orderId) || $data->orderId <= 0) {
            return false;
        }
        
        // Check if amount is valid
        if (!is_numeric($data->amount) || $data->amount <= 0) {
            return false;
        }
        
        // Check if paymentMethod is valid
        $validPaymentMethods = ['credit_card', 'debit_card', 'paypal', 'bank_transfer', 'crypto'];
        if (!in_array($data->paymentMethod, $validPaymentMethods)) {
            return false;
        }
        
        // Validate transaction ID if provided
        if (isset($data->transactionId) && (empty($data->transactionId) || strlen($data->transactionId) < 5)) {
            return false;
        }
        
        return true;
    }
    
    // Validate payment status update
    public static function validatePaymentStatusUpdate($data) {
        // Check if required fields exist
        if (!isset($data->id) || !isset($data->status)) {
            return false;
        }
        
        // Check if payment ID is valid
        if (!is_numeric($data->id) || $data->id <= 0) {
            return false;
        }
        
        // Check if status is valid
        $validStatuses = ['pending', 'processing', 'completed', 'failed', 'refunded'];
        if (!in_array($data->status, $validStatuses)) {
            return false;
            
        }
        
        return true;
    }
}
?>