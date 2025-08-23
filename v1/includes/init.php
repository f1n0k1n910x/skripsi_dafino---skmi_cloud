<?php
/**
 * SKMI Cloud Storage - Application Initialization
 * Main application class and initialization logic
 */

class SKMICloudApp {
    private $conn;
    private $userId;
    private $userData;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->userId = $_SESSION['user_id'] ?? null;
        $this->loadUserData();
    }
    
    private function loadUserData() {
        if ($this->userId) {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $this->userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $this->userData = $result->fetch_assoc();
            $stmt->close();
        }
    }
    
    public function run() {
        $page = $_GET['page'] ?? 'dashboard';
        
        // Include header
        $this->includeHeader();
        
        // Route to appropriate page
        switch ($page) {
            case 'dashboard':
                $this->showDashboard();
                break;
            case 'files':
                $this->showFiles();
                break;
            case 'recycle_bin':
                $this->showRecycleBin();
                break;
            case 'profile':
                $this->showProfile();
                break;
            case 'members':
                $this->showMembers();
                break;
            default:
                $this->showDashboard();
        }
        
        // Include footer
        $this->includeFooter();
    }
    
    private function includeHeader() {
        include 'templates/header.php';
    }
    
    private function includeFooter() {
        include 'templates/footer.php';
    }
    
    private function showDashboard() {
        // Make database connection available to the dashboard page
        global $conn;
        $GLOBALS['conn'] = $conn;
        include 'pages/dashboard.php';
    }
    
    private function showFiles() {
        include 'pages/files.php';
    }
    
    private function showRecycleBin() {
        include 'pages/recycle_bin.php';
    }
    
    private function showProfile() {
        include 'pages/profile.php';
    }
    
    private function showMembers() {
        include 'pages/members.php';
    }
    
    public function getUserData() {
        return $this->userData;
    }
    
    public function getUserId() {
        return $this->userId;
    }
    
    public function getConnection() {
        return $this->conn;
    }
}
