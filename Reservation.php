<?php
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

class Reservation {
    private $conn;
    private $table = 'reservations';
    
    public function __construct() {
        $this->conn = getConnection();
    }
    
    public function getAll() {
        $sql = "SELECT r.*, t.table_number, c.name as customer_name_db 
                FROM {$this->table} r 
                LEFT JOIN tables t ON r.table_id = t.id 
                LEFT JOIN customers c ON r.customer_id = c.id 
                ORDER BY r.reservation_date DESC, r.reservation_time DESC";
        $result = $this->conn->query($sql);
        $reservations = array();
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $reservations[] = $row;
            }
        }
        return $reservations;
    }
    
    public function getUpcoming() {
        $sql = "SELECT r.*, t.table_number 
                FROM {$this->table} r 
                LEFT JOIN tables t ON r.table_id = t.id 
                WHERE r.reservation_date >= CURDATE() AND r.status IN ('pending', 'confirmed')
                ORDER BY r.reservation_date ASC, r.reservation_time ASC";
        $result = $this->conn->query($sql);
        $reservations = array();
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $reservations[] = $row;
            }
        }
        return $reservations;
    }
    
    public function getToday() {
        $sql = "SELECT r.*, t.table_number 
                FROM {$this->table} r 
                LEFT JOIN tables t ON r.table_id = t.id 
                WHERE r.reservation_date = CURDATE() AND r.status IN ('pending', 'confirmed')
                ORDER BY r.reservation_time ASC";
        $result = $this->conn->query($sql);
        $reservations = array();
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $reservations[] = $row;
            }
        }
        return $reservations;
    }
    
    public function getById($id) {
        $sql = "SELECT r.*, t.table_number 
                FROM {$this->table} r 
                LEFT JOIN tables t ON r.table_id = t.id 
                WHERE r.id = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }
    
    public function create($data) {
        $table_id = !empty($data['table_id']) ? intval($data['table_id']) : null;
        $customer_id = !empty($data['customer_id']) ? intval($data['customer_id']) : null;
        $customer_name = escape($this->conn, $data['customer_name']);
        $customer_phone = escape($this->conn, $data['customer_phone']);
        $guest_count = intval($data['guest_count']);
        $reservation_date = escape($this->conn, $data['reservation_date']);
        $reservation_time = escape($this->conn, $data['reservation_time']);
        $notes = escape($this->conn, isset($data['notes']) ? $data['notes'] : '');
        $status = 'pending';
        
        $sql = "INSERT INTO {$this->table} (table_id, customer_id, customer_name, customer_phone, guest_count, reservation_date, reservation_time, notes, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iississss", $table_id, $customer_id, $customer_name, $customer_phone, $guest_count, $reservation_date, $reservation_time, $notes, $status);
        
        if ($stmt->execute()) {
            // Update table status if table selected
            if ($table_id) {
                $this->updateTableStatus($table_id, 'reserved');
            }
            return array('success' => true, 'message' => 'Reservation created successfully');
        }
        return array('success' => false, 'error' => 'Failed to create reservation');
    }
    
    public function update($id, $data) {
        $table_id = !empty($data['table_id']) ? intval($data['table_id']) : null;
        $customer_name = escape($this->conn, $data['customer_name']);
        $customer_phone = escape($this->conn, $data['customer_phone']);
        $guest_count = intval($data['guest_count']);
        $reservation_date = escape($this->conn, $data['reservation_date']);
        $reservation_time = escape($this->conn, $data['reservation_time']);
        $notes = escape($this->conn, isset($data['notes']) ? $data['notes'] : '');
        $status = escape($this->conn, $data['status']);
        
        $sql = "UPDATE {$this->table} SET table_id = ?, customer_name = ?, customer_phone = ?, guest_count = ?, reservation_date = ?, reservation_time = ?, notes = ?, status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ississssi", $table_id, $customer_name, $customer_phone, $guest_count, $reservation_date, $reservation_time, $notes, $status, $id);
        
        if ($stmt->execute()) {
            return array('success' => true, 'message' => 'Reservation updated successfully');
        }
        return array('success' => false, 'error' => 'Failed to update reservation');
    }
    
    public function updateStatus($id, $status) {
        $status = escape($this->conn, $status);
        $sql = "UPDATE {$this->table} SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $status, $id);
        
        if ($stmt->execute()) {
            // If cancelled or completed, free the table
            if ($status == 'cancelled' || $status == 'completed') {
                $reservation = $this->getById($id);
                if ($reservation && $reservation['table_id']) {
                    $this->updateTableStatus($reservation['table_id'], 'available');
                }
            }
            return array('success' => true, 'message' => 'Status updated');
        }
        return array('success' => false, 'error' => 'Failed to update status');
    }
    
    public function delete($id) {
        $reservation = $this->getById($id);
        
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if ($reservation && $reservation['table_id']) {
                $this->updateTableStatus($reservation['table_id'], 'available');
            }
            return array('success' => true, 'message' => 'Reservation deleted');
        }
        return array('success' => false, 'error' => 'Failed to delete');
    }
    
    private function updateTableStatus($tableId, $status) {
        $sql = "UPDATE tables SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $status, $tableId);
        $stmt->execute();
    }
    
    public function countByStatus() {
        $sql = "SELECT status, COUNT(*) as count FROM {$this->table} WHERE reservation_date >= CURDATE() GROUP BY status";
        $result = $this->conn->query($sql);
        $counts = array('pending' => 0, 'confirmed' => 0, 'cancelled' => 0, 'completed' => 0);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $counts[$row['status']] = $row['count'];
            }
        }
        return $counts;
    }
    
    public function __destruct() {
        if ($this->conn) $this->conn->close();
    }
}
?>