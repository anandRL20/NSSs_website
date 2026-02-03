<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $conn = getDBConnection();
        
        // Check user credentials
        $stmt = $conn->prepare("SELECT id, username, email, password, full_name, is_admin, is_whitelisted FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['is_admin'] = $user['is_admin'];
                $_SESSION['is_whitelisted'] = $user['is_whitelisted'];
                
                // Redirect based on admin status
                if ($user['is_admin'] && $user['is_whitelisted']) {
                    header('Location: admin/dashboard.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit();
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - College Student Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Work+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a1a2e;
            --secondary: #16213e;
            --accent: #0f4c75;
            --highlight: #3282b8;
            --light: #bbe1fa;
            --white: #ffffff;
            --gold: #d4af37;
            --error: #e74c3c;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Work Sans', sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 50%, var(--accent) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 480px;
            width: 100%;
        }

        .form-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 50px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, var(--gold), var(--highlight));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            color: var(--primary);
            text-align: center;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .subtitle {
            text-align: center;
            color: var(--secondary);
            margin-bottom: 35px;
            opacity: 0.7;
            font-size: 15px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            color: var(--primary);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid rgba(15, 76, 117, 0.1);
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Work Sans', sans-serif;
            transition: all 0.3s ease;
            background: var(--white);
        }

        input:focus {
            outline: none;
            border-color: var(--highlight);
            box-shadow: 0 0 0 4px rgba(50, 130, 184, 0.1);
        }

        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--accent), var(--highlight));
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            letter-spacing: 0.5px;
            box-shadow: 0 10px 30px rgba(15, 76, 117, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(15, 76, 117, 0.4);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 500;
            background: rgba(231, 76, 60, 0.1);
            color: var(--error);
            border-left: 4px solid var(--error);
            animation: slideIn 0.3s ease-out;
        }

        .register-link {
            text-align: center;
            margin-top: 25px;
            color: var(--secondary);
            font-size: 14px;
        }

        .register-link a {
            color: var(--highlight);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .register-link a:hover {
            color: var(--accent);
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: var(--secondary);
            text-decoration: none;
            font-size: 14px;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .back-link a:hover {
            opacity: 1;
        }

        .demo-info {
            background: rgba(212, 175, 55, 0.1);
            border-left: 4px solid var(--gold);
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 13px;
            color: var(--secondary);
        }

        .demo-info strong {
            color: var(--primary);
        }

        @media (max-width: 600px) {
            .form-card {
                padding: 35px 25px;
            }

            h1 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <div class="logo">🔐</div>
            <h1>Welcome Back</h1>
            <p class="subtitle">Login to your student portal</p>

            <div class="demo-info">
                <strong>Demo Admin Login:</strong><br>
                Username: admin<br>
                Password: admin123
            </div>

            <?php if ($error): ?>
                <div class="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn">Login</button>
            </form>

            <div class="register-link">
                Don't have an account? <a href="register.php">Register here</a>
            </div>

            <div class="back-link">
                <a href="index.html">← Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>