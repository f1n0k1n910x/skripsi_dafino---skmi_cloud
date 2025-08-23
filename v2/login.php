<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include '../config.php'; // Your database connection

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
        $stmt = $conn->prepare("SELECT id, username, password, is_member FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
        }
        $stmt->close();

        // 2. If not found in 'users' table, try to find by additional email in 'user_emails' table
        if (!$user) {
            $stmt_additional = $conn->prepare("SELECT u.id, u.username, u.password, u.is_member FROM users u JOIN user_emails ue ON u.id = ue.user_id WHERE ue.email = ?");
            $stmt_additional->bind_param("s", $username);
            $stmt_additional->execute();
            $result_additional = $stmt_additional->get_result();
            if ($result_additional->num_rows === 1) {
                $user = $result_additional->fetch_assoc();
            }
            $stmt_additional->close();
        }

        if ($user) {
            // Verifikasi password
            if (password_verify($password, $user['password'])) {
                // Check if user is a member, if not, update their status
                if ($user['is_member'] == 0) {
                    $stmt_update = $conn->prepare("UPDATE users SET is_member = 1 WHERE id = ?");
                    $stmt_update->bind_param("i", $user['id']);
                    $stmt_update->execute();
                    $stmt_update->close();
                }

                // Login berhasil
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header('Location: index.php'); // Redirect ke halaman dashboard
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
    <title>Login - SKMI Cloud</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap">
    <style>
        :root {
            --primary-color: #0078d7;
            --secondary-color: #e6e6e6;
            --background-color: #f0f0f0;
            --text-color: #333;
            --tile-background: #fff;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            transition: background-color 0.3s ease;
        }

        .container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }

        .login-box {
            background-color: var(--tile-background);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
            padding: 40px;
            text-align: center;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h2 {
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--primary-color);
        }

        .error-message, .success-message {
            background-color: #ffcccc;
            color: #d9534f;
            border: 1px solid #d9534f;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            animation: shake 0.5s;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color);
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-group input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 5px rgba(0, 120, 215, 0.5);
            outline: none;
        }

        .form-group input.error {
            border-color: #d9534f;
        }
        
        .password-container {
            position: relative;
        }
        
        .password-container input {
            padding-right: 40px; /* Make space for the toggle icon */
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        .button-primary {
            background-color: var(--primary-color);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            font-size: 18px;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .button-primary:hover {
            background-color: #005a9e;
            transform: translateY(-2px);
        }

        .button-primary:active {
            transform: translateY(0);
        }

        .switch-link {
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }

        .switch-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .switch-link a:hover {
            color: #005a9e;
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 600px) {
            .login-box {
                padding: 20px;
                box-shadow: none;
                border-radius: 0;
            }
            body {
                padding: 0;
            }
            .container {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-box">
            <h2>Login</h2>
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="username">Username or Email:</label>
                    <input type="text" id="username" name="username" required class="<?php echo ($error) ? 'error' : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" required class="<?php echo ($error) ? 'error' : ''; ?>">
                        <span class="password-toggle" onclick="togglePasswordVisibility('password')">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                <button type="submit" class="button-primary">Login</button>
            </form>
            <div class="switch-link">
                Don't have an account? <a href="register.php">Sign Up</a>
            </div>
        </div>
    </div>

    <script>
        function togglePasswordVisibility(id) {
            const passwordField = document.getElementById(id);
            const toggleIcon = passwordField.nextElementSibling.querySelector('i');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>