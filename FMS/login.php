<?php
require_once 'config/config.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, username, password, full_name, email, department, position, role, profile_picture FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['department'] = $user['department'];
            $_SESSION['position'] = $user['position'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['profile_picture'] = $user['profile_picture'];
            $_SESSION['last_activity'] = time();
            
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        /* Color Styles */
        :root {
            --header-label: #FFFFFF;           /* White - Header Label */
            --main-color-1: #E5E5E5;           /* Light Gray - Main color 1 */
            --accent-color: #1E3A5F;           /* Dark Blue - accent Color */
            --main-color-2: #4A90E2;           /* Bright Cyan/Light Blue - Main Color 2 */
            --text-col-1: #808080;             /* Medium Gray - text col 1 */
            --label-col2: #4A4A4A;             /* Darker Gray - Label col2 */
            --highlighted-label: #1A1A1A;      /* Very Dark Gray - Highlighted label */
            --deep-blue: #1E3A5F;              /* Deep Blue for globe and Costa text */
            --vibrant-teal: #00BCD4;           /* Vibrant Teal/Cyan for ship, Cargo text, accents */
        }
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .grid-pattern {
            background-image: 
                radial-gradient(circle, var(--main-color-2) 1px, transparent 1px);
            background-size: 20px 20px;
            opacity: 0.3;
        }
        
        .arc-decoration {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 300px;
            height: 300px;
            border-radius: 50% 0 0 0;
            background: radial-gradient(circle at bottom right, rgba(74, 144, 226, 0.2) 0%, transparent 70%);
        }
        
        .arc-decoration::before {
            content: '';
            position: absolute;
            bottom: -50px;
            right: -50px;
            width: 400px;
            height: 400px;
            border-radius: 50% 0 0 0;
            background: radial-gradient(circle at bottom right, rgba(74, 144, 226, 0.15) 0%, transparent 70%);
        }
        
        .arc-decoration::after {
            content: '';
            position: absolute;
            bottom: -100px;
            right: -100px;
            width: 500px;
            height: 500px;
            border-radius: 50% 0 0 0;
            background: radial-gradient(circle at bottom right, rgba(74, 144, 226, 0.1) 0%, transparent 70%);
        }
        
        input::placeholder {
            color: var(--text-col-1);
            font-size: 13px;
        }
    </style>
</head>
<body class="flex min-h-screen">
    <!-- Left Section - Login Form (50%) -->
    <div class="form w-[50%] h-screen bg-white flex flex-col items-center justify-center p-8">
        
        <!-- CostaCargo Logo -->
        <img src="image/Label.png" alt="Logo" style="max-width: 300px; width: 100%; height: 500px;">

        <!-- Sign In Heading -->
        <h1 class="text-3xl font-bold mb-8" style="color: #05386D; font-size: 20px; margin-bottom: 1px;">Sign In</h1>

        <!-- Error Message -->
        <?php if ($error): ?>
            <div class="mb-6 w-full max-w-md p-4 rounded-lg" style="background-color: var(--main-color-1); border: 1px solid var(--main-color-2);">
                <p class="text-center" style="color: var(--highlighted-label); font-size: 20px;"><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="login.php" class="w-full max-w-md space-y-6" style="margin-bottom: 60px;">
            <div>
                <label for="username" class="block font-medium mb-2" style="color: var(--label-col2); font-size: 13px;">
                    Email
                </label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?php echo htmlspecialchars($username); ?>"
                    required
                    autofocus
                    class="w-full px-4 py-3 border rounded-lg outline-none transition"
                    style="border-color: var(--main-color-1); color: var(--text-col-1); font-size: 13px;"
                    onfocus="this.style.borderColor='var(--accent-color)'; this.style.color='var(--highlighted-label)';"
                    onblur="this.style.borderColor='var(--main-color-1)'; this.style.color='var(--text-col-1)';"
                    placeholder="Value"
                />
            </div>

            <div>
                <label for="password" class="block font-medium mb-2" style="color: var(--label-col2); font-size: 13px;">
                    Password
                </label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    class="w-full px-4 py-3 border rounded-lg outline-none transition"
                    style="border-color: var(--main-color-1); color: var(--text-col-1); font-size: 13px;"
                    onfocus="this.style.borderColor='var(--accent-color)'; this.style.color='var(--highlighted-label)';"
                    onblur="this.style.borderColor='var(--main-color-1)'; this.style.color='var(--text-col-1)';"
                    placeholder="Value"
                />
            </div>

            <button
                type="submit"
                class="w-full py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 transition"
                style="background-color: var(--accent-color); color: var(--header-label); font-size: 13px;"
                onmouseover="this.style.backgroundColor='var(--main-color-2)';"
                onmouseout="this.style.backgroundColor='var(--accent-color)';"
            >
                Sign In
            </button>
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-500">Interested in joining us? 
                    <a href="apply.php" class="font-semibold" style="color: var(--main-color-2);">Apply Now</a>
                </p>
            </div>
        </form>
    </div>

    <!-- Right Section - Branding (50%) -->
    <div class="image-box w-[50%] h-screen overflow-hidden relative flex items-center justify-center" style="background-color: var(--accent-color);">
        <img src="image/side.png" alt="Side Image" class="w-full h-full object-cover">
        
        <!-- Grid Pattern (Top Left) -->
        <div class="absolute top-0 left-0 w-64 h-64 grid-pattern z-0"></div>
        
        <!-- Arc Decorations (Bottom Right) -->
        <div class="arc-decoration z-0"></div>
        
        <!-- System Title -->
        <div class="absolute inset-0 flex items-center z-10" style="justify-content: flex-start; padding-left: 60px;">
            <h1 class="font-bold leading-tight" style="color: var(--header-label); font-size: 48px;">
                <span class="block">Freight</span>
                <span class="block">Management</span>
                <span class="block">System</span>
            </h1>
        </div>
    </div>
</body>
</html>
