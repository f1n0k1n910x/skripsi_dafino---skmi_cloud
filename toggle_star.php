<?php
session_start();
include 'config.php'; // Include your database connection
include 'functions.php'; // For logActivity

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$starred_file = 'starred.json';

// Ensure starred.json exists and is readable/writable
if (!file_exists($starred_file)) {
    file_put_contents($starred_file, json_encode([]));
}

// Read existing starred items
$starred_items = json_decode(file_get_contents($starred_file), true);
if (!is_array($starred_items)) {
    $starred_items = [];
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$item_id = $input['id'] ?? null;
$item_type = $input['type'] ?? null; // 'file' or 'folder'
$item_name = $input['name'] ?? null; // Only used when starring for the first time
$unstar_request = $input['unstar'] ?? false; // True if explicitly unstarring

if (!$item_id || !$item_type) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID or type.']);
    exit();
}

// Initialize user's starred items if not exists
if (!isset($starred_items[$user_id])) {
    $starred_items[$user_id] = [];
}

$found = false;
foreach ($starred_items[$user_id] as $key => $item) {
    if ($item['id'] == $item_id && $item['type'] == $item_type) {
        $found = true;
        // If found and it's an unstar request, remove it
        if ($unstar_request) {
            unset($starred_items[$user_id][$key]);
            $starred_items[$user_id] = array_values($starred_items[$user_id]); // Re-index array
            saveStarredItems($starred_file, $starred_items);
            logActivity($conn, $user_id, 'unstar_item', "Unstarred {$item_type}: {$item_name} (ID: {$item_id})");
            echo json_encode(['success' => true, 'message' => 'Item unpinned from Priority.']);
            exit();
        } else {
            // If found and not an unstar request, it means it's already starred, so unstar it
            unset($starred_items[$user_id][$key]);
            $starred_items[$user_id] = array_values($starred_items[$user_id]); // Re-index array
            saveStarredItems($starred_file, $starred_items);
            logActivity($conn, $user_id, 'unstar_item', "Unstarred {$item_type}: {$item_name} (ID: {$item_id})");
            echo json_encode(['success' => true, 'message' => 'Item unpinned from Priority.']);
            exit();
        }
    }
}

// If not found and not an unstar request, add it
if (!$found && !$unstar_request) {
    // Validate item exists in database before starring
    if ($item_type === 'file') {
        $stmt = $conn->prepare("SELECT file_name FROM files WHERE id = ?");
        $stmt->bind_param("i", $item_id);
    } elseif ($item_type === 'folder') {
        $stmt = $conn->prepare("SELECT folder_name FROM folders WHERE id = ?");
        $stmt->bind_param("i", $item_id);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid item type.']);
        exit();
    }

    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $actual_name = $item_type === 'file' ? $row['file_name'] : $row['folder_name'];
        
        $starred_items[$user_id][] = [
            'id' => $item_id,
            'type' => $item_type,
            'name' => $actual_name, // Use actual name from DB
            'starred_at' => date('Y-m-d H:i:s')
        ];
        saveStarredItems($starred_file, $starred_items);
        logActivity($conn, $user_id, 'star_item', "Starred {$item_type}: {$actual_name} (ID: {$item_id})");
        echo json_encode(['success' => true, 'message' => 'Item pinned to Priority.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found in database.']);
    }
    $stmt->close();
    exit();
}

// If not found and it was an unstar request (shouldn't happen if logic is correct)
echo json_encode(['success' => false, 'message' => 'Item not found or already unstarred.']);

// Function to save starred items (defined here for toggle_star.php's scope)
function saveStarredItems($starred_file, $items) {
    file_put_contents($starred_file, json_encode($items, JSON_PRETTY_PRINT));
}
?>
