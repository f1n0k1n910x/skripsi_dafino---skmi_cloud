<?php
session_start();
include 'config.php'; // Your database connection

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username']; // This can be username, primary email, or additional email
    $password = $_POST['password'];

    // Basic validation
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        $user = null;

        // 1. Try to find user by username or primary email in 'users' table
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
        }
        $stmt->close();

        // 2. If not found in 'users' table, try to find by additional email in 'user_emails' table
        if (!$user) {
            $stmt_additional = $conn->prepare("SELECT u.id, u.username, u.password FROM users u JOIN user_emails ue ON u.id = ue.user_id WHERE ue.email = ?");
            $stmt_additional->bind_param("s", $username);
            $stmt_additional->execute();
            $result_additional = $stmt_additional->get_result();
            if ($result_additional->num_rows === 1) {
                $user = $result_additional->fetch_assoc();
            }
            $stmt_additional->close();
        }

        if ($user) {
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];

                // Update last login time and IP, and also last_active for online status
                $last_login_time = date('Y-m-d H:i:s');
                $last_login_ip = $_SERVER['REMOTE_ADDR'];
                $last_active_time = date('Y-m-d H:i:s'); // Update last_active on login

                $stmt_update = $conn->prepare("UPDATE users SET last_login_time = ?, last_login_ip = ?, last_active = ? WHERE id = ?");
                $stmt_update->bind_param("sssi", $last_login_time, $last_login_ip, $last_active_time, $user['id']);
                $stmt_update->execute();
                $stmt_update->close();

                // Insert into login_history table (if you create one)
                $stmt_history_insert = $conn->prepare("INSERT INTO login_history (user_id, login_time, ip_address) VALUES (?, ?, ?)");
                $stmt_history_insert->bind_param("iss", $user['id'], $last_login_time, $last_login_ip);
                $stmt_history_insert->execute();
                $stmt_history_insert->close();


                header("Location: index.php"); // Redirect to the main application page
                exit();
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SKMI Cloud Storage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Metro Design (Modern UI) & Windows 7 Animations */
        :root {
            --metro-blue: #0078D7; /* Windows 10/Metro accent blue */
            --metro-dark-blue: #0056b3;
            --metro-light-gray: #E1E1E1;
            --metro-medium-gray: #C8C8C8;
            --metro-dark-gray: #666666;
            --metro-text-color: #333333;
            --metro-bg-color: #F0F0F0;
            --metro-sidebar-bg: #2D2D30; /* Darker sidebar for contrast */
            --metro-sidebar-text: #F0F0F0;
            --metro-success: #4CAF50;
            --metro-error: #E81123; /* Windows 10 error red */
            --metro-warning: #FF8C00; /* Windows 10 warning orange */
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: var(--metro-bg-color); /* Metro background color */
            background-image: url('https://source.unsplash.com/random/1920x1080/?abstract,technology'); /* Dynamic background image */
            background-size: cover;
            background-position: center;
            color: var(--metro-text-color);
            overflow: hidden;
        }

        .login-container {
            display: flex;
            background-color: white;
            border-radius: 8px; /* Softer rounded corners */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15); /* More pronounced shadow */
            overflow: hidden;
            max-width: 900px;
            width: 90%;
            animation: fadeInScale 0.5s ease-out forwards; /* Animation for the whole container */
        }

        .left-panel {
            flex: 1;
            padding: 40px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            background-image: linear-gradient(to right, rgba(0,0,0,0.5), rgba(0,0,0,0.2)), url('https://source.unsplash.com/random/800x600/?cloud,storage'); /* Dark overlay + dynamic background image */
            background-size: cover;
            background-position: center;
            position: relative;
            overflow: hidden; /* For subtle animation */
        }

        .left-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, rgba(0, 120, 215, 0.1), rgba(0, 120, 215, 0.3)); /* Subtle blue overlay */
            z-index: 0;
        }

        .left-panel-content {
            position: relative;
            z-index: 1;
            animation: slideInFromBottom 0.6s ease-out forwards; /* Animation for content */
        }

        .left-panel h2 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 300; /* Lighter font weight */
            text-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }

        .left-panel p {
            font-size: 1.1em;
            line-height: 1.6;
            text-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        .right-panel {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background-color: #FFFFFF; /* White background for form */
            animation: slideInFromRight 0.6s ease-out forwards; /* Animation for form */
        }

        .right-panel h2 {
            font-size: 2.2em; /* Slightly larger heading */
            margin-bottom: 30px;
            color: var(--metro-text-color); /* Metro text color */
            text-align: center;
            font-weight: 300; /* Lighter font weight */
            border-bottom: 1px solid var(--metro-light-gray); /* Subtle border */
            padding-bottom: 15px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--metro-text-color); /* Metro text color */
            font-weight: 600; /* Bolder label */
            font-size: 1.05em;
        }

        .form-group input {
            width: calc(100% - 24px); /* Adjust for padding and border */
            padding: 12px; /* More padding */
            border: 1px solid var(--metro-medium-gray); /* Subtle border */
            border-radius: 3px; /* Small border-radius */
            font-size: 1em;
            outline: none;
            background-color: var(--metro-bg-color); /* Light gray background for input */
            color: var(--metro-text-color);
            transition: border-color 0.2s ease-out, box-shadow 0.2s ease-out, background-color 0.2s ease-out;
        }

        .form-group input:focus {
            border-color: var(--metro-blue); /* Metro blue for focus */
            box-shadow: 0 0 0 2px rgba(0,120,215,0.3); /* Metro blue shadow */
            background-color: #FFFFFF; /* White background on focus */
        }

        .form-group input.error {
            border-color: var(--metro-error); /* Metro error red */
        }

        .error-message {
            color: var(--metro-error); /* Metro error red */
            font-size: 0.9em;
            margin-top: 5px;
            text-align: center;
            padding: 8px;
            background-color: rgba(232, 17, 35, 0.1); /* Light red background */
            border-radius: 3px;
            border: 1px solid var(--metro-error);
        }

        .button-primary {
            width: 100%;
            padding: 12px;
            background-color: var(--metro-blue); /* Metro blue for login */
            color: white;
            border: none;
            border-radius: 3px; /* Small border-radius */
            font-size: 1.2em;
            cursor: pointer;
            transition: background-color 0.2s ease-out, transform 0.1s ease-out, box-shadow 0.2s ease-out;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); /* Subtle shadow */
        }

        .button-primary:hover {
            background-color: var(--metro-dark-blue); /* Darker blue on hover */
            transform: translateY(-1px); /* Subtle lift */
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .button-primary:active {
            transform: translateY(0); /* Press effect */
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .switch-link {
            text-align: center;
            margin-top: 20px;
            color: var(--metro-dark-gray); /* Metro dark gray */
            font-size: 0.95em;
        }

        .switch-link a {
            color: var(--metro-blue); /* Metro blue for links */
            text-decoration: none;
            font-weight: bold;
            transition: color 0.2s ease-out, text-decoration 0.2s ease-out;
        }

        .switch-link a:hover {
            text-decoration: underline;
            color: var(--metro-dark-blue);
        }

        /* Windows 7-like Animations */
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes slideInFromBottom {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes slideInFromRight {
            from {
                transform: translateX(50px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="left-panel">
            <div class="left-panel-content">
                <h2>Welcome to SKMI Cloud Storage</h2>
                <p>Your secure and reliable cloud storage solution. Access your files anytime, anywhere.</p>
            </div>
        </div>
        <div class="right-panel">
            <h2>Login</h2>
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="username">Username or E-mail:</label>
                    <input type="text" id="username" name="username" required class="<?php echo ($error) ? 'error' : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required class="<?php echo ($error) ? 'error' : ''; ?>">
                </div>
                <button type="submit" class="button-primary">Login</button>
            </form>
            <div class="switch-link">
                Don't have an account? <a href="register.php">Sign Up</a>
            </div>
        </div>
    </div>
</body>
</html>
