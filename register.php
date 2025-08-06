<?php
session_start(); // Pastikan session dimulai di awal skrip
include 'config.php'; // File konfigurasi database Anda

$error = '';
$success = '';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'Username or E-mail already taken.';
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Tambahkan variabel untuk full_name, karena tidak ada di form, kita set kosong
            $full_name = ''; // Atau NULL jika kolom diizinkan NULL di database

            // Set is_member to 1 (true) by default for new registrations
            $is_member = 1; 
            
            // Set initial last_active and last_login to current time for new users
            $current_time = date('Y-m-d H:i:s');

            // Insert new user into database, termasuk full_name dan is_member
            // Pastikan kolom 'is_member' ada di tabel 'users' Anda.
            // Jika belum ada, Anda perlu menambahkannya dengan query SQL seperti:
            // ALTER TABLE users ADD COLUMN is_member TINYINT(1) DEFAULT 0;
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, is_member, last_active, last_login) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssiss", $username, $email, $hashed_password, $full_name, $is_member, $current_time, $current_time); // 'ssssiss' karena ada 4 string, 1 integer, 2 string

            if ($stmt->execute()) {
                // Registrasi berhasil!
                // Ambil ID pengguna yang baru saja dibuat
                $new_user_id = $conn->insert_id;

                // Simpan ID pengguna ke sesi
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['username'] = $username; // Opsional: simpan username juga

                // Redirect langsung ke index.php
                header("Location: index.php");
                exit(); // Penting: Hentikan eksekusi skrip setelah redirect
            } else {
                $error = 'Error during registration. Please try again.';
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - SKMI Cloud Storage</title>
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
            background-image: url('https://source.unsplash.com/random/1920x1080/?abstract,technology,data'); /* Dynamic background image */
            background-size: cover;
            background-position: center;
            color: var(--metro-text-color);
            overflow: hidden;
        }

        .container {
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
            background-image: linear-gradient(to right, rgba(0,0,0,0.5), rgba(0,0,0,0.2)), url('https://source.unsplash.com/random/800x600/?cloud,storage,security'); /* Dark overlay + dynamic background image */
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

        .success-message {
            color: var(--metro-success); /* Metro success green */
            font-size: 0.9em;
            margin-top: 5px;
            text-align: center;
            padding: 8px;
            background-color: rgba(76, 175, 80, 0.1); /* Light green background */
            border-radius: 3px;
            border: 1px solid var(--metro-success);
            margin-bottom: 15px;
        }

        .button-primary {
            width: 100%;
            padding: 12px;
            background-color: var(--metro-blue); /* Metro blue for register */
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
    <div class="container">
        <div class="left-panel">
            <div class="left-panel-content">
                <h2>Join SKMI Cloud Storage</h2>
                <p>Create your account to securely store and manage your files from anywhere, anytime.</p>
            </div>
        </div>
        <div class="right-panel">
            <h2>Sign Up</h2>
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            <form action="register.php" method="POST">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" class="<?php echo ($error) ? 'error' : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="email">E-mail:</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" class="<?php echo ($error) ? 'error' : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required class="<?php echo ($error) ? 'error' : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required class="<?php echo ($error) ? 'error' : ''; ?>">
                </div>
                <button type="submit" class="button-primary">Sign Up</button>
            </form>
            <div class="switch-link">
                Already have an account? <a href="login.php">Login</a>
            </div>
        </div>
    </div>
</body>
</html>
